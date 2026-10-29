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
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#059669;font-weight:700;">%s</a>',
				esc_url( 'https://fbsfusion.com/plugins/' ),
				esc_html__( 'Get Pro', 'fbs-secure-optimize' )
			);
		}

		return $links;
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

		wp_enqueue_style(
			'fbsseop-admin',
			FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FBS_SECURE_OPTIMIZE_VERSION
		);

		wp_enqueue_script(
			'fbsseop-admin',
			FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FBS_SECURE_OPTIMIZE_VERSION,
			true
		);

		wp_localize_script(
			'fbsseop-admin',
			'fbsseopAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fbsseop_admin_nonce' ),
				'isPro'   => self::is_pro_active(),
				'proUrl'  => 'https://fbsfusion.com/plugins/',
				'strings' => array(
					'confirmCleanup' => __( 'Run database cleanup now? Selected revisions, drafts, spam, and expired transients will be permanently removed.', 'fbs-secure-optimize' ),
					'cleaning'       => __( 'Running cleanup...', 'fbs-secure-optimize' ),
					'scanning'       => __( 'Running 6-Layer Deep Scan...', 'fbs-secure-optimize' ),
					'scanBtnLabel'   => __( 'Run 6-Layer Deep Scan', 'fbs-secure-optimize' ),
					'confirmDelete'  => __( 'Permanently delete this threat? Make sure you have verified the preview first.', 'fbs-secure-optimize' ),
					'savedSuccess'   => __( 'Settings saved successfully.', 'fbs-secure-optimize' ),
					'genericError'   => __( 'An unexpected error occurred. Please refresh and try again.', 'fbs-secure-optimize' ),
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
				'minify_css'        => 0,
				'minify_js'         => 0,
				'defer_js'          => 0,
				'lazy_load_images'  => 1,
				'lazy_load_iframes' => 1,
				'disable_emojis'    => 1,
				'disable_dashicons' => 1,
				'disable_embeds'    => 0,
				'clean_wp_head'     => 1,
				'heartbeat_control' => 1,
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
