<?php
/*
Plugin Name: Private Media
Plugin URI: https://github.com/froger-me/private-media/
Text Domain: pvtmed
Description: Add access restrictions to specific items of the WordPress Media Library.
Version: 1.2
Author: Alexandre Froger
Author URI: https://froger.me/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'PVTMED_PLUGIN_PATH' ) ) {
	define( 'PVTMED_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PVTMED_PLUGIN_URL' ) ) {
	define( 'PVTMED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once PVTMED_PLUGIN_PATH . 'inc/class-private-media.php';
require_once PVTMED_PLUGIN_PATH . 'inc/class-private-media-attachment-manager.php';

register_activation_hook( __FILE__, array( 'Private_media', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Private_media', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Private_media', 'uninstall' ) );

function pvtmed_run() {
	require_once PVTMED_PLUGIN_PATH . 'inc/class-private-media-request-handler.php';

	$request_handler    = new Private_Media_Request_Handler();
	$attachment_manager = new Private_Media_Attachment_Manager( true );
	$pvtmed             = new Private_Media( $request_handler, $attachment_manager, true );
}
add_action( 'plugins_loaded', 'pvtmed_run', 10, 0 );

if ( ! Private_media::is_doing_api_request() ) {
	require_once plugin_dir_path( __FILE__ ) . 'lib/wp-update-migrate/class-wp-update-migrate.php';

	add_action( 'plugins_loaded', function() {
		$pvtmed_update_migrate = WP_Update_Migrate::get_instance( __FILE__, 'pvtmed' );

		if ( false === $pvtmed_update_migrate->get_result() ) {

			if ( false !== has_action( 'plugins_loaded', 'pvtmed_run' ) ) {

				remove_action( 'plugins_loaded', 'pvtmed_run', 10 );
			}
		}

	}, PHP_INT_MIN );
}
