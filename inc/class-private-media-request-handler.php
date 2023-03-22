<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Provides access to private files.
 */
class Private_Media_Request_Handler {
	/**
	 * Create instance.
	 */
	public function __construct( $init_hooks = false ) {}

	/**
	 * Handle a request.
	 */
	public function handle_request() {
		global $wp_filesystem;

		//check if authorized
		$authorized = $this->is_authorized();

		if ( $authorized ) {
			//authorized

			// 1) check file exists
			$file = Private_Media_Attachment_Manager::get_data_dir() . str_replace( '..', '', $this->get_file() );

			if ( ! $wp_filesystem->is_file( $file ) ) {
				//file not found
				$this->send_response( '404' );
			} else if ( $authorized === 404 ) {
				//file exists but attachment not found (most likely a bug in our code)

				//log
				error_log('-> file exists: ' . $file);

				//block (private but permissions unknown)
				header( 'PvtMed-Error: Unknown Attachment' );
				$this->send_forbidden();
			}

			// 2) get content type
			$mime = wp_check_filetype( $file );

			if ( false === $mime['type'] && function_exists( 'mime_content_type' ) ) {
				$mime['type'] = mime_content_type( $file );
			}

			if ( $mime['type'] ) {
				$mimetype = $mime['type'];
			} else {
				$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );
			}

			// 3) get last modified (for etag)
			$last_modified = gmdate( 'D, d M Y H:i:s', $wp_filesystem->mtime( $file ) );
			$etag          = '"' . md5( $last_modified ) . '"';

			//send headers
			$this->send_headers( $file, $mimetype, $last_modified, $etag );

			//check etag
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
				//not modified (304)
				$this->send_response( '304' );
			}

			//send file
			$this->send_response( '200 OK', $file );
		} else {
			//forbidden (403)
			$this->send_forbidden();
		}
	}

	/**
	 * Send forbidden image.
	 */
	protected function send_forbidden() {
		//return forbidden image
		$mimetype = apply_filters( 'pvtmed_forbidden_mimetype', 'image/svg+xml' );

		header( 'Content-Type: ' . $mimetype );

		$this->send_response( '403' );
	}

	/**
	 * Check if access is granted.
	 */
	protected function is_authorized() {
		//get attachment
		$file = $this->get_file(); //get the file path
		$attachment_id = $this->file_url_to_attachment_id( $file );

		if (!$attachment_id) {
			//unknown attachment file
			return 404;
		}

		//check permissions
		$authorized    = true;
		$permissions = Private_Media_Attachment_Manager::get_attachment_permissions( $attachment_id );

		if ( ! empty( $permissions ) ) {
			//has one or more permissions (i.e. is private file)
			$attachment         = get_post( $attachment_id );
			$post_parent_id     = $attachment->post_parent;

			//call filter first (overwrite default behavior)
		 	if (apply_filters( 'pvtmed_has_permissions', false, $attachment, $permissions )) {
				return true;
			}

			//Note: always private mode has no effect on other permissions (could mean no access at all i.e. fully protected)

			//check hotlinking
			$hotlink_authorized = true;

			if ( isset( $permissions['disable_hotlinks'] ) && 1 === $permissions['disable_hotlinks'] ) {
				//check if linked from this website (cookie was set)
				$hotlink_authorized = filter_input( INPUT_COOKIE, 'pvtmed' );
			}

			unset( $permissions['disable_hotlinks'] );

			//check if user role rules are active
			if ( in_array( 1, $permissions, true ) ) {
				//check if user is logged in
				if ( is_user_logged_in() ) {
					$current_user = wp_get_current_user();
					$roles        = $current_user->roles;

					//collect active permissions
					$authorized_roles = [];

					foreach ( $permissions as $role => $value ) {
						if ( 1 === $value ) {
							$authorized_roles[] = $role;
						}
					}

					//check password protection
					$post_password_required = ( isset( $post_parent_id ) ) ? ( post_password_required( $post_parent_id ) ) : false;

					if ( $post_password_required ) {
						//no hot linking allowed on password protected pages
						$hotlink_authorized = false;
					}

					//check at least one role matches
					$authorized = ! empty( array_intersect( $authorized_roles, $roles ) );
				} else {
					$authorized = false;
				}
			}

			$authorized = $authorized && $hotlink_authorized;
		}

		//call filter
		return apply_filters( 'pvtmed_is_authorized', $authorized, $attachment_id );
	}

	/**
	 * Get file URL parameter.
	 */
	protected function get_file() {
		global $wp;

		//debug
		//error_log('Get file:');
		//error_log(json_encode($_SERVER));
		//error_log(json_encode($_GET)); //Note: empty
		//error_log(json_encode($wp->query_vars));
		//error_log(urldecode($wp->query_vars['file']));

		//Note: urldecode() needed to convert %xy values appearing sometimes in Apache redirect URL (e.g. %c2%ad which is as a single character in the database's string)
		return urldecode($wp->query_vars['file']);
	}

	/**
	 * Get attachment ID from file name.
	 */
	protected function file_url_to_attachment_id( $file ) {
		global $wpdb;

		//see https://developer.wordpress.org/reference/functions/attachment_url_to_postid/

		//remove resized image (e.g. -212x300)
		$file2 = preg_replace( '#\-[0-9]+x[0-9]+#', '', $file );

		//PDF preview images
		$file2 = str_replace( '-pdf.jpg', '.pdf', $file2 );

		//find attachment
		$query = $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s;", $file2 );

		$attachment_id = $wpdb->get_var( $query ); // @codingStandardsIgnoreLine

		//log
		if (!$attachment_id) {
			error_log('Unknown pvtmed attachment: ' . $file);
			error_log($file2);
		}

		return (int) $attachment_id;
	}

	/**
	 * Send response headers.
	 */
	protected function send_headers( $file, $mimetype, $last_modified, $etag ) {
		global $wp_filesystem;

		//set content type
		$mimetype = apply_filters( 'pvtmed_mimetype', $mimetype, $file );

		header( 'Content-Type: ' . $mimetype );

		if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
			header( 'Content-Length: ' . $wp_filesystem->size( $file ) );
		}

		//set caching headers
		header( 'Last-Modified: ' . $last_modified . ' GMT' );
		header( 'ETag: ' . $etag );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', current_time( 'timestamp' ) + 100000000 ) . ' GMT' );
	}

	/**
	 * Send file as response.
	 */
	protected function send_response( $status, $file = '' ) {
		global $wp_filesystem;

		//apply filters
		$file   = apply_filters( 'pvtmed_file', $file );
		$status = apply_filters( 'pvtmed_status', $status, $file );

		status_header( intval( $status ) );

		// 1) handle forbidden
		if ( '403' === $status ) {
			//support custom redirect
			$redirect = apply_filters( 'pvtmed_forbidden_redirect', false, $file );
			$status   = apply_filters( 'pvtmed_forbidden_status', $status, $file );

			if ( $redirect ) {
				wp_redirect( $redirect, $status );

				exit();
			}

			//deliver forbidden image
			$forbidden_response_content = $wp_filesystem->get_contents( PVTMED_PLUGIN_URL . 'assets/images/forbidden.svg' );

			echo apply_filters( 'pvtmed_forbidden_response_content', $forbidden_response_content, $file ); // @codingStandardsIgnoreLine

			exit();
		}

		// 2) any other values
		if ( '200 OK' !== $status ) {
			//empty response
			exit();
		}

		// 3) handle OK

		//stream the file content
		$file_handle   = fopen( $file, 'r' ); // @codingStandardsIgnoreLine
		$output_handle = fopen( 'php://output', 'w' );  // @codingStandardsIgnoreLine

		stream_copy_to_stream( $file_handle, $output_handle );
		fclose( $file_handle ); // @codingStandardsIgnoreLine
		fclose( $output_handle ); // @codingStandardsIgnoreLine

		exit();
	}
}