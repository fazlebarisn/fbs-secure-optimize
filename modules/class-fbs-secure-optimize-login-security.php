<?php
/**
 * Login Security module for FBS Secure Optimize plugin
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
 * Login Security class
 */
class FBS_Secure_Optimize_Login_Security {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Login_Security
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Login_Security
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
        // Only run if login security is enabled
        if (FBS_Secure_Optimize_Controller::is_feature_enabled('login_security', 'limit_login_attempts')) {
            add_action('wp_login_failed', array($this, 'handle_failed_login'));
            add_action('wp_login', array($this, 'handle_successful_login'));
            add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
            add_action('login_enqueue_scripts', array($this, 'enqueue_login_styles'));
            add_action('login_footer', array($this, 'add_login_scripts'));
        }
    }

    /**
     * Handle failed login attempt
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $username Username that failed to login
     */
    public function handle_failed_login($username) {
        $ip_address = $this->get_client_ip();
        $this->log_login_attempt($ip_address, $username, false);
        
        // Check if IP should be blocked
        if ($this->is_ip_blocked($ip_address)) {
            $this->block_ip($ip_address);
        }
    }

    /**
     * Handle successful login
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $username Username that successfully logged in
     */
    public function handle_successful_login($username) {
        $ip_address = $this->get_client_ip();
        $this->log_login_attempt($ip_address, $username, true);
        
        // Clear failed attempts for this IP
        $this->clear_failed_attempts($ip_address);
    }

    /**
     * Check login attempts before authentication
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param WP_User|WP_Error|null $user User object or error
     * @param string $username Username
     * @param string $password Password
     * @return WP_User|WP_Error User object or error
     */
    public function check_login_attempts($user, $username, $password) {
        // Skip if already an error
        if (is_wp_error($user)) {
            return $user;
        }

        $ip_address = $this->get_client_ip();
        
        // Check if IP is whitelisted
        if ($this->is_ip_whitelisted($ip_address)) {
            return $user;
        }
        
        // Check if IP is blocked
        if ($this->is_ip_blocked($ip_address)) {
            $lockout_duration = FBS_Secure_Optimize_Controller::get_setting('login_security', 'lockout_duration', 15);
            
            return new WP_Error(
                'fbs_opt_ip_blocked',
                sprintf(
                    /* translators: %d is the number of minutes until the IP address is unblocked */
                    __('Your IP address has been temporarily blocked due to too many failed login attempts. Please try again in %d minutes.', 'fbs-secure-optimize'),
                    $lockout_duration
                )
            );
        }
        
        return $user;
    }

    /**
     * Log login attempt
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address
     * @param string $username Username
     * @param bool $success Whether login was successful
     */
    private function log_login_attempt($ip_address, $username, $success) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security logging operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security logging operation
        $wpdb->insert(
            $table_name,
            array(
                'ip_address' => $ip_address,
                'username' => sanitize_text_field($username),
                'success' => $success ? 1 : 0,
                'attempt_time' => current_time('mysql'),
            ),
            array('%s', '%s', '%d', '%s')
        );
        
        // Clean old records (older than 30 days)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security cleanup operation
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . esc_sql($table_name) . " WHERE attempt_time < %s",
                gmdate('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );
    }

    /**
     * Check if IP address is blocked
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address
     * @return bool True if blocked
     */
    private function is_ip_blocked($ip_address) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security check operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        $max_attempts = FBS_Secure_Optimize_Controller::get_setting('login_security', 'max_attempts', 5);
        $lockout_duration = FBS_Secure_Optimize_Controller::get_setting('login_security', 'lockout_duration', 15);
        
        // Count failed attempts in the last lockout period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security check operation
        $failed_attempts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table_name) . " 
                 WHERE ip_address = %s 
                 AND success = 0 
                 AND attempt_time > %s",
                $ip_address,
                gmdate('Y-m-d H:i:s', strtotime("-{$lockout_duration} minutes"))
            )
        );
        
        return $failed_attempts >= $max_attempts;
    }

    /**
     * Block IP address
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address
     */
    private function block_ip($ip_address) {
        // Log the blocking
        FBS_Secure_Optimize_Controller::log(
            sprintf('IP address %s blocked due to too many failed login attempts', $ip_address),
            'warning'
        );
        
        // You could implement additional blocking mechanisms here
        // such as adding to .htaccess or using a firewall
    }

    /**
     * Clear failed attempts for IP
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address
     */
    private function clear_failed_attempts($ip_address) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security cleanup operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security cleanup operation
        $wpdb->delete(
            $table_name,
            array('ip_address' => $ip_address, 'success' => 0),
            array('%s', '%d')
        );
    }

    /**
     * Check if IP is whitelisted
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address
     * @return bool True if whitelisted
     */
    private function is_ip_whitelisted($ip_address) {
        $whitelist_ips = FBS_Secure_Optimize_Controller::get_setting('login_security', 'whitelist_ips', '');
        
        if (empty($whitelist_ips)) {
            return false;
        }
        
        $whitelist = array_map('trim', explode("\n", $whitelist_ips));
        
        foreach ($whitelist as $whitelist_ip) {
            if ($this->ip_in_range($ip_address, $whitelist_ip)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is in range
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip IP address
     * @param string $range IP range (supports CIDR notation)
     * @return bool True if IP is in range
     */
    private function ip_in_range($ip, $range) {
        // Handle CIDR notation
        if (strpos($range, '/') !== false) {
            list($subnet, $bits) = explode('/', $range);
            
            if ($bits === null) {
                $bits = 32;
            }
            
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            
            return ($ip & $mask) === $subnet;
        }
        
        // Handle single IP
        return $ip === $range;
    }

    /**
     * Get client IP address
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Enqueue login page styles
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function enqueue_login_styles() {
        wp_enqueue_style(
            'fbs-opt-login',
            FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FBS_SECURE_OPTIMIZE_VERSION
        );
    }

    /**
     * Add login page scripts
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function add_login_scripts() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add rate limiting notice
            const loginForm = document.getElementById('loginform');
            if (loginForm) {
                const notice = document.createElement('div');
                notice.className = 'fbs-opt-login-warning';
                notice.innerHTML = '<?php echo esc_js(__('For security purposes, login attempts are limited. Multiple failed attempts will result in temporary IP blocking.', 'fbs-secure-optimize')); ?>';
                loginForm.parentNode.insertBefore(notice, loginForm);
            }
        });
        </script>
        <?php
    }

    /**
     * Get login attempt statistics
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param int $hours Number of hours to look back
     * @return array Statistics
     */
    public function get_login_stats($hours = 24) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        $stats = array(
            'total_attempts' => 0,
            'failed_attempts' => 0,
            'successful_attempts' => 0,
            'unique_ips' => 0,
            'blocked_ips' => array(),
        );
        
        // Get total attempts in the specified time period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        $stats['total_attempts'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table_name) . " 
                 WHERE attempt_time > %s",
                gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"))
            )
        );
        
        // Get failed attempts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        $stats['failed_attempts'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table_name) . " 
                 WHERE success = 0 
                 AND attempt_time > %s",
                gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"))
            )
        );
        
        // Get successful attempts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        $stats['successful_attempts'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table_name) . " 
                 WHERE success = 1 
                 AND attempt_time > %s",
                gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"))
            )
        );
        
        // Get unique IPs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        $stats['unique_ips'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_address) FROM " . esc_sql($table_name) . " 
                 WHERE attempt_time > %s",
                gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"))
            )
        );
        
        // Get currently blocked IPs
        $max_attempts = FBS_Secure_Optimize_Controller::get_setting('login_security', 'max_attempts', 5);
        $lockout_duration = FBS_Secure_Optimize_Controller::get_setting('login_security', 'lockout_duration', 15);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        $blocked_ips = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip_address, COUNT(*) as attempts 
                 FROM " . esc_sql($table_name) . " 
                 WHERE success = 0 
                 AND attempt_time > %s 
                 GROUP BY ip_address 
                 HAVING attempts >= %d",
                gmdate('Y-m-d H:i:s', strtotime("-{$lockout_duration} minutes")),
                $max_attempts
            )
        );
        
        $stats['blocked_ips'] = $blocked_ips;
        
        return $stats;
    }

    /**
     * Get recent login attempts
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param int $limit Number of attempts to retrieve
     * @return array Recent attempts
     */
    public function get_recent_attempts($limit = 50) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security statistics operation
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . esc_sql($table_name) . " 
                 ORDER BY attempt_time DESC 
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Clear all login attempts
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return bool True if successful
     */
    public function clear_all_attempts() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security cleanup operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security cleanup operation
        $result = $wpdb->query("TRUNCATE TABLE " . esc_sql($table_name));
        
        if ($result !== false) {
            FBS_Secure_Optimize_Controller::log('All login attempts cleared', 'info');
            return true;
        }
        
        return false;
    }

    /**
     * Unblock IP address
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address to unblock
     * @return bool True if successful
     */
    public function unblock_ip($ip_address) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security management operation
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fbs_opt_login_attempts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Login security management operation
        $result = $wpdb->delete(
            $table_name,
            array('ip_address' => $ip_address, 'success' => 0),
            array('%s', '%d')
        );
        
        if ($result !== false) {
            FBS_Secure_Optimize_Controller::log("IP address $ip_address unblocked", 'info');
            return true;
        }
        
        return false;
    }

    /**
     * Add IP to whitelist
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address to whitelist
     * @return bool True if successful
     */
    public function add_to_whitelist($ip_address) {
        $whitelist_ips = FBS_Secure_Optimize_Controller::get_setting('login_security', 'whitelist_ips', '');
        $whitelist = array_map('trim', explode("\n", $whitelist_ips));
        
        if (!in_array($ip_address, $whitelist)) {
            $whitelist[] = $ip_address;
            $new_whitelist = implode("\n", array_filter($whitelist));
            
            return FBS_Secure_Optimize_Controller::update_setting('login_security', 'whitelist_ips', $new_whitelist);
        }
        
        return true;
    }

    /**
     * Remove IP from whitelist
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $ip_address IP address to remove
     * @return bool True if successful
     */
    public function remove_from_whitelist($ip_address) {
        $whitelist_ips = FBS_Secure_Optimize_Controller::get_setting('login_security', 'whitelist_ips', '');
        $whitelist = array_map('trim', explode("\n", $whitelist_ips));
        
        $key = array_search($ip_address, $whitelist);
        if ($key !== false) {
            unset($whitelist[$key]);
            $new_whitelist = implode("\n", array_filter($whitelist));
            
            return FBS_Secure_Optimize_Controller::update_setting('login_security', 'whitelist_ips', $new_whitelist);
        }
        
        return true;
    }
}
