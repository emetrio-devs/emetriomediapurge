=== MediaSweep ===
Contributors: emetrio
Donate link: https://www.emetrio.com/
Tags: media, unused, cleanup, attachments, delete
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MediaSweep finds and bulk-deletes unused WordPress attachments to keep your media library clean and organized.

== Description ==
MediaSweep finds attachments that are not used in posts/pages and displays them so you can safely remove them from your WordPress site with bulk delete options.

== Installation ==
1. Upload the `mediasweep` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress Admin.
3. Go to Media <span aria-hidden="true" class="wp-exclude-emoji"><span aria-hidden="true" class="wp-exclude-emoji"><span aria-hidden="true" class="wp-exclude-emoji">→</span></span></span> Media Sweep to view unused items.
4. Make a full backup before deleting items (deletions are permanent).

== Screenshots ==
1. screenshot-1.png — Media Sweep list table with preview, filename, size and delete actions.
2. screenshot-2.png — Filter and search in top table nav.

== Frequently Asked Questions ==
= Is deletion permanent? =
Yes — the plugin calls `wp_delete_attachment(…, true)` to permanently remove files.

= How can I rebuild the scan? =
The plugin stores results in a transient `mediasweep_unused_ids`. Deleting the transient (or resaving an attachment) will force a rescan. Consider using WP-CLI or a scheduled job on large sites.

== Changelog ==
= 1.0.0 =
* Initial release — unused media scan, admin list, single & bulk delete.

== Upgrade Notice ==
= 1.0.0 =
Initial release.

== Screenshots and assets ==
Place `screenshot-1.png`, `screenshot-2.png`, `screenshot-3.png`, … in the plugin root for the WordPress.org screenshots to be picked up by the readme.
