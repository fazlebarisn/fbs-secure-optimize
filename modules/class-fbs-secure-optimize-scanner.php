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
				if ( ! in_array( $basename, $allowed_root, true ) ) {
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

		// 5. Heuristic Signature Scan on Plugins & Themes.
		$signatures    = self::get_heuristic_signatures();
		$scanned_count = 0;
		foreach ( array( WP_CONTENT_DIR . '/plugins', WP_CONTENT_DIR . '/themes' ) as $scan_dir ) {
			if ( is_dir( $scan_dir ) ) {
				$findings = array_merge( $findings, $this->scan_directory_heuristics( $scan_dir, $signatures, $scanned_count ) );
			}
		}

		// 6. Database Backdoor & Hidden Administrator Audit.
		$db_threats   = $this->scan_database_for_backdoors();
		$hidden_audit = self::detect_hidden_users();

		/**
		 * Allows FBS Secure Optimize Pro to append User Guard privilege-escalation violations or auto-quarantine results.
		 *
		 * @since 2.0.0
		 * @param array<int, array<string, mixed>> $user_violations Detected unauthorized user accounts.
		 */
		$user_violations = apply_filters( 'fbsseop_scanner_user_violations', array() );

		$total_issues = count( $findings ) + count( $db_threats ) + count( $user_violations ) + ( ! empty( $hidden_audit['hidden_users'] ) ? count( $hidden_audit['hidden_users'] ) : 0 );

		$summary = array(
			'scanned_at'    => current_time( 'mysql' ),
			'scanned_files' => $scanned_count,
			'total_issues'  => $total_issues,
		);
		update_option( 'fbsseop_last_scan_summary', $summary, false );

		return array(
			'scanned_at'      => $summary['scanned_at'],
			'scanned_files'   => $scanned_count,
			'total_issues'    => $total_issues,
			'findings'        => $findings,
			'mu_plugins'      => $mu_plugins,
			'db_threats'      => $db_threats,
			'hidden_audit'    => $hidden_audit,
			'user_violations' => $user_violations,
			'is_pro'          => FBS_Secure_Optimize_Controller::is_pro_active(),
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

				$results[] = array(
					'type'       => 'uploads_php',
					'severity'   => 'CRITICAL',
					'file'       => self::normalize_rel_path( $full_path ),
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
	 * Recursively scan plugins and themes for backdoor signatures.
	 *
	 * @since 2.0.0
	 * @param string                $dir           Directory path.
	 * @param array<string, string> $signatures    Heuristic regex rules.
	 * @param int                   $scanned_count Counter passed by reference.
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_directory_heuristics( $dir, $signatures, &$scanned_count ) {
		$infected = array();

		if ( $scanned_count > 3500 || ! is_readable( $dir ) ) {
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
				$infected = array_merge( $infected, $this->scan_directory_heuristics( $path, $signatures, $scanned_count ) );
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
	 * Inspect database options and Code Snippets tables for injected PHP backdoors.
	 *
	 * @since 2.0.0
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_database_for_backdoors() {
		global $wpdb;
		$threats = array();

		$eval_like = '%ev' . 'al(base64%';
		$gz_like   = '%ev' . 'al(gzinflate%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security audit of wp_options.
		$option_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, SUBSTRING(option_value, 1, 500) AS preview
				 FROM {$wpdb->options}
				 WHERE option_name NOT LIKE %s
				   AND option_name NOT LIKE %s
				   AND option_name NOT LIKE %s
				   AND (
				       option_value LIKE %s
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				   )
				 LIMIT 40",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( 'fbsseop_' ) . '%',
				'%pre_user_query%',
				$eval_like,
				$gz_like
			)
		);

		if ( is_array( $option_rows ) ) {
			foreach ( $option_rows as $opt ) {
				$threats[] = array(
					'table'    => $wpdb->options,
					'row_id'   => (int) $opt->option_id,
					'name'     => (string) $opt->option_name,
					'severity' => 'HIGH',
					'reason'   => __( 'Suspicious backdoor signature in wp_options', 'fbs-secure-optimize' ),
					'preview'  => (string) $opt->preview,
				);
			}
		}

		return $threats;
	}

	/**
	 * Anti-Hidden-Admin Detector: compares raw SQL elevated usermeta against WP_User_Query
	 * and inspects active pre_user_query hooks via Reflection.
	 *
	 * @since 2.0.0
	 * @return array<string, mixed>
	 */
	public static function detect_hidden_users() {
		global $wpdb, $wp_filter;

		$cap_key = $wpdb->prefix . 'capabilities';

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
					);
				}
			}
		}

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

		$hidden_users = array();
		foreach ( $raw_admin_map as $uid => $uinfo ) {
			$has_admin_role = in_array( 'administrator', $uinfo['caps'], true );
			if ( ! in_array( $uid, $visible_ids, true ) || ( $has_admin_role && ! in_array( $uid, $queried_ids, true ) ) ) {
				$uinfo['hidden_type'] = __( 'Hidden from WP_User_Query via pre_user_query filter', 'fbs-secure-optimize' );
				$hidden_users[ $uid ] = $uinfo;
			} elseif ( ! $has_admin_role ) {
				$uinfo['hidden_type'] = __( 'Stealth capability injection (elevated capabilities without administrator role)', 'fbs-secure-optimize' );
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
	private static function describe_callable( $callable ) {
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
