<?php
/*
Plugin Name: WordPress Importer for Events Manager 
Version: 1.0.1
Plugin URI: http://franceimage.com/en/tech-blog
Description: Companion to Wordpress Importer plugin to import events and locations 
Author: FranceImage
Author URI: http://franceimage.com/en/tech-blog
*/



class Fi_events_manager_importer {
	// post ids in the target site (saved in wp_import_post_meta, used in import_end)
	var $location_post_ids = array();
	var $event_post_ids = array();
	

	function __construct()  {
		add_action('init', array(&$this, 'init'));
	}


	function init() {
		add_filter('wp_import_categories', array(&$this, 'wp_import_categories'), 10, 1); 
		add_filter('wp_import_tags', array(&$this, 'wp_import_tags'), 10, 1); 
		add_filter('wp_import_terms', array(&$this, 'wp_import_terms'), 10, 1); 
		add_filter('wp_import_posts', array(&$this, 'wp_import_posts'), 10, 1); 
		add_filter('wp_import_post_meta', array(&$this, 'wp_import_post_meta'), 10, 3);
		add_action('import_end', array(&$this, 'import_end'));
	}


	function wp_import_categories($categories) {
		// we don't import categories
		return array();
	}
	
	
	function wp_import_tags($tags) {
		// we don't import tags
		return array();
	}

	
	function wp_import_terms($terms) {
		$id = 0;
		// we import only events manager terms
		foreach ($terms as $term) {
			if($terms[$id]['term_taxonomy'] != 'events-tags' && $terms[$id]['term_taxonomy'] != 'events-categories') {
				unset($terms[$id]);
			}
			$id++;
		}

		return $terms;
	}
	
	
	/**
	 * Import file has been scanned.
	 * Importer is about to create posts.
	 */
	function wp_import_posts($posts) {
		
		global $wpdb;
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "postmeta WHERE meta_key='_imported_location_id'");
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "postmeta WHERE meta_key='_imported_event_id'");
		
		// We don't want Events Manager to process the events while they are imported
		remove_action('save_post',array('EM_Event_Post_Admin','save_post'),10,1);
		remove_action('save_post',array('EM_Location_Post_Admin','save_post'),10,1);
		

		$id = 0;
		$post_ids = array();
		$thumbnail_ids = array();
		foreach ($posts as $post) {
			if($post['post_type'] != 'event' && $post['post_type'] != 'location' && $post['post_type'] != 'event-recurring' && $post['post_type'] != 'attachment') {
				unset($posts[$id]);
			}
			
			// rename _location_id postmeta (to _imported_location_id) so that Events Manager creates a new one in import_end step 
			if($post['post_type'] == 'location') {
				$postmeta = $post['postmeta'];
				$meta_id = 0;
				foreach ($postmeta as $meta) {
					if($meta['key'] == '_location_id') {
						$posts[$id]['postmeta'][$meta_id]['key'] = '_imported_location_id';
					}				
					if($meta['key'] == '_thumbnail_id') {
						$thumbnail_ids[] = $meta['value'];
					}
					$meta_id++;
				}
				$posts[$id]['postmeta'] = array_values($posts[$id]['postmeta']); // reindex array (after some unset())
				$post_ids[] = $post['post_id'];
			}
			
			// rename _event_id postmeta (to _imported_event_id) so that Events Manager creates a new one in import_end step 
			// remove events with a _recurrence_id so that they are not created twice (as they are automatically created when we import recurring-events)
			if($post['post_type'] == 'event' || $post['post_type'] == 'event-recurring') {
				$postmeta = $post['postmeta'];
				$thumbnail_id = false;
				$meta_id = 0;
				foreach ($postmeta as $meta) {					
					if($meta['key'] == '_event_id') {
						$posts[$id]['postmeta'][$meta_id]['key'] = '_imported_event_id';
					}	
					if($meta['key'] == '_thumbnail_id') {
						$thumbnail_id = $meta['value'];
					}
						
					if($post['post_type'] == 'event' && $meta['key'] == '_recurrence_id' && $meta['value'] != '') {
						// recurrences are created when importing recurring events
						unset($posts[$id]);
						break;
					}
								
					$meta_id++;
				}
				if(isset($posts[$id])) {
					$post_ids[] = $post['post_id'];
					if($thumbnail_id) {
						$thumbnail_ids[] = $thumbnail_id;
					}
				}
			}
			
			$id++;
		}
		
		// reindex array (after some unset())
		$posts = array_values($posts); 
		
		// a second iteration for the attachments
		// we'll import attachments if they were uploaded to an event manager object or are the thumbnail of an event manager object
		$id = 0;
		foreach ($posts as $post) {
			if($post['post_type'] == 'attachment') {
				if(!in_array($post['post_parent'], $post_ids) && !in_array($post['post_id'], $thumbnail_ids)) {
					unset($posts[$id]);
				}
			}
		
			$id++;
		}
		
		
		return $posts;
	}
	

	/**
	 * $post_id: newly created post id 
	 */
	function wp_import_post_meta($postmeta, $post_id, $post) {
		if($post['post_type'] == 'location' || $post['post_type'] == 'event' || $post['post_type'] == 'event-recurring') {
		
			$imported_id = false;
			// Works around Wordpress Importer bug. If we don't do that, post_meta will be duplicated
			// Another way would be to replace add_post_meta( $post_id, $key, $value ) with update_post_meta( $post_id, $key, $value )
			if(count($postmeta)) {
				foreach ($postmeta AS $meta) {
					if($meta['key'] == '_imported_location_id' || $meta['key'] == '_imported_event_id') {
						$imported_id = $meta['value'];
					}
					delete_post_meta($post_id, $meta['key']);
				}
			}
		
			// Collect ids of the posts created during the import. Will be useful in import_end step.
			if($post['post_type'] == 'location') {
				$this->location_post_ids[$imported_id] = $post_id;
			}
			if($post['post_type'] == 'event' || $post['post_type'] == 'event-recurring') {
				$this->event_post_ids[$imported_id] = $post_id;
			}
		}
		
		return $postmeta;
	}
	
	
	/**
	 * Create location and event rows in custom tables.
	 */
	function import_end() {
		$location_ids = array();
		foreach ($this->location_post_ids AS $post_id) {
			$location = new EM_Location($post_id, 'post_id');
			$location->save();
			$location_ids[$post_id] = $location->location_id;
		}
		foreach ($this->event_post_ids AS $post_id) {
			$location_id = get_post_meta($post_id, '_location_id', true);
			if($location_id) {
				$remap = $location_ids[$this->location_post_ids[$location_id]];
				update_post_meta($post_id, '_location_id', $remap);
			}
			$event = new EM_Event($post_id, 'post_id');
			$event->save();
		}
	}
	
}


new Fi_events_manager_importer();