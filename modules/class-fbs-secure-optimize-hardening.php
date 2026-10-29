<?php
/**
 * WordPress Core Hardening & Uploads Shield Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides toggleable XML-RPC blocking, file-editor locking, user-enumeration blocking,
 * high-risk plugin detection, and 1-click uploads/.htaccess PHP execution protection.
 *
 * @since 2.0.0
 */
class FBS_Secure_Optimize_Hardening {

	/**
	 * Singleton instance.
	 *
	 * @since 2.0.0
	 * @var FBS_Secure_Optimize_Hardening|null
	 */
	private static $instance = null;

	/**
	 * High-risk filesystem/database/code execution plugins frequently abused after credential theft.
	 *
	 * @var array<string, array<string, string>>
	 */
	const HIGH_RISK_PLUGINS = array(
		'file-manager-advanced' => array(
			'name' => 'Advanced File Manager',
			'risk' => 'Exposes direct server filesystem uploads & edits inside wp-admin.',
		),
		'wp-file-manager'       => array(
			'name' => 'WP File Manager',
			'risk' => 'Provides full server filesystem read/write access inside wp-admin.',
		),
		'code-snippets'         => array(
			'name' => 'Code Snippets',
			'risk' => 'Executes database-stored PHP code on every request if compromised.',
		),
		'pexlechris-adminer'    => array(
			'name' => 'Database Management (Adminer)',
			'risk' => 'Exposes direct SQL table & user capability manipulation.',
		),
		'wp-ide'                => array(
			'name' => 'WP IDE / File Editor',
			'risk' => 'Exposes server PHP file editing inside wp-admin.',
		),
	);

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 2.0.0
	 * @return FBS_Secure_Optimize_Hardening
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
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hardening hooks according to individual settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function init_hooks() {
		$settings = FBS_Secure_Optimize_Controller::get_all_settings();
		$opts     = isset( $settings['wp_hardening'] ) ? $settings['wp_hardening'] : array();

		// 1. Disable XML-RPC & X-Pingback header.
		if ( ! empty( $opts['disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_x_pingback' ) );
		}

		// 2. Lock theme & plugin file editors via map_meta_cap.
		if ( ! empty( $opts['disable_file_edit'] ) ) {
			add_filter( 'map_meta_cap', array( $this, 'block_file_edit_capabilities' ), 10, 2 );
		}

		// 3. Block ?author=N username enumeration scans on frontend.
		if ( ! empty( $opts['block_author_enum'] ) && ! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'block_author_id_enumeration' ), 1 );
		}

		// 4. Restrict unauthenticated /wp-json/wp/v2/users endpoint enumeration.
		if ( ! empty( $opts['disable_rest_users'] ) ) {
			add_filter( 'rest_endpoints', array( $this, 'restrict_rest_user_endpoints' ) );
		}

		// AJAX endpoints for 1-click uploads shield & risky plugin deactivation.
		add_action( 'wp_ajax_fbsseop_protect_uploads', array( $this, 'ajax_protect_uploads' ) );
		add_action( 'wp_ajax_fbsseop_deactivate_risky_plugin', array( $this, 'ajax_deactivate_risky_plugin' ) );
	}

	/**
	 * Strip X-Pingback response header.
	 *
	 * @since 2.0.0
	 * @param array<string, string> $headers HTTP headers.
	 * @return array<string, string>
	 */
	public function remove_x_pingback( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Block edit_files, edit_plugins, and edit_themes capabilities dynamically.
	 *
	 * @since 2.0.0
	 * @param array<int, string> $caps Required primitive capabilities.
	 * @param string             $cap  Capability being checked.
	 * @return array<int, string>
	 */
	public function block_file_edit_capabilities( $caps, $cap ) {
		if ( in_array( $cap, array( 'edit_files', 'edit_plugins', 'edit_themes' ), true ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Redirect ?author=1 bot enumeration probes safely to home URL.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function block_author_id_enumeration() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Inspecting public query parameter for bot enumeration probe.
		if ( isset( $_GET['author'] ) && ! current_user_can( 'list_users' ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Hide /wp/v2/users REST routes from unauthenticated visitors to stop username harvesting.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $endpoints Registered REST endpoints.
	 * @return array<string, mixed>
	 */
	public function restrict_rest_user_endpoints( $endpoints ) {
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}

		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}

		return $endpoints;
	}

	/**
	 * Detect active high-risk filesystem/database/code plugins.
	 *
	 * @since 2.0.0
	 * @return array<int, array<string, string>>
	 */
	public static function get_active_high_risk_plugins() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins  = array_unique( array_merge( $active_plugins, $network_plugins ) );
		}

		$detected = array();
		foreach ( $active_plugins as $plugin_basename ) {
			foreach ( self::HIGH_RISK_PLUGINS as $slug => $meta ) {
				if ( 0 === strpos( $plugin_basename, $slug . '/' ) || $plugin_basename === $slug . '.php' ) {
					$detected[] = array(
						'slug'     => $slug,
						'basename' => $plugin_basename,
						'name'     => $meta['name'],
						'risk'     => $meta['risk'],
					);
				}
			}
		}

		return $detected;
	}

	/**
	 * Check whether wp-content/uploads/.htaccess contains PHP execution blocking rules.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function is_uploads_protected() {
		$upload_dir = wp_upload_dir();
		$basedir    = ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';
		$htaccess   = trailingslashit( $basedir ) . '.htaccess';

		if ( ! is_file( $htaccess ) || ! is_readable( $htaccess ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local .htaccess status file.
		$content = (string) file_get_contents( $htaccess );
		return ( false !== strpos( $content, 'FBS Secure Optimize Uploads Shield' ) || false !== stripos( $content, 'FilesMatch' ) );
	}

	/**
	 * Write Apache 2.2/2.4 PHP execution blocking rules to wp-content/uploads/.htaccess via WP_Filesystem.
	 *
	 * @since 2.0.0
	 * @return true|WP_Error
	 */
	public static function protect_uploads_directory() {
		$upload_dir = wp_upload_dir();
		$basedir    = ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';

		if ( ! is_dir( $basedir ) && ! wp_mkdir_p( $basedir ) ) {
			return new WP_Error( 'uploads_missing', __( 'The uploads directory could not be found or created.', 'fbs-secure-optimize' ) );
		}

		$htaccess = trailingslashit( $basedir ) . '.htaccess';
		$existing = '';
		if ( is_file( $htaccess ) && is_readable( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local .htaccess read.
			$existing = (string) file_get_contents( $htaccess );
		}

		if ( false !== strpos( $existing, '# BEGIN FBS Secure Optimize Uploads Shield' ) || false !== strpos( $existing, '# BEGIN FBS Sentinel Uploads Protection' ) ) {
			return true;
		}

		$rules = "\n# BEGIN FBS Secure Optimize Uploads Shield\n"
			. "<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|php8|phar|pl|py|cgi|sh)$\">\n"
			. "\t<IfModule mod_authz_core.c>\n"
			. "\t\tRequire all denied\n"
			. "\t</IfModule>\n"
			. "\t<IfModule !mod_authz_core.c>\n"
			. "\t\tOrder allow,deny\n"
			. "\t\tDeny from all\n"
			. "\t</IfModule>\n"
			. "</FilesMatch>\n"
			. "# END FBS Secure Optimize Uploads Shield\n";

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $htaccess, ltrim( $existing . $rules ), FS_CHMOD_FILE ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write to wp-content/uploads/.htaccess. Please check directory permissions.', 'fbs-secure-optimize' ) );
		}

		return true;
	}

	/**
	 * AJAX handler: Protect wp-content/uploads/ against direct PHP execution.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_protect_uploads() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$result = self::protect_uploads_directory();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Direct PHP execution inside wp-content/uploads/ is now blocked via .htaccess!', 'fbs-secure-optimize' ),
			)
		);
	}

	/**
	 * AJAX handler: Deactivate a flagged high-risk plugin in 1 click.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_deactivate_risky_plugin() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$basename = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
		if ( '' === $basename ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin specified.', 'fbs-secure-optimize' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $basename );

		wp_send_json_success(
			array(
				/* translators: %s: Plugin basename. */
				'message'  => sprintf( __( 'Plugin "%s" has been deactivated.', 'fbs-secure-optimize' ), $basename ),
				'basename' => $basename,
			)
		);
	}
}
