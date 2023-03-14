<?php
/**
* Run as little as possible of the WordPress core with PRivate Media actions and filters.
* Effect:
* - keep only a selection of plugins (@see $pvtmed_always_active_plugins below)
* - prevent inclusion of themes functions.php (parent and child)
* - remove all core actions and filters that haven't been fired yet
*
* Place this file in a wp-content/mu-plugin folder (after editing if needed) and it will be loaded automatically.
* Use the @see global $pvtmed_doing_private_media_api_request in the plugins you kept active for optimization purposes.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $pvtmed_doing_private_media_api_request, $pvtmed_always_active_plugins;

if ( ! $pvtmed_always_active_plugins ) {
	$pvtmed_always_active_plugins = array(
		// Edit with your plugin IDs here to keep them active during media access.
		// 'my-plugin-slug/my-plugin-file.php',
		// 'my-other-plugin-slug/my-other-plugin-file.php',
		'private-media/private-media.php',
	);
}

$pvtmed_doing_private_media_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], 'pvtmed-uploads' ) );

if ( true === $pvtmed_doing_private_media_api_request ) {

	$hooks = array(
		'registered_taxonomy',
		'wp_register_sidebar_widget',
		'registered_post_type',
		'widgets_init',
		'wp_default_scripts',
		'option_siteurl',
		'option_home',
		'option_active_plugins',
		'query',
		'option_blog_charset',
		'plugins_loaded',
		'sanitize_comment_cookies',
		'template_directory',
		'stylesheet_directory',
		'set_current_user',
		'user_has_cap',
		'init',
		'option_category_base',
		'option_tag_base',
		'heartbeat_settings',
		'locale',
		'wp_loaded',
		'query_vars',
		'request',
		'parse_request',
		'shutdown',
	);

	foreach ( $hooks as $hook ) {
		remove_all_filters( $hook );
	}

	add_filter( 'option_active_plugins', 'pvtmed_unset_plugins', 99, 1 );
	add_filter( 'template_directory', 'pvtmed_bypass_themes_functions', 99, 3 );
	add_filter( 'stylesheet_directory', 'pvtmed_bypass_themes_functions', 99, 3 );
	add_filter( 'enable_loading_advanced_cache_dropin', 'pvtmed_bypass_cache', 99, 1 );
}

function pvtmed_unset_plugins( $plugins ) {
	global $pvtmed_always_active_plugins;

	foreach ( $plugins as $key => $plugin ) {

		if ( ! in_array( $plugin, $pvtmed_always_active_plugins, true ) ) {
			unset( $plugins[ $key ] );
		}
	}

	return $plugins;
}

function pvtmed_bypass_cache( $is_cache ) {

	return false;
}

function pvtmed_bypass_themes_functions( $template_dir, $template, $theme_root ) {

	return dirname( __FILE__ );
}
