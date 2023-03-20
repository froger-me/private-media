<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages private and public attachments.
 */
class Private_Media_Attachment_Manager {
	//data directory
	protected static $root_data_dirname = 'pvtmed-uploads';

	//post meta
	const POST_META_PRIVATE  = 'pvtmed_private';
	const POST_META_SETTINGS = 'pvtmed_settings';

	protected static $fix_prefixes = [
		'default' => [
			'src'  => 'src="',
			'href' => 'href="',
		],
		'video'   => [
			'flv'  => 'flv="',
			'mp4'  => 'mp4="',
			'm4v'  => 'm4v="',
			'webm' => 'webm="',
			'ogv'  => 'ogv="',
			'wmv'  => 'wmv="',
		],
		'audio'   => [
			'mp3' => 'mp3="',
			'm4a' => 'm4a="',
			'ogg' => 'ogg="',
			'wav' => 'wav="',
			'wma' => 'wma="',
		],
	];

	/**
	 * Create instance.
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'delete_attachment', [ $this, 'delete_attachment' ], -10, 1 );

			add_filter( 'add_post_metadata', [ $this, 'add_post_metadata' ], 10, 5 );
			add_filter( '_wp_relative_upload_path', [ $this, 'wp_relative_upload_path' ], -10, 2 );
			add_filter( 'get_attached_file', [ $this, 'get_attached_file' ], -10, 2 );
			add_filter( 'wp_get_attachment_url', [ $this, 'wp_get_attachment_url' ], -10, 2 );
			add_filter( 'get_image_tag_class', [ $this, 'get_image_tag_class' ], -10, 4 );
			add_filter( 'wp_calculate_image_srcset', [ $this, 'wp_calculate_image_srcset' ], 10, 5 );
		}
	}

	/**
	 * Get the name of the root directory used to store all protected fles.
	 */
	public static function get_root_dir_name() {
		return self::$root_data_dirname;
	}

	/**
	 * Get the data directory (full path with trailing slash).
	 */
	public static function get_data_dir() {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			self::trigger_plugin_error( __( 'File system not available.', 'pvtmed' ), E_USER_ERROR );
		}

		$data_dir = trailingslashit( $wp_filesystem->wp_content_dir() . self::$root_data_dirname );

		return trailingslashit( $data_dir );
	}

	/**
	 * Create the data directory if needed an add .htaccess file.
	 *
	 * .htaccess file redirect to rewrite rulle ULR (/pvtmed/<file>)
	 */
	public function maybe_setup_directories() {
		// 1) create the data directory
		$root_dir = self::get_data_dir();
		$result   = true;

		global $wp_filesystem;

		if ( ! $wp_filesystem->is_dir( $root_dir ) ) {
			$result = $result && $this->create_data_dir();
		}

		// 2) create .htaccess file in data directory
		$htaccess_path = $root_dir . '.htaccess';

		if ( ! $wp_filesystem->is_file( $htaccess_path ) ) {
			$result = $result && $this->generate_restricted_htaccess( $htaccess_path );
		}

		return $result;
	}

	/**
	 * Move all private files to be in the private or public folder.
	 *
	 * Note: runs on plugin activation only
	 */
	public function apply_plugin_private_policy( $apply ) {
		//get all private attachments
		$attachment_ids = $this->get_all_private_attachment_ids();
		$operation      = ( $apply ) ? 'private' : 'public';

		if ( ! empty( $attachment_ids ) ) {
			//move files
			foreach ( $attachment_ids as $key => $attachment_id ) {
				//keep private flag
				$this->move_media( $attachment_id, $operation, false );
			}

			//modify HTML markup (Note: does not support ACF fields)
			if ( $apply ) {
				//on activate
				$this->replace_public_markup_in_posts();
			} else {
				//on deactivate
				$this->replace_private_markup_in_posts();
			}
		}
	}

	/**
	 * Private links are now broken: mark them in post_content.
	 */
	protected function replace_private_markup_in_posts() {
		global $wpdb;

		$public_upload_url  = self::get_public_upload_url();
		$private_upload_url = self::get_private_upload_url();

		foreach ( self::$fix_prefixes as $type => $types ) {
			//skip URL updates
			if ( 'audio' === $type || 'video' === $type ) {
				if ( ! get_option( 'pvtmed_deactivate_migrate_' . $type, false ) ) {
					continue;
				} else {
					delete_option( 'pvtmed_deactivate_migrate_' . $type );
				}
			}

			//modify HTML attributes
			foreach ( $types as $key => $prefix ) {
				$affected_rows = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET `post_content` = REPLACE ( `post_content`, %s, %s ) WHERE (`post_content` LIKE %s)",
						//previous private URL
						$prefix . $private_upload_url,
						//insert data-pvtmed="needs-fix" before
						'data-pvtmed="needs-fix" ' . $prefix . $public_upload_url,
						//URL pattern
						'%' . $prefix . $private_upload_url . '%'
					)
				);

				//store amount
				if ( $affected_rows > 0 ) {
					update_option( 'pvtmed_needs_fix_' . $key, true, false );
				}
			}
		}
	}

	/**
	 * Remove previously added markers again.
	 */
	protected function replace_public_markup_in_posts() {
		global $wpdb;

		$public_upload_url  = self::get_public_upload_url();
		$private_upload_url = self::get_private_upload_url();

		//iterate list of prefixes
		foreach ( self::$fix_prefixes as $type => $types ) {
			foreach ( $types as $key => $prefix ) {
				if ( get_option( 'pvtmed_needs_fix_' . $key ) ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->posts} SET `post_content` = REPLACE ( `post_content`, %s, %s ) WHERE (`post_content` LIKE %s)",
							//previous value
							'data-pvtmed="needs-fix" ' . $prefix . $public_upload_url,
							//corrected value
							$prefix . $private_upload_url,
							//search pattern
							'%data-pvtmed="needs-fix" ' . $prefix . $public_upload_url . '%'
						)
					);

					delete_option( 'pvtmed_needs_fix_' . $key );
				}
			}
		}
	}

	/**
	 * Get IDs of all private attachments.
	 */
	protected function get_all_private_attachment_ids() {
		$args = [
			'fields'      => 'ids',
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => [
				[
					'key'   => self::POST_META_PRIVATE,
					'value' => true,
					'type'  => 'BOOLEAN'
				]
			]
		];
		$query = new WP_Query( $args );

		wp_reset_postdata();

		return ( $query->posts ) ? $query->posts : [];
	}

	/**
	 * Filter post meta additions. Prevents private files being using in enclosures.
	 *
	 *   - https://developer.wordpress.org/reference/hooks/add_meta_type_metadata/
	 *
	 * Enclosure:
	 *
	 *   - https://github.com/WordPress/WordPress/blob/a2c7bba031f4d6b84193103f13662c185bb0a156/wp-includes/class-wp-xmlrpc-server.php#L5672
	 *   - https://github.com/WordPress/WordPress/blob/a2c7bba031f4d6b84193103f13662c185bb0a156/wp-includes/class-wp-xmlrpc-server.php#L976
	 *   - https://odd.blog/2004/10/11/enclosures-in-wordpress/
	 */
	public function add_post_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
		if ( 'enclosure' === $meta_key ) {
			//get all post attachments
			$args = [
				'post_parent'    => $object_id,
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC'
			];
			$attachments = get_children( $args );

			foreach ( $attachments as $attachment ) {
				if ( self::is_private_attachment( $attachment->ID ) ) {
					//private file
					$private_upload_url = self::get_private_upload_url();

					//do not allow private files in enclosures
					if ( false !== strpos( $meta_value, $private_upload_url ) ) {
						$check = false;
					}
				}
			}
		}

		return $check;
	}

	/**
	 * Delete an attachment.
	 */
	public function delete_attachment( $attachment_id ) {
		//check if private file
		if ( ! self::is_private_attachment( $attachment_id ) ) {
			//skip public
			return;
		}

		//delete all files
		$meta         = wp_get_attachment_metadata( $attachment_id );
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$file         = get_attached_file( $attachment_id );
		$private_dir  = self::get_data_dir();

		if ( ! empty( $meta['thumb'] ) ) {
			global $wpdb;

			if ( ! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%' . $wpdb->esc_like( $meta['thumb'] ) . '%', $attachment_id ) ) ) {
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

	/**
	 * Modify relative upload path of file.
	 *
	 * See https://developer.wordpress.org/reference/hooks/_wp_relative_upload_path/.
	 */
	public function wp_relative_upload_path( $new_path, $path ) {
		//check if private file
		$private_dir = self::get_data_dir();

		if ( 0 === strpos( $new_path, $private_dir ) ) {
			//create relative path from full path
			$new_path = str_replace( $private_dir, '', $new_path );
			$new_path = ltrim( $new_path, '/' );
		}

		return $new_path;
	}

	/**
	 * Convert path of private files.
	 *
	 * See https://developer.wordpress.org/reference/hooks/get_attached_file/.
	 */
	public function get_attached_file( $path, $attachment_id ) {
		//check if private file
		if ( self::is_private_attachment( $attachment_id ) ) {
			//private file
			$public_dir  = self::get_public_upload_directory();
			$private_dir = self::get_data_dir();
			$path        = str_replace( $public_dir, $private_dir, $path );
		}

		return $path;
	}

	/**
	 * Return the attachment URL.
	 */
	public function wp_get_attachment_url( $url, $attachment_id ) {
		//check if private file
		if ( self::is_private_attachment( $attachment_id ) ) {
			//private file
			$public_upload_url  = self::get_public_upload_url();
			$private_upload_url = self::get_private_upload_url();
			$url                = str_replace( $public_upload_url, $private_upload_url, $url );
		}

		return $url;
	}

	/**
	 * Add custom CSS class.
	 */
	public function get_image_tag_class( $class, $id, $align, $size ) {
		$class .= ' pvtmed-enabled';

		return $class;
	}

	/**
	 * Convert all image URLs used by srcsets.
	 */
	public function wp_calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		//check if private URL is being used
		$private_upload_url = self::get_private_upload_url();

		if ( false !== strpos( $image_src, $private_upload_url ) ) {
			//private file
			$public_upload_url = self::get_public_upload_url();

			//convert all source files URLs
			foreach ( $sources as $key => $url ) {
				$sources[ $key ] = str_replace( $public_upload_url, $private_upload_url, $url );
			}
		}

		return $sources;
	}

	/**
	 * Move a file from public to private folder or vice versa.
	 */
	protected function move_media( $attachment_id, $operation, $update_meta = true ) {
		global $wp_filesystem;

		$public_dir     = self::get_public_upload_directory();
		$private_dir    = self::get_data_dir();
		$file_path      = get_attached_file( $attachment_id, true );
		$path_fragments = explode( '/', $file_path );

		array_pop( $path_fragments );

		$file_basepath       = trailingslashit( implode( '/', $path_fragments ) );
		$destination_basedir = ( 'public' === $operation ) ? $public_dir : $private_dir;
		$source_basedir      = ( 'public' === $operation ) ? $private_dir : $public_dir;
		$subdir              = trailingslashit( str_replace( $destination_basedir, '', str_replace( $source_basedir, '', $file_basepath ) ) );
		$attachment_meta     = wp_get_attachment_metadata( $attachment_id, true );

		//collect all files
		$files = [ str_replace( $destination_basedir, $source_basedir, $file_path ) ];

		//add all size
		if ( isset( $attachment_meta['sizes'] ) && ! empty( $attachment_meta['sizes'] ) ) {
			foreach ( $attachment_meta['sizes'] as $size_info ) {
				$files[] = $source_basedir . $subdir . $size_info['file'];
			}
		}

		//create directories
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

		//move file
		foreach ( $files as $file ) {
			$source      = $file;
			$destination = str_replace( $source_basedir, $destination_basedir, $file );

			if ( $wp_filesystem->is_file( $source ) ) {
				$wp_filesystem->move( $source, $destination, true );
			}
		}

		//update post meta value
		update_attached_file( $attachment_id, str_replace( $destination_basedir, $source_basedir, $file_path ) );

		if ( $update_meta ) {
			//set the private post meta flag (Note: boolean is stored as '1'/'' for true/false)
			update_post_meta( $attachment_id, self::POST_META_PRIVATE, ( 'private' === $operation ) );
		}
	}

	/**
	 * Set the attachment state.
	 *
	 * Note: $attachment is array, not a WP_Post instance!
	 */
	public function set_attachment_permissions( $attachment, $permissions = null ) {
		//accept integer parameters
		if (is_integer( $attachment )) {
			$attachment_id = $attachment;
			$attachment = get_post( $attachment_id, ARRAY_A ); //backwards compatible (using array)
		} else {
			$attachment_id = $attachment['ID'];
		}

		//check private
		$private = ! empty( $permissions ) && in_array( 1, array_values( $permissions ), true );

		//check if move required (state changed)
		$location_changed = self::is_private_attachment( $attachment_id ) !== $private;

		if ($location_changed) {
			//move needed
			if ( $private ) {
				//private file
				$mime_type = $attachment['post_mime_type'];

				//skip URL updates for video/audio HTML in activation/deactivation of plugin
				if ( 0 === strpos( $mime_type, 'video' ) ) {
					update_option( 'pvtmed_deactivate_migrate_video', true, false );
				}

				if ( 0 === strpos( $mime_type, 'audio' ) ) {
					update_option( 'pvtmed_deactivate_migrate_audio', true, false );
				}

				//move the files (sets private flag)
				$this->move_media( $attachment_id, 'private' );
			} else {
				//public file (resets private flag)
				$this->move_media( $attachment_id, 'public' );
			}
		}

		//update permissions
		if ( $private ) {
			update_post_meta( $attachment_id, self::POST_META_SETTINGS, $permissions );
		} else {
			//remove
			delete_post_meta( $attachment_id, self::POST_META_SETTINGS );
		}

		return true;
	}

	/**
	 * Create the data directory.
	 */
	protected function create_data_dir() {
		global $wp_filesystem;

		return $wp_filesystem->mkdir( self::get_data_dir() );
	}

	/**
	 * Creates the .htaccess file in the data directory if needed.
	 *
	 * Default is the template version which can be modified by the
	 * pvtmed_htaccess_rules filter.
	 */
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

	/**
	 * Check if an attachment is private (i.e. the file is stored at the private folder location).
	 */
	public static function is_private_attachment( $attachment_id ) {
		return get_post_meta( $attachment_id, self::POST_META_PRIVATE, true ) === '1';
	}

	/**
	 * Get the raw permission array.
	 */
	public static function get_attachment_permissions( $attachment_id ) {
		return get_post_meta( $attachment_id, self::POST_META_SETTINGS, true );
	}

	/**
	 * Get the public upload directory URL.
	 */
	public static function get_public_upload_url() {
		$upload_dir        = wp_upload_dir();
		$public_upload_url = trailingslashit( $upload_dir['baseurl'] );

		return $public_upload_url;
	}

	/**
	 * Get the public upload directory.
	 */
	public static function get_public_upload_directory() {
		$upload_dir = wp_upload_dir();
		$public_dir = trailingslashit( $upload_dir['basedir'] );

		return $public_dir;
	}

	/**
	 * Get the private upload directory URL (data directory URL).
	 */
	public static function get_private_upload_url() {
		$site_url           = get_option( 'siteurl' );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', self::get_data_dir() );

		//apply filter
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		return $private_upload_url;
	}
}
