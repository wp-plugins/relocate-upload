=== Relocate Upload ===
Contributors: alanft
Tags: admin, upload, folder, relocate
Requires at least: 2.6
Tested up to: 2.8.2
Stable tag: 0.14

Wordpress uploads media to one pre-set folder. Relocate Upload lets you switch media to other folders.


== Description ==
Relocate Upload lets you specify folders, and adds a menu to the Media Library (and Edit Media admin page) that lets you switch media items between these folders and WPs default upload location.

== Installation ==

1. Upload the whole `relocate-upload` folder to the plugins directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Specify your folders in the settings page.

== Frequently Asked Questions ==

= Why? What's the point of these other folders? =

Most servers are setup so that assets can only be used when referred from your own site - to stop bandwidth leeching. However folders can be set aside that don't obey this rule - eg for placing images on other sites, downloading mp3s from RSS feeds and so on.

And how about plugins that have 'default folders', eh?

= It not work, fool =

That's not a question. Let me know on the [WP Forums](http://wordpress.org/tags/relocate-upload?forum_id=10#postform)  - i'm working on getting it from 'just about working' to a robust all-server plugin.

== Screenshots ==

1. A simple menu switches media assets to your favourite locations. All through the magic of AJAX.
2. A simple settings page to define the folder locations.

== Changelog ==
0.14 - Many small fixes (check for existing files, database prefix bug fix, jquery enqueue, 2.8 media library update, path 'fixing')

0.11 - FIrst tiny bug fix (no thumbnails caused an error)

0.10 - Just starting out, something to get it working
