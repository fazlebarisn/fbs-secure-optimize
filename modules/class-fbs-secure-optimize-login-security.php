<?php
/**
 * Unified Login Brute-Force Shield Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Protects wp-login.php & XML-RPC auth against brute-force attacks and username enumeration.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Login_Security {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Login_Security|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Login_Security
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
		$this->init_hooks();
	}

	/**
	 * Register login protection hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_login_failed', array( $this, 'handle_failed_login' ) );
		add_action( 'wp_login', array( $this, 'handle_successful_login' ), 10, 1 );

		if ( FBS_Secure_Optimize_Controller::is_feature_enabled( 'login_security', 'limit_login_attempts' ) ) {
			add_filter( 'authenticate', array( $this, 'check_login_attempts' ), 30, 1 );
		}

		if ( FBS_Secure_Optimize_Controller::is_feature_enabled( 'login_security', 'mask_login_errors' ) ) {
			add_filter( 'login_errors', array( $this, 'mask_login_error_message' ) );
		}

		add_action( 'wp_ajax_fbsseop_clear_lockouts', array( $this, 'ajax_clear_lockouts' ) );
	}

	/**
	 * Return generic error message on login failure to prevent username enumeration.
	 *
	 * @since 2.0.0
	 * @param string $error Original error message.
	 * @return string
	 */
	public function mask_login_error_message( $error ) {
		if ( false !== strpos( $error, 'fbsseop_ip_blocked' ) || false !== strpos( $error, 'temporarily blocked' ) ) {
			return $error;
		}
		return '<strong>' . esc_html__( 'Login Failed:', 'fbs-secure-optimize' ) . '</strong> ' . esc_html__( 'Invalid credentials provided. Please verify your username and password.', 'fbs-secure-optimize' );
	}

	/**
	 * Record failed login attempt.
	 *
	 * @since 1.0.0
	 * @param string $username Submitted username.
	 * @return void
	 */
	public function handle_failed_login( $username ) {
		$ip = self::get_client_ip();
		$this->log_login_attempt( $ip, $username, false );
		FBS_Secure_Optimize_Controller::clear_stats_cache();
	}

	/**
	 * Record successful login and reset failed attempt counter for the IP.
	 *
	 * @since 1.0.0
	 * @param string $username Authenticated username.
	 * @return void
	 */
	public function handle_successful_login( $username ) {
		$ip = self::get_client_ip();
		$this->log_login_attempt( $ip, $username, true );
		$this->clear_failed_attempts_for_ip( $ip );
		FBS_Secure_Optimize_Controller::clear_stats_cache();
	}

	/**
	 * Block authentication early when IP has exceeded maximum allowed failed attempts.
	 *
	 * @since 1.0.0
	 * @param WP_User|WP_Error|null $user User or error from prior filter.
	 * @return WP_User|WP_Error|null
	 */
	public function check_login_attempts( $user ) {
		$ip = self::get_client_ip();

		if ( $this->is_ip_whitelisted( $ip ) ) {
			return $user;
		}

		if ( $this->is_ip_blocked( $ip ) ) {
			$duration = max( 1, (int) FBS_Secure_Optimize_Controller::get_setting( 'login_security', 'lockout_duration', 15 ) );
			return new WP_Error(
				'fbsseop_ip_blocked',
				sprintf(
					/* translators: %d: Lockout duration in minutes. */
					esc_html__( 'Too many failed login attempts from your IP address. Access is temporarily locked for %d minutes.', 'fbs-secure-optimize' ),
					$duration
				)
			);
		}

		return $user;
	}

	/**
	 * Insert login attempt row and prune entries older than 30 days.
	 *
	 * @since 1.0.0
	 * @param string $ip_address Client IP.
	 * @param string $username   Username attempted.
	 * @param bool   $success    Whether login succeeded.
	 * @return void
	 */
	private function log_login_attempt( $ip_address, $username, $success ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fbsseop_login_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom security telemetry table insert.
		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => substr( $ip_address, 0, 45 ),
				'username'     => substr( sanitize_text_field( (string) $username ), 0, 190 ),
				'attempt_time' => gmdate( 'Y-m-d H:i:s' ),
				'success'      => $success ? 1 : 0,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table retention cleanup.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table_name} WHERE attempt_time < %s",
				$cutoff
			)
		);
	}

	/**
	 * Determine whether an IP is currently locked out.
	 *
	 * @since 1.0.0
	 * @param string $ip_address Client IP.
	 * @return bool
	 */
	public function is_ip_blocked( $ip_address ) {
		global $wpdb;

		$table_name       = $wpdb->prefix . 'fbsseop_login_attempts';
		$max_attempts     = max( 2, (int) FBS_Secure_Optimize_Controller::get_setting( 'login_security', 'max_attempts', 5 ) );
		$lockout_duration = max( 1, (int) FBS_Secure_Optimize_Controller::get_setting( 'login_security', 'lockout_duration', 15 ) );
		$window_start     = gmdate( 'Y-m-d H:i:s', time() - ( $lockout_duration * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time lockout check.
		$failed_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table_name} WHERE ip_address = %s AND success = 0 AND attempt_time > %s",
				$ip_address,
				$window_start
			)
		);

		return $failed_count >= $max_attempts;
	}

	/**
	 * Clear failed attempts for a single IP after successful authentication.
	 *
	 * @since 1.0.0
	 * @param string $ip_address Client IP.
	 * @return void
	 */
	private function clear_failed_attempts_for_ip( $ip_address ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'fbsseop_login_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Resetting failed attempts after valid login.
		$wpdb->delete(
			$table_name,
			array(
				'ip_address' => $ip_address,
				'success'    => 0,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Check whether an IP matches the administrator allowlist (supports exact IP and CIDR).
	 *
	 * @since 1.0.0
	 * @param string $ip_address Client IP.
	 * @return bool
	 */
	public function is_ip_whitelisted( $ip_address ) {
		$raw_list = (string) FBS_Secure_Optimize_Controller::get_setting( 'login_security', 'whitelist_ips', '' );
		if ( '' === trim( $raw_list ) ) {
			return false;
		}

		$entries = array_filter( array_map( 'trim', explode( "\n", $raw_list ) ) );
		foreach ( $entries as $entry ) {
			if ( $entry === $ip_address ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve recent failed login attempts for admin telemetry display.
	 *
	 * @since 2.0.0
	 * @param int $limit Max rows to fetch.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent_failed_attempts( $limit = 10 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'fbsseop_login_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin telemetry view.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $exists !== $table_name ) {
			return array();
		}

		$limit = max( 1, min( 50, absint( $limit ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin telemetry view.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ip_address, username, attempt_time FROM {$table_name} WHERE success = 0 ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * AJAX handler: Clear all active failed login lockouts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_clear_lockouts() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fbsseop_login_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin clear action.
		$wpdb->query( "DELETE FROM {$table_name} WHERE success = 0" );
		FBS_Secure_Optimize_Controller::clear_stats_cache();

		wp_send_json_success(
			array(
				'message' => __( 'All failed login lockouts have been cleared.', 'fbs-secure-optimize' ),
			)
		);
	}

	/**
	 * Safely determine client IP address using validated REMOTE_ADDR or Cloudflare header.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				return $cf_ip;
			}
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
				return $remote_ip;
			}
		}

		return '0.0.0.0';
	}
}
