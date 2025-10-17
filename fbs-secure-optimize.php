<?php
/**
 * Plugin Name: FBS Secure Optimize
 * Plugin URI: https://github.com/fazlebarisn/fbs-optimize
 * Description: A comprehensive WordPress plugin for performance optimization and security enhancement. Features asset optimization, database cleanup, login security, and security headers.
 * Version: 1.0.0
 * Author: Fazle Bari
 * Author URI: https://github.com/fazlebarisn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fbs-secure-optimize
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package FBS_Optimize
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FBS_SECURE_OPTIMIZE_VERSION', '1.0.0');
define('FBS_SECURE_OPTIMIZE_PLUGIN_FILE', __FILE__);
define('FBS_SECURE_OPTIMIZE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FBS_SECURE_OPTIMIZE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FBS_SECURE_OPTIMIZE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */
class FBS_Secure_Optimize {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Optimize
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Optimize
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_plugin'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load required files
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function load_dependencies() {
        // Load main controller class
        require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'includes/class-fbs-secure-optimize.php';
        
        // Load admin class
        if (is_admin()) {
            require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'admin/class-fbs-secure-optimize-admin.php';
        }
        
        // Load modules
        require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-asset-optimizer.php';
        require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-database-cleanup.php';
        require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-login-security.php';
        require_once FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'modules/class-fbs-secure-optimize-security-headers.php';
    }

    /**
     * Initialize the plugin
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function init_plugin() {
        // Initialize main controller
        FBS_Secure_Optimize_Controller::get_instance();
        
        // Initialize admin interface
        if (is_admin()) {
            FBS_Secure_Optimize_Admin::get_instance();
        }
    }

    /**
     * Plugin activation
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('fbs_opt_cleanup_database');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Login attempts table
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            username varchar(255) NOT NULL,
            attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
            success tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY attempt_time (attempt_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function set_default_options() {
        // Use centralized default settings from controller
        $default_options = FBS_Secure_Optimize_Controller::get_default_settings();
        add_option('fbs_opt_settings', $default_options);
    }
}

// Initialize the plugin
FBS_Secure_Optimize::get_instance();
