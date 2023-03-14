<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Private_Media_Attachment_Manager {

	protected static $root_data_dirname = 'pvtmed-uploads';
	protected static $fix_prefixes      = array(
		'default' => array(
			'src'  => 'src="',
			'href' => 'href="',
		),
		'video'   => array(
			'flv'  => 'flv="',
			'mp4'  => 'mp4="',
			'm4v'  => 'm4v="',
			'webm' => 'webm="',
			'ogv'  => 'ogv="',
			'wmv'  => 'wmv="',
		),
		'audio'   => array(
			'mp3' => 'mp3="',
			'm4a' => 'm4a="',
			'ogg' => 'ogg="',
			'wav' => 'wav="',
			'wma' => 'wma="',
		),
	);

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'delete_attachment', array( $this, 'delete_attachment' ), -10, 1 );

			add_filter( 'add_post_metadata', array( $this, 'add_post_metadata' ), 10, 5 );
			add_filter( '_wp_relative_upload_path', array( $this, 'wp_relative_upload_path' ), -10, 2 );
			add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), -10, 2 );
			add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), -10, 2 );
			add_filter( 'get_image_tag_class', array( $this, 'get_image_tag_class' ), -10, 4 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'wp_calculate_image_srcset' ), 10, 5 );
		}
	}

	public static function get_root_dir_name() {

		return self::$root_data_dirname;
	}

	public static function get_data_dir() {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			self::trigger_plugin_error( __( 'File system not available.', 'pvtmed' ), E_USER_ERROR );
		}

		$data_dir = trailingslashit( $wp_filesystem->wp_content_dir() . self::$root_data_dirname );

		return trailingslashit( $data_dir );
	}

	public function maybe_setup_directories() {
		$root_dir = self::get_data_dir();
		$result   = true;

		global $wp_filesystem;

		if ( ! $wp_filesystem->is_dir( $root_dir ) ) {
			$result = $result && $this->create_data_dir( 'uploads-private', false, true );
		}

		$htaccess_path = self::get_data_dir() . '.htaccess';

		if ( ! $wp_filesystem->is_file( $htaccess_path ) ) {
			$result = $result && $this->generate_restricted_htaccess( $htaccess_path );
		}

		return $result;
	}

	public function apply_plugin_private_policy( $apply ) {
		$attachment_ids = $this->get_all_private_attachement_ids();
		$operation      = ( $apply ) ? 'private' : 'public';

		if ( ! empty( $attachment_ids ) ) {

			foreach ( $attachment_ids as $key => $attachment_id ) {
				$this->move_media( $attachment_id, $operation, false );
			}

			if ( $apply ) {
				$this->replace_public_markup_in_posts();
			} else {
				$this->replace_private_markup_in_posts();
			}
		}
	}

	protected function replace_private_markup_in_posts() {
		global $wpdb;

		$upload_dir         = wp_upload_dir();
		$site_url           = get_option( 'siteurl' );
		$public_upload_url  = trailingslashit( $upload_dir['baseurl'] );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		foreach ( self::$fix_prefixes as $type => $types ) {

			if ( 'audio' === $type || 'video' === $type ) {

				if ( ! get_option( 'pvtmed_deactivate_migrate_' . $type, false ) ) {

					continue;
				} else {
					delete_option( 'pvtmed_deactivate_migrate_' . $type );
				}
			}

			foreach ( $types as $key => $prefix ) {
				$affected_rows = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET `post_content` = REPLACE ( `post_content`, %s, %s ) WHERE (`post_content` LIKE %s)",
						$prefix . $private_upload_url,
						'data-pvtmed="needs-fix" ' . $prefix . $public_upload_url,
						'%' . $prefix . $private_upload_url . '%'
					)
				);

				if ( $affected_rows > 0 ) {
					update_option( 'pvtmed_needs_fix_' . $key, true, false );
				}
			}
		}
	}

	protected function replace_public_markup_in_posts() {
		global $wpdb;

		$upload_dir         = wp_upload_dir();
		$site_url           = get_option( 'siteurl' );
		$public_upload_url  = trailingslashit( $upload_dir['baseurl'] );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		foreach ( self::$fix_prefixes as $type => $types ) {

			foreach ( $types as $key => $prefix ) {

				if ( get_option( 'pvtmed_needs_fix_' . $key ) ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->posts} SET `post_content` = REPLACE ( `post_content`, %s, %s ) WHERE (`post_content` LIKE %s)",
							'data-pvtmed="needs-fix" ' . $prefix . $public_upload_url,
							$prefix . $private_upload_url,
							'%data-pvtmed="needs-fix" ' . $prefix . $public_upload_url . '%'
						)
					);
					delete_option( 'pvtmed_needs_fix_' . $key );
				}
			}
		}
	}

	protected function get_all_private_attachement_ids() {
		$args  = array(
			'fields'      => 'ids',
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => array(
				array(
					'key'   => 'pvtmed_private',
					'value' => true,
					'type'  => 'BOOLEAN',
				),
			),
		);
		$query = new WP_Query( $args );

		wp_reset_postdata();

		return ( $query->posts ) ? $query->posts : array();
	}

	public function add_post_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {

		if ( 'enclosure' === $meta_key ) {

			$args        = array(
				'post_parent'    => $object_id,
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);
			$attachments = get_children( $args );

			foreach ( $attachments as $attachment ) {
				$permissions = get_post_meta( $attachment->ID, 'pvtmed_settings', true );

				if ( is_array( $permissions ) && in_array( 1, array_values( $permissions ), true ) ) {
					$site_url           = get_option( 'siteurl' );
					$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );
					$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

					if ( false !== strpos( $meta_value, $private_upload_url ) ) {
						$check = false;
					}
				}
			}
		}

		return $check;
	}

	public function delete_attachment( $attachment_id ) {
		$permissions = get_post_meta( $attachment_id, 'pvtmed_settings', true );

		if ( ! ( is_array( $permissions ) && in_array( 1, array_values( $permissions ), true ) ) ) {

			return;
		}

		$meta         = wp_get_attachment_metadata( $attachment_id );
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$file         = get_attached_file( $attachment_id );
		$private_dir  = self::get_data_dir();

		if ( ! empty( $meta['thumb'] ) ) {

			if ( ! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%' . $wpdb->esc_like( $meta['thumb'] ) . '%', $post_id ) ) ) {
				$thumbfile = str_replace( basename( $file ), $meta['thumb'], $file );
				$thumbfile = apply_filters( 'wp_delete_file', $thumbfile );

				@ unlink( path_join( $private_dir, $thumbfile ) ); // @codingStandardsIgnoreLine
			}
		}

		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {

			foreach ( $meta['sizes'] as $size => $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				$intermediate_file = apply_filters( 'wp_delete_file', $intermediate_file );

				@ unlink( path_join( $private_dir, $intermediate_file ) ); // @codingStandardsIgnoreLine
			}
		}

		if ( is_array( $backup_sizes ) ) {

			foreach ( $backup_sizes as $size ) {
				$del_file = path_join( dirname( $meta['file'] ), $size['file'] );
				$del_file = apply_filters( 'wp_delete_file', $del_file );

				@ unlink( path_join( $private_dir, $del_file ) ); // @codingStandardsIgnoreLine
			}
		}
	}

	public function wp_relative_upload_path( $new_path, $path ) {
		$private_dir = self::get_data_dir();

		if ( 0 === strpos( $new_path, $private_dir ) ) {
			$new_path = str_replace( $private_dir, '', $new_path );
			$new_path = ltrim( $new_path, '/' );
		}

		return $new_path;
	}

	public function get_attached_file( $path, $attachment_id ) {
		$permissions = get_post_meta( $attachment_id, 'pvtmed_settings', true );

		if ( is_array( $permissions ) && in_array( 1, array_values( $permissions ), true ) ) {
			$upload_dir  = wp_upload_dir();
			$public_dir  = trailingslashit( $upload_dir['basedir'] );
			$private_dir = self::get_data_dir();
			$path        = str_replace( $public_dir, $private_dir, $path );
		}

		return $path;
	}

	public function wp_get_attachment_url( $url, $attachment_id ) {
		$permissions = get_post_meta( $attachment_id, 'pvtmed_settings', true );

		if ( is_array( $permissions ) && in_array( 1, array_values( $permissions ), true ) ) {
			$upload_dir         = wp_upload_dir();
			$site_url           = get_option( 'siteurl' );
			$public_upload_url  = trailingslashit( $upload_dir['baseurl'] );
			$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );
			$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );
			$url                = str_replace( $public_upload_url, $private_upload_url, $url );
		}

		return $url;
	}

	public function get_image_tag_class( $class, $id, $align, $size ) {
		$class .= ' pvtmed-enabled';

		return $class;
	}

	public function wp_calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$site_url           = get_option( 'siteurl' );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		if ( false !== strpos( $image_src, $private_upload_url ) ) {
			$upload_dir        = wp_upload_dir();
			$public_upload_url = trailingslashit( $upload_dir['baseurl'] );

			foreach ( $sources as $key => $url ) {
				$sources[ $key ] = str_replace( $public_upload_url, $private_upload_url, $url );
			}
		}

		return $sources;
	}

	public function move_media( $attachment_id, $operation, $update_meta = true ) {
		global $wp_filesystem;

		$upload_dir     = wp_upload_dir();
		$private_dir    = self::get_data_dir();
		$public_dir     = trailingslashit( $upload_dir['basedir'] );
		$file_path      = get_attached_file( $attachment_id, true );
		$path_fragments = explode( '/', $file_path );

		array_pop( $path_fragments );

		$file_basepath       = trailingslashit( implode( '/', $path_fragments ) );
		$destination_basedir = ( 'public' === $operation ) ? $public_dir : $private_dir;
		$source_basedir      = ( 'public' === $operation ) ? $private_dir : $public_dir;
		$subdir              = trailingslashit( str_replace( $destination_basedir, '', str_replace( $source_basedir, '', $file_basepath ) ) );
		$attachment_meta     = wp_get_attachment_metadata( $attachment_id, true );
		$files               = array( str_replace( $destination_basedir, $source_basedir, $file_path ) );

		if ( isset( $attachment_meta['sizes'] ) && ! empty( $attachment_meta['sizes'] ) ) {

			foreach ( $attachment_meta['sizes'] as $size_info ) {
				$files[] = $source_basedir . $subdir . $size_info['file'];
			}
		}

		if ( ! empty( $subdir ) ) {
			$path_fragments = explode( '/', untrailingslashit( $subdir ) );
			$partial_path   = $destination_basedir;

			foreach ( $path_fragments as $fragment ) {

				if ( ! $wp_filesystem->is_dir( $partial_path . $fragment ) ) {
					$wp_filesystem->mkdir( $partial_path . $fragment );
				}

				$partial_path = trailingslashit( $partial_path . $fragment );
			}
		}

		foreach ( $files as $file ) {
			$source      = $file;
			$destination = str_replace( $source_basedir, $destination_basedir, $file );

			if ( $wp_filesystem->is_file( $source ) ) {
				$wp_filesystem->move( $source, $destination, true );
			}
		}

		update_attached_file( $attachment_id, str_replace( $destination_basedir, $source_basedir, $file_path ) );

		if ( $update_meta ) {
			update_post_meta( $attachment_id, 'pvtmed_private', ( 'private' === $operation ) );
		}
	}

	protected function create_data_dir() {
		global $wp_filesystem;

		return $wp_filesystem->mkdir( self::get_data_dir() );
	}

	protected function generate_restricted_htaccess( $file_path ) {
		WP_Filesystem();

		global $wp_filesystem;

		$site_url       = get_option( 'siteurl' );
		$htaccess_rules = $wp_filesystem->get_contents( PVTMED_PLUGIN_PATH . 'assets/data/htaccess.txt' );
		$htaccess_rules = str_replace( '[SITE_URL]', $site_url, $htaccess_rules );
		$htaccess_rules = apply_filters( 'pvtmed_htaccess_rules', $htaccess_rules );

		if ( ! $wp_filesystem->is_file( $file_path ) ) {
			$result = $wp_filesystem->touch( $file_path );

			return $result && $wp_filesystem->put_contents( $file_path, $htaccess_rules, 0644 );
		}

		return true;
	}
}
