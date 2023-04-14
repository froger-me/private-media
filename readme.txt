=== Private Media ===
Contributors: frogerme, cbratschi
Tags: media, uploads, private
Requires at least: 4.9.8
Tested up to: 6.2
Requires PHP: 7.4
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

= 1.6 =
* Added pvtmed_is_attachment_authorized filter: check if active user is authorized.

= 1.5 =
* Fix: added readme.txt back again

= 1.4 =
* Added pvtmed_load_frontend filter: opt-out of loading main.js on all pages.
* Added pvtmed_hotlink_feature filter: opt-out of hotlinking feature.
* Added pvtmed_has_permissions filter: implement own permission system.
* Added pvtmed_add_permissions filter: add custom permissions.
* Added pvtmed_edit_settings filter: customize media settings.
* Added pvtmed_edit_roles filter: modify displayed roles in media edit screen.
* Added "Always Private" checkbox.
  * Keep files private even without any permissions.
  * Can be used for instance by filter passed permissions.
* Added lock icon to media grid view for protected files.
* Added Private Media column to media list view.
* New APIs:
  * $private = apply_filters('pvtmed_is_attachment_private', null, $attachmend_id);
  * $permissions = apply_filters('pvtmed_get_attachment_permissions', null, $attachmend_id);
  * do_action('pvtmed_set_attachment_permissions', $attachment_id, [ 'admin' => '1' ]);
    * Make private with admin access.
  * do_action('pvtmed_set_attachment_permissions', $attachment_id);
    * Make public.
* URL decode file query parameter.
* Support PDF preview images.

= 1.3 =
* fixed crash in status_header() call
* added build system
* check if TinyMCE is available

= 1.2 =
* Add `pvtmed_private_upload_url` and `pvtmed_htaccess_rules` filters (see [this support request for details]())

= 1.1 =
* Bump version - supported by WordPress 5.0 (post edit screen fallbacks only in Classic Editor)

= 1.0 =
* First version