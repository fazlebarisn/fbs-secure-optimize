<?php
/**
 * 6-Layer Malware, Core Checksum & Hidden-Admin Scanner Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performs read-only deep diagnostics across WordPress root, official Core MD5 checksums,
 * wp-content/uploads, mu-plugins, plugin/theme PHP heuristics, database options/snippets,
 * and hidden pre_user_query administrator accounts.
 *
 * @since 2.0.0
 */
class FBS_Secure_Optimize_Scanner {

	/**
	 * Singleton instance.
	 *
	 * @since 2.0.0
	 * @var FBS_Secure_Optimize_Scanner|null
	 */
	private static $instance = null;

	/**
	 * Official WordPress root PHP files.
	 *
	 * @var array<int, string>
	 */
	const ALLOWED_ROOT_PHP_FILES = array(
		'index.php',
		'wp-activate.php',
		'wp-blog-header.php',
		'wp-comments-post.php',
		'wp-config.php',
		'wp-config-sample.php',
		'wp-cron.php',
		'wp-links-opml.php',
		'wp-load.php',
		'wp-login.php',
		'wp-mail.php',
		'wp-settings.php',
		'wp-signup.php',
		'wp-trackback.php',
		'xmlrpc.php',
		'wp-salt.php',
	);

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 2.0.0
	 * @return FBS_Secure_Optimize_Scanner
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
		add_action( 'wp_ajax_fbsseop_run_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_fbsseop_preview_file', array( $this, 'ajax_preview_file' ) );
		add_action( 'wp_ajax_fbsseop_view_db_code', array( 'FBS_Secure_Optimize_Scanner_Forensics', 'ajax_view_db_code' ) );
		add_action( 'wp_ajax_fbsseop_remediate_snippet', array( 'FBS_Secure_Optimize_Scanner_Forensics', 'ajax_remediate_snippet' ) );
		add_action( 'wp_ajax_fbsseop_force_delete_user', array( 'FBS_Secure_Optimize_Scanner_Forensics', 'ajax_force_delete_user' ) );
		add_action( 'wp_ajax_fbsseop_revoke_app_password', array( 'FBS_Secure_Optimize_Scanner_Forensics', 'ajax_revoke_app_password' ) );
		add_action( 'wp_ajax_fbsseop_post_cleanup_purge', array( 'FBS_Secure_Optimize_Scanner_Forensics', 'ajax_post_cleanup_purge' ) );
	}

	/**
	 * Return heuristic regex signatures keyed by human-readable threat label.
	 *
	 * @since 2.0.0
	 * @return array<string, string>
	 */
	public static function get_heuristic_signatures() {
		$eval_kw = 'ev' . 'al';
		$b64_kw  = 'base64_' . 'decode';

		$signatures = array(
			'Obfuscated Eval Payload'                    => '/\b' . $eval_kw . '\s*\(\s*(?:' . $b64_kw . '|gzinflate|gzuncompress|str_rot13)/i',
			'Direct Variable Eval'                       => '/\b' . $eval_kw . '\s*\(\s*\$/i',
			'OS Command Execution (shell_exec/system)'   => '/\b(?:shell_exec|system|passthru|popen|proc_open)\s*\(\s*\$/i',
			'Obfuscation Chain (gzinflate/str_rot13)'    => '/\b(?:gzinflate|str_rot13)\s*\(\s*' . $b64_kw . '/i',
			'Encoded Request Backdoor'                   => '/\b' . $b64_kw . '\s*\(\s*(?:\$_(?:POST|GET|REQUEST|COOKIE)|[\'"][A-Za-z0-9+\/=]{140,})/i',
			'Hidden Admin Filter (pre_user_query)'       => '/add_(?:action|filter)\s*\(\s*[\'"]pre_user_query[\'"][\s\S]{0,300}(?:user_login|ID\s*!=|NOT\s+IN)/i',
			'Direct Capability Injection (usermeta)'     => '/\bupdate_user_meta\s*\([^;]*capabilities[^;]*administrator/i',
			'Unauthorized Admin Creation'                => '/\b(?:wp_insert_user|wp_create_user)\s*\([\s\S]{0,250}administrator/i',
		);

		/**
		 * Filters scanner heuristic regex rules.
		 *
		 * @since 2.0.0
		 * @param array<string, string> $signatures Regex patterns keyed by threat label.
		 */
		return apply_filters( 'fbsseop_scanner_signatures', $signatures );
	}

	/**
	 * Execute full 6-layer diagnostic scan and return structured findings array.
	 *
	 * @since 2.0.0
	 * @return array<string, mixed>
	 */
	public function execute_full_scan() {
		$findings   = array();
		$mu_plugins = array();

		// 1. Root Directory Unknown PHP File Detection.
		$allowed_root = apply_filters( 'fbsseop_allowed_root_files', self::ALLOWED_ROOT_PHP_FILES );
		$root_php     = glob( ABSPATH . '*.php' );
		if ( is_array( $root_php ) ) {
			foreach ( $root_php as $full_path ) {
				$basename = basename( $full_path );
				if ( ! in_array( $basename, $allowed_root, true ) && ! self::is_verified_legitimate_root_php( $full_path, $basename ) ) {
					$size       = is_readable( $full_path ) ? (int) filesize( $full_path ) : 0;
					$mtime      = is_readable( $full_path ) ? (int) filemtime( $full_path ) : 0;
					$findings[] = array(
						'type'       => 'root_unknown',
						'severity'   => 'CRITICAL',
						'file'       => $basename,
						'size'       => $size,
						'mtime'      => $mtime ? gmdate( 'Y-m-d H:i:s', $mtime ) : 'Unknown',
						'reason'     => __( 'Unknown PHP file in WordPress root directory', 'fbs-secure-optimize' ),
						'can_delete' => true,
						'preview'    => self::get_file_snippet( $full_path, 400 ),
					);
				}
			}
		}

		// 2. Official WordPress.org Core MD5 Checksum Verification.
		global $wp_version;
		$checksum_url = 'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( (string) $wp_version );
		$response     = wp_remote_get( $checksum_url, array( 'timeout' => 8 ) );
		if ( ! is_wp_error( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $data['checksums'][ $wp_version ] ) && is_array( $data['checksums'][ $wp_version ] ) ) {
				$critical_files = array(
					'index.php',
					'wp-settings.php',
					'wp-load.php',
					'wp-blog-header.php',
					'wp-cron.php',
					'wp-login.php',
					'wp-mail.php',
					'wp-comments-post.php',
					'xmlrpc.php',
				);
				foreach ( $critical_files as $cfile ) {
					$local_path = ABSPATH . $cfile;
					if ( is_file( $local_path ) && is_readable( $local_path ) && isset( $data['checksums'][ $wp_version ][ $cfile ] ) ) {
						$official_hash = $data['checksums'][ $wp_version ][ $cfile ];
						$local_hash    = md5_file( $local_path );
						if ( $official_hash !== $local_hash ) {
							// Normalize Windows CRLF line endings before flagging mismatch.
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local core file checksum verification.
							$normalized = str_replace( "\r\n", "\n", (string) file_get_contents( $local_path ) );
							if ( md5( $normalized ) !== $official_hash ) {
								$findings[] = array(
									'type'       => 'core_modified',
									'severity'   => 'HIGH',
									'file'       => $cfile,
									'size'       => (int) filesize( $local_path ),
									'mtime'      => gmdate( 'Y-m-d H:i:s', (int) filemtime( $local_path ) ),
									/* translators: %s: Current WordPress version. */
									'reason'     => sprintf( __( 'Modified WordPress core file (MD5 mismatch vs WP %s)', 'fbs-secure-optimize' ), $wp_version ),
									'can_delete' => false,
									'preview'    => self::get_file_snippet( $local_path, 400 ),
								);
							}
						}
					}
				}
			}
		}

		// 3. Zero-Tolerance wp-content/uploads/ PHP Detection.
		$upload_dir_info = wp_upload_dir();
		$uploads_basedir = ! empty( $upload_dir_info['basedir'] ) ? $upload_dir_info['basedir'] : WP_CONTENT_DIR . '/uploads';
		if ( is_dir( $uploads_basedir ) ) {
			$findings = array_merge( $findings, $this->scan_uploads_directory( $uploads_basedir ) );
		}

		// 4. MU-Plugins Audit.
		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( is_dir( $mu_dir ) ) {
			$mu_plugins = $this->audit_mu_plugins( $mu_dir );
			foreach ( $mu_plugins as $mu_item ) {
				if ( ! empty( $mu_item['matched_rules'] ) ) {
					$findings[] = array(
						'type'       => 'mu_plugin_suspicious',
						'severity'   => 'CRITICAL',
						'file'       => $mu_item['file'],
						'size'       => $mu_item['size'],
						'mtime'      => $mu_item['mtime'],
						'reason'     => 'Suspicious MU-Plugin (' . implode( ', ', $mu_item['matched_rules'] ) . ')',
						'can_delete' => true,
						'preview'    => $mu_item['preview'],
					);
				}
			}
		}

		// 5. Heuristic Signature Scan on Plugins & Themes (with Official WP.org Checksum Verification).
		$signatures           = self::get_heuristic_signatures();
		$scanned_count        = 0;
		$verified_clean_count = 0;
		foreach ( array( WP_CONTENT_DIR . '/plugins', WP_CONTENT_DIR . '/themes' ) as $scan_dir ) {
			if ( is_dir( $scan_dir ) ) {
				$findings = array_merge( $findings, $this->scan_directory_heuristics( $scan_dir, $signatures, $scanned_count, $verified_clean_count ) );
			}
		}

		// 5b. Active & Parent Theme Official Checksum & Rootkit/Spam Template Audit.
		$theme_findings = FBS_Secure_Optimize_Scanner_Forensics::audit_active_and_parent_themes();
		if ( ! empty( $theme_findings ) ) {
			$existing_files = array();
			foreach ( $findings as $f_item ) {
				if ( ! empty( $f_item['file'] ) ) {
					$existing_files[ $f_item['file'] ] = true;
				}
			}
			foreach ( $theme_findings as $tf_item ) {
				if ( empty( $existing_files[ $tf_item['file'] ] ) ) {
					$findings[]                          = $tf_item;
					$existing_files[ $tf_item['file'] ] = true;
				}
			}
		}

		// 6. Deep Database Code Snippets, CPTs, Options & WP-Cron Persistence Audit.
		$deep_db         = FBS_Secure_Optimize_Scanner_Forensics::scan_deep_database();
		$db_threats      = $deep_db['threats'];
		$active_snippets = $deep_db['active_snippets'];

		// 7. Hidden/Rogue Administrator, 6-Hook Reflection & Application Passwords Audit.
		$hidden_audit                      = self::detect_hidden_users();
		$hook_app_audit                    = FBS_Secure_Optimize_Scanner_Forensics::audit_user_hooks_and_app_passwords();
		$suspicious_hooks                  = $hook_app_audit['suspicious_hooks'];
		$app_passwords                     = $hook_app_audit['app_passwords'];
		$hidden_audit['suspicious_hooks']  = $suspicious_hooks;

		$critical_hooks = 0;
		foreach ( $suspicious_hooks as $shook ) {
			if ( ! empty( $shook['is_critical'] ) ) {
				$critical_hooks++;
			}
		}

		// 8. Front-End Live DOM & Googlebot Cloaked SEO Spam Loopback Scanner.
		$frontend_threats = FBS_Secure_Optimize_Scanner_Forensics::scan_frontend_cloaking_and_spam();

		/**
		 * Allows FBS Secure Optimize Pro to append User Guard privilege-escalation violations or auto-quarantine results.
		 *
		 * @since 2.0.0
		 * @param array<int, array<string, mixed>> $user_violations Detected unauthorized user accounts.
		 */
		$user_violations = apply_filters( 'fbsseop_scanner_user_violations', array() );

		$total_issues = count( $findings )
			+ count( $db_threats )
			+ count( $frontend_threats )
			+ count( $user_violations )
			+ ( ! empty( $hidden_audit['hidden_users'] ) ? count( $hidden_audit['hidden_users'] ) : 0 )
			+ $critical_hooks;

		$summary = array(
			'scanned_at'           => current_time( 'mysql' ),
			'scanned_files'        => $scanned_count,
			'verified_clean_files' => $verified_clean_count,
			'total_issues'         => $total_issues,
		);
		update_option( 'fbsseop_last_scan_summary', $summary, false );

		return array(
			'scanned_at'           => $summary['scanned_at'],
			'scanned_files'        => $scanned_count,
			'verified_clean_files' => $verified_clean_count,
			'total_issues'         => $total_issues,
			'findings'             => $findings,
			'mu_plugins'           => $mu_plugins,
			'db_threats'           => $db_threats,
			'active_snippets'      => $active_snippets,
			'frontend_threats'     => $frontend_threats,
			'hidden_audit'         => $hidden_audit,
			'app_passwords'        => $app_passwords,
			'user_violations'      => $user_violations,
			'is_pro'               => FBS_Secure_Optimize_Controller::is_pro_active(),
		);
	}

	/**
	 * AJAX handler: Run full 6-layer security scan.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_run_scan() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$report = $this->execute_full_scan();

		$report['message'] = 0 === (int) $report['total_issues']
			? __( '6-Layer Deep Scan complete! Zero backdoors, unauthorized PHP files, or hidden administrators detected.', 'fbs-secure-optimize' )
			: sprintf(
				/* translators: %d: Number of detected security issues. */
				__( 'Deep scan finished: %d security issue(s) flagged for review.', 'fbs-secure-optimize' ),
				(int) $report['total_issues']
			);

		wp_send_json_success( $report );
	}

	/**
	 * AJAX handler: Safely preview code snippet of a flagged file inside ABSPATH.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_preview_file() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$rel_file  = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
		$real_path = self::resolve_safe_path( $rel_file );

		if ( ! $real_path || ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found or unreadable.', 'fbs-secure-optimize' ) ) );
		}

		$size = (int) filesize( $real_path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local flagged file preview capped at 8KB.
		$content = 0 === $size ? '[0-byte empty file]' : (string) file_get_contents( $real_path, false, null, 0, 8192 );

		wp_send_json_success(
			array(
				'file'    => $rel_file,
				'size'    => $size,
				'mtime'   => gmdate( 'Y-m-d H:i:s', (int) filemtime( $real_path ) ),
				'content' => $content,
			)
		);
	}

	/**
	 * Scan wp-content/uploads/ for executable PHP files.
	 *
	 * @since 2.0.0
	 * @param string $dir Uploads base directory.
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_uploads_directory( $dir ) {
		$results    = array();
		$extensions = array( 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar' );

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $file_info ) {
				if ( ! $file_info->isFile() ) {
					continue;
				}

				$full_path = $file_info->getPathname();
				$filename  = $file_info->getFilename();
				$ext       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

				$is_php_ext = in_array( $ext, $extensions, true ) || (bool) preg_match( '/\.(?:php[0-9]?|phtml|phar)$/i', $filename );
				if ( ! $is_php_ext ) {
					continue;
				}

				if ( 'index.php' === strtolower( $filename ) && self::is_benign_index_php( $full_path ) ) {
					continue;
				}

				$rel_path = self::normalize_rel_path( $full_path );
				if ( self::is_verified_legitimate_uploads_php( $full_path, $rel_path, $filename ) ) {
					continue;
				}

				$results[] = array(
					'type'       => 'uploads_php',
					'severity'   => 'CRITICAL',
					'file'       => $rel_path,
					'size'       => (int) $file_info->getSize(),
					'mtime'      => gmdate( 'Y-m-d H:i:s', (int) $file_info->getMTime() ),
					'reason'     => __( 'Executable PHP file inside wp-content/uploads/', 'fbs-secure-optimize' ),
					'can_delete' => true,
					'preview'    => self::get_file_snippet( $full_path, 400 ),
				);
			}
		} catch ( Exception $e ) {
			FBS_Secure_Optimize_Controller::log( 'Uploads scan exception: ' . $e->getMessage(), 'warning' );
		}

		return $results;
	}

	/**
	 * Verify whether an index.php stub contains only comments/whitespace ("Silence is golden").
	 *
	 * @since 2.0.0
	 * @param string $path File path.
	 * @return bool
	 */
	public static function is_benign_index_php( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$size = filesize( $path );
		if ( false === $size || $size > 120 ) {
			return false;
		}
		if ( 0 === $size ) {
			return true;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local index.php check.
		$content  = (string) file_get_contents( $path );
		$stripped = preg_replace( '/<\?(?:php)?|\?>|\/\/[^\r\n]*|\/\*[\s\S]*?\*\/|\s+/i', '', $content );
		return '' === $stripped;
	}

	/**
	 * Check whether PHP content contains any dangerous execution, obfuscation, or user-input superglobal vectors.
	 *
	 * @since 2.2.0
	 * @param string $content Raw PHP file content.
	 * @return bool True if suspicious vectors are present.
	 */
	private static function has_suspicious_php_vectors( $content ) {
		$eval_kw = 'ev' . 'al';
		$b64_kw  = 'base64_' . 'decode';

		$pattern = '/\b(?:' . $eval_kw . '|' . $b64_kw . '|gzinflate|gzuncompress|str_rot13|shell_exec|system|passthru|exec|popen|proc_open|pcntl_exec|assert|create_function|move_uploaded_file|fsockopen|curl_exec)\s*\(|\$_(?:GET|POST|REQUEST|FILES|COOKIE)\b|php:\/\/input/i';

		return (bool) preg_match( $pattern, $content );
	}

	/**
	 * Content-verify well-known legitimate firewall bootstrap files in ABSPATH (such as wordfence-waf.php,
	 * aios-bootstrap.php, and malcare-waf.php) so they are not flagged as unknown root shells unless tampered with.
	 *
	 * @since 2.2.0
	 * @param string $full_path Full path in ABSPATH.
	 * @param string $basename  Filename.
	 * @return bool
	 */
	public static function is_verified_legitimate_root_php( $full_path, $basename ) {
		$known_root_bootstraps = array(
			'wordfence-waf.php'  => array( 'wordfence/waf/bootstrap.php', 'WFWAF_' ),
			'aios-bootstrap.php' => array( 'all-in-one-wp-security', 'AIOS_' ),
			'malcare-waf.php'    => array( 'malcare-security', 'MCWAF' ),
		);

		$lower_name = strtolower( $basename );
		if ( ! isset( $known_root_bootstraps[ $lower_name ] ) || ! is_file( $full_path ) || ! is_readable( $full_path ) ) {
			return false;
		}

		$size = filesize( $full_path );
		if ( false === $size || $size <= 0 || $size > 8192 ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Content verification of root WAF bootstrap.
		$content = file_get_contents( $full_path );
		if ( ! is_string( $content ) || '' === $content || self::has_suspicious_php_vectors( $content ) ) {
			return false;
		}

		foreach ( $known_root_bootstraps[ $lower_name ] as $marker ) {
			if ( false !== strpos( $content, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Content-verify legitimate compiled template caches and guarded log stubs inside wp-content/uploads/
	 * (such as Timber/Twig compiled templates in timber-cache/, Acorn/Blade compiled views, and exit-guarded logs)
	 * while still flagging any tampered or spoofed webshell as CRITICAL.
	 *
	 * @since 2.2.0
	 * @param string $full_path Full filesystem path.
	 * @param string $rel_path  ABSPATH-relative forward-slash path.
	 * @param string $filename  Base filename.
	 * @return bool
	 */
	public static function is_verified_legitimate_uploads_php( $full_path, $rel_path, $filename ) {
		if ( ! is_file( $full_path ) || ! is_readable( $full_path ) ) {
			return false;
		}

		$size = filesize( $full_path );
		if ( false === $size || $size <= 0 || $size > 524288 ) {
			return false;
		}

		$rel_lc = strtolower( $rel_path );

		$is_candidate_dir = (
			false !== strpos( $rel_lc, '/timber-cache/' ) ||
			false !== strpos( $rel_lc, '/twig/' ) ||
			false !== strpos( $rel_lc, '/cache/' ) ||
			false !== strpos( $rel_lc, '/sucuri/' ) ||
			false !== strpos( $rel_lc, '/wflogs/' )
		);

		if ( ! $is_candidate_dir ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Strict content verification of uploads cache file.
		$content = file_get_contents( $full_path );
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		// Zero-tolerance gate: reject immediately if any execution, obfuscation, or superglobal input exists.
		if ( self::has_suspicious_php_vectors( $content ) ) {
			return false;
		}

		// 1. Timber / Twig compiled template class (e.g. wp-content/uploads/timber-cache/d0/d0bbde54...php).
		if (
			( false !== strpos( $rel_lc, '/timber-cache/' ) || false !== strpos( $rel_lc, '/twig/' ) ) &&
			preg_match( '/^[a-f0-9]{16,64}\.php$/i', $filename ) &&
			false !== strpos( $content, 'class __TwigTemplate_' ) &&
			( false !== strpos( $content, 'use Twig\Template;' ) || false !== strpos( $content, 'Twig_Template' ) )
		) {
			return true;
		}

		// 2. Roots Sage / Acorn Blade compiled view cache inside wp-content/uploads/cache/.
		if (
			false !== strpos( $rel_lc, '/cache/' ) &&
			preg_match( '/^[a-f0-9]{16,64}\.php$/i', $filename ) &&
			false !== strpos( $content, '/**PATH ' ) &&
			false !== strpos( $content, 'ENDPATH**/' )
		) {
			return true;
		}

		// 3. Sucuri / security log files guarded on line 1 by an immediate PHP exit(0) statement with no executable PHP afterwards.
		if (
			false !== strpos( $rel_lc, '/sucuri/' ) &&
			preg_match( '/^\s*<\?php\s+(?:exit|die)\s*(?:\(\s*0?\s*\))?\s*;\s*\?' . '>/i', $content ) &&
			1 === substr_count( strtolower( $content ), '<?php' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Audit files inside wp-content/mu-plugins/.
	 *
	 * @since 2.0.0
	 * @param string $mu_dir MU-plugins path.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_mu_plugins( $mu_dir ) {
		$items      = array();
		$signatures = self::get_heuristic_signatures();
		$entries    = is_readable( $mu_dir ) ? scandir( $mu_dir ) : false;

		if ( ! is_array( $entries ) ) {
			return $items;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full_path = $mu_dir . DIRECTORY_SEPARATOR . $entry;
			if ( is_file( $full_path ) && is_readable( $full_path ) ) {
				if ( 'index.php' === strtolower( $entry ) && self::is_benign_index_php( $full_path ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local mu-plugin audit.
				$content       = (string) file_get_contents( $full_path );
				$matched_rules = array();
				foreach ( $signatures as $label => $pattern ) {
					if ( preg_match( $pattern, $content ) ) {
						$matched_rules[] = $label;
					}
				}
				$items[] = array(
					'file'          => self::normalize_rel_path( $full_path ),
					'name'          => $entry,
					'size'          => (int) filesize( $full_path ),
					'mtime'         => gmdate( 'Y-m-d H:i:s', (int) filemtime( $full_path ) ),
					'matched_rules' => $matched_rules,
					'preview'       => self::get_file_snippet( $full_path, 400 ),
				);
			}
		}

		return $items;
	}

	/**
	 * Recursively scan plugins and themes for backdoor signatures, verifying matched
	 * WordPress.org plugin files against official cryptographic checksums to eliminate vendor false positives.
	 *
	 * @since 2.0.0
	 * @param string                $dir                  Directory path.
	 * @param array<string, string> $signatures           Heuristic regex rules.
	 * @param int                   $scanned_count        Counter passed by reference.
	 * @param int                   $verified_clean_count Verified official WP.org vendor files counter passed by reference.
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_directory_heuristics( $dir, $signatures, &$scanned_count, &$verified_clean_count = 0 ) {
		$infected = array();

		if ( $scanned_count > 12000 || ! is_readable( $dir ) ) {
			return $infected;
		}

		$files = scandir( $dir );
		if ( ! is_array( $files ) ) {
			return $infected;
		}

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$path       = $dir . DIRECTORY_SEPARATOR . $file;
			$normalized = str_replace( '\\', '/', $path );

			// Skip security scanner plugins to prevent self-matching regex signature strings.
			if (
				false !== strpos( $normalized, '/plugins/fbs-secure-optimize' ) ||
				false !== strpos( $normalized, '/plugins/fbs-sentinel' ) ||
				false !== strpos( $normalized, '/plugins/wordfence' )
			) {
				continue;
			}

			if ( is_dir( $path ) ) {
				$infected = array_merge( $infected, $this->scan_directory_heuristics( $path, $signatures, $scanned_count, $verified_clean_count ) );
			} elseif ( is_file( $path ) && is_readable( $path ) ) {
				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, array( 'php', 'phtml', 'php5', 'phar' ), true ) ) {
					$scanned_count++;
					$size = filesize( $path );
					if ( false === $size || $size > 2 * 1024 * 1024 ) {
						continue;
					}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local PHP file heuristic scan.
					$content = file_get_contents( $path );
					if ( false !== $content ) {
						foreach ( $signatures as $label => $pattern ) {
							if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
								if ( self::is_known_safe_library_match( $normalized, $label, $content ) ) {
									continue;
								}

								if ( false !== strpos( $normalized, '/plugins/' ) && FBS_Secure_Optimize_Scanner_Forensics::is_verified_official_wporg_plugin_file( $path ) ) {
									$verified_clean_count++;
									break;
								}

								$offset  = isset( $matches[0][1] ) ? (int) $matches[0][1] : 0;
								$start   = max( 0, $offset - 100 );
								$snippet = substr( $content, $start, 350 );

								$infected[] = array(
									'type'       => 'heuristic_match',
									'severity'   => 'HIGH',
									'file'       => self::normalize_rel_path( $path ),
									'size'       => (int) $size,
									'mtime'      => gmdate( 'Y-m-d H:i:s', (int) filemtime( $path ) ),
									'reason'     => 'Matched Signature: ' . $label,
									'can_delete' => true,
									'preview'    => $snippet,
								);
								break;
							}
						}
					}
				}
			}
		}

		return $infected;
	}

	/**
	 * Verify whether a heuristic match belongs to a known-safe Composer/vendor library file.
	 *
	 * Prevents false positives (e.g. Monolog\Handler\ProcessHandler::proc_open) while still
	 * rejecting tampered files that inject superglobal input or other malware signatures.
	 *
	 * @since 2.1.1
	 * @param string $normalized_path Forward-slash normalized absolute file path.
	 * @param string $label           Matched heuristic signature label.
	 * @param string $content         Raw PHP file contents.
	 * @return bool
	 */
	private static function is_known_safe_library_match( $normalized_path, $label, $content ) {
		$is_safe = false;

		if ( 'OS Command Execution (shell_exec/system)' === $label && ! preg_match( '/\$_(?:GET|POST|REQUEST|COOKIE)\b/', $content ) ) {
			$known_libraries = array(
				'/monolog/monolog/src/Monolog/Handler/ProcessHandler.php' => array( 'namespace Monolog\Handler;', 'class ProcessHandler' ),
				'/symfony/process/Process.php'                            => array( 'namespace Symfony\Component\Process;', 'class Process' ),
				'/symfony/process/Pipes/UnixPipes.php'                    => array( 'namespace Symfony\Component\Process\Pipes;', 'class UnixPipes' ),
				'/symfony/process/Pipes/WindowsPipes.php'                 => array( 'namespace Symfony\Component\Process\Pipes;', 'class WindowsPipes' ),
				'/composer/composer/src/Composer/Util/ProcessExecutor.php' => array( 'namespace Composer\Util;', 'class ProcessExecutor' ),
			);

			$lower_path = strtolower( $normalized_path );
			foreach ( $known_libraries as $path_suffix => $markers ) {
				if ( substr( $lower_path, -strlen( $path_suffix ) ) === strtolower( $path_suffix ) ) {
					$has_all_markers = true;
					foreach ( $markers as $marker ) {
						if ( false === strpos( $content, $marker ) ) {
							$has_all_markers = false;
							break;
						}
					}
					if ( $has_all_markers ) {
						$is_safe = true;
						break;
					}
				}
			}
		}

		/**
		 * Filters whether a matched heuristic finding should be treated as a known-safe library match.
		 *
		 * @since 2.1.1
		 * @param bool   $is_safe         Whether to suppress this specific signature match.
		 * @param string $normalized_path Normalized file path.
		 * @param string $label           Matched heuristic signature label.
		 * @param string $content         File contents.
		 */
		return (bool) apply_filters( 'fbsseop_scanner_is_safe_match', $is_safe, $normalized_path, $label, $content );
	}

	/**
	 * Anti-Hidden-Admin & Rogue-User Anomaly Detector: compares raw SQL elevated usermeta against
	 * get_users() / WP_User_Query, flags empty/invalid emails, default backdoor logins, and invalid
	 * registration timestamps, and inspects active pre_user_query hooks via Reflection.
	 *
	 * @since 2.0.0
	 * @return array<string, mixed>
	 */
	public static function detect_hidden_users() {
		global $wpdb, $wp_filter;

		$cap_key    = $wpdb->prefix . 'capabilities';
		$current_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct SQL comparison to uncover hidden pre_user_query accounts.
		$raw_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.user_login, u.user_email, u.user_registered, um.meta_value
				 FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				 WHERE um.meta_key = %s
				   AND (um.meta_value LIKE %s OR um.meta_value LIKE %s OR um.meta_value LIKE %s)",
				$cap_key,
				'%administrator%',
				'%manage_options%',
				'%activate_plugins%'
			)
		);

		$raw_admin_map = array();
		if ( is_array( $raw_rows ) ) {
			foreach ( $raw_rows as $row ) {
				$caps = maybe_unserialize( $row->meta_value );
				if ( is_array( $caps ) && ( ! empty( $caps['administrator'] ) || ! empty( $caps['manage_options'] ) || ! empty( $caps['activate_plugins'] ) ) ) {
					$raw_admin_map[ (int) $row->ID ] = array(
						'ID'              => (int) $row->ID,
						'user_login'      => (string) $row->user_login,
						'user_email'      => (string) $row->user_email,
						'user_registered' => (string) $row->user_registered,
						'caps'            => array_keys( array_filter( $caps ) ),
						'is_current'      => ( (int) $row->ID === $current_id ),
					);
				}
			}
		}

		$get_users_ids = array_map(
			'intval',
			(array) get_users(
				array(
					'role'   => 'administrator',
					'fields' => 'ID',
				)
			)
		);

		$user_query  = new WP_User_Query(
			array(
				'role'   => 'Administrator',
				'fields' => 'ID',
			)
		);
		$queried_ids = array_map( 'intval', (array) $user_query->get_results() );

		$all_query   = new WP_User_Query(
			array(
				'include' => empty( $raw_admin_map ) ? array( 0 ) : array_keys( $raw_admin_map ),
				'fields'  => 'ID',
			)
		);
		$visible_ids = array_map( 'intval', (array) $all_query->get_results() );

		$suspicious_logins = array(
			'wp',
			'admin_backup',
			'wp_support',
			'support_user',
			'wordpress',
			'system',
			'testadmin',
			'sysadmin',
			'wp_admin',
			'backup_admin',
		);

		$hidden_users = array();
		foreach ( $raw_admin_map as $uid => $uinfo ) {
			$reasons        = array();
			$has_admin_role = in_array( 'administrator', $uinfo['caps'], true );
			$email_trimmed  = trim( $uinfo['user_email'] );
			$login_lc       = strtolower( trim( $uinfo['user_login'] ) );
			$reg_str        = trim( $uinfo['user_registered'] );
			$reg_ts         = '' !== $reg_str ? strtotime( $reg_str ) : false;

			if ( ! in_array( $uid, $visible_ids, true ) || ( $has_admin_role && ( ! in_array( $uid, $queried_ids, true ) || ! in_array( $uid, $get_users_ids, true ) ) ) ) {
				$reasons[] = __( 'Hidden from WP_User_Query / get_users() via pre_user_query filter', 'fbs-secure-optimize' );
			}

			if ( ! $has_admin_role ) {
				$reasons[] = __( 'Stealth capability injection (elevated capabilities without administrator role)', 'fbs-secure-optimize' );
			}

			if ( '' === $email_trimmed || ! is_email( $email_trimmed ) ) {
				$reasons[] = __( 'Rogue admin anomaly: empty or invalid email address', 'fbs-secure-optimize' );
			}

			if ( in_array( $login_lc, $suspicious_logins, true ) && ! $uinfo['is_current'] ) {
				$reasons[] = sprintf(
					/* translators: %s: Suspicious administrator login name. */
					__( 'Suspicious backdoor login name ("%s")', 'fbs-secure-optimize' ),
					$uinfo['user_login']
				);
			}

			if ( '' === $reg_str || '0000-00-00 00:00:00' === $reg_str || false === $reg_ts || $reg_ts > ( time() + DAY_IN_SECONDS ) ) {
				$reasons[] = __( 'Invalid or forged user_registered timestamp', 'fbs-secure-optimize' );
			}

			if ( ! empty( $reasons ) ) {
				$uinfo['hidden_type'] = implode( ' • ', $reasons );
				$hidden_users[ $uid ] = $uinfo;
			}
		}

		$hooked_callbacks = array();
		if ( isset( $wp_filter['pre_user_query'] ) && $wp_filter['pre_user_query'] instanceof WP_Hook ) {
			foreach ( $wp_filter['pre_user_query']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $cb_data ) {
					$desc               = self::describe_callable( $cb_data['function'] );
					$hooked_callbacks[] = array(
						'priority' => (int) $priority,
						'callback' => $desc['label'],
						'file'     => $desc['file'],
						'line'     => $desc['line'],
					);
				}
			}
		}

		return array(
			'raw_admin_count'   => count( $raw_admin_map ),
			'query_admin_count' => count( $queried_ids ),
			'hidden_users'      => array_values( $hidden_users ),
			'hooked_callbacks'  => $hooked_callbacks,
		);
	}

	/**
	 * Inspect a hooked callable via Reflection to report its source file and line.
	 *
	 * @since 2.0.0
	 * @param mixed $callable Hooked function, method, or closure.
	 * @return array<string, mixed>
	 */
	public static function describe_callable( $callable ) {
		$info = array(
			'label' => 'Callback',
			'file'  => '',
			'line'  => 0,
		);

		try {
			if ( is_string( $callable ) && function_exists( $callable ) ) {
				$ref           = new ReflectionFunction( $callable );
				$info['label'] = $callable . '()';
				$info['file']  = self::normalize_rel_path( (string) $ref->getFileName() );
				$info['line']  = (int) $ref->getStartLine();
			} elseif ( is_array( $callable ) && 2 === count( $callable ) && method_exists( $callable[0], (string) $callable[1] ) ) {
				$class_name    = is_object( $callable[0] ) ? get_class( $callable[0] ) : (string) $callable[0];
				$method_name   = (string) $callable[1];
				$ref           = new ReflectionMethod( $callable[0], $method_name );
				$info['label'] = $class_name . '::' . $method_name . '()';
				$info['file']  = self::normalize_rel_path( (string) $ref->getFileName() );
				$info['line']  = (int) $ref->getStartLine();
			} elseif ( $callable instanceof Closure ) {
				$ref           = new ReflectionFunction( $callable );
				$info['file']  = self::normalize_rel_path( (string) $ref->getFileName() );
				$info['line']  = (int) $ref->getStartLine();
				$info['label'] = 'Closure (' . $info['file'] . ':' . $info['line'] . ')';
			}
		} catch ( Exception $e ) {
			$info['label'] = 'Unresolved Callback';
		}

		return $info;
	}

	/**
	 * Convert absolute filesystem path into a clean ABSPATH-relative path.
	 *
	 * @since 2.0.0
	 * @param string $full_path Full path.
	 * @return string
	 */
	public static function normalize_rel_path( $full_path ) {
		$norm_full = str_replace( '\\', '/', $full_path );
		$norm_abs  = str_replace( '\\', '/', ABSPATH );
		if ( 0 === strpos( $norm_full, $norm_abs ) ) {
			return substr( $norm_full, strlen( $norm_abs ) );
		}
		return $norm_full;
	}

	/**
	 * Read short file snippet safely.
	 *
	 * @since 2.0.0
	 * @param string $full_path File path.
	 * @param int    $max_len   Max bytes.
	 * @return string
	 */
	public static function get_file_snippet( $full_path, $max_len = 400 ) {
		if ( ! is_file( $full_path ) || ! is_readable( $full_path ) ) {
			return '';
		}
		$size = (int) filesize( $full_path );
		if ( 0 === $size ) {
			return '[0-byte empty file]';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading bounded snippet of local file.
		return (string) file_get_contents( $full_path, false, null, 0, $max_len );
	}

	/**
	 * Resolve and validate a relative path strictly inside ABSPATH.
	 *
	 * @since 2.0.0
	 * @param string $rel_file Relative path.
	 * @return string|false
	 */
	public static function resolve_safe_path( $rel_file ) {
		$rel_file = ltrim( str_replace( '\\', '/', $rel_file ), '/' );
		if ( '' === $rel_file || false !== strpos( $rel_file, '..' ) ) {
			return false;
		}

		$real_file = realpath( ABSPATH . $rel_file );
		$real_abs  = realpath( ABSPATH );

		if ( false === $real_file || false === $real_abs ) {
			return false;
		}

		$norm_file = str_replace( '\\', '/', $real_file );
		$norm_abs  = rtrim( str_replace( '\\', '/', $real_abs ), '/' ) . '/';

		if ( 0 !== strpos( $norm_file, $norm_abs ) ) {
			return false;
		}

		return $real_file;
	}
}
