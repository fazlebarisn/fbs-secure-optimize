<?php
/**
 * Main Controller Class for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates independent modules, settings, and telemetry statistics.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Controller {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Controller|null
	 */
	private static $instance = null;

	/**
	 * Active module instances.
	 *
	 * @since 1.0.0
	 * @var array<string, object>
	 */
	private $modules = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Controller
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->maybe_upgrade_schema();
		$this->load_modules();
		$this->init_hooks();
	}

	/**
	 * Ensure login attempts table exists if plugin was updated via FTP/SVN without reactivation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function maybe_upgrade_schema() {
		$installed_ver = get_option( 'fbsseop_db_version', '' );
		if ( FBS_SECURE_OPTIMIZE_VERSION === $installed_ver ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'fbsseop_login_attempts';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			username varchar(191) NOT NULL,
			attempt_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			success tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY ip_address (ip_address),
			KEY attempt_time (attempt_time)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$existing = get_option( 'fbsseop_settings', array() );
		update_option( 'fbsseop_settings', self::merge_settings_with_defaults( is_array( $existing ) ? $existing : array() ) );
		update_option( 'fbsseop_db_version', FBS_SECURE_OPTIMIZE_VERSION );
	}

	/**
	 * Register core WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'plugin_action_links_' . FBS_SECURE_OPTIMIZE_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );

		// Universal Top Admin Bar 1-Click Cache Purge & Auto-Invalidation Hooks (only when Asset Optimizer is active).
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_menu' ), 99 );
		add_action( 'admin_post_fbsseop_purge_cache', array( $this, 'handle_admin_bar_purge_cache' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_purge_admin_notice' ) );

		if ( self::is_module_enabled( 'asset_optimizer' ) ) {
			add_action( 'switch_theme', array( __CLASS__, 'purge_all_caches_without_opcache' ) );
			add_action( 'customize_save_after', array( __CLASS__, 'purge_all_caches_without_opcache' ) );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'purge_all_caches_without_opcache' ) );
			add_action( 'save_post', array( __CLASS__, 'maybe_purge_page_cache_on_post_change' ) );
			add_action( 'deleted_post', array( __CLASS__, 'purge_disk_page_cache' ) );
			add_action( 'wp_update_nav_menu', array( __CLASS__, 'purge_disk_page_cache' ) );
			add_action( 'comment_post', array( __CLASS__, 'purge_disk_page_cache' ) );
			add_action( 'wp_set_comment_status', array( __CLASS__, 'purge_disk_page_cache' ) );
		}
	}

	/**
	 * Purge guest HTML page cache when a published post or page is created or updated.
	 *
	 * @since 2.2.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_purge_page_cache_on_post_change( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::purge_disk_page_cache();
	}

	/**
	 * Add 1-Click "Purge Cache" menu node to the WordPress Top Admin Bar (frontend & backend).
	 *
	 * @since 2.1.0
	 * @param WP_Admin_Bar $wp_admin_bar WordPress Admin Bar instance.
	 * @return void
	 */
	public function register_admin_bar_menu( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! ( $wp_admin_bar instanceof WP_Admin_Bar ) ) {
			return;
		}

		// Only display the Top Admin Bar "Purge Cache" node when Asset & Core Web Vitals Optimizer is enabled.
		if ( ! self::is_module_enabled( 'asset_optimizer' ) ) {
			return;
		}

		$purge_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=fbsseop_purge_cache' ),
			'fbsseop_purge_cache_action'
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'fbsseop-toolbar',
				'title' => '<span class="ab-icon dashicons dashicons-performance" style="margin-top:2px;"></span><span class="ab-label">' . esc_html__( 'Purge Cache', 'fbs-secure-optimize' ) . '</span>',
				'href'  => $purge_url,
				'meta'  => array(
					'title' => esc_attr__( 'Purge FBS Secure Optimize, Object, OPcache & Host Caches', 'fbs-secure-optimize' ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'fbsseop-toolbar-purge-all',
				'parent' => 'fbsseop-toolbar',
				'title'  => esc_html__( 'Purge All Caches Now', 'fbs-secure-optimize' ),
				'href'   => $purge_url,
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'fbsseop-toolbar-speed',
				'parent' => 'fbsseop-toolbar',
				'title'  => esc_html__( 'Speed & Core Web Vitals', 'fbs-secure-optimize' ),
				'href'   => admin_url( 'admin.php?page=fbs-secure-optimize&tab=performance' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'fbsseop-toolbar-control',
				'parent' => 'fbsseop-toolbar',
				'title'  => esc_html__( 'Module Control Center', 'fbs-secure-optimize' ),
				'href'   => admin_url( 'admin.php?page=fbs-secure-optimize' ),
			)
		);

		if ( self::is_module_enabled( 'malware_scanner' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'fbsseop-toolbar-scanner',
					'parent' => 'fbsseop-toolbar',
					'title'  => esc_html__( '6-Layer Malware Scanner', 'fbs-secure-optimize' ),
					'href'   => admin_url( 'admin.php?page=fbs-secure-optimize&tab=scanner' ),
				)
			);
		}
	}

	/**
	 * Handle Top Admin Bar 1-Click Cache Purge request and redirect back safely.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function handle_admin_bar_purge_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'fbs-secure-optimize' ) );
		}

		check_admin_referer( 'fbsseop_purge_cache_action' );

		self::purge_all_caches( true );

		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = admin_url( 'admin.php?page=fbs-secure-optimize' );
		}

		$referer = remove_query_arg( array( 'fbsseop_purged', '_wpnonce' ), $referer );
		$target  = add_query_arg( 'fbsseop_purged', '1', $referer );

		self::safe_redirect_or_fallback( $target );
	}

	/**
	 * Perform a safe admin redirect with an automatic HTML/JS fallback if headers were already sent.
	 *
	 * Prevents blank white screens on admin-post.php if any server or third-party plugin emitted output.
	 *
	 * @since 2.2.1
	 * @param string $url Validated admin destination URL.
	 * @return void
	 */
	public static function safe_redirect_or_fallback( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		$safe_url = esc_url( wp_validate_redirect( $url, admin_url( 'admin.php?page=fbs-secure-optimize' ) ) );
		echo '<script>window.location.replace(' . wp_json_encode( $safe_url ) . ');</script>';
		echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $safe_url ) . '"></noscript>';
		exit;
	}

	/**
	 * Display confirmation admin notice on standard wp-admin screens after a Top Bar cache purge.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function maybe_render_purge_admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice flag after verified admin-post redirect.
		if ( ! isset( $_GET['fbsseop_purged'] ) || '1' !== $_GET['fbsseop_purged'] ) {
			return;
		}

		// FBS Secure Optimize screens render their own native SaaS toast banner.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 === strpos( $page, 'fbs-secure-optimize' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'FBS Secure Optimize:', 'fbs-secure-optimize' ),
			esc_html__( 'All optimization, CSS/CLS, object, OPcache, and compatible page caches have been purged.', 'fbs-secure-optimize' )
		);
	}

	/**
	 * Boot admin UI controller when inside wp-admin.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init_admin() {
		if ( is_admin() && class_exists( 'FBS_Secure_Optimize_Admin' ) ) {
			FBS_Secure_Optimize_Admin::get_instance();
		}
	}

	/**
	 * Add quick Settings and Upgrade links on the Plugins list screen.
	 *
	 * @since 2.0.0
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=fbs-secure-optimize' ) ),
			esc_html__( 'Settings', 'fbs-secure-optimize' )
		);
		array_unshift( $links, $settings_link );

		if ( ! self::is_pro_active() ) {
			$is_bfcm = self::is_bfcm_promo_active();
			$pro_url = $is_bfcm
				? self::utm_url( 'black-friday/', 'bfcm', 'plugins-list', 'fbs-secure-optimize-pro' )
				: self::utm_url( 'plugins/fbs-secure-optimize/', 'pro-upgrade', 'plugins-list' );

			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#059669;font-weight:700;">%s</a>',
				esc_url( $pro_url ),
				$is_bfcm
					? esc_html__( '40% OFF Black Friday', 'fbs-secure-optimize' )
					: esc_html__( 'Get Pro', 'fbs-secure-optimize' )
			);
		}

		return $links;
	}

	/**
	 * Build an FBS Fusion URL tagged with clean UTM attribution parameters.
	 *
	 * @since 2.2.1
	 * @param string $path     Relative path on fbsfusion.com (e.g. 'black-friday/' or 'plugins/fbs-secure-optimize/').
	 * @param string $campaign Campaign slug (e.g. 'bfcm', 'pro-upgrade', 'studio-audit').
	 * @param string $content  Placement identifier (e.g. 'pro-firewall-tab', 'overview-banner').
	 * @param string $anchor   Optional hash fragment without leading '#'.
	 * @return string
	 */
	public static function utm_url( $path = 'plugins/fbs-secure-optimize/', $campaign = 'pro-upgrade', $content = 'dashboard', $anchor = '' ) {
		$clean_path = ltrim( (string) $path, '/' );
		$base_url   = 'https://fbsfusion.com/' . $clean_path;

		$url = add_query_arg(
			array(
				'utm_source'   => 'fbs-secure-optimize',
				'utm_medium'   => 'wp-admin',
				'utm_campaign' => sanitize_key( $campaign ),
				'utm_content'  => sanitize_key( $content ),
			),
			$base_url
		);

		if ( '' !== $anchor ) {
			$url .= '#' . ltrim( sanitize_text_field( $anchor ), '#' );
		}

		return $url;
	}

	/**
	 * Determine whether the annual Black Friday / Cyber Monday promotional window (Nov 12 – Dec 2) is active.
	 *
	 * @since 2.2.1
	 * @return bool
	 */
	public static function is_bfcm_promo_active() {
		$md = (int) gmdate( 'md' );
		return ( $md >= 1112 && $md <= 1202 );
	}

	/**
	 * Conditionally instantiate modules based on independent master toggles.
	 *
	 * Disabled modules register zero frontend hooks for zero overhead.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_modules() {
		if ( self::is_module_enabled( 'asset_optimizer' ) ) {
			$this->modules['asset_optimizer'] = FBS_Secure_Optimize_Asset_Optimizer::get_instance();
		}

		if ( self::is_module_enabled( 'database_cleanup' ) ) {
			$this->modules['database_cleanup'] = FBS_Secure_Optimize_Database_Cleanup::get_instance();
		}

		if ( self::is_module_enabled( 'login_security' ) ) {
			$this->modules['login_security'] = FBS_Secure_Optimize_Login_Security::get_instance();
		}

		if ( self::is_module_enabled( 'security_headers' ) ) {
			$this->modules['security_headers'] = FBS_Secure_Optimize_Security_Headers::get_instance();
		}

		if ( self::is_module_enabled( 'wp_hardening' ) ) {
			$this->modules['wp_hardening'] = FBS_Secure_Optimize_Hardening::get_instance();
		}

		if ( self::is_module_enabled( 'malware_scanner' ) ) {
			$this->modules['malware_scanner'] = FBS_Secure_Optimize_Scanner::get_instance();
		}
	}

	/**
	 * Enqueue admin stylesheet and script only on FBS Secure Optimize screens.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin screen hook suffix.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( false === strpos( $hook, 'fbs-secure-optimize' ) ) {
			return;
		}

		$css_ver = FBS_SECURE_OPTIMIZE_VERSION . '.' . (int) filemtime( FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'assets/css/admin.css' );
		$js_ver  = FBS_SECURE_OPTIMIZE_VERSION . '.' . (int) filemtime( FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'assets/js/admin.js' );

		wp_enqueue_style(
			'fbsseop-admin',
			FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'fbsseop-admin',
			FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		$js_pro_url = self::is_bfcm_promo_active()
			? self::utm_url( 'black-friday/', 'bfcm', 'scanner-results', 'fbs-secure-optimize-pro' )
			: self::utm_url( 'plugins/fbs-secure-optimize/', 'pro-upgrade', 'scanner-results' );

		wp_localize_script(
			'fbsseop-admin',
			'fbsseopAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fbsseop_admin_nonce' ),
				'isPro'   => self::is_pro_active(),
				'proUrl'  => $js_pro_url,
				'strings' => array(
					'confirmCleanup'           => __( 'Run database cleanup now? Selected revisions, drafts, spam, and expired transients will be permanently removed.', 'fbs-secure-optimize' ),
					'cleaning'                 => __( 'Running cleanup...', 'fbs-secure-optimize' ),
					'purging'                  => __( 'Purging cache...', 'fbs-secure-optimize' ),
					'scanning'                 => __( 'Running Deep Forensic Scan...', 'fbs-secure-optimize' ),
					'scanBtnLabel'             => __( 'Run 6-Layer Deep Scan', 'fbs-secure-optimize' ),
					'confirmDelete'            => __( 'Permanently delete this threat? Make sure you have verified the preview first.', 'fbs-secure-optimize' ),
					'confirmDeactivateSnippet' => __( 'Deactivate this database snippet directly in MySQL? Its PHP/HTML execution will stop immediately.', 'fbs-secure-optimize' ),
					'confirmDeleteSnippet'     => __( 'Permanently delete this database record or unschedule this WP-Cron hook? Make sure you viewed the full code first.', 'fbs-secure-optimize' ),
					'confirmForceDeleteUser'   => __( 'Force-delete this rogue user directly from wp_users and wp_usermeta? Any authored posts will be reassigned to your account and active sessions destroyed.', 'fbs-secure-optimize' ),
					'confirmRevokeAppPass'     => __( 'Revoke this Application Password immediately? Any REST API client using it will lose access.', 'fbs-secure-optimize' ),
					'confirmPostCleanup'       => __( 'Clear all site/object/OPcache caches AND invalidate login sessions for all other users (keeping your current session active)?', 'fbs-secure-optimize' ),
					'postCleaning'             => __( 'Purging & Invalidating Sessions...', 'fbs-secure-optimize' ),
					'savedSuccess'             => __( 'Settings saved successfully.', 'fbs-secure-optimize' ),
					'genericError'             => __( 'An unexpected error occurred. Please refresh and try again.', 'fbs-secure-optimize' ),
				),
			)
		);
	}

	/**
	 * Retrieve a loaded module instance.
	 *
	 * @since 1.0.0
	 * @param string $module_name Module slug.
	 * @return object|null
	 */
	public function get_module( $module_name ) {
		return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
	}

	/**
	 * Check whether FBS Secure Optimize Pro add-on is active.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function is_pro_active() {
		/**
		 * Filters whether FBS Secure Optimize Pro capabilities are unlocked.
		 *
		 * @since 2.0.0
		 * @param bool $is_pro Whether Pro is active.
		 */
		return (bool) apply_filters( 'fbsseop_is_pro_active', false );
	}

	/**
	 * Check whether an independent module master toggle is enabled.
	 *
	 * @since 2.0.0
	 * @param string $module_key Module identifier.
	 * @return bool
	 */
	public static function is_module_enabled( $module_key ) {
		$settings = self::get_all_settings();
		return ! empty( $settings['modules'][ $module_key ] );
	}

	/**
	 * Check if a specific feature flag inside a section is enabled.
	 *
	 * @since 1.0.0
	 * @param string $section Settings section key.
	 * @param string $option  Option key.
	 * @return bool
	 */
	public static function is_feature_enabled( $section, $option ) {
		$settings = self::get_all_settings();
		return ! empty( $settings[ $section ][ $option ] );
	}

	/**
	 * Retrieve a setting value with fallback default.
	 *
	 * @since 1.0.0
	 * @param string $section Settings section key.
	 * @param string $option  Option key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get_setting( $section, $option, $default = '' ) {
		$settings = self::get_all_settings();
		if ( isset( $settings[ $section ][ $option ] ) ) {
			return $settings[ $section ][ $option ];
		}
		return $default;
	}

	/**
	 * Retrieve complete settings merged with defaults.
	 *
	 * @since 2.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all_settings() {
		$saved = get_option( 'fbsseop_settings', array() );
		return self::merge_settings_with_defaults( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Deep-merge saved settings array with current default schema.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $saved Saved settings array.
	 * @return array<string, array<string, mixed>>
	 */
	public static function merge_settings_with_defaults( $saved ) {
		$defaults = self::get_default_settings();
		$merged   = array();

		foreach ( $defaults as $section => $section_defaults ) {
			$saved_section      = ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) ? $saved[ $section ] : array();
			$merged[ $section ] = wp_parse_args( $saved_section, $section_defaults );
		}

		return $merged;
	}

	/**
	 * Default settings schema for all 6 modular engines.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_default_settings() {
		return array(
			'modules'            => array(
				'asset_optimizer'  => 1,
				'database_cleanup' => 1,
				'security_headers' => 1,
				'login_security'   => 1,
				'wp_hardening'     => 1,
				'malware_scanner'  => 1,
			),
			'asset_optimization' => array(
				'preload_lcp_image'         => 1,
				'lcp_preload_url'           => '',
				'cls_stabilize_builders'    => 1,
				'cls_image_dimensions'      => 1,
				'non_blocking_google_fonts' => 1,
				'deblock_css_imports'       => 1,
				'async_icon_css'            => 1,
				'inline_small_css'          => 1,
				'unload_unused_assets'      => 0,
				'delay_third_party_js'      => 0,
				'defer_js'                  => 0,
				'enable_page_cache'         => 0,
				'minify_css'                => 0,
				'minify_js'                 => 0,
				'lazy_load_images'          => 1,
				'lazy_load_iframes'         => 1,
				'disable_emojis'            => 1,
				'disable_dashicons'         => 1,
				'disable_embeds'            => 0,
				'clean_wp_head'             => 1,
				'heartbeat_control'         => 1,
			),
			'database_cleanup'   => array(
				'cleanup_revisions'     => 1,
				'cleanup_autodrafts'    => 1,
				'cleanup_spam_comments' => 1,
				'cleanup_transients'    => 1,
				'cleanup_orphaned_meta' => 1,
				'optimize_tables'       => 1,
				'auto_cleanup'          => 0,
				'cleanup_frequency'     => 'weekly',
			),
			'login_security'     => array(
				'limit_login_attempts' => 1,
				'max_attempts'         => 5,
				'lockout_duration'     => 15,
				'mask_login_errors'    => 1,
				'whitelist_ips'        => '',
			),
			'security_headers'   => array(
				'x_content_type_options'    => 1,
				'x_frame_options'           => 1,
				'x_xss_protection'          => 0,
				'referrer_policy'           => 1,
				'permissions_policy'        => 1,
				'strict_transport_security' => 0,
				'hide_wp_version'           => 1,
			),
			'wp_hardening'       => array(
				'disable_xmlrpc'       => 1,
				'disable_file_edit'    => 0,
				'block_author_enum'    => 1,
				'disable_rest_users'   => 1,
			),
		);
	}

	/**
	 * Gather dashboard telemetry statistics with 5-minute object caching.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_stats() {
		$cache_key = 'fbsseop_stats_v2';
		$stats     = wp_cache_get( $cache_key, 'fbsseop' );

		if ( false === $stats ) {
			global $wpdb;

			$stats = array();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$stats['post_revisions'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$stats['auto_drafts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$stats['spam_comments'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$stats['transients'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_' ) . '%',
					$wpdb->esc_like( '_site_transient_' ) . '%'
				)
			);

			// Autoloaded options total size in KB + top 5 heaviest rows.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$autoload_bytes = (int) $wpdb->get_var(
				"SELECT COALESCE(SUM(LENGTH(option_value)), 0) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')"
			);
			$stats['autoload_kb'] = round( $autoload_bytes / 1024, 1 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$stats['heavy_autoloads'] = $wpdb->get_results(
				"SELECT option_name, ROUND(LENGTH(option_value) / 1024, 1) AS size_kb
				 FROM {$wpdb->options}
				 WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')
				 ORDER BY LENGTH(option_value) DESC
				 LIMIT 5",
				ARRAY_A
			);

			// Failed login attempts in last 24 hours.
			$login_table = $wpdb->prefix . 'fbsseop_login_attempts';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
			$table_found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $login_table ) );
			if ( $table_found === $login_table ) {
				$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached below via wp_cache_set.
				$stats['failed_logins'] = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}fbsseop_login_attempts WHERE success = 0 AND attempt_time > %s",
						$since
					)
				);
			} else {
				$stats['failed_logins'] = 0;
			}

			wp_cache_set( $cache_key, $stats, 'fbsseop', 300 );
		}

		return $stats;
	}

	/**
	 * Clear cached statistics.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_stats_cache() {
		wp_cache_delete( 'fbsseop_stats_v2', 'fbsseop' );
	}

	/**
	 * Retrieve active cache version salt used for O(1) instant cache invalidation.
	 *
	 * @since 2.1.0
	 * @return string
	 */
	public static function get_cache_salt() {
		$salt = get_option( 'fbsseop_cache_salt', '' );
		if ( empty( $salt ) ) {
			$salt = 'v1';
			update_option( 'fbsseop_cache_salt', $salt, true );
		}
		return (string) $salt;
	}

	/**
	 * Return base filesystem directory and public URL for FBS cache artifacts inside wp_upload_dir().
	 *
	 * Uses wp_upload_dir() rather than WP_CONTENT_DIR to comply with WordPress Plugin Check and read-only hosts.
	 *
	 * @since 2.2.0
	 * @return array{basedir: string, baseurl: string}
	 */
	public static function get_cache_dir_info() {
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return array(
				'basedir' => '',
				'baseurl' => '',
			);
		}

		return array(
			'basedir' => trailingslashit( $uploads['basedir'] ) . 'fbs-optimize-cache',
			'baseurl' => trailingslashit( $uploads['baseurl'] ) . 'fbs-optimize-cache',
		);
	}

	/**
	 * Purge only cached guest HTML pages inside uploads/fbs-optimize-cache/pages/.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function purge_disk_page_cache() {
		$cache_info = self::get_cache_dir_info();
		if ( '' !== $cache_info['basedir'] ) {
			self::delete_cache_directory_contents( $cache_info['basedir'] . '/pages' );
		}
	}

	/**
	 * Recursively remove files inside a verified plugin cache directory inside uploads/fbs-optimize-cache.
	 *
	 * @since 2.2.0
	 * @param string $dir_path Target directory inside uploads/fbs-optimize-cache.
	 * @return void
	 */
	public static function delete_cache_directory_contents( $dir_path ) {
		$cache_info = self::get_cache_dir_info();
		if ( '' === $cache_info['basedir'] ) {
			return;
		}

		$allowed_root = wp_normalize_path( $cache_info['basedir'] );
		$norm_target  = wp_normalize_path( $dir_path );

		if ( 0 !== strpos( $norm_target, $allowed_root ) || ! is_dir( $dir_path ) ) {
			return;
		}

		$items = @scandir( $dir_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$full = trailingslashit( $dir_path ) . $item;
			if ( is_dir( $full ) && ! is_link( $full ) ) {
				self::delete_cache_directory_contents( $full );
				@rmdir( $full ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
			} elseif ( is_file( $full ) || is_link( $full ) ) {
				wp_delete_file( $full );
			}
		}
	}

	/**
	 * Hook callback wrapper that purges all asset/page caches without resetting PHP OPcache mid-request.
	 *
	 * @since 2.2.1
	 * @return void
	 */
	public static function purge_all_caches_without_opcache() {
		self::purge_all_caches( false );
	}

	/**
	 * Purge all FBS Secure Optimize asset/CLS/page caches, WP Object Cache, optional PHP OPcache,
	 * and trigger compatible host/third-party page cache flushes.
	 *
	 * @since 2.1.0
	 * @param bool $reset_opcache Whether to schedule a post-response PHP OPcache reset (default false on settings save).
	 * @return array<int, string> List of cache layers cleared.
	 */
	public static function purge_all_caches( $reset_opcache = false ) {
		unset( $reset_opcache );
		$cleared = array();

		// Buffer any stray output from third-party cache plugins so admin-post.php redirects never break.
		ob_start();

		// 1. Rotate FBS cache salt & clear static disk CSS / Guest HTML Page Cache.
		$old_salt = get_option( 'fbsseop_cache_salt', 'v1' );
		delete_transient( 'fbsseop_img_dims_' . $old_salt );
		$new_salt = 'v' . time() . '_' . wp_rand( 100, 999 );
		update_option( 'fbsseop_cache_salt', $new_salt, true );
		$cache_info = self::get_cache_dir_info();
		if ( '' !== $cache_info['basedir'] ) {
			self::delete_cache_directory_contents( $cache_info['basedir'] );
		}
		self::clear_stats_cache();
		$cleared[] = __( 'FBS Asset, CLS & Page Cache', 'fbs-secure-optimize' );

		// 2. WordPress Object Cache (Redis / Memcached / runtime).
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$cleared[] = __( 'WP Object Cache', 'fbs-secure-optimize' );
		}

		// Note: Do not call opcache_reset() here. Wiping shared server-wide PHP bytecode OPcache
		// causes worker lock contention, 429 rate-limit blocks, and IP blackholing on 20i/StackCP,
		// LiteSpeed LSAPI, and shared PHP-FPM hosts.

		// 3. Compatible third-party & managed host page cache hooks (zero-conflict bridge).
		if ( has_action( 'litespeed_purge_all' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official LiteSpeed Cache API hook.
			do_action( 'litespeed_purge_all' );
			$cleared[] = 'LiteSpeed Cache';
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$cleared[] = 'WP Rocket';
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$cleared[] = 'W3 Total Cache';
		}

		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
			$cleared[] = 'WP Fastest Cache';
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$cleared[] = 'WP Super Cache';
		}

		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
			$cleared[] = 'Autoptimize';
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$cleared[] = 'SiteGround Optimizer';
		}

		if ( has_action( 'breeze_clear_all_cache' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official Cloudways Breeze API hook.
			do_action( 'breeze_clear_all_cache' );
			$cleared[] = 'Breeze Cache';
		}

		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
			$cleared[] = 'WP Engine Cache';
		}

		/**
		 * Fires after FBS Secure Optimize purges all optimization and ecosystem caches.
		 *
		 * @since 2.1.0
		 * @param array<int, string> $cleared Names of cleared cache layers.
		 */
		do_action( 'fbsseop_cache_purged', $cleared );

		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		return $cleared;
	}

	/**
	 * Debug logger active only when WP_DEBUG_LOG is enabled.
	 *
	 * @since 1.0.0
	 * @param string $message Message to log.
	 * @param string $level   Severity level.
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG_LOG.
			error_log( sprintf( '[FBS Secure Optimize %s] %s', strtoupper( $level ), $message ) );
		}
	}
}
