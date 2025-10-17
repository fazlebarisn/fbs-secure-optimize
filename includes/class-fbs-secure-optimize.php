<?php
/**
 * Main controller class for FBS Secure Optimize plugin
 *
 * @package FBS_Optimize
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main controller class
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */
class FBS_Secure_Optimize_Controller {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Controller
     */
    private static $instance = null;

    /**
     * Plugin modules
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var array
     */
    private $modules = array();

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Controller
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
        $this->load_modules();
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Initialize the plugin
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function init() {
        // Plugin initialization logic can be added here if needed
    }

    /**
     * Load plugin modules
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function load_modules() {
        // Load asset optimizer module
        $this->modules['asset_optimizer'] = new FBS_Secure_Optimize_Asset_Optimizer();
        
        // Load database cleanup module
        $this->modules['database_cleanup'] = new FBS_Secure_Optimize_Database_Cleanup();
        
        // Load login security module
        $this->modules['login_security'] = new FBS_Secure_Optimize_Login_Security();
        
        // Load security headers module
        $this->modules['security_headers'] = new FBS_Secure_Optimize_Security_Headers();
    }

    /**
     * Enqueue frontend scripts and styles
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue if lazy loading is enabled
        $settings = get_option('fbs_opt_settings', array());
        $asset_settings = isset($settings['asset_optimization']) ? $settings['asset_optimization'] : array();
        
        if (isset($asset_settings['lazy_load_images']) && $asset_settings['lazy_load_images']) {
            wp_enqueue_script(
                'fbs-opt-lazy-load',
                FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/js/lazy-load.js',
                array(),
                FBS_SECURE_OPTIMIZE_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'fbs-secure-optimize') === false) {
            return;
        }

        wp_enqueue_style(
            'fbs-opt-admin',
            FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FBS_SECURE_OPTIMIZE_VERSION
        );

        wp_enqueue_script(
            'fbs-opt-admin',
            FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FBS_SECURE_OPTIMIZE_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('fbs-opt-admin', 'fbsOptAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fbs_opt_admin_nonce'),
            'strings' => array(
                'confirmCleanup' => __('Are you sure you want to perform database cleanup? This action cannot be undone.', 'fbs-secure-optimize'),
                'cleanupSuccess' => __('Database cleanup completed successfully.', 'fbs-secure-optimize'),
                'cleanupError' => __('An error occurred during database cleanup.', 'fbs-secure-optimize'),
            ),
        ));
    }

    /**
     * Get a specific module
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $module_name Module name
     * @return object|null Module instance or null if not found
     */
    public function get_module($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }

    /**
     * Get all modules
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Array of module instances
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * Check if a feature is enabled
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $section Settings section
     * @param string $option Option name
     * @return bool True if enabled, false otherwise
     */
    public static function is_feature_enabled($section, $option) {
        $settings = get_option('fbs_opt_settings', array());
        $section_settings = isset($settings[$section]) ? $settings[$section] : array();
        
        return isset($section_settings[$option]) && $section_settings[$option];
    }

    /**
     * Get plugin setting
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $section Settings section
     * @param string $option Option name
     * @param mixed $default Default value
     * @return mixed Setting value or default
     */
    public static function get_setting($section, $option, $default = '') {
        $settings = get_option('fbs_opt_settings', array());
        $section_settings = isset($settings[$section]) ? $settings[$section] : array();
        
        return isset($section_settings[$option]) ? $section_settings[$option] : $default;
    }

    /**
     * Update plugin setting
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $section Settings section
     * @param string $option Option name
     * @param mixed $value Value to set
     * @return bool True if updated successfully
     */
    public static function update_setting($section, $option, $value) {
        $settings = get_option('fbs_opt_settings', array());
        
        if (!isset($settings[$section])) {
            $settings[$section] = array();
        }
        
        $settings[$section][$option] = $value;
        
        return update_option('fbs_opt_settings', $settings);
    }

    /**
     * Log plugin activity
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
            error_log(sprintf('[FBS Secure Optimize %s] %s', strtoupper($level), $message));
        }
    }

    /**
     * Get plugin statistics
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Plugin statistics
     */
    public static function get_stats() {
        // Check cache first
        $cache_key = 'fbs_opt_stats';
        $stats = wp_cache_get($cache_key, 'fbs_optimize');
        
        if (false === $stats) {
            global $wpdb;
            
            $stats = array();
            
            // Database cleanup stats
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering with caching
            $stats['post_revisions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering with caching
            $stats['auto_drafts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering with caching
            $stats['spam_comments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering with caching
            $stats['transients'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
            
            // Login attempts stats
            $login_attempts_table = $wpdb->prefix . 'fbs_opt_login_attempts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check with caching
            if ($wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($login_attempts_table) . "'") == $login_attempts_table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering with caching
                $stats['failed_logins'] = $wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($login_attempts_table) . " WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            } else {
                $stats['failed_logins'] = 0;
            }
            
            // Cache the results for 5 minutes
            wp_cache_set($cache_key, $stats, 'fbs_optimize', 300);
        }
        
        return $stats;
    }

    /**
     * Clear statistics cache
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public static function clear_stats_cache() {
        wp_cache_delete('fbs_opt_stats', 'fbs_optimize');
    }
}
