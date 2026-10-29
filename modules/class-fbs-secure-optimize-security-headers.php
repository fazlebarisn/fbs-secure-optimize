<?php
/**
 * HTTP Security Headers Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends configurable Grade A+ HTTP security headers and hides WordPress version leaks.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Security_Headers {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Security_Headers|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Security_Headers
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
	 * Register HTTP header and version-masking hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );

		if ( FBS_Secure_Optimize_Controller::is_feature_enabled( 'security_headers', 'hide_wp_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'style_loader_src', array( $this, 'strip_wp_ver_query_arg' ), 9999 );
			add_filter( 'script_loader_src', array( $this, 'strip_wp_ver_query_arg' ), 9999 );
		}
	}

	/**
	 * Output enabled HTTP security headers safely without breaking external fonts or gateways.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		$settings = FBS_Secure_Optimize_Controller::get_all_settings();
		$headers  = isset( $settings['security_headers'] ) ? $settings['security_headers'] : array();

		if ( ! empty( $headers['x_content_type_options'] ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		if ( ! empty( $headers['x_frame_options'] ) ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
		}

		if ( ! empty( $headers['x_xss_protection'] ) ) {
			header( 'X-XSS-Protection: 1; mode=block' );
		}

		if ( ! empty( $headers['referrer_policy'] ) ) {
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}

		if ( ! empty( $headers['permissions_policy'] ) ) {
			header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=()' );
		}

		if ( ! empty( $headers['strict_transport_security'] ) && is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}

		/**
		 * Allows FBS Secure Optimize Pro to append custom CSP or HSTS preload headers.
		 *
		 * @since 2.0.0
		 */
		do_action( 'fbsseop_send_custom_headers' );
	}

	/**
	 * Remove exact WordPress core version query arg (?ver=6.x.x) to prevent version fingerprinting.
	 *
	 * @since 2.0.0
	 * @param string $src Asset URL.
	 * @return string
	 */
	public function strip_wp_ver_query_arg( $src ) {
		global $wp_version;
		if ( ! empty( $src ) && ! empty( $wp_version ) && false !== strpos( $src, 'ver=' . $wp_version ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}
}
