=== Private Media ===
Contributors: frogerme
Tags: media, uploads, private
Requires at least: 4.9.8
Tested up to: 4.9.8
Requires PHP: 7.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add access restrictions to specific items of the WordPress Media Library.

== Description ==

Ever wanted to make your media truely private? Make sure images, videos and other files are only accessible to chosen roles, or cannot be hotlinked, with permissions specifically set per item.  

This plugin adds the following major features to WordPress:

* **Media Privacy:** Lock access to items in the Media Library by preventing hotlinks only or by limiting access to files to selected user roles.
* **User-friendly forbidden handler:** Images set to private do not break on the frontend. Instead, they are replaced by a simple access denied SVG picture - the forbidden handler can be replaced using the filter hooks `pvtmed_forbidden_response_content` (`apply_filters( 'pvtmed_forbidden_response_content', $forbidden_response_content, $file );`) and `pvtmed_forbidden_mimetype` (`apply_filters( 'pvtmed_forbidden_mimetype', 'image/svg+xml' );`).
* **Customizable for more granularity:** Restricted media will be checked for autorization - plugin developers can hook into the `pvtmed_is_authorized` filter (`apply_filters( 'pvtmed_is_authorized', $authorized, $attachment_id );`) to apply more complex conditions for authorization.
* **Optimized private media delivery:** Files with access restriction are served using streams without loading the file entirely in memory before delivery, and WordPress files are loaded as lightly as possible for an optimised server memory usage.
* **Fallbacks:** Restricted files are kept in an alternate `wp-content/pvtmed-uploads` folder (or equivalent if `WP_CONTENT_DIR` is not the default) ; fallbacks are in place to make sure:
	* moving a media to private does not break previously embedded media (javascript dynamic fallback with notice on post edit screen - Classic Editor only).  
	* deactivating the plugin does not break previously embedded media (database update).  
	* deleting the plugin does not break previously embedded media (database update).  

A [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins) `pvtmed-endpoint-optimizer.php` is installed automatically to make sure WordPress is loaded as lightly as possible when requesting restricted media items. Developers can safely edit it to enable their plugin to execute during such request if necessary.  

The media privacy policy is set per media item - therefore, this plugin is not a replacement for general image hotlink prevention plugins, but is ideal for anyone looking for preventing direct links to files depending on specific conditions.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/private-media` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Edit Media Privacy Settings on media items in the Media Library

== Changelog ==

= 1.2 =
* Add `pvtmed_private_upload_url` and `pvtmed_htaccess_rules` filters (see [this support request for details]())

= 1.1 =
* Bump version - supported by WordPress 5.0 (post edit screen fallbacks only in Classic Editor)

= 1.0 =
* First version