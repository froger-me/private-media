<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Main plugin class.
 *
 * Manages file handling and admin interface integration.
 */
class Private_Media {
	protected $request_handler;
	protected $attachment_manager;

	protected static $doing_private_media_api_request;

	/**
	 * Create instance.
	 */
	public function __construct( $request_handler, $attachment_manager, $init_hooks = false ) {
		WP_Filesystem();

		$this->request_handler    = $request_handler;
		$this->attachment_manager = $attachment_manager;

		if ( $init_hooks ) {
			//request handling
			add_filter( 'query_vars', [ $this, 'add_query_vars' ], -10, 1 );
			add_action( 'parse_request', [ $this, 'parse_request' ], -10, 0 );

			//cbxx TODO custom APIs

			if ( ! self::is_doing_api_request() ) {
				//general handling
				add_action( 'init', [ $this, 'add_endpoints' ], -10, 0 );
				add_action( 'init', [ $this, 'register_activation_notices' ], 99, 0 );
				add_action( 'init', [ $this, 'maybe_flush' ], 99, 0 );
				add_action( 'init', [ $this, 'load_textdomain' ], 10, 0 );

				add_action( 'wp_enqueue_scripts', [ $this, 'add_frontend_scripts' ], -10, 0 );

				//admin only
				if ( is_admin() ) {
					add_action( 'wp_tiny_mce_init', [ $this, 'add_wp_tiny_mce_init_script' ], -10, 0 );

					add_filter( 'admin_enqueue_scripts', [ $this, 'add_admin_scripts' ], -10, 1 );

					//attachment settings
					add_filter( 'attachment_fields_to_save', [ $this, 'attachment_field_settings_save' ], 10, 2 );
					add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_field_settings' ], 10, 2 );

					//media grid view
					add_filter( 'wp_prepare_attachment_for_js', [ $this, 'attachment_js_data' ], 10, 3 );

					//media list view
					//cbxx TODO
					add_filter( 'manage_media_columns', [ $this, 'add_media_list_column' ], 10, 2 );
					add_action( 'manage_media_custom_column', [ $this, 'render_media_list_column' ], 10, 2 );
				}
			}
		}
	}

	/**
	 * The plugin got activated.
	 */
	public static function activate() {
		//update rewrite rules later
		set_transient( 'pvtmed_flush', 1, 60 );

		// 1) setup directories
		$manager = new Private_Media_Attachment_Manager();
		$result  = $manager->maybe_setup_directories();

		if ( ! $result ) {
			// translators: %1$s is the path to the plugin's data directory
			$error_message = sprintf( __( 'Permission errors creating <code>%1$s</code> - could not setup the data directory. Please check the parent directory is writable.', 'pvtmed' ), Private_Media_Attachment_Manager::get_data_dir() );

			die( $error_message ); // @codingStandardsIgnoreLine
		}

		// 2) move private attachments
		$manager->apply_plugin_private_policy( true );

		// 3) install must-use plugin
		$result = self::maybe_setup_mu_plugin();

		if ( $result ) {
			set_transient( 'pvtmed_activated_mu_success', 1, 60 );
		} else {
			set_transient( 'pvtmed_activated_mu_failure', 1, 60 );
		}
	}

	/**
	 * The plugin got deactivated.
	 */
	public static function deactivate() {
		$manager = new Private_Media_Attachment_Manager();

		//move private attachments to uploads folder
		$manager->apply_plugin_private_policy( false );

		flush_rewrite_rules();
	}

	/**
	 * Uninstall the plugin.
	 */
	public static function uninstall() {
		require_once PVTMED_PLUGIN_PATH . 'uninstall.php';
	}

	/**
	 * Setup of must-use plugin failed.
	 */
	public static function setup_mu_plugin_failure_notice() {
		$class = 'notice notice-error';
		// translators: %1$s is the path to the mu-plugins directory, %2$s is the path of the source MU Plugin
		$message = sprintf( __( 'Permission errors for <code>%1$s</code> - could not setup the endpoint optimizer MU Plugin. You may create the directory if necessary and manually copy <code>%2$s</code> in it (recommended).', 'pvtmed' ),
			trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ),
			wp_normalize_path( WPPUS_PLUGIN_PATH . 'assets/data/pvtmed-endpoint-optimizer.php' )
		);

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // @codingStandardsIgnoreLine
	}

	/**
	 * Setup of must-use plugin was successful.
	 */
	public static function setup_mu_plugin_success_notice() {
		$class = 'notice notice-info is-dismissible';
		// translators: %1$s is the path to the mu-plugin
		$message = sprintf( __( 'An endpoint optimizer MU Plugin has been confirmed to be installed in <code>%1$s</code>.', 'pvtmed' ),
			trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) . 'pvtmed-endpoint-optimizer.php'
		);

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // @codingStandardsIgnoreLine
	}

	/**
	 * Check if requesting private file.
	 */
	public static function is_doing_api_request() {
		//get state
		if ( null === self::$doing_private_media_api_request ) {
			self::$doing_private_media_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], Private_Media_Attachment_Manager::get_root_dir_name() ) );
		}

		return self::$doing_private_media_api_request;
	}

	/**
	 * Show must-use plugin notices.
	 */
	public function register_activation_notices() {
		if ( get_transient( 'pvtmed_activated_mu_failure' ) ) {
			delete_transient( 'pvtmed_activated_mu_failure' );
			add_action( 'admin_notices', array( __CLASS__, 'setup_mu_plugin_failure_notice' ), 10, 0 );
		}

		if ( get_transient( 'pvtmed_activated_mu_success' ) ) {
			delete_transient( 'pvtmed_activated_mu_success' );
			add_action( 'admin_notices', array( __CLASS__, 'setup_mu_plugin_success_notice' ), 10, 0 );
		}
	}

	/**
	 * Load language files.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pvtmed', false, 'private-media/languages' );
	}

	/**
	 * Update rewrite rules if needed.
	 */
	public function maybe_flush() {
		if ( get_transient( 'pvtmed_flush' ) ) {
			delete_transient( 'pvtmed_flush' );

			flush_rewrite_rules();
		}
	}

	/**
	 * Add query vars used by rewrite rule.
	 */
	public function add_query_vars( $query_vars ) {
		$vars = [
			'__pvtmed',
			'file',
		];
		$query_vars = array_merge( $query_vars, $vars );

		return $query_vars;
	}

	/**
	 * Intercept private file requests.
	 */
	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__pvtmed'] ) && $this->request_handler ) {
			$this->request_handler->handle_request();

			exit();
		}

		//check hotlinking support
		$hotlinkFeature = apply_filters( 'pvtmed_hotlink_feature', true );

		if ($hotlinkFeature) {
			//not a private media access -> set hotlink cookie
			setcookie( 'pvtmed', 1, current_time( 'timestamp' ) + 10, '/' );
		}
	}

	/**
	 * Define rewrite rule for delivering protected file.
	 */
	public function add_endpoints() {
		//file accessed at: https://<domain>/pvtmed/<file.ext>
		add_rewrite_rule( '^pvtmed/(.*)$', 'index.php?__pvtmed=1&file=$matches[1]', 'top' );
	}

	/**
	 * Filter attachments fields to be saved.
	 */
	public function attachment_field_settings_save( $attachment, $fields ) {
		global $wp_roles;

		//get roles and attachment permissions
		$attachment_id = $attachment['ID'];
		$roles         = $wp_roles->get_names();
		$permissions   = get_post_meta( $attachment_id, 'pvtmed_settings', true );

		if ( empty( $permissions ) ) {
			$permissions = [];
		}

		//add custom permissions
		$permissions = apply_filters('pvtmed_add_permissions', $permissions, $attachment, $fields);

		//check all roles
		foreach ( $roles as $key => $role_name ) {
			if ( isset( $fields[ 'pvtmed_' . $key ] ) ) {
				$permissions[ $key ] = 1;
			} else {
				unset( $permissions[ $key ] );
			}
		}

		//check always private
		if ( isset( $fields['pvtmed_always_private'] ) ) {
			$permissions['always_private'] = 1;
		} else {
			unset( $permissions['always_private'] );
		}

		//check hotlinking
		if ( isset( $fields['pvtmed_disable_hotlinks'] ) ) {
			$permissions['disable_hotlinks'] = 1;
		} else {
			unset( $permissions['disable_hotlinks'] );
		}

		//check one value is active (private file)
		if ( in_array( 1, array_values( $permissions ), true ) ) {
			//private file
			$mime_type = $attachment['post_mime_type'];

			//skip URL updates for video/audio HTML in activation/deactivation of plugin
			if ( 0 === strpos( $mime_type, 'video' ) ) {
				update_option( 'pvtmed_deactivate_migrate_video', true, false );
			}

			if ( 0 === strpos( $mime_type, 'audio' ) ) {
				update_option( 'pvtmed_deactivate_migrate_audio', true, false );
			}

			//move the files
			$this->attachment_manager->move_media( $attachment_id, 'private' );
		} else {
			//public file
			$this->attachment_manager->move_media( $attachment_id, 'public' );
		}

		//write permissions
		update_post_meta( $attachment_id, 'pvtmed_settings', $permissions );

		return $attachment;
	}

	/**
	 * Display attachments settings.
	 */
	public function attachment_field_settings( $form_fields, $attachment ) {
		//get permissions
		$permissions = get_post_meta( $attachment->ID, 'pvtmed_settings', true );

		global $wp_roles;

		$role_boxes = '<div class="setting">';

		//show warning if file is being used
		if ( $attachment->post_parent && -1 !== strpos( $attachment->post_mime_type, 'image' ) ) {
			$role_boxes .= '<em>' . __( 'Warning: This media is already attached to at least one existing post. You may need to re-insert it to make sure it is still accessible after changing its Media Privacy settings.', 'pvtmed' ) . '</em>';
		}

		$role_boxes .= '<ul>';

		//always private checkbox (define access rules later or by custom filter)
		$always_private = ( isset( $permissions['always_private'] ) && 1 === $permissions['always_private'] ) ? 'checked' : '';

		$role_boxes .= '<li><span>' . __( 'Private file (always kept private)', 'pvtmed' ) . '</span><input type="checkbox" name="attachments[' . $attachment->ID . '][pvtmed_always_private]" id="attachments[pvtmed_always_private]" value="1" ' . $always_private . '/></li>';
		$role_boxes .= '<li> <hr/> </li>';

		//hotlinking checkbox
		$hotlinkFeature = apply_filters( 'pvtmed_hotlink_feature', true );

		if ($hotlinkFeature) {
			$no_hotlinks = ( isset( $permissions['disable_hotlinks'] ) && 1 === $permissions['disable_hotlinks'] ) ? 'checked' : '';

			$role_boxes .= '<li><span>' . __( 'Prevent hotlinks (even when not limited by role)', 'pvtmed' ) . '</span><input type="checkbox" name="attachments[' . $attachment->ID . '][pvtmed_disable_hotlinks]" id="attachments[pvtmed_disable_hotlinks]" value="1" ' . $no_hotlinks . '/></li>';
			$role_boxes .= '<li> <hr/> </li>';
		}

		//role checkboxes
		$roles = $wp_roles->get_names();

		foreach ( $roles as $key => $role_name ) {
			$role_checked = ( isset( $permissions[ $key ] ) && 1 === $permissions[ $key ] ) ? 'checked' : '';

			// translators: %s is the role name
			$role_boxes .= '<li><span>' . sprintf( __( 'Limit to %s role' ), $role_name ) . '</span><input type="checkbox" name="attachments[' . $attachment->ID . '][pvtmed_' . $key . ']" id="attachments[pvtmed_' . $key . ']" value="' . $key . '" ' . $role_checked . '/></li>';
		}

		//end of UI
		$role_boxes .= '</ul></div>';

		//call filter
		$role_boxes = apply_filters( 'pvtmed_edit_settings', $role_boxes, $attachment, $form_fields );

		if ($role_boxes) {
			$form_fields['pvtmed'] = [
				'label' => __( 'Media Privacy', 'pvtmed' ),
				'input' => 'html',
				'html'  => $role_boxes
			];
		}

		//debug
		//$this->debug($form_fields);

		return $form_fields;
	}

	/**
	 * Add data for JavaScript rendering.
	 */
	public function attachment_js_data( $response, $attachment, $meta ) {
		//add private media flag
		$response['privateMedia'] = $this->is_private_attachment( $attachment->ID );

		//debug
		//$response['attachmentMeta'] = $meta;
		//$response['allMeta'] = get_post_meta( $attachment->ID );

		return $response;
	}

	/**
	 * Add a column to the media list view.
	 */
	public function add_media_list_column( $posts_columns, $detached ) {
		$posts_columns['pvtmed'] = __( 'Private Media', 'pvtmed' );

		return $posts_columns;
	}

	/**
	 * Render media list view column.
	 */
	public function render_media_list_column( string $column_name, int $post_id ) {
		if ( $column_name === 'pvtmed' ) {
			//display lock icon
			echo '<span class="dashicons dashicons-lock"></span>';
		}
	}

	//cbxx TODO move to attachment manager
	/**
	 * Check if an attachment is private (i.e. the file is stored at the private folder location).
	 */
	public function is_private_attachment( $attachment_id ) {
		return get_post_meta( $attachment_id, 'pvtmed_private', true ) === '1';
	}

	/**
	 * Get the raw permission array.
	 */
	public function get_attachment_permissions( $attachment_id ) {
		return get_post_meta( $attachment_id, 'pvtmed_settings', true );
	}

	/**
	 * Load frontend scripts.
	 *
	 * Used to workaround invalid URLs.
	 */
	public function add_frontend_scripts() {
		//check non-admin usage
		if (!is_admin() && !apply_filters( 'pvtmed_load_frontend', true )) {
			return;
		}

		$debug = (bool) ( constant( 'WP_DEBUG' ) );

		$upload_dir         = wp_upload_dir();
		$site_url           = get_option( 'siteurl' );
		$public_upload_url  = trailingslashit( $upload_dir['baseurl'] );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', Private_Media_Attachment_Manager::get_data_dir() );
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		$js_ext  = ( $debug ) ? '.js' : '.min.js';
		$version = filemtime( PVTMED_PLUGIN_PATH . 'assets/js/main' . $js_ext );

		$script_params = [
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'debug'             => $debug,
			'publicUrlBase'     => $public_upload_url,
			'privateUrlBase'    => $private_upload_url,
			'isAdmin'           => is_admin(),
			'brokenMessage'     => __( "Private Media Warning - a media in the post content has a broken source. An attempt to quickfix it dynamically will be performed, but it is recommended to delete it and insert it again.\nMedia URL:\n", 'pvtmed' ),
			'scriptUrls'        => [
				trailingslashit( get_option( 'siteurl' ) ) . 'wp-includes/js/jquery/jquery.js',
				PVTMED_PLUGIN_URL . 'assets/js/main' . $js_ext,
			],
			'deactivateConfirm' => __( "You are about to deactivate Private Media. All the media with restricted access will be publicly accessible again.\nIf you re-activate the plugin, Private Media will attempt to re-apply the privacy settings and fix possible broken links.\n\nAre you sure you want to do this?", 'pvtmed' ),
		];

		wp_enqueue_script( 'pvtmed-main', PVTMED_PLUGIN_URL . 'assets/js/main' . $js_ext, array( 'jquery' ), $version );
		wp_localize_script( 'pvtmed-main', 'Pvtmed', $script_params );
	}

	/**
	 * Load TinyMCE script.
	 */
	public function add_wp_tiny_mce_init_script() {
		$debug = (bool) ( constant( 'WP_DEBUG' ) );

		$upload_dir         = wp_upload_dir();
		$site_url           = get_option( 'siteurl' );
		$public_upload_url  = trailingslashit( $upload_dir['baseurl'] );
		$private_upload_url = trailingslashit( $site_url ) . str_replace( ABSPATH, '', Private_Media_Attachment_Manager::get_data_dir() );
		$private_upload_url = apply_filters( 'pvtmed_private_upload_url', $private_upload_url );

		$js_ext  = ( $debug ) ? '.js' : '.min.js';
		$version = filemtime( PVTMED_PLUGIN_PATH . 'assets/js/tinymce' . $js_ext );

		printf( '<script type="text/javascript" src="%s"></script>', PVTMED_PLUGIN_URL . 'assets/js/tinymce' . $js_ext . '?ver=' . $version ); // @codingStandardsIgnoreLine
	}

	/**
	 * Load admin scripts.
	 */
	public function add_admin_scripts( $hook ) {
		global $parent_file;

		$debug = (bool) ( constant( 'WP_DEBUG' ) );

		//check page type
		$hooks = [
			'plugins.php',
			'post.php',
			'post-new.php',
			'edit.php',
		];

		if ( in_array( $hook, $hooks, true ) || 'upload.php' === $parent_file ) {
			//load script
			$ext     = ( $debug ) ? '.js' : '.min.js';
			$version = filemtime( PVTMED_PLUGIN_PATH . 'assets/js/admin/main' . $ext );

			wp_enqueue_script(
				'pvtmed-admin-main',
				PVTMED_PLUGIN_URL . 'assets/js/admin/main' . $ext,
				array( 'jquery' ),
				$version,
				true
			);

			//load CSS
			$ext     = ( $debug ) ? '.css' : '.min.css';
			$version = filemtime( PVTMED_PLUGIN_PATH . 'assets/css/admin/main' . $ext );

			//load frontend scripts too
			$this->add_frontend_scripts();

			wp_enqueue_style(
				'pvtmed-admin-main',
				PVTMED_PLUGIN_URL . 'assets/css/admin/main' . $ext,
				[],
				$version
			);
		}
	}

	/**
	 * Handle plugin errors.
	 */
	protected static function trigger_plugin_error( $message, $err_type ) {
		$action = filter_input( INPUT_GET, 'action' );

		if ( $action && 'error_scrape' === $action ) {
			echo '<strong>' . $message . '</strong>'; // @codingStandardsIgnoreLine

			exit;
		} else {
			trigger_error( $message, $err_type ); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * Copy the endpoint optimizer to the must-use plugins folder.
	 */
	protected static function maybe_setup_mu_plugin() {
		global $wp_filesystem;

		$result        = true;
		$mu_plugin_dir = trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) );
		$mu_plugin     = $mu_plugin_dir . 'pvtmed-endpoint-optimizer.php';

		if ( ! $wp_filesystem->is_dir( $mu_plugin_dir ) ) {
			$result = $wp_filesystem->mkdir( $mu_plugin_dir );
		}

		if ( $result && ! $wp_filesystem->is_file( $mu_plugin ) ) {
			$source_mu_plugin = wp_normalize_path( PVTMED_PLUGIN_PATH . 'assets/data/pvtmed-endpoint-optimizer.php' );
			$result           = $wp_filesystem->copy( $source_mu_plugin, $mu_plugin );
		}

		return $result;
	}

	/**
	 * Debug function.
	 */
	protected function debug($msg, $params = []) {
		//pass to Quer Monitor
		do_action('qm/debug', $msg, $params);
	}
}


