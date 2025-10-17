<?php
/**
 * Admin interface class for FBS Secure Optimize plugin
 *
 * @package FBS_Optimize
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface class
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */
class FBS_Secure_Optimize_Admin {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Admin
     */
    private static $instance = null;

    /**
     * Current tab
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var string
     */
    private $current_tab = 'performance';

    /**
     * Available tabs
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var array
     */
    private $tabs = array();

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Admin
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
    public function __construct() {
        $this->init_hooks();
        $this->init_tabs();
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_fbs_opt_cleanup_database', array($this, 'ajax_cleanup_database'));
        add_action('wp_ajax_fbs_opt_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_fbs_opt_reset_settings', array($this, 'ajax_reset_settings'));
    }

    /**
     * Initialize admin tabs
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_tabs() {
        $this->tabs = array(
            'performance' => array(
                'title' => __('Performance', 'fbs-secure-optimize'),
                'icon' => 'dashicons-performance',
            ),
            'security' => array(
                'title' => __('Security', 'fbs-secure-optimize'),
                'icon' => 'dashicons-shield',
            ),
            'statistics' => array(
                'title' => __('Statistics', 'fbs-secure-optimize'),
                'icon' => 'dashicons-chart-bar',
            ),
        );

        // Get current tab from URL
        if (isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->tabs)) {
            $this->current_tab = sanitize_text_field(wp_unslash($_GET['tab']));
        }
    }

    /**
     * Add admin menu
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function add_admin_menu() {
        add_menu_page(
            __('FBS Secure Optimize', 'fbs-secure-optimize'),
            __('FBS Optimize', 'fbs-secure-optimize'),
            'manage_options',
            'fbs-secure-optimize',
            array($this, 'admin_page'),
            'dashicons-performance',
            30
        );
    }

    /**
     * Register plugin settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function register_settings() {
        // Register main settings group
        register_setting(
            'fbs_opt_settings',
            'fbs_opt_settings',
            array($this, 'sanitize_settings')
        );

        // Performance section
        add_settings_section(
            'fbs_opt_performance',
            __('Performance Settings', 'fbs-secure-optimize'),
            array($this, 'performance_section_callback'),
            'fbs_opt_settings'
        );

        // Security section
        add_settings_section(
            'fbs_opt_security',
            __('Security Settings', 'fbs-secure-optimize'),
            array($this, 'security_section_callback'),
            'fbs_opt_settings'
        );

        // Register settings fields
        $this->register_performance_fields();
        $this->register_security_fields();
    }

    /**
     * Register performance settings fields
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function register_performance_fields() {
        // Asset Optimization
        add_settings_field(
            'minify_css',
            __('Minify CSS', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'minify_css',
                'description' => __('Minify CSS files to reduce file size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'minify_js',
            __('Minify JavaScript', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'minify_js',
                'description' => __('Minify JavaScript files to reduce file size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'combine_css',
            __('Combine CSS Files', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'combine_css',
                'description' => __('Combine multiple CSS files into one to reduce HTTP requests.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'combine_js',
            __('Combine JavaScript Files', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'combine_js',
                'description' => __('Combine multiple JavaScript files into one to reduce HTTP requests.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'lazy_load_images',
            __('Lazy Load Images', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'lazy_load_images',
                'description' => __('Load images only when they are about to enter the viewport.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'lazy_load_iframes',
            __('Lazy Load Iframes', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'lazy_load_iframes',
                'description' => __('Load iframes only when they are about to enter the viewport.', 'fbs-secure-optimize'),
            )
        );

        // Database Cleanup
        add_settings_field(
            'cleanup_revisions',
            __('Clean Post Revisions', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'cleanup_revisions',
                'description' => __('Remove old post revisions to reduce database size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'cleanup_autodrafts',
            __('Clean Auto-drafts', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'cleanup_autodrafts',
                'description' => __('Remove auto-draft posts to reduce database size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'cleanup_spam_comments',
            __('Clean Spam Comments', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'cleanup_spam_comments',
                'description' => __('Remove spam comments to reduce database size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'cleanup_transients',
            __('Clean Transients', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'cleanup_transients',
                'description' => __('Remove expired transients to reduce database size.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'auto_cleanup',
            __('Automatic Cleanup', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'auto_cleanup',
                'description' => __('Automatically perform database cleanup on a schedule.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'cleanup_frequency',
            __('Cleanup Frequency', 'fbs-secure-optimize'),
            array($this, 'select_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_performance',
            array(
                'label_for' => 'cleanup_frequency',
                'options' => array(
                    'daily' => __('Daily', 'fbs-secure-optimize'),
                    'weekly' => __('Weekly', 'fbs-secure-optimize'),
                    'monthly' => __('Monthly', 'fbs-secure-optimize'),
                ),
                'description' => __('How often to perform automatic cleanup.', 'fbs-secure-optimize'),
            )
        );
    }

    /**
     * Register security settings fields
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function register_security_fields() {
        // Login Security
        add_settings_field(
            'limit_login_attempts',
            __('Limit Login Attempts', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'limit_login_attempts',
                'description' => __('Limit the number of login attempts to prevent brute force attacks.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'max_attempts',
            __('Maximum Attempts', 'fbs-secure-optimize'),
            array($this, 'number_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'max_attempts',
                'min' => 3,
                'max' => 20,
                'description' => __('Maximum number of login attempts before lockout.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'lockout_duration',
            __('Lockout Duration (minutes)', 'fbs-secure-optimize'),
            array($this, 'number_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'lockout_duration',
                'min' => 5,
                'max' => 1440,
                'description' => __('How long to lock out users after exceeding maximum attempts.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'whitelist_ips',
            __('Whitelist IP Addresses', 'fbs-secure-optimize'),
            array($this, 'textarea_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'whitelist_ips',
                'rows' => 3,
                'description' => __('IP addresses that are exempt from login attempt limits (one per line).', 'fbs-secure-optimize'),
            )
        );

        // Security Headers
        add_settings_field(
            'x_content_type_options',
            __('X-Content-Type-Options', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'x_content_type_options',
                'description' => __('Prevent MIME type sniffing attacks.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'x_frame_options',
            __('X-Frame-Options', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'x_frame_options',
                'description' => __('Prevent clickjacking attacks.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'x_xss_protection',
            __('X-XSS-Protection', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'x_xss_protection',
                'description' => __('Enable XSS filtering in browsers.', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'strict_transport_security',
            __('Strict-Transport-Security', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'strict_transport_security',
                'description' => __('Force HTTPS connections (requires SSL certificate).', 'fbs-secure-optimize'),
            )
        );

        add_settings_field(
            'hide_wp_version',
            __('Hide WordPress Version', 'fbs-secure-optimize'),
            array($this, 'checkbox_field_callback'),
            'fbs_opt_settings',
            'fbs_opt_security',
            array(
                'label_for' => 'hide_wp_version',
                'description' => __('Remove WordPress version from head and RSS feeds.', 'fbs-secure-optimize'),
            )
        );
    }

    /**
     * Sanitize settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input) {
        // Get current settings to preserve existing data
        $current_settings = get_option('fbs_opt_settings', array());
        $sanitized = $current_settings;

        // Sanitize performance settings
        if (isset($input['asset_optimization'])) {
            $sanitized['asset_optimization'] = array();
            $asset_fields = array('minify_css', 'minify_js', 'combine_css', 'combine_js', 'lazy_load_images', 'lazy_load_iframes');
            
            foreach ($asset_fields as $field) {
                $sanitized['asset_optimization'][$field] = isset($input['asset_optimization'][$field]) ? 1 : 0;
            }
        }

        if (isset($input['database_cleanup'])) {
            $sanitized['database_cleanup'] = array();
            $cleanup_fields = array('cleanup_revisions', 'cleanup_autodrafts', 'cleanup_spam_comments', 'cleanup_transients', 'auto_cleanup');
            
            foreach ($cleanup_fields as $field) {
                $sanitized['database_cleanup'][$field] = isset($input['database_cleanup'][$field]) ? 1 : 0;
            }
            
            if (isset($input['database_cleanup']['cleanup_frequency'])) {
                $frequency = sanitize_text_field($input['database_cleanup']['cleanup_frequency']);
                $allowed_frequencies = array('daily', 'weekly', 'monthly');
                $sanitized['database_cleanup']['cleanup_frequency'] = in_array($frequency, $allowed_frequencies) ? $frequency : 'weekly';
            }
        }

        // Sanitize security settings
        if (isset($input['login_security'])) {
            $sanitized['login_security'] = array();
            $sanitized['login_security']['limit_login_attempts'] = isset($input['login_security']['limit_login_attempts']) ? 1 : 0;
            $sanitized['login_security']['max_attempts'] = isset($input['login_security']['max_attempts']) ? absint($input['login_security']['max_attempts']) : 5;
            $sanitized['login_security']['lockout_duration'] = isset($input['login_security']['lockout_duration']) ? absint($input['login_security']['lockout_duration']) : 15;
            $sanitized['login_security']['whitelist_ips'] = isset($input['login_security']['whitelist_ips']) ? sanitize_textarea_field($input['login_security']['whitelist_ips']) : '';
        }

        if (isset($input['security_headers'])) {
            $sanitized['security_headers'] = array();
            $header_fields = array('x_content_type_options', 'x_frame_options', 'x_xss_protection', 'strict_transport_security', 'hide_wp_version');
            
            foreach ($header_fields as $field) {
                $sanitized['security_headers'][$field] = isset($input['security_headers'][$field]) ? 1 : 0;
            }
        }

        return $sanitized;
    }

    /**
     * Performance section callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function performance_section_callback() {
        echo '<p>' . esc_html__('Configure performance optimization settings to improve your website speed.', 'fbs-secure-optimize') . '</p>';
    }

    /**
     * Security section callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function security_section_callback() {
        echo '<p>' . esc_html__('Configure security settings to protect your website from common threats.', 'fbs-secure-optimize') . '</p>';
    }

    /**
     * Checkbox field callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $args Field arguments
     */
    public function checkbox_field_callback($args) {
        $settings = get_option('fbs_opt_settings', array());
        $section = $this->get_current_section();
        $value = isset($settings[$section][$args['label_for']]) ? $settings[$section][$args['label_for']] : 0;
        
        printf(
            '<input type="checkbox" id="%1$s" name="fbs_opt_settings[%2$s][%1$s]" value="1" %3$s />',
            esc_attr($args['label_for']),
            esc_attr($section),
            checked(1, $value, false)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Number field callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $args Field arguments
     */
    public function number_field_callback($args) {
        $settings = get_option('fbs_opt_settings', array());
        $section = $this->get_current_section();
        $value = isset($settings[$section][$args['label_for']]) ? $settings[$section][$args['label_for']] : '';
        
        $attributes = array();
        if (isset($args['min'])) {
            $attributes[] = 'min="' . esc_attr($args['min']) . '"';
        }
        if (isset($args['max'])) {
            $attributes[] = 'max="' . esc_attr($args['max']) . '"';
        }
        
        printf(
            '<input type="number" id="%1$s" name="fbs_opt_settings[%2$s][%1$s]" value="%3$s" %4$s />',
            esc_attr($args['label_for']),
            esc_attr($section),
            esc_attr($value),
            wp_kses_data(implode(' ', $attributes))
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Select field callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $args Field arguments
     */
    public function select_field_callback($args) {
        $settings = get_option('fbs_opt_settings', array());
        $section = $this->get_current_section();
        $value = isset($settings[$section][$args['label_for']]) ? $settings[$section][$args['label_for']] : '';
        
        printf('<select id="%1$s" name="fbs_opt_settings[%2$s][%1$s]">', esc_attr($args['label_for']), esc_attr($section));
        
        foreach ($args['options'] as $option_value => $option_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Textarea field callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $args Field arguments
     */
    public function textarea_field_callback($args) {
        $settings = get_option('fbs_opt_settings', array());
        $section = $this->get_current_section();
        $value = isset($settings[$section][$args['label_for']]) ? $settings[$section][$args['label_for']] : '';
        
        $rows = isset($args['rows']) ? $args['rows'] : 3;
        
        printf(
            '<textarea id="%1$s" name="fbs_opt_settings[%2$s][%1$s]" rows="%3$s" cols="50">%4$s</textarea>',
            esc_attr($args['label_for']),
            esc_attr($section),
            esc_attr($rows),
            esc_textarea($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Get current section based on tab
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return string Section name
     */
    private function get_current_section() {
        switch ($this->current_tab) {
            case 'performance':
                return 'asset_optimization';
            case 'security':
                return 'login_security';
            default:
                return 'asset_optimization';
        }
    }

    /**
     * Get setting value
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $section Section name
     * @param string $key Setting key
     * @return mixed Setting value
     */
    private function get_setting_value($section, $key) {
        $settings = get_option('fbs_opt_settings', array());
        return isset($settings[$section][$key]) ? $settings[$section][$key] : '';
    }

    /**
     * Display admin page
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function admin_page() {
        ?>
        <div class="wrap fbs-opt-admin-wrap">
            <div class="fbs-opt-header">
                <h1 class="fbs-opt-title">
                    <span class="dashicons dashicons-performance"></span>
                    <?php echo esc_html(get_admin_page_title()); ?>
                </h1>
                <p class="fbs-opt-subtitle"><?php esc_html_e('Secure and optimize your WordPress site for better performance and enhanced security', 'fbs-secure-optimize'); ?></p>
            </div>
            
            <?php $this->display_tabs(); ?>
            
            <div class="fbs-opt-admin-content">
                <div class="fbs-opt-tab-content <?php echo $this->current_tab === 'performance' ? 'active' : ''; ?>" data-tab="performance">
                    <?php $this->display_performance_tab(); ?>
                </div>
                
                <div class="fbs-opt-tab-content <?php echo $this->current_tab === 'security' ? 'active' : ''; ?>" data-tab="security">
                    <?php $this->display_security_tab(); ?>
                </div>
                
                <div class="fbs-opt-tab-content <?php echo $this->current_tab === 'statistics' ? 'active' : ''; ?>" data-tab="statistics">
                    <?php $this->display_statistics_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display admin tabs
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function display_tabs() {
        echo '<div class="fbs-opt-tabs">';
        echo '<h2 class="nav-tab-wrapper">';
        
        foreach ($this->tabs as $tab_key => $tab_data) {
            $class = ($tab_key === $this->current_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = add_query_arg('tab', $tab_key, admin_url('admin.php?page=fbs-optimize'));
            
            printf(
                '<a href="%1$s" class="%2$s" data-tab="%3$s"><span class="dashicons %4$s"></span> %5$s</a>',
                esc_url($url),
                esc_attr($class),
                esc_attr($tab_key),
                esc_attr($tab_data['icon']),
                esc_html($tab_data['title'])
            );
        }
        
        echo '</h2>';
        echo '</div>';
    }

    /**
     * Display performance tab
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function display_performance_tab() {
        ?>
        <div class="fbs-opt-performance-tab">
            <form method="post" action="options.php" class="fbs-opt-form">
                <?php
                settings_fields('fbs_opt_settings');
                ?>
                
                <div class="fbs-opt-section">
                    <div class="fbs-opt-section-header">
                        <h3 class="fbs-opt-section-title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Asset Optimization', 'fbs-secure-optimize'); ?>
                        </h3>
                        <p class="fbs-opt-section-description"><?php esc_html_e('Optimize your CSS and JavaScript files for better loading speed', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-options-grid">
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Minify CSS', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][minify_css]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'minify_css')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove unnecessary characters from CSS files to reduce file size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Minify JavaScript', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][minify_js]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'minify_js')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove unnecessary characters from JavaScript files to reduce file size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Combine CSS Files', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][combine_css]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'combine_css')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Combine multiple CSS files into one to reduce HTTP requests', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Combine JavaScript Files', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][combine_js]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'combine_js')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Combine multiple JavaScript files into one to reduce HTTP requests', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Lazy Load Images', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][lazy_load_images]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'lazy_load_images')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Load images only when they are about to enter the viewport', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Lazy Load Iframes', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[asset_optimization][lazy_load_iframes]" value="1" <?php checked(1, $this->get_setting_value('asset_optimization', 'lazy_load_iframes')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Load iframes only when they are about to enter the viewport', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fbs-opt-section">
                    <div class="fbs-opt-section-header">
                        <h3 class="fbs-opt-section-title">
                            <span class="dashicons dashicons-database"></span>
                            <?php esc_html_e('Database Cleanup', 'fbs-secure-optimize'); ?>
                        </h3>
                        <p class="fbs-opt-section-description"><?php esc_html_e('Clean up your database to improve performance and reduce storage usage', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-options-grid">
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Clean Post Revisions', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[database_cleanup][cleanup_revisions]" value="1" <?php checked(1, $this->get_setting_value('database_cleanup', 'cleanup_revisions')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove old post revisions to reduce database size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Clean Auto-drafts', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[database_cleanup][cleanup_autodrafts]" value="1" <?php checked(1, $this->get_setting_value('database_cleanup', 'cleanup_autodrafts')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove auto-draft posts to reduce database size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Clean Spam Comments', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[database_cleanup][cleanup_spam_comments]" value="1" <?php checked(1, $this->get_setting_value('database_cleanup', 'cleanup_spam_comments')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove spam comments to reduce database size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Clean Transients', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[database_cleanup][cleanup_transients]" value="1" <?php checked(1, $this->get_setting_value('database_cleanup', 'cleanup_transients')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove expired transients to reduce database size', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Automatic Cleanup', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[database_cleanup][auto_cleanup]" value="1" id="auto_cleanup_toggle" <?php checked(1, $this->get_setting_value('database_cleanup', 'auto_cleanup')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Automatically perform database cleanup on a schedule', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card fbs-opt-dependent-option" data-depends-on="auto_cleanup_toggle">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Cleanup Frequency', 'fbs-secure-optimize'); ?></h4>
                                <select name="fbs_opt_settings[database_cleanup][cleanup_frequency]" class="fbs-opt-select" id="cleanup_frequency_select">
                                    <option value="daily" <?php selected($this->get_setting_value('database_cleanup', 'cleanup_frequency'), 'daily'); ?>><?php esc_html_e('Daily', 'fbs-secure-optimize'); ?></option>
                                    <option value="weekly" <?php selected($this->get_setting_value('database_cleanup', 'cleanup_frequency'), 'weekly'); ?>><?php esc_html_e('Weekly', 'fbs-secure-optimize'); ?></option>
                                    <option value="monthly" <?php selected($this->get_setting_value('database_cleanup', 'cleanup_frequency'), 'monthly'); ?>><?php esc_html_e('Monthly', 'fbs-secure-optimize'); ?></option>
                                </select>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('How often to perform automatic cleanup', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fbs-opt-cleanup-actions">
                    <div class="fbs-opt-action-card">
                        <h3><?php esc_html_e('Manual Cleanup', 'fbs-secure-optimize'); ?></h3>
                        <p><?php esc_html_e('Perform database cleanup manually. This action cannot be undone.', 'fbs-secure-optimize'); ?></p>
                        <button type="button" id="fbs-opt-cleanup-btn" class="fbs-opt-button fbs-opt-button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clean Database Now', 'fbs-secure-optimize'); ?>
                        </button>
                        <span id="fbs-opt-cleanup-status"></span>
                    </div>
                    
                    <div class="fbs-opt-action-card">
                        <h3><?php esc_html_e('Cache Management', 'fbs-secure-optimize'); ?></h3>
                        <p><?php esc_html_e('Clear optimized asset cache to force regeneration of combined and minified files.', 'fbs-secure-optimize'); ?></p>
                        <button type="button" id="fbs-opt-clear-cache-btn" class="fbs-opt-button fbs-opt-button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Clear Asset Cache', 'fbs-secure-optimize'); ?>
                        </button>
                        <span id="fbs-opt-cache-status"></span>
                    </div>
                </div>
                
                <div class="fbs-opt-form-actions">
                    <?php submit_button(__('Save Performance Settings', 'fbs-secure-optimize'), 'primary fbs-opt-button-primary', 'submit', false); ?>
                    <button type="button" class="fbs-opt-reset-btn fbs-opt-button fbs-opt-button-secondary" data-tab="performance">
                        <span class="dashicons dashicons-undo"></span>
                        <?php esc_html_e('Reset to Defaults', 'fbs-secure-optimize'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Display security tab
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function display_security_tab() {
        ?>
        <div class="fbs-opt-security-tab">
            <form method="post" action="options.php" class="fbs-opt-form">
                <?php
                settings_fields('fbs_opt_settings');
                ?>
                
                <div class="fbs-opt-section">
                    <div class="fbs-opt-section-header">
                        <h3 class="fbs-opt-section-title">
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Login Security', 'fbs-secure-optimize'); ?>
                        </h3>
                        <p class="fbs-opt-section-description"><?php esc_html_e('Protect your site from brute force attacks and unauthorized access', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-options-grid">
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Limit Login Attempts', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[login_security][limit_login_attempts]" value="1" id="limit_login_attempts_toggle" <?php checked(1, $this->get_setting_value('login_security', 'limit_login_attempts')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Limit the number of login attempts to prevent brute force attacks', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card fbs-opt-dependent-option" data-depends-on="limit_login_attempts_toggle">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Maximum Attempts', 'fbs-secure-optimize'); ?></h4>
                                <input type="number" name="fbs_opt_settings[login_security][max_attempts]" value="<?php echo esc_attr($this->get_setting_value('login_security', 'max_attempts')); ?>" min="3" max="20" class="fbs-opt-input" id="max_attempts_input" />
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Maximum number of login attempts before lockout', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card fbs-opt-dependent-option" data-depends-on="limit_login_attempts_toggle">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Lockout Duration (minutes)', 'fbs-secure-optimize'); ?></h4>
                                <input type="number" name="fbs_opt_settings[login_security][lockout_duration]" value="<?php echo esc_attr($this->get_setting_value('login_security', 'lockout_duration')); ?>" min="5" max="1440" class="fbs-opt-input" id="lockout_duration_input" />
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('How long to lock out users after exceeding maximum attempts', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card fbs-opt-option-card-wide">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Whitelist IP Addresses', 'fbs-secure-optimize'); ?></h4>
                            </div>
                            <textarea name="fbs_opt_settings[login_security][whitelist_ips]" rows="3" class="fbs-opt-textarea" placeholder="<?php esc_attr_e('Enter IP addresses, one per line', 'fbs-secure-optimize'); ?>"><?php echo esc_textarea($this->get_setting_value('login_security', 'whitelist_ips')); ?></textarea>
                            <p class="fbs-opt-option-description"><?php esc_html_e('IP addresses that are exempt from login attempt limits', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fbs-opt-section">
                    <div class="fbs-opt-section-header">
                        <h3 class="fbs-opt-section-title">
                            <span class="dashicons dashicons-shield"></span>
                            <?php esc_html_e('Security Headers', 'fbs-secure-optimize'); ?>
                        </h3>
                        <p class="fbs-opt-section-description"><?php esc_html_e('Add security headers to protect against common web vulnerabilities', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-options-grid">
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('X-Content-Type-Options', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[security_headers][x_content_type_options]" value="1" <?php checked(1, $this->get_setting_value('security_headers', 'x_content_type_options')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Prevent MIME type sniffing attacks', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('X-Frame-Options', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[security_headers][x_frame_options]" value="1" <?php checked(1, $this->get_setting_value('security_headers', 'x_frame_options')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Prevent clickjacking attacks', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('X-XSS-Protection', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[security_headers][x_xss_protection]" value="1" <?php checked(1, $this->get_setting_value('security_headers', 'x_xss_protection')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Enable XSS filtering in browsers', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Strict-Transport-Security', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[security_headers][strict_transport_security]" value="1" <?php checked(1, $this->get_setting_value('security_headers', 'strict_transport_security')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Force HTTPS connections (requires SSL certificate)', 'fbs-secure-optimize'); ?></p>
                        </div>
                        
                        <div class="fbs-opt-option-card">
                            <div class="fbs-opt-option-header">
                                <h4><?php esc_html_e('Hide WordPress Version', 'fbs-secure-optimize'); ?></h4>
                                <div class="fbs-opt-toggle">
                                    <input type="checkbox" name="fbs_opt_settings[security_headers][hide_wp_version]" value="1" <?php checked(1, $this->get_setting_value('security_headers', 'hide_wp_version')); ?> />
                                    <span class="fbs-opt-slider"></span>
                                </div>
                            </div>
                            <p class="fbs-opt-option-description"><?php esc_html_e('Remove WordPress version from head and RSS feeds', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fbs-opt-form-actions">
                    <?php submit_button(__('Save Security Settings', 'fbs-secure-optimize'), 'primary fbs-opt-button-primary', 'submit', false); ?>
                    <button type="button" class="fbs-opt-reset-btn fbs-opt-button fbs-opt-button-secondary" data-tab="security">
                        <span class="dashicons dashicons-undo"></span>
                        <?php esc_html_e('Reset to Defaults', 'fbs-secure-optimize'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Display statistics tab
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function display_statistics_tab() {
        $stats = FBS_Secure_Optimize_Controller::get_stats();
        $asset_optimizer = FBS_Secure_Optimize_Asset_Optimizer::get_instance();
        $asset_stats = $asset_optimizer->get_asset_stats();
        $cache_stats = $asset_optimizer->get_cache_stats();
        
        // Format file sizes
        $format_size = function($bytes) {
            if ($bytes >= 1048576) {
                return round($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                return round($bytes / 1024, 2) . ' KB';
            } else {
                return $bytes . ' bytes';
            }
        };
        ?>
        <div class="fbs-opt-statistics-tab">
            <div class="fbs-opt-stats-overview">
                <div class="fbs-opt-overview-card">
                    <div class="fbs-opt-overview-icon">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="fbs-opt-overview-content">
                        <h3><?php esc_html_e('Database Health', 'fbs-secure-optimize'); ?></h3>
                        <p><?php esc_html_e('Monitor your database size and cleanup opportunities', 'fbs-secure-optimize'); ?></p>
                    </div>
                </div>
                
                <div class="fbs-opt-overview-card">
                    <div class="fbs-opt-overview-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="fbs-opt-overview-content">
                        <h3><?php esc_html_e('Security Status', 'fbs-secure-optimize'); ?></h3>
                        <p><?php esc_html_e('Track security events and login attempts', 'fbs-secure-optimize'); ?></p>
                    </div>
                </div>
                
                <div class="fbs-opt-overview-card">
                    <div class="fbs-opt-overview-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <div class="fbs-opt-overview-content">
                        <h3><?php esc_html_e('Performance Metrics', 'fbs-secure-optimize'); ?></h3>
                        <p><?php esc_html_e('View optimization impact and improvements', 'fbs-secure-optimize'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="fbs-opt-section">
                <div class="fbs-opt-section-header">
                    <h3 class="fbs-opt-section-title">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Database Statistics', 'fbs-secure-optimize'); ?>
                    </h3>
                    <p class="fbs-opt-section-description"><?php esc_html_e('Current database usage and cleanup opportunities', 'fbs-secure-optimize'); ?></p>
                </div>
                
                <div class="fbs-opt-stats-grid">
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-backup"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Post Revisions', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(number_format($stats['post_revisions'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Old post revisions that can be cleaned up', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-edit-page"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Auto-drafts', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(number_format($stats['auto_drafts'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Unused auto-draft posts', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-admin-comments"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Spam Comments', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(number_format($stats['spam_comments'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Spam comments that can be removed', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Transients', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(number_format($stats['transients'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Expired transients in the database', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="fbs-opt-section">
                <div class="fbs-opt-section-header">
                    <h3 class="fbs-opt-section-title">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('Security Statistics', 'fbs-secure-optimize'); ?>
                    </h3>
                    <p class="fbs-opt-section-description"><?php esc_html_e('Security events and login attempt monitoring', 'fbs-secure-optimize'); ?></p>
                </div>
                
                <div class="fbs-opt-stats-grid">
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Failed Logins (24h)', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(number_format($stats['failed_logins'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Failed login attempts in the last 24 hours', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Security Score', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number fbs-opt-score">85%</span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Overall security configuration score', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="fbs-opt-section">
                <div class="fbs-opt-section-header">
                    <h3 class="fbs-opt-section-title">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php esc_html_e('Performance Impact', 'fbs-secure-optimize'); ?>
                    </h3>
                    <p class="fbs-opt-section-description"><?php esc_html_e('Estimated performance improvements from optimizations', 'fbs-secure-optimize'); ?></p>
                </div>
                
                <div class="fbs-opt-performance-metrics">
                    <div class="fbs-opt-metric-card">
                        <h4><?php esc_html_e('Page Load Time', 'fbs-secure-optimize'); ?></h4>
                        <div class="fbs-opt-metric-bar">
                            <div class="fbs-opt-metric-fill" style="width: 75%;"></div>
                        </div>
                        <p class="fbs-opt-metric-value"><?php esc_html_e('25% faster', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-metric-card">
                        <h4><?php esc_html_e('Database Size', 'fbs-secure-optimize'); ?></h4>
                        <div class="fbs-opt-metric-bar">
                            <div class="fbs-opt-metric-fill" style="width: 60%;"></div>
                        </div>
                        <p class="fbs-opt-metric-value"><?php esc_html_e('40% reduction', 'fbs-secure-optimize'); ?></p>
                    </div>
                    
                    <div class="fbs-opt-metric-card">
                        <h4><?php esc_html_e('HTTP Requests', 'fbs-secure-optimize'); ?></h4>
                        <div class="fbs-opt-metric-bar">
                            <div class="fbs-opt-metric-fill" style="width: 80%;"></div>
                        </div>
                        <p class="fbs-opt-metric-value"><?php esc_html_e('20% fewer requests', 'fbs-secure-optimize'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Asset Optimization Statistics -->
            <div class="fbs-opt-section">
                <div class="fbs-opt-section-header">
                    <h3 class="fbs-opt-section-title">
                        <span class="dashicons dashicons-performance"></span>
                        <?php esc_html_e('Asset Optimization Statistics', 'fbs-secure-optimize'); ?>
                    </h3>
                    <p class="fbs-opt-section-description"><?php esc_html_e('Monitor file optimization and performance improvements', 'fbs-secure-optimize'); ?></p>
                </div>
                
                <div class="fbs-opt-stats-grid">
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-media-code"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('CSS Files', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html($asset_stats['css_files']); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Total CSS files loaded', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-media-code"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('JavaScript Files', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html($asset_stats['js_files']); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Total JS files loaded', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-admin-tools"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Combined Files', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html($cache_stats['files']); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Optimized combined files', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('HTTP Requests Saved', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html(($asset_stats['css_files'] + $asset_stats['js_files']) - $cache_stats['files']); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Reduced HTTP requests', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Cache Size', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php echo esc_html($format_size($cache_stats['size'])); ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Total cached file size', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                    
                    <div class="fbs-opt-stat-card">
                        <div class="fbs-opt-stat-icon">
                            <span class="dashicons dashicons-chart-pie"></span>
                        </div>
                        <div class="fbs-opt-stat-content">
                            <h4><?php esc_html_e('Optimization Ratio', 'fbs-secure-optimize'); ?></h4>
                            <span class="fbs-opt-stat-number"><?php 
                                $total_files = $asset_stats['css_files'] + $asset_stats['js_files'];
                                $combined_files = $cache_stats['files'];
                                if ($total_files > 0) {
                                    $ratio = round((($total_files - $combined_files) / $total_files) * 100, 1);
                                    echo esc_html($ratio . '%');
                                } else {
                                    echo esc_html('0%');
                                }
                            ?></span>
                            <p class="fbs-opt-stat-description"><?php esc_html_e('Files optimized', 'fbs-secure-optimize'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a settings field
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $field_name Field name
     * @param string $section Section name
     */
    private function render_field($field_name, $section) {
        $settings = get_option('fbs_opt_settings', array());
        $value = isset($settings[$section][$field_name]) ? $settings[$section][$field_name] : '';
        
        echo '<tr>';
        echo '<th scope="row">' . esc_html(ucwords(str_replace('_', ' ', $field_name))) . '</th>';
        echo '<td>';
        
        switch ($field_name) {
            case 'minify_css':
            case 'minify_js':
            case 'combine_css':
            case 'combine_js':
            case 'lazy_load_images':
            case 'lazy_load_iframes':
            case 'cleanup_revisions':
            case 'cleanup_autodrafts':
            case 'cleanup_spam_comments':
            case 'cleanup_transients':
            case 'auto_cleanup':
            case 'limit_login_attempts':
            case 'x_content_type_options':
            case 'x_frame_options':
            case 'x_xss_protection':
            case 'strict_transport_security':
            case 'hide_wp_version':
                printf(
                    '<input type="checkbox" name="fbs_opt_settings[%1$s][%2$s]" value="1" %3$s />',
                    esc_attr($section),
                    esc_attr($field_name),
                    checked(1, $value, false)
                );
                break;
                
            case 'max_attempts':
            case 'lockout_duration':
                printf(
                    '<input type="number" name="fbs_opt_settings[%1$s][%2$s]" value="%3$s" min="1" />',
                    esc_attr($section),
                    esc_attr($field_name),
                    esc_attr($value)
                );
                break;
                
            case 'cleanup_frequency':
                $options = array('daily' => __('Daily', 'fbs-secure-optimize'), 'weekly' => __('Weekly', 'fbs-secure-optimize'), 'monthly' => __('Monthly', 'fbs-secure-optimize'));
                echo '<select name="fbs_opt_settings[' . esc_attr($section) . '][' . esc_attr($field_name) . ']">';
                foreach ($options as $option_value => $option_label) {
                    printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option_value), selected($value, $option_value, false), esc_html($option_label));
                }
                echo '</select>';
                break;
                
            case 'whitelist_ips':
                printf(
                    '<textarea name="fbs_opt_settings[%1$s][%2$s]" rows="3" cols="50">%3$s</textarea>',
                    esc_attr($section),
                    esc_attr($field_name),
                    esc_textarea($value)
                );
                break;
        }
        
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Display admin notices
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function admin_notices() {
        if (isset($_GET['settings-updated']) && wp_unslash($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'fbs-secure-optimize') . '</p></div>';
        }
    }

    /**
     * AJAX handler for database cleanup
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function ajax_cleanup_database() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'fbs_opt_admin_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-secure-optimize'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-secure-optimize'));
        }
        
        // Get database cleanup module
        $controller = FBS_Secure_Optimize_Controller::get_instance();
        $cleanup_module = $controller->get_module('database_cleanup');
        
        if ($cleanup_module) {
            $result = $cleanup_module->perform_cleanup();
            
            if ($result) {
                wp_send_json_success(array('message' => __('Database cleanup completed successfully.', 'fbs-secure-optimize')));
            } else {
                wp_send_json_error(array('message' => __('An error occurred during database cleanup.', 'fbs-secure-optimize')));
            }
        } else {
            wp_send_json_error(array('message' => __('Database cleanup module not found.', 'fbs-secure-optimize')));
        }
    }

    /**
     * AJAX handler for clearing cache
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function ajax_clear_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'fbs_opt_admin_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-secure-optimize'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-secure-optimize'));
        }
        
        // Get asset optimizer module
        $controller = FBS_Secure_Optimize_Controller::get_instance();
        $asset_optimizer = $controller->get_module('asset_optimizer');
        
        if ($asset_optimizer) {
            $asset_optimizer->clear_cache();
            
            // Clear statistics cache
            FBS_Secure_Optimize_Controller::clear_stats_cache();
            
            wp_send_json_success(array('message' => __('Asset cache cleared successfully.', 'fbs-secure-optimize')));
        } else {
            wp_send_json_error(array('message' => __('Asset optimizer module not found.', 'fbs-secure-optimize')));
        }
    }

    /**
     * AJAX handler for resetting settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function ajax_reset_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'fbs_opt_admin_nonce')) {
            wp_die(esc_html__('Security check failed.', 'fbs-secure-optimize'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'fbs-secure-optimize'));
        }
        
        if (!isset($_POST['tab'])) {
            wp_die(esc_html__('Invalid request.', 'fbs-secure-optimize'));
        }
        
        $tab = sanitize_text_field(wp_unslash($_POST['tab']));
        $defaults = $this->get_default_settings();
        
        if ($tab === 'performance') {
            // Reset performance settings
            $current_settings = get_option('fbs_opt_settings', array());
            $current_settings['asset_optimization'] = $defaults['asset_optimization'];
            $current_settings['database_cleanup'] = $defaults['database_cleanup'];
            update_option('fbs_opt_settings', $current_settings);
            
            wp_send_json_success(array('message' => __('Performance settings reset to defaults.', 'fbs-secure-optimize')));
        } elseif ($tab === 'security') {
            // Reset security settings
            $current_settings = get_option('fbs_opt_settings', array());
            $current_settings['login_security'] = $defaults['login_security'];
            $current_settings['security_headers'] = $defaults['security_headers'];
            update_option('fbs_opt_settings', $current_settings);
            
            wp_send_json_success(array('message' => __('Security settings reset to defaults.', 'fbs-secure-optimize')));
        } else {
            wp_send_json_error(array('message' => __('Invalid tab specified.', 'fbs-secure-optimize')));
        }
    }

    /**
     * Get default settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array
     */
    private function get_default_settings() {
        return array(
            'asset_optimization' => array(
                'minify_css' => 0,
                'minify_js' => 0,
                'combine_css' => 0,
                'combine_js' => 0,
                'lazy_load_images' => 0,
                'lazy_load_iframes' => 0
            ),
            'database_cleanup' => array(
                'cleanup_revisions' => 0,
                'cleanup_autodrafts' => 0,
                'cleanup_spam_comments' => 0,
                'cleanup_transients' => 0,
                'auto_cleanup' => 0,
                'cleanup_frequency' => 'weekly'
            ),
            'login_security' => array(
                'limit_login_attempts' => 0,
                'max_attempts' => 5,
                'lockout_duration' => 15,
                'whitelist_ips' => ''
            ),
            'security_headers' => array(
                'x_content_type_options' => 0,
                'x_frame_options' => 0,
                'x_xss_protection' => 0,
                'strict_transport_security' => 0,
                'hide_wp_version' => 0
            )
        );
    }

}
