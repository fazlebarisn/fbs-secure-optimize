<?php
/**
 * Plugin Name:       FBS Secure Optimize
 * Plugin URI:        https://fbsfusion.com/plugins/
 * Description:       Modular WordPress performance optimization and fortress security suite with independent module toggles: Asset & Bloat Optimizer, Database & Autoload Cleaner, HTTP Security Headers, Login Brute-Force Shield, WP Core Hardening, and 6-Layer Malware & Hidden-Admin Scanner.
 * Version:           2.0.0
 * Author:            Fazle Bari
 * Author URI:        https://fbsfusion.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fbs-secure-optimize
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP:      7.4
 *
 * @package FBS_Secure_Optimize
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'FBS_SECURE_OPTIMIZE_VERSION', '2.0.0' );
define( 'FBS_SECURE_OPTIMIZE_PLUGIN_FILE', __FILE__ );
define( 'FBS_SECURE_OPTIMIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FBS_SECURE_OPTIMIZE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FBS_SECURE_OPTIMIZE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main FBS_Secure_Optimize bootstrap class.
 *
 * @since 1.0.0
 */
final class FBS_Secure_Optimize {

	/**
	 * Single instance of the class.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize
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
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required core and module files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'includes/class-fbs-secure-optimize.php';

		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-asset-optimizer.php';
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-database-cleanup.php';
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-login-security.php';
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-security-headers.php';
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-hardening.php';
		require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-scanner.php';

		if ( is_admin() ) {
			require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'admin/class-fbs-secure-optimize-admin.php';
		}
	}

	/**
	 * Register lifecycle and initialization hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 5 );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize main controller and fire extensibility hook for Pro add-on.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_plugin() {
		FBS_Secure_Optimize_Controller::get_instance();

		/**
		 * Fires once FBS Secure Optimize core and modules are initialized.
		 *
		 * @since 2.0.0
		 */
		do_action( 'fbsseop_loaded' );
	}

	/**
	 * Plugin activation routine.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate() {
		$this->create_tables();
		$this->ensure_default_options();
	}

	/**
	 * Plugin deactivation routine.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'fbsseop_cleanup_database' );
	}

	/**
	 * Create login lockout tracking table via dbDelta.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function create_tables() {
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
	}

	/**
	 * Seed or merge default settings without overwriting existing user preferences.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function ensure_default_options() {
		$defaults = FBS_Secure_Optimize_Controller::get_default_settings();
		$existing = get_option( 'fbsseop_settings', false );

		if ( false === $existing || ! is_array( $existing ) ) {
			add_option( 'fbsseop_settings', $defaults );
			return;
		}

		$merged = FBS_Secure_Optimize_Controller::merge_settings_with_defaults( $existing );
		update_option( 'fbsseop_settings', $merged );
	}
}

FBS_Secure_Optimize::get_instance();
