<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Private_Media_Request_Handler {

	public function __construct( $init_hooks = false ) {}

	public function handle_request() {
		global $wp_filesystem;

		$authorized = $this->is_authorized();

		if ( $authorized ) {
			$file = Private_Media_Attachment_Manager::get_data_dir() . str_replace( '..', '', $this->get_file() );

			if ( ! $wp_filesystem->is_file( $file ) ) {
				$this->send_response( '404' );
			}

			$mime = wp_check_filetype( $file );

			if ( false === $mime['type'] && function_exists( 'mime_content_type' ) ) {
				$mime['type'] = mime_content_type( $file );
			}

			if ( $mime['type'] ) {
				$mimetype = $mime['type'];
			} else {
				$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );
			}

			$last_modified = gmdate( 'D, d M Y H:i:s', $wp_filesystem->mtime( $file ) );
			$etag          = '"' . md5( $last_modified ) . '"';

			$this->send_headers( $file, $mimetype, $last_modified, $etag );

			$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

			if ( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
				$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;
			}

			$client_last_modified      = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
			$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;
			$modified_timestamp        = strtotime( $last_modified );

			if ( ( $client_last_modified && $client_etag )
				? ( ( $client_modified_timestamp >= $modified_timestamp ) && ( $client_etag === $etag ) )
				: ( ( $client_modified_timestamp >= $modified_timestamp ) || ( $client_etag === $etag ) )
				) {
				$this->send_response( '304' );
			}

			$this->send_response( '200 OK', $file );
		} else {
			$mimetype = apply_filters( 'pvtmed_forbidden_mimetype', 'image/svg+xml' );

			header( 'Content-Type: ' . $mimetype );

			$this->send_response( '403' );
		}
	}

	protected function is_authorized() {
		$attachment_id = $this->file_url_to_attachment_id( $this->get_file() );
		$authorized    = true;

		if ( $attachment_id ) {
			$permissions = get_post_meta( $attachment_id, 'pvtmed_settings', true );

			if ( ! empty( $permissions ) ) {
				$hotlink_authorized = true;
				$attachment         = get_post( $attachment_id );
				$post_parent_id     = $attachment->post_parent;

				if ( isset( $permissions['disable_hotlinks'] ) && 1 === $permissions['disable_hotlinks'] ) {
					$hotlink_authorized = filter_input( INPUT_COOKIE, 'pvtmed' );
				}

				unset( $permissions['disable_hotlinks'] );

				if ( in_array( 1, $permissions, true ) ) {

					if ( is_user_logged_in() ) {
						$current_user           = wp_get_current_user();
						$roles                  = $current_user->roles;
						$authorized_roles       = array();
						$post_password_required = ( isset( $post_parent_id ) ) ? ( post_password_required( $post_parent_id ) ) : false;

						foreach ( $permissions as $role => $value ) {

							if ( 1 === $value ) {
								$authorized_roles[] = $role;
							}
						}

						if ( $post_password_required ) {
							$hotlink_authorized = false;
						}

						$authorized = ! empty( array_intersect( $authorized_roles, $roles ) );
					} else {
						$authorized = false;
					}
				}

				$authorized = $authorized && $hotlink_authorized;
			}
		}

		return apply_filters( 'pvtmed_is_authorized', $authorized, $attachment_id );
	}

	protected function get_file() {
		global $wp;

		return $wp->query_vars['file'];
	}

	protected function file_url_to_attachment_id( $file ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s;", preg_replace( '#\-[0-9]+x[0-9]+#', '', $file ) );

		$attachment_id = $wpdb->get_var( $query ); // @codingStandardsIgnoreLine

		return (int) $attachment_id;
	}

	protected function send_headers( $file, $mimetype, $last_modified, $etag ) {
		global $wp_filesystem;

		$mimetype = apply_filters( 'pvtmed_mimetype', $mimetype, $file );

		header( 'Content-Type: ' . $mimetype );

		if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
			header( 'Content-Length: ' . $wp_filesystem->size( $file ) );
		}

		header( 'Last-Modified: ' . $last_modified . ' GMT' );
		header( 'ETag: ' . $etag );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', current_time( 'timestamp' ) + 100000000 ) . ' GMT' );
	}

	protected function send_response( $status, $file = '' ) {
		global $wp_filesystem;

		$file   = apply_filters( 'pvtmed_file', $file );
		$status = apply_filters( 'pvtmed_status', $status, $file );

		status_header( $status );

		if ( '403' === $status ) {
			$redirect = apply_filters( 'pvtmed_forbidden_redirect', false, $file );
			$status   = apply_filters( 'pvtmed_forbidden_status', $status, $file );

			if ( $redirect ) {
				wp_redirect( $redirect, $status );

				exit();
			}

			$forbidden_response_content = $wp_filesystem->get_contents( PVTMED_PLUGIN_URL . 'assets/images/forbidden.svg' );

			echo apply_filters( 'pvtmed_forbidden_response_content', $forbidden_response_content, $file ); // @codingStandardsIgnoreLine

			exit();
		}

		if ( '200 OK' !== $status ) {

			exit();
		}

		$file_handle   = fopen( $file, 'r' ); // @codingStandardsIgnoreLine
		$output_handle = fopen( 'php://output', 'w' );  // @codingStandardsIgnoreLine

		stream_copy_to_stream( $file_handle, $output_handle );
		fclose( $file_handle ); // @codingStandardsIgnoreLine
		fclose( $output_handle ); // @codingStandardsIgnoreLine

		exit();
	}

}


