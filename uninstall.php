<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

WP_Filesystem();

global $wp_filesystem;

$mu_plugin = trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) . 'pvtmed-endpoint-optimizer.php';

$wp_filesystem->delete( $mu_plugin );
$wp_filesystem->delete( $mu_plugin . '.backup' );

$transient_prefix = $wpdb->esc_like( '_transient_pvtmed_' ) . '%';
$option_prefix    = $wpdb->esc_like( 'pvtmed' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE `option_name` LIKE %s OR `option_name` LIKE %s",
		$option_prefix,
		$transient_prefix
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE `meta_key` LIKE %s",
		$option_prefix
	)
);

$private_frag = 'pvtmed-';

$wpdb->query(
	$wpdb->prepare(
		"UPDATE {$wpdb->posts} SET `post_content` = REPLACE ( `post_content`, %s, '' ) WHERE (`post_content` LIKE %s)",
		$private_frag,
		'%' . $private_frag . '%'
	)
);

global $wp_filesystem;

$upload_dir = wp_upload_dir();
$public_dir = trailingslashit( $upload_dir['basedir'] );
$data_dir   = trailingslashit( $wp_filesystem->wp_content_dir() . 'pvtmed-uploads' );
$info       = $wp_filesystem->dirlist( $data_dir, false, true );

foreach ( $info as $name => $i ) {
	pvtmed_uninstall_move_files( $i, trailingslashit( $public_dir . $name ) );
}

$wp_filesystem->delete( $data_dir, true );

function pvtmed_uninstall_move_files( $info, $path ) {
	global $wp_filesystem;

	if ( 'd' === $info['type'] && isset( $info['files'] ) ) {

		if ( ! $wp_filesystem->is_dir( $path ) ) {
			$wp_filesystem->mkdir( $path );
		}

		foreach ( $info['files'] as $name => $i ) {
			pvtmed_uninstall_move_files( $i, trailingslashit( $path . $name ) );
		}
	} else {
		$upload_dir  = wp_upload_dir();
		$destination = untrailingslashit( $path );
		$source      = str_replace(
			trailingslashit( $upload_dir['basedir'] ),
			trailingslashit( $wp_filesystem->wp_content_dir() . 'pvtmed-uploads' ),
			$destination
		);
		$wp_filesystem->move( $source, $destination, true );
	}
}
