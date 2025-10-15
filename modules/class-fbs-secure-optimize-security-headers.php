<?php
/**
 * Security Headers module for FBS Secure Optimize plugin
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
 * Security Headers class
 */
class FBS_Secure_Optimize_Security_Headers {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Security_Headers
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Security_Headers
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
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Hide WordPress version
        if (FBS_Secure_Optimize_Controller::is_feature_enabled('security_headers', 'hide_wp_version')) {
            add_filter('the_generator', '__return_empty_string');
            add_filter('wp_head', array($this, 'remove_wp_version_from_head'), 1);
            add_filter('rss2_head', array($this, 'remove_wp_version_from_rss'), 1);
            add_filter('rdf_header', array($this, 'remove_wp_version_from_rss'), 1);
            add_filter('atom_head', array($this, 'remove_wp_version_from_rss'), 1);
            add_filter('commentsrss2_head', array($this, 'remove_wp_version_from_rss'), 1);
            add_filter('opml_head', array($this, 'remove_wp_version_from_rss'), 1);
            add_filter('app_head', array($this, 'remove_wp_version_from_rss'), 1);
        }
    }

    /**
     * Add security headers
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function add_security_headers() {
        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        // X-Content-Type-Options
        if (isset($header_settings['x_content_type_options']) && $header_settings['x_content_type_options']) {
            header('X-Content-Type-Options: nosniff');
        }

        // X-Frame-Options
        if (isset($header_settings['x_frame_options']) && $header_settings['x_frame_options']) {
            header('X-Frame-Options: SAMEORIGIN');
        }

        // X-XSS-Protection
        if (isset($header_settings['x_xss_protection']) && $header_settings['x_xss_protection']) {
            header('X-XSS-Protection: 1; mode=block');
        }

        // Strict-Transport-Security (only if HTTPS)
        if (isset($header_settings['strict_transport_security']) && $header_settings['strict_transport_security']) {
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
        }

        // Additional security headers
        $this->add_additional_security_headers();
    }

    /**
     * Add additional security headers
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function add_additional_security_headers() {
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (formerly Feature Policy)
        $permissions_policy = array(
            'camera' => '()',
            'microphone' => '()',
            'geolocation' => '()',
            'payment' => '()',
            'usb' => '()',
            'magnetometer' => '()',
            'gyroscope' => '()',
            'speaker' => '()',
            'vibrate' => '()',
            'fullscreen' => '()',
            'sync-xhr' => '()',
        );
        
        $policy_string = '';
        foreach ($permissions_policy as $feature => $allowlist) {
            $policy_string .= $feature . '=' . $allowlist . ', ';
        }
        $policy_string = rtrim($policy_string, ', ');
        
        header('Permissions-Policy: ' . $policy_string);
        
        // Content Security Policy (basic)
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.gstatic.com *.google-analytics.com *.googletagmanager.com; style-src 'self' 'unsafe-inline' *.googleapis.com *.gstatic.com; img-src 'self' data: *.gravatar.com *.wp.com; font-src 'self' *.googleapis.com *.gstatic.com; connect-src 'self' *.google-analytics.com *.googletagmanager.com; frame-src 'self' *.youtube.com *.vimeo.com;";
        
        header('Content-Security-Policy: ' . $csp);
        
        // Cross-Origin Embedder Policy
        header('Cross-Origin-Embedder-Policy: require-corp');
        
        // Cross-Origin Opener Policy
        header('Cross-Origin-Opener-Policy: same-origin');
        
        // Cross-Origin Resource Policy
        header('Cross-Origin-Resource-Policy: same-origin');
    }

    /**
     * Remove WordPress version from head
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function remove_wp_version_from_head() {
        remove_action('wp_head', 'wp_generator');
    }

    /**
     * Remove WordPress version from RSS feeds
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function remove_wp_version_from_rss() {
        remove_action('rss2_head', 'the_generator');
        remove_action('rdf_header', 'the_generator');
        remove_action('atom_head', 'the_generator');
        remove_action('commentsrss2_head', 'the_generator');
        remove_action('opml_head', 'the_generator');
        remove_action('app_head', 'the_generator');
    }

    /**
     * Get security headers status
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Security headers status
     */
    public function get_security_headers_status() {
        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        $status = array(
            'x_content_type_options' => isset($header_settings['x_content_type_options']) && $header_settings['x_content_type_options'],
            'x_frame_options' => isset($header_settings['x_frame_options']) && $header_settings['x_frame_options'],
            'x_xss_protection' => isset($header_settings['x_xss_protection']) && $header_settings['x_xss_protection'],
            'strict_transport_security' => isset($header_settings['strict_transport_security']) && $header_settings['strict_transport_security'],
            'hide_wp_version' => isset($header_settings['hide_wp_version']) && $header_settings['hide_wp_version'],
        );

        return $status;
    }

    /**
     * Test security headers
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Test results
     */
    public function test_security_headers() {
        $results = array();
        
        // Test if headers are being sent
        $headers = $this->get_response_headers();
        
        $expected_headers = array(
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=(), vibrate=(), fullscreen=(), sync-xhr=()',
            'Content-Security-Policy' => 'default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' *.googleapis.com *.gstatic.com *.google-analytics.com *.googletagmanager.com; style-src \'self\' \'unsafe-inline\' *.googleapis.com *.gstatic.com; img-src \'self\' data: *.gravatar.com *.wp.com; font-src \'self\' *.googleapis.com *.gstatic.com; connect-src \'self\' *.google-analytics.com *.googletagmanager.com; frame-src \'self\' *.youtube.com *.vimeo.com;',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        );
        
        if (is_ssl()) {
            $expected_headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }
        
        foreach ($expected_headers as $header_name => $expected_value) {
            $results[$header_name] = array(
                'present' => isset($headers[$header_name]),
                'value' => isset($headers[$header_name]) ? $headers[$header_name] : '',
                'expected' => $expected_value,
                'correct' => isset($headers[$header_name]) && $headers[$header_name] === $expected_value,
            );
        }
        
        return $results;
    }

    /**
     * Get response headers
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Response headers
     */
    private function get_response_headers() {
        $headers = array();
        
        if (function_exists('getallheaders')) {
            $all_headers = getallheaders();
            if ($all_headers) {
                foreach ($all_headers as $name => $value) {
                    $headers[$name] = $value;
                }
            }
        }
        
        return $headers;
    }

    /**
     * Generate security headers report
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Security report
     */
    public function generate_security_report() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'is_ssl' => is_ssl(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => FBS_SECURE_OPTIMIZE_VERSION,
            'headers_status' => $this->get_security_headers_status(),
            'headers_test' => $this->test_security_headers(),
            'recommendations' => $this->get_security_recommendations(),
        );
        
        return $report;
    }

    /**
     * Get security recommendations
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Security recommendations
     */
    private function get_security_recommendations() {
        $recommendations = array();
        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        // Check SSL
        if (!is_ssl()) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Enable SSL/HTTPS for your website to use Strict-Transport-Security header.', 'fbs-secure-optimize'),
            );
        }

        // Check WordPress version hiding
        if (!isset($header_settings['hide_wp_version']) || !$header_settings['hide_wp_version']) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Consider hiding WordPress version to prevent information disclosure.', 'fbs-secure-optimize'),
            );
        }

        // Check security headers
        if (!isset($header_settings['x_content_type_options']) || !$header_settings['x_content_type_options']) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Enable X-Content-Type-Options header to prevent MIME type sniffing attacks.', 'fbs-secure-optimize'),
            );
        }

        if (!isset($header_settings['x_frame_options']) || !$header_settings['x_frame_options']) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Enable X-Frame-Options header to prevent clickjacking attacks.', 'fbs-secure-optimize'),
            );
        }

        if (!isset($header_settings['x_xss_protection']) || !$header_settings['x_xss_protection']) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Enable X-XSS-Protection header for additional XSS protection.', 'fbs-secure-optimize'),
            );
        }

        return $recommendations;
    }

    /**
     * Validate security headers configuration
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Validation results
     */
    public function validate_configuration() {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
        );

        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        // Check if any security headers are enabled
        $enabled_headers = array_filter($header_settings);
        if (empty($enabled_headers)) {
            $validation['warnings'][] = __('No security headers are currently enabled.', 'fbs-secure-optimize');
        }

        // Check Strict-Transport-Security without SSL
        if (isset($header_settings['strict_transport_security']) && $header_settings['strict_transport_security'] && !is_ssl()) {
            $validation['warnings'][] = __('Strict-Transport-Security header is enabled but SSL is not active.', 'fbs-secure-optimize');
        }

        return $validation;
    }

    /**
     * Export security configuration
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Security configuration
     */
    public function export_configuration() {
        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        return array(
            'security_headers' => $header_settings,
            'export_timestamp' => current_time('mysql'),
            'site_url' => home_url(),
        );
    }

    /**
     * Import security configuration
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $config Configuration to import
     * @return bool True if successful
     */
    public function import_configuration($config) {
        if (!isset($config['security_headers']) || !is_array($config['security_headers'])) {
            return false;
        }

        $settings = get_option('fbs_opt_settings', array());
        $settings['security_headers'] = $config['security_headers'];

        return update_option('fbs_opt_settings', $settings);
    }

    /**
     * Reset security headers to defaults
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return bool True if successful
     */
    public function reset_to_defaults() {
        $default_headers = array(
            'x_content_type_options' => 1,
            'x_frame_options' => 1,
            'x_xss_protection' => 1,
            'strict_transport_security' => 0,
            'hide_wp_version' => 1,
        );

        $settings = get_option('fbs_opt_settings', array());
        $settings['security_headers'] = $default_headers;

        return update_option('fbs_opt_settings', $settings);
    }

    /**
     * Get security score
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Security score
     */
    public function get_security_score() {
        $settings = get_option('fbs_opt_settings', array());
        $header_settings = isset($settings['security_headers']) ? $settings['security_headers'] : array();

        $score = 0;
        $max_score = 0;
        $details = array();

        // X-Content-Type-Options
        $max_score += 20;
        if (isset($header_settings['x_content_type_options']) && $header_settings['x_content_type_options']) {
            $score += 20;
            $details[] = __('X-Content-Type-Options header enabled', 'fbs-secure-optimize');
        } else {
            $details[] = __('X-Content-Type-Options header disabled', 'fbs-secure-optimize');
        }

        // X-Frame-Options
        $max_score += 20;
        if (isset($header_settings['x_frame_options']) && $header_settings['x_frame_options']) {
            $score += 20;
            $details[] = __('X-Frame-Options header enabled', 'fbs-secure-optimize');
        } else {
            $details[] = __('X-Frame-Options header disabled', 'fbs-secure-optimize');
        }

        // X-XSS-Protection
        $max_score += 15;
        if (isset($header_settings['x_xss_protection']) && $header_settings['x_xss_protection']) {
            $score += 15;
            $details[] = __('X-XSS-Protection header enabled', 'fbs-secure-optimize');
        } else {
            $details[] = __('X-XSS-Protection header disabled', 'fbs-secure-optimize');
        }

        // Strict-Transport-Security
        $max_score += 25;
        if (isset($header_settings['strict_transport_security']) && $header_settings['strict_transport_security'] && is_ssl()) {
            $score += 25;
            $details[] = __('Strict-Transport-Security header enabled', 'fbs-secure-optimize');
        } elseif (isset($header_settings['strict_transport_security']) && $header_settings['strict_transport_security'] && !is_ssl()) {
            $score += 10;
            $details[] = __('Strict-Transport-Security header enabled but SSL not active', 'fbs-secure-optimize');
        } else {
            $details[] = __('Strict-Transport-Security header disabled', 'fbs-secure-optimize');
        }

        // Hide WordPress version
        $max_score += 20;
        if (isset($header_settings['hide_wp_version']) && $header_settings['hide_wp_version']) {
            $score += 20;
            $details[] = __('WordPress version hidden', 'fbs-secure-optimize');
        } else {
            $details[] = __('WordPress version visible', 'fbs-secure-optimize');
        }

        $percentage = $max_score > 0 ? round(($score / $max_score) * 100) : 0;

        return array(
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'details' => $details,
        );
    }
}
