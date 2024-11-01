=== Plugin Name ===
Contributors: FranceImage
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=VVWKFRJYLXD28
Tags: events manager, import, importer
Requires at least: 3.0.1
Tested up to: 3.7.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import events manager objects into another site (events and locations along with their attachments, thumbnails, comments and taxonomies)

== Description ==

# On source site #

* Events Manager plugin must be active.
* Use WordPress export tool with 'All Content' selected.

# On target site #

* [Events Manager plugin](http://wordpress.org/plugins/events-manager/) must be active.
* [WordPress Importer plugin](http://wordpress.org/plugins/wordpress-importer/) must be active.
* Events Manager Importer must be active.
* Use WordPress import tool; it will only import Events Manager locations, events, recurring events and event tags and categories (along with their comments, thumbnails and attachments if 'Download file attachments' has been checked)

# Limitations #

* Can only transfer ALL events and location (no filter)
* Does not transfer tickets and bookings
* Does not transfer comments made on recurring events
* Does not work in a WordPress network



== Installation ==

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 1.0.0 =
* Initial version

= 1.0.1 =
* Modify plugin name to avoid conflict with https://github.com/soixantecircuits/wp-events-manager-importer


