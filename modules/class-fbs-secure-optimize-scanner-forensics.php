<?php
/**
 * Deep Database Rootkit, Cloaking, Checksum & Direct-DB Remediation Forensics Engine.
 *
 * @package FBS_Secure_Optimize
 * @since   2.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extends the Malware Scanner with deep database code-snippet/CPT/cron auditing,
 * 6-hook user-concealment Reflection, Application Passwords auditing, dual-UA
 * front-end cloaked SEO spam detection, official WordPress.org plugin/theme
 * checksum verification, and safe 1-click direct-DB remediation handlers.
 *
 * @since 2.3.0
 */
class FBS_Secure_Optimize_Scanner_Forensics {

	/**
	 * Core WordPress options that must never be deleted via 1-click DB cleanup.
	 *
	 * @var array<int, string>
	 */
	const PROTECTED_CORE_OPTIONS = array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'admin_email',
		'users_can_register',
		'default_role',
		'template',
		'stylesheet',
		'active_plugins',
		'uninstall_plugins',
		'cron',
		'fbsseop_settings',
	);

	/**
	 * Cached installed plugins metadata keyed by plugin folder slug.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private static $installed_plugins_map = null;

	/**
	 * Return high-risk database/snippet/template signatures for rootkits, rogue-user creation,
	 * UI concealment, hidden SEO spam, and backdoor execution.
	 *
	 * @since 2.3.0
	 * @return array<string, array{severity: string, regex: string}>
	 */
	public static function get_database_code_signatures() {
		$eval_kw = 'ev' . 'al';
		$b64_kw  = 'base64_' . 'decode';

		$rules = array(
			'Rogue User Creation / Role Escalation'      => array(
				'severity' => 'CRITICAL',
				'regex'    => '/\b(?:wp_create_user|wp_insert_user|wp_update_user|set_role|add_role|add_cap)\s*\(/i',
			),
			'Hidden Admin / User Table Cloaking Hook'    => array(
				'severity' => 'CRITICAL',
				'regex'    => '/\b(?:pre_user_query|views_users|users_list_table_query_args|load-user-edit\.php|load-users\.php)\b/i',
			),
			'Self-Hiding Admin UI Rootkit'               => array(
				'severity' => 'CRITICAL',
				'regex'    => '/(?:admin_footer|admin_head)[\s\S]{0,600}(?:snippets_unhide|#snippet-|\.wp-list-table|tr\[data-id|display\s*:\s*none)/i',
			),
			'Off-Screen / Hidden SEO Spam Link'          => array(
				'severity' => 'CRITICAL',
				'regex'    => '/(?:(?:left|top|right|text-indent)\s*:\s*-\d{3,}px|font-size\s*:\s*0(?:px)?\b|opacity\s*:\s*0(?:\.0+)?\s*(?:;|!|"|\'))[\s\S]{0,800}<a\s+[^>]*href\s*=\s*[\'"]https?:\/\//i',
			),
			'Hidden Gambling / Pharma SEO Spam'          => array(
				'severity' => 'CRITICAL',
				'regex'    => '/(?:display\s*:\s*none|visibility\s*:\s*hidden|-\d{3,}px)[\s\S]{0,800}\b(?:pokies|casino|sportsbook|betting|jackpot|roulette|viagra|cialis|levitra|payday\s+loan)\b/i',
			),
			'Obfuscated Eval / Payload Execution'        => array(
				'severity' => 'CRITICAL',
				'regex'    => '/\b(?:' . $eval_kw . '|' . $b64_kw . '|gzinflate|gzuncompress|str_rot13|shell_exec|system|passthru|popen|proc_open|assert|create_function)\s*\(/i',
			),
			'JavaScript String.fromCharCode Obfuscation' => array(
				'severity' => 'HIGH',
				'regex'    => '/String\.fromCharCode\s*\(\s*(?:\d+\s*,\s*){4,}/i',
			),
			'Arbitrary File Write / Remote Code Fetch'   => array(
				'severity' => 'HIGH',
				'regex'    => '/\b(?:file_put_contents|move_uploaded_file)\s*\(|(?:wp_remote_get|curl_exec|file_get_contents)\s*\([^)]*https?:\/\/[^)]*\.(?:txt|php|bin|dat)/i',
			),
		);

		/**
		 * Filters database code & snippet malware signatures.
		 *
		 * @since 2.3.0
		 * @param array<string, array{severity: string, regex: string}> $rules Signature map.
		 */
		return apply_filters( 'fbsseop_db_code_signatures', $rules );
	}

	/**
	 * Match a code/HTML string against all database code signatures.
	 *
	 * @since 2.3.0
	 * @param string $code Raw snippet, option, or post content.
	 * @return array{matched: array<int, string>, severity: string}
	 */
	public static function evaluate_code_payload( $code ) {
		if ( ! is_string( $code ) || '' === trim( $code ) ) {
			return array(
				'matched'  => array(),
				'severity' => 'LOW',
			);
		}

		$rules    = self::get_database_code_signatures();
		$matched  = array();
		$severity = 'HIGH';

		foreach ( $rules as $label => $meta ) {
			if ( preg_match( $meta['regex'], $code ) ) {
				$matched[] = $label;
				if ( 'CRITICAL' === $meta['severity'] ) {
					$severity = 'CRITICAL';
				}
			}
		}

		return array(
			'matched'  => $matched,
			'severity' => $severity,
		);
	}

	/**
	 * Inspect published post/page HTML content for executable front-end injections (obfuscated <script>,
	 * malicious event handlers, or off-screen hidden SEO spam links) after stripping educational
	 * <pre>/<code> code blocks so developer/security blog posts do not trigger false positives.
	 *
	 * @since 2.3.0
	 * @param string $html Raw post_content HTML.
	 * @return array{matched: array<int, string>, severity: string}
	 */
	public static function evaluate_post_html_injection( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return array(
				'matched'  => array(),
				'severity' => 'LOW',
			);
		}

		// Strip Gutenberg code blocks, <pre>, <code>, and HTML comments so tutorial code examples never false-flag.
		$cleaned = (string) preg_replace(
			array(
				'/<!--\s*wp:(?:code|preformatted)\b[\s\S]*?\/wp:(?:code|preformatted)\s*-->/i',
				'/<pre\b[^>]*>[\s\S]*?<\/pre>/i',
				'/<code\b[^>]*>[\s\S]*?<\/code>/i',
				'/<!--[\s\S]*?-->/',
			),
			'',
			$html
		);

		if ( '' === trim( $cleaned ) ) {
			return array(
				'matched'  => array(),
				'severity' => 'LOW',
			);
		}

		$eval_kw = 'ev' . 'al';
		$rules   = array(
			'Injected Obfuscated <script> Payload'      => array(
				'severity' => 'CRITICAL',
				'regex'    => '/<script\b[^>]*>[\s\S]*?(?:\b' . $eval_kw . '\s*\(|\batob\s*\(|String\.fromCharCode\s*\(|document\.write\s*\(\s*unescape|(?:\\\\x[0-9a-f]{2}){4,})[\s\S]*?<\/script>/i',
			),
			'Malicious Inline JS Event Handler'         => array(
				'severity' => 'HIGH',
				'regex'    => '/<[a-z0-9]+\b[^>]*\bon(?:error|load|mouseover|focus)\s*=\s*([\'"])[^\'"]*(?:\b' . $eval_kw . '\s*\(|\batob\s*\(|fromCharCode|fetch\s*\(\s*[\'"]https?:\/\/)/i',
			),
			'Off-Screen Hidden SEO Spam Link'           => array(
				'severity' => 'CRITICAL',
				'regex'    => '/<([a-z0-9]+)\b[^>]*\bstyle\s*=\s*([\'"])[^\'"]*(?:(?:left|top|right|text-indent)\s*:\s*-\d{3,}px|font-size\s*:\s*0(?:px)?\b|opacity\s*:\s*0(?:\.0+)?\s*(?:;|!|$))[^\'"]*\2[^>]*>[\s\S]{0,1200}<a\s+[^>]*href\s*=\s*[\'"]https?:\/\//i',
			),
			'Hidden Gambling / Pharma SEO Spam Block'   => array(
				'severity' => 'CRITICAL',
				'regex'    => '/<([a-z0-9]+)\b[^>]*\bstyle\s*=\s*([\'"])[^\'"]*(?:display\s*:\s*none|visibility\s*:\s*hidden|-\d{3,}px)[^\'"]*\2[^>]*>[\s\S]{0,1200}<a\s+[^>]*href\s*=\s*[\'"]https?:\/\/[\s\S]{0,600}\b(?:pokies|casino|sportsbook|betting|jackpot|roulette|viagra|cialis|levitra|payday\s+loan)\b/i',
			),
		);

		$matched  = array();
		$severity = 'HIGH';

		foreach ( $rules as $label => $meta ) {
			if ( preg_match( $meta['regex'], $cleaned ) ) {
				$matched[] = $label;
				if ( 'CRITICAL' === $meta['severity'] ) {
					$severity = 'CRITICAL';
				}
			}
		}

		return array(
			'matched'  => $matched,
			'severity' => $severity,
		);
	}

	/**
	 * Check whether a specific database table exists in MySQL.
	 *
	 * @since 2.3.0
	 * @param string $table_name Exact table name.
	 * @return bool
	 */
	public static function table_exists( $table_name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only schema check during manual security scan.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return ( $found === $table_name );
	}

	/**
	 * Perform comprehensive deep database audit across Code Snippets tables, HFCM, XYZ PHP,
	 * Elementor/WPCode/Woody CPTs + postmeta, post/page spam injections, header/footer options,
	 * and WP-Cron persistence hooks.
	 *
	 * @since 2.3.0
	 * @return array{threats: array<int, array<string, mixed>>, active_snippets: array<int, array<string, mixed>>}
	 */
	public static function scan_deep_database() {
		global $wpdb;

		$threats         = array();
		$active_snippets = array();

		// 1. Code Snippets plugin tables ({prefix}snippets & {base_prefix}ms_snippets) — bypasses UI rootkits like SnCache.
		$snippet_tables = array_unique(
			array(
				$wpdb->prefix . 'snippets',
				$wpdb->base_prefix . 'ms_snippets',
			)
		);

		foreach ( $snippet_tables as $snip_table ) {
			if ( ! self::table_exists( $snip_table ) ) {
				continue;
			}

			$is_ms_snip  = ( $snip_table === $wpdb->base_prefix . 'ms_snippets' ) && ( $wpdb->base_prefix . 'ms_snippets' !== $wpdb->prefix . 'snippets' );
			$source_type = $is_ms_snip ? 'ms_snippets' : 'wp_snippets';

			if ( $is_ms_snip ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct security audit of multisite snippets table.
				$rows = $wpdb->get_results( "SELECT id, name, description, code, tags, scope, active, modified FROM {$wpdb->base_prefix}ms_snippets ORDER BY id DESC LIMIT 500" );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct security audit of site snippets table.
				$rows = $wpdb->get_results( "SELECT id, name, description, code, tags, scope, active, modified FROM {$wpdb->prefix}snippets ORDER BY id DESC LIMIT 500" );
			}
			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$row_id    = (int) $row->id;
				$name      = '' !== trim( (string) $row->name ) ? (string) $row->name : __( '(Untitled Snippet)', 'fbs-secure-optimize' );
				$code      = (string) $row->code;
				$is_active = ! empty( $row->active );
				$scope     = isset( $row->scope ) ? (string) $row->scope : 'global';
				$modified  = isset( $row->modified ) ? (string) $row->modified : '';
				$eval_res  = self::evaluate_code_payload( $code );
				$flagged   = ! empty( $eval_res['matched'] );

				if ( $is_active ) {
					$active_snippets[] = array(
						'source'      => $source_type,
						'table'       => $snip_table,
						'row_id'      => $row_id,
						'name'        => $name,
						'scope'       => $scope,
						'modified'    => $modified,
						'code_len'    => strlen( $code ),
						'is_flagged'  => $flagged,
						'can_disable' => true,
						'can_delete'  => true,
					);
				}

				if ( $flagged ) {
					$status_badge = $is_active ? __( 'ACTIVE', 'fbs-secure-optimize' ) : __( 'INACTIVE', 'fbs-secure-optimize' );
					$threats[]    = array(
						'source'      => $source_type,
						'table'       => $snip_table,
						'row_id'      => $row_id,
						'name'        => sprintf( '%s #%d: "%s" [%s]', $snip_table, $row_id, $name, $status_badge ),
						'severity'    => $is_active ? $eval_res['severity'] : 'HIGH',
						'is_active'   => $is_active,
						'reason'      => implode( ' + ', $eval_res['matched'] ),
						'preview'     => substr( $code, 0, 500 ),
						'can_disable' => $is_active,
						'can_delete'  => true,
					);
				}
			}
		}

		// 2. Header Footer Code Manager ({prefix}hfcm_scripts).
		$hfcm_table = $wpdb->prefix . 'hfcm_scripts';
		if ( self::table_exists( $hfcm_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct security audit of HFCM snippets table.
			$hfcm_rows = $wpdb->get_results( "SELECT script_id, name, snippet, snippet_type, location, status, last_modified_date FROM {$wpdb->prefix}hfcm_scripts ORDER BY script_id DESC LIMIT 300" );
			if ( is_array( $hfcm_rows ) ) {
				foreach ( $hfcm_rows as $hrow ) {
					$hid       = (int) $hrow->script_id;
					$hname     = '' !== trim( (string) $hrow->name ) ? (string) $hrow->name : __( '(HFCM Snippet)', 'fbs-secure-optimize' );
					$hcode     = (string) $hrow->snippet;
					$is_active = ( isset( $hrow->status ) && 'active' === strtolower( (string) $hrow->status ) );
					$eval_res  = self::evaluate_code_payload( $hcode );
					$flagged   = ! empty( $eval_res['matched'] );

					if ( $is_active ) {
						$active_snippets[] = array(
							'source'      => 'hfcm_scripts',
							'table'       => $hfcm_table,
							'row_id'      => $hid,
							'name'        => $hname,
							'scope'       => isset( $hrow->location ) ? (string) $hrow->location : 'header/footer',
							'modified'    => isset( $hrow->last_modified_date ) ? (string) $hrow->last_modified_date : '',
							'code_len'    => strlen( $hcode ),
							'is_flagged'  => $flagged,
							'can_disable' => true,
							'can_delete'  => true,
						);
					}

					if ( $flagged ) {
						$threats[] = array(
							'source'      => 'hfcm_scripts',
							'table'       => $hfcm_table,
							'row_id'      => $hid,
							'name'        => sprintf( 'HFCM #%d: "%s" [%s]', $hid, $hname, $is_active ? 'ACTIVE' : 'INACTIVE' ),
							'severity'    => $is_active ? $eval_res['severity'] : 'HIGH',
							'is_active'   => $is_active,
							'reason'      => implode( ' + ', $eval_res['matched'] ),
							'preview'     => substr( $hcode, 0, 500 ),
							'can_disable' => $is_active,
							'can_delete'  => true,
						);
					}
				}
			}
		}

		// 3. XYZ PHP Code ({prefix}xyz_ips_short_code).
		$xyz_table = $wpdb->prefix . 'xyz_ips_short_code';
		if ( self::table_exists( $xyz_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct security audit of XYZ PHP snippets table.
			$xyz_rows = $wpdb->get_results( "SELECT id, title, content, status FROM {$wpdb->prefix}xyz_ips_short_code ORDER BY id DESC LIMIT 300" );
			if ( is_array( $xyz_rows ) ) {
				foreach ( $xyz_rows as $xrow ) {
					$xid       = (int) $xrow->id;
					$xname     = '' !== trim( (string) $xrow->title ) ? (string) $xrow->title : 'XYZ Snippet';
					$xcode     = (string) $xrow->content;
					$is_active = ( 1 === (int) $xrow->status );
					$eval_res  = self::evaluate_code_payload( $xcode );
					$flagged   = ! empty( $eval_res['matched'] );

					if ( $is_active ) {
						$active_snippets[] = array(
							'source'      => 'xyz_ips',
							'table'       => $xyz_table,
							'row_id'      => $xid,
							'name'        => $xname,
							'scope'       => 'shortcode',
							'modified'    => '',
							'code_len'    => strlen( $xcode ),
							'is_flagged'  => $flagged,
							'can_disable' => true,
							'can_delete'  => true,
						);
					}

					if ( $flagged ) {
						$threats[] = array(
							'source'      => 'xyz_ips',
							'table'       => $xyz_table,
							'row_id'      => $xid,
							'name'        => sprintf( 'XYZ PHP #%d: "%s"', $xid, $xname ),
							'severity'    => $is_active ? $eval_res['severity'] : 'HIGH',
							'is_active'   => $is_active,
							'reason'      => implode( ' + ', $eval_res['matched'] ),
							'preview'     => substr( $xcode, 0, 500 ),
							'can_disable' => $is_active,
							'can_delete'  => true,
						);
					}
				}
			}
		}

		// 4. Elementor Custom Code, WPCode, Woody Snippets & Template CPTs (wp_posts + wp_postmeta).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cpt_rows = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_status, post_content, post_modified
			 FROM {$wpdb->posts}
			 WHERE post_type IN ('elementor_snippet', 'wpcode', 'wbcr-snippets', 'custom_css', 'wp_template', 'wp_template_part')
			   AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
			 ORDER BY ID DESC
			 LIMIT 300"
		);

		if ( is_array( $cpt_rows ) ) {
			foreach ( $cpt_rows as $cpost ) {
				$pid          = (int) $cpost->ID;
				$ptype        = (string) $cpost->post_type;
				$pstatus      = (string) $cpost->post_status;
				$ptitle       = '' !== trim( (string) $cpost->post_title ) ? (string) $cpost->post_title : $ptype . ' #' . $pid;
				$combined_src = (string) $cpost->post_content;

				// Append associated code postmeta (_elementor_code, _wpcode_snippet_code, wbcr_inp_snippet_code).
				$meta_keys = array( '_elementor_code', '_wpcode_snippet_code', 'wbcr_inp_snippet_code' );
				foreach ( $meta_keys as $mkey ) {
					$mval = get_post_meta( $pid, $mkey, true );
					if ( is_string( $mval ) && '' !== $mval ) {
						$combined_src .= "\n" . $mval;
					}
				}

				$is_active   = ( 'publish' === $pstatus );
				$is_snip_cpt = in_array( $ptype, array( 'elementor_snippet', 'wpcode', 'wbcr-snippets' ), true );
				$eval_res    = self::evaluate_code_payload( $combined_src );
				$flagged     = ! empty( $eval_res['matched'] );

				if ( $is_active && $is_snip_cpt ) {
					$active_snippets[] = array(
						'source'      => 'wp_posts',
						'table'       => $wpdb->posts,
						'row_id'      => $pid,
						'name'        => sprintf( '[%s] %s', $ptype, $ptitle ),
						'scope'       => $ptype,
						'modified'    => (string) $cpost->post_modified,
						'code_len'    => strlen( $combined_src ),
						'is_flagged'  => $flagged,
						'can_disable' => true,
						'can_delete'  => true,
					);
				}

				if ( $flagged ) {
					$threats[] = array(
						'source'      => 'wp_posts',
						'table'       => $wpdb->posts,
						'row_id'      => $pid,
						'name'        => sprintf( 'CPT %s #%d: "%s" [%s]', $ptype, $pid, $ptitle, strtoupper( $pstatus ) ),
						'severity'    => $is_active ? $eval_res['severity'] : 'HIGH',
						'is_active'   => $is_active,
						'reason'      => implode( ' + ', $eval_res['matched'] ),
						'preview'     => substr( trim( $combined_src ), 0, 500 ),
						'can_disable' => $is_active && $is_snip_cpt,
						'can_delete'  => $is_snip_cpt,
					);
				}
			}
		}

		// 5. Published Posts & Pages Executable Script / Off-Screen HTML Spam Audit (ignores <pre>/<code> tutorials).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$injected_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_content
				 FROM {$wpdb->posts}
				 WHERE post_type IN ('post', 'page')
				   AND post_status = 'publish'
				   AND (
				       post_content LIKE %s
				       OR post_content LIKE %s
				       OR post_content LIKE %s
				       OR post_content LIKE %s
				       OR post_content LIKE %s
				       OR post_content LIKE %s
				   )
				 LIMIT 80",
				'%-9999px%',
				'%<script%',
				'%fromCharCode%',
				'%onerror=%',
				'%font-size:0%',
				'%font-size: 0%'
			)
		);

		if ( is_array( $injected_posts ) ) {
			foreach ( $injected_posts as $ipost ) {
				$eval_res = self::evaluate_post_html_injection( (string) $ipost->post_content );
				if ( ! empty( $eval_res['matched'] ) ) {
					$threats[] = array(
						'source'      => 'wp_posts_content',
						'table'       => $wpdb->posts,
						'row_id'      => (int) $ipost->ID,
						'name'        => sprintf( '%s #%d: "%s"', ucfirst( (string) $ipost->post_type ), (int) $ipost->ID, (string) $ipost->post_title ),
						'severity'    => $eval_res['severity'],
						'is_active'   => true,
						'reason'      => 'Injected Post HTML/JS: ' . implode( ' + ', $eval_res['matched'] ),
						'preview'     => substr( (string) $ipost->post_content, 0, 500 ),
						'can_disable' => false,
						'can_delete'  => false,
					);
				}
			}
		}

		// 6. Header/Footer Injection Options & Suspicious wp_options Rows.
		$header_footer_opts = array(
			'ihaf_insert_header',
			'ihaf_insert_footer',
			'ihaf_insert_body',
			'wpcode_snippets',
			'elementor_custom_head_scripts',
			'elementor_custom_body_open_scripts',
			'elementor_custom_body_end_scripts',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value
				 FROM {$wpdb->options}
				 WHERE option_name NOT LIKE %s
				   AND option_name NOT LIKE %s
				   AND option_name NOT LIKE %s
				   AND option_name NOT LIKE %s
				   AND (
				       option_name IN ('ihaf_insert_header','ihaf_insert_footer','ihaf_insert_body','wpcode_snippets','elementor_custom_head_scripts','elementor_custom_body_open_scripts','elementor_custom_body_end_scripts')
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				       OR option_value LIKE %s
				   )
				 LIMIT 80",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( 'fbsseop_' ) . '%',
				$wpdb->esc_like( 'wordfence' ) . '%',
				'%pre_user_query%',
				'%views_users%',
				'%' . $wpdb->esc_like( 'ev' . 'al(base64' ) . '%',
				'%' . $wpdb->esc_like( 'ev' . 'al(gzinflate' ) . '%',
				'%-9999px%',
				'%wp_create_user%'
			)
		);

		if ( is_array( $option_rows ) ) {
			foreach ( $option_rows as $opt ) {
				$oname = (string) $opt->option_name;
				$oval  = (string) $opt->option_value;
				if ( '' === trim( $oval ) ) {
					continue;
				}

				$eval_res   = self::evaluate_code_payload( $oval );
				$is_hf_opt  = in_array( $oname, $header_footer_opts, true );
				$can_del_op = ! in_array( $oname, self::PROTECTED_CORE_OPTIONS, true );

				if ( ! empty( $eval_res['matched'] ) ) {
					$threats[] = array(
						'source'      => 'wp_options',
						'table'       => $wpdb->options,
						'row_id'      => (int) $opt->option_id,
						'name'        => 'wp_options: ' . $oname,
						'severity'    => $eval_res['severity'],
						'is_active'   => true,
						'reason'      => implode( ' + ', $eval_res['matched'] ),
						'preview'     => substr( $oval, 0, 500 ),
						'can_disable' => false,
						'can_delete'  => $can_del_op,
					);
				} elseif ( $is_hf_opt && false !== stripos( $oval, '<script' ) ) {
					// Surface active raw header/footer script options in the active snippets inventory for full visibility.
					$active_snippets[] = array(
						'source'      => 'wp_options',
						'table'       => $wpdb->options,
						'row_id'      => (int) $opt->option_id,
						'name'        => 'Option: ' . $oname,
						'scope'       => 'header/footer',
						'modified'    => '',
						'code_len'    => strlen( $oval ),
						'is_flagged'  => false,
						'can_disable' => false,
						'can_delete'  => $can_del_op,
					);
				}
			}
		}

		// 7. WP-Cron Persistence Audit.
		$cron_threats = self::audit_wp_cron();
		if ( ! empty( $cron_threats ) ) {
			$threats = array_merge( $threats, $cron_threats );
		}

		return array(
			'threats'         => $threats,
			'active_snippets' => $active_snippets,
		);
	}

	/**
	 * Audit scheduled WP-Cron hooks strictly for real malware persistence:
	 * - Direct dangerous PHP execution callbacks (eval, assert, shell_exec, system, base64_decode, etc.)
	 * - Database/eval()'d code, Code Snippets, or wp-content/uploads/ callbacks
	 * - Malicious payloads passed inside scheduled WP-Cron event arguments
	 * - Unverified Closure/MU-Plugin callbacks containing backdoor/rootkit signatures
	 *
	 * Does NOT flag normal unattached plugin hooks (many legitimate plugins like WP Rocket and Rank Math
	 * only register cron listeners inside wp_doing_cron()).
	 *
	 * @since 2.3.0
	 * @return array<int, array<string, mixed>>
	 */
	public static function audit_wp_cron() {
		global $wp_filter;

		$cron_array = function_exists( '_get_cron_array' ) ? _get_cron_array() : get_option( 'cron', array() );
		if ( ! is_array( $cron_array ) || empty( $cron_array ) ) {
			return array();
		}

		$core_safe_hooks = array(
			'wp_version_check',
			'wp_update_plugins',
			'wp_update_themes',
			'wp_scheduled_delete',
			'wp_scheduled_auto_draft_delete',
			'delete_expired_transients',
			'wp_privacy_delete_old_export_files',
			'wp_https_detection',
			'wp_site_health_scheduled_check',
			'recovery_mode_clean_expired_keys',
			'do_pings',
			'publish_future_post',
			'wp_update_user_counts',
			'fbsseop_cleanup_database',
			'fbsseop_pro_daily_scan_cron',
		);

		$seen_hooks = array();
		$findings   = array();
		$dangerous  = array( 'eval', 'assert', 'system', 'shell_exec', 'passthru', 'exec', 'popen', 'proc_open', 'base64_decode', 'create_function' );

		foreach ( $cron_array as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) || ! is_numeric( $timestamp ) ) {
				continue;
			}

			foreach ( $hooks as $hook_name => $events ) {
				$hook_str = (string) $hook_name;
				if ( isset( $seen_hooks[ $hook_str ] ) || in_array( $hook_str, $core_safe_hooks, true ) ) {
					continue;
				}
				$seen_hooks[ $hook_str ] = true;

				// 1. Inspect scheduled event arguments for serialized/injected code or remote backdoor payloads.
				if ( is_array( $events ) ) {
					$serialized_args = wp_json_encode( $events );
					if ( is_string( $serialized_args ) && '' !== $serialized_args ) {
						$arg_eval = self::evaluate_code_payload( $serialized_args );
						if ( ! empty( $arg_eval['matched'] ) ) {
							$findings[] = array(
								'source'      => 'wp_cron',
								'table'       => 'wp_options (cron)',
								'row_id'      => 0,
								'hook_name'   => $hook_str,
								'name'        => 'WP-Cron Hook: ' . $hook_str,
								'severity'    => 'CRITICAL',
								'is_active'   => true,
								'reason'      => 'Malicious payload in WP-Cron arguments: ' . implode( ' + ', $arg_eval['matched'] ),
								'preview'     => substr( $serialized_args, 0, 420 ),
								'can_disable' => false,
								'can_delete'  => true,
							);
							continue;
						}
					}
				}

				$has_listeners = isset( $wp_filter[ $hook_str ] ) && ( $wp_filter[ $hook_str ] instanceof WP_Hook ) && ! empty( $wp_filter[ $hook_str ]->callbacks );
				if ( ! $has_listeners ) {
					continue;
				}

				foreach ( $wp_filter[ $hook_str ]->callbacks as $priority => $cbs ) {
					foreach ( $cbs as $cb_item ) {
						$fn       = $cb_item['function'];
						$info     = FBS_Secure_Optimize_Scanner::describe_callable( $fn );
						$rel_file = str_replace( '\\', '/', (string) $info['file'] );
						$file     = strtolower( $rel_file );

						if ( is_string( $fn ) && in_array( strtolower( $fn ), $dangerous, true ) ) {
							$findings[] = array(
								'source'      => 'wp_cron',
								'table'       => 'wp_options (cron)',
								'row_id'      => 0,
								'hook_name'   => $hook_str,
								'name'        => 'WP-Cron Hook: ' . $hook_str,
								'severity'    => 'CRITICAL',
								'is_active'   => true,
								'reason'      => 'WP-Cron executes dangerous PHP function directly: ' . $fn . '()',
								'preview'     => 'Callback: ' . $info['label'],
								'can_disable' => false,
								'can_delete'  => true,
							);
							continue;
						}

						$is_db_or_uploads = (
							false !== strpos( $file, "eval()'d code" ) ||
							false !== strpos( $file, 'code-snippets' ) ||
							false !== strpos( $file, 'wp-content/uploads' )
						);

						if ( $is_db_or_uploads ) {
							$sev        = ( false !== strpos( $file, "eval()'d code" ) || false !== strpos( $file, 'wp-content/uploads' ) ) ? 'CRITICAL' : 'HIGH';
							$findings[] = array(
								'source'      => 'wp_cron',
								'table'       => 'wp_options (cron)',
								'row_id'      => 0,
								'hook_name'   => $hook_str,
								'name'        => 'WP-Cron Hook: ' . $hook_str,
								'severity'    => $sev,
								'is_active'   => true,
								'reason'      => 'Database / uploads WP-Cron persistence callback (' . $info['label'] . ')',
								'preview'     => 'Source: ' . $rel_file . ':' . $info['line'] . ' (Priority ' . (int) $priority . ')',
								'can_disable' => false,
								'can_delete'  => true,
							);
							continue;
						}

						if ( $fn instanceof Closure ) {
							if ( 0 === strpos( $file, 'wp-content/plugins/' ) && self::is_verified_official_wporg_plugin_file( ABSPATH . $rel_file ) ) {
								continue;
							}

							$closure_src = self::get_callable_source_slice( $fn );
							$closure_res = self::evaluate_code_payload( $closure_src );
							$is_mu_cron  = ( 0 === strpos( $file, 'wp-content/mu-plugins/' ) );

							if ( ! empty( $closure_res['matched'] ) || $is_mu_cron ) {
								$findings[] = array(
									'source'      => 'wp_cron',
									'table'       => 'wp_options (cron)',
									'row_id'      => 0,
									'hook_name'   => $hook_str,
									'name'        => 'WP-Cron Hook: ' . $hook_str,
									'severity'    => ! empty( $closure_res['matched'] ) ? $closure_res['severity'] : 'HIGH',
									'is_active'   => true,
									'reason'      => ! empty( $closure_res['matched'] )
										? 'Malicious WP-Cron Closure (' . implode( ' + ', $closure_res['matched'] ) . ')'
										: 'Anonymous WP-Cron Closure inside mu-plugins (' . $info['label'] . ')',
									'preview'     => 'Source: ' . $rel_file . ':' . $info['line'] . ' (Priority ' . (int) $priority . ')',
									'can_disable' => false,
									'can_delete'  => true,
								);
							}
						}
					}
				}
			}
		}

		return $findings;
	}

	/**
	 * Audit all 6 user-concealment & tamper-blocking hooks via Reflection plus Administrator
	 * Application Passwords in wp_usermeta.
	 *
	 * @since 2.3.0
	 * @return array{suspicious_hooks: array<int, array<string, mixed>>, app_passwords: array<int, array<string, mixed>>}
	 */
	public static function audit_user_hooks_and_app_passwords() {
		global $wpdb, $wp_filter;

		$monitored_hooks = array(
			'pre_user_query',
			'views_users',
			'users_list_table_query_args',
			'load-user-edit.php',
			'load-users.php',
			'delete_user',
		);

		$suspicious_hooks = array();

		foreach ( $monitored_hooks as $hook_name ) {
			if ( ! isset( $wp_filter[ $hook_name ] ) || ! ( $wp_filter[ $hook_name ] instanceof WP_Hook ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $cb_data ) {
					$desc     = FBS_Secure_Optimize_Scanner::describe_callable( $cb_data['function'] );
					$rel_file = str_replace( '\\', '/', (string) $desc['file'] );
					$file_lc  = strtolower( $rel_file );

					// Ignore built-in WordPress core hooks in wp-includes/ or wp-admin/.
					if ( 0 === strpos( $file_lc, 'wp-includes/' ) || 0 === strpos( $file_lc, 'wp-admin/' ) ) {
						continue;
					}

					// Ignore our own FBS Secure Optimize / Pro security hooks.
					if ( false !== strpos( $file_lc, 'plugins/fbs-secure-optimize' ) ) {
						continue;
					}

					$is_db_exec = (
						false !== strpos( $file_lc, "eval()'d code" ) ||
						false !== strpos( $file_lc, 'code-snippets' ) ||
						false !== strpos( $file_lc, 'insert-headers-and-footers' ) ||
						false !== strpos( $file_lc, 'wpcode' ) ||
						false !== strpos( $file_lc, 'header-footer-code-manager' ) ||
						false !== strpos( $file_lc, 'woody-code-snippets' ) ||
						false !== strpos( $file_lc, 'xyz-wp-insert-code-snippet' ) ||
						false !== strpos( $file_lc, 'wp-content/uploads' ) ||
						'' === $file_lc
					);

					if ( ! $is_db_exec ) {
						// Suppress unmodified official WordPress.org plugins (e.g. WooCommerce user cleanup callbacks).
						if ( 0 === strpos( $file_lc, 'wp-content/plugins/' ) && self::is_verified_official_wporg_plugin_file( ABSPATH . $rel_file ) ) {
							continue;
						}

						$is_mu_plugin = ( 0 === strpos( $file_lc, 'wp-content/mu-plugins/' ) );
						$fn_source    = self::get_callable_source_slice( $cb_data['function'] );

						// Inspect actual callback body so benign delete_user cleanup methods in custom/offline plugins do not false-flag.
						if ( ! $is_mu_plugin && ! self::is_user_hook_body_suspicious( $hook_name, $fn_source ) ) {
							continue;
						}
					}

					$severity = $is_db_exec ? 'CRITICAL' : 'HIGH';
					$reason   = $is_db_exec
						? sprintf(
							/* translators: %s: WordPress hook name. */
							__( 'CRITICAL: Database-Executed / Cloaking Hook attached to "%s"', 'fbs-secure-optimize' ),
							$hook_name
						)
						: sprintf(
							/* translators: %s: WordPress hook name. */
							__( 'Suspicious user-concealment or tamper-blocking logic attached to "%s"', 'fbs-secure-optimize' ),
							$hook_name
						);

					$suspicious_hooks[] = array(
						'hook'        => $hook_name,
						'priority'    => (int) $priority,
						'callback'    => $desc['label'],
						'file'        => $rel_file,
						'line'        => (int) $desc['line'],
						'severity'    => $severity,
						'is_critical' => $is_db_exec,
						'reason'      => $reason,
					);
				}
			}
		}

		// Audit _application_passwords across all users (highlighting Administrators).
		$app_passwords = array();
		$cap_key       = $wpdb->prefix . 'capabilities';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$app_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT um.user_id, um.meta_value, u.user_login, u.user_email, cap.meta_value AS raw_caps
				 FROM {$wpdb->usermeta} um
				 INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
				 LEFT JOIN {$wpdb->usermeta} cap ON cap.user_id = um.user_id AND cap.meta_key = %s
				 WHERE um.meta_key = '_application_passwords'",
				$cap_key
			)
		);

		if ( is_array( $app_rows ) ) {
			foreach ( $app_rows as $arow ) {
				$items = maybe_unserialize( $arow->meta_value );
				if ( ! is_array( $items ) || empty( $items ) ) {
					continue;
				}

				$caps     = maybe_unserialize( $arow->raw_caps );
				$is_admin = is_array( $caps ) && ( ! empty( $caps['administrator'] ) || ! empty( $caps['manage_options'] ) );

				foreach ( $items as $item ) {
					if ( ! is_array( $item ) || empty( $item['uuid'] ) ) {
						continue;
					}

					$created_ts   = ! empty( $item['created'] ) ? (int) $item['created'] : 0;
					$last_used_ts = ! empty( $item['last_used'] ) ? (int) $item['last_used'] : 0;

					$app_passwords[] = array(
						'user_id'    => (int) $arow->user_id,
						'user_login' => (string) $arow->user_login,
						'user_email' => (string) $arow->user_email,
						'is_admin'   => $is_admin,
						'uuid'       => (string) $item['uuid'],
						'name'       => isset( $item['name'] ) ? (string) $item['name'] : __( '(Unnamed)', 'fbs-secure-optimize' ),
						'created'    => $created_ts > 0 ? gmdate( 'Y-m-d H:i', $created_ts ) : '—',
						'last_used'  => $last_used_ts > 0 ? gmdate( 'Y-m-d H:i', $last_used_ts ) : __( 'Never', 'fbs-secure-optimize' ),
						'last_ip'    => ! empty( $item['last_ip'] ) ? (string) $item['last_ip'] : '—',
					);
				}
			}
		}

		return array(
			'suspicious_hooks' => $suspicious_hooks,
			'app_passwords'    => $app_passwords,
		);
	}

	/**
	 * Extract the PHP source lines of a reflected function, method, or closure on disk.
	 *
	 * @since 2.3.0
	 * @param mixed $callable Hooked PHP callable.
	 * @return string
	 */
	private static function get_callable_source_slice( $callable ) {
		try {
			$ref = null;
			if ( is_string( $callable ) && function_exists( $callable ) ) {
				$ref = new ReflectionFunction( $callable );
			} elseif ( is_array( $callable ) && 2 === count( $callable ) && method_exists( $callable[0], (string) $callable[1] ) ) {
				$ref = new ReflectionMethod( $callable[0], (string) $callable[1] );
			} elseif ( $callable instanceof Closure ) {
				$ref = new ReflectionFunction( $callable );
			}

			if ( ! $ref ) {
				return '';
			}

			$file  = (string) $ref->getFileName();
			$start = (int) $ref->getStartLine();
			$end   = (int) $ref->getEndLine();

			if ( '' === $file || ! is_file( $file ) || ! is_readable( $file ) || $start <= 0 || $end < $start ) {
				return '';
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file -- Bounded local Reflection line slice.
			$lines = file( $file );
			if ( ! is_array( $lines ) ) {
				return '';
			}

			$len = min( 150, ( $end - $start ) + 1 );
			return implode( '', array_slice( $lines, $start - 1, $len ) );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Determine whether a non-WP.org callback's PHP body exhibits user-concealment or tamper-blocking behavior.
	 *
	 * @since 2.3.0
	 * @param string $hook_name WordPress hook name.
	 * @param string $source    Extracted PHP source lines of the callback.
	 * @return bool
	 */
	private static function is_user_hook_body_suspicious( $hook_name, $source ) {
		if ( '' === trim( $source ) ) {
			return true;
		}

		$eval_kw = 'ev' . 'al';
		$b64_kw  = 'base64_' . 'decode';

		// Any obfuscation or rogue-user creation inside a user hook is immediately suspicious.
		if ( preg_match( '/\b(?:' . $eval_kw . '|' . $b64_kw . '|gzinflate|str_rot13|wp_create_user|wp_insert_user)\s*\(/i', $source ) ) {
			return true;
		}

		// On delete_user / load-user-edit.php / load-users.php, normal plugins clean up user metadata; rootkits block edits via wp_die / exit / redirect.
		if ( in_array( $hook_name, array( 'delete_user', 'load-user-edit.php', 'load-users.php' ), true ) ) {
			return (bool) preg_match( '/\b(?:wp_die|die|exit|wp_redirect|wp_safe_redirect)\s*(?:\(|;)|Invalid\s+user/i', $source );
		}

		// On pre_user_query / views_users / users_list_table_query_args, check for exclusion / count-spoofing patterns.
		return (bool) preg_match( '/(?:user_login|NOT\s+IN|!=\s*[\'"]|<>\s*[\'"]|\bexclude\b|preg_replace\s*\(|str_replace\s*\()/i', $source );
	}

	/**
	 * Perform dual HTTP loopback inspection on home_url('/') (Standard Visitor vs Googlebot + Google Referer)
	 * to uncover front-end hidden/off-screen SEO spam links and conditional search-engine cloaking.
	 *
	 * @since 2.3.0
	 * @return array<int, array<string, mixed>>
	 */
	public static function scan_frontend_cloaking_and_spam() {
		$findings = array();
		$home_url = home_url( '/' );
		$host     = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );

		$common_args = array(
			'timeout'     => 6,
			'redirection' => 3,
			'sslverify'   => false,
			'cookies'     => array(),
		);

		$visitor_res = wp_remote_get(
			$home_url,
			array_merge(
				$common_args,
				array(
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
					'headers'    => array( 'Cache-Control' => 'no-cache' ),
				)
			)
		);

		if ( is_wp_error( $visitor_res ) || 200 !== (int) wp_remote_retrieve_response_code( $visitor_res ) ) {
			return $findings;
		}

		$visitor_html = (string) wp_remote_retrieve_body( $visitor_res );

		$bot_res = wp_remote_get(
			$home_url,
			array_merge(
				$common_args,
				array(
					'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
					'headers'    => array(
						'Referer'       => 'https://www.google.com/',
						'Cache-Control' => 'no-cache',
					),
				)
			)
		);

		$bot_html = ( ! is_wp_error( $bot_res ) && 200 === (int) wp_remote_retrieve_response_code( $bot_res ) )
			? (string) wp_remote_retrieve_body( $bot_res )
			: '';

		// 1. Scan both responses for off-screen / hidden external link containers (e.g. left: -9999px pokies spam).
		$seen_spam_urls = array();
		$responses      = array(
			'Visitor DOM'   => $visitor_html,
			'Googlebot DOM' => $bot_html,
		);

		foreach ( $responses as $view_label => $raw_html ) {
			if ( '' === $raw_html ) {
				continue;
			}

			// Match elements carrying inline off-screen or zero-visibility styles.
			if ( preg_match_all( '/<([a-z0-9]+)\b[^>]*\bstyle\s*=\s*([\'"])([^\'"]*(?:(?:left|top|right|text-indent)\s*:\s*-\d{3,}px|font-size\s*:\s*0(?:px)?\b|opacity\s*:\s*0(?:\.0+)?\s*(?:;|!|$)|display\s*:\s*none|visibility\s*:\s*hidden)[^\'"]*)\2[^>]*>(.*?)<\/\1>/is', $raw_html, $blocks, PREG_SET_ORDER ) ) {
				foreach ( $blocks as $blk ) {
					$full_blk   = $blk[0];
					$style_attr = strtolower( $blk[3] );
					$inner_html = $blk[4];

					// Skip standard WordPress screen-reader skip links and SVG/icon defs.
					if ( false !== stripos( $blk[0], 'screen-reader-text' ) || false !== stripos( $blk[0], 'skip-link' ) ) {
						continue;
					}

					$is_severe_offscreen = (bool) preg_match( '/(?:left|top|right|text-indent)\s*:\s*-\d{3,}px|font-size\s*:\s*0/i', $style_attr );
					$has_spam_kw         = (bool) preg_match( '/\b(?:pokies|casino|sportsbook|betting|jackpot|roulette|slot\s+games|viagra|cialis|levitra|payday\s+loan)\b/i', $inner_html );

					// Only flag display:none / visibility:hidden if paired with offscreen positioning OR spam keywords (avoids flagging normal mobile nav dropdowns).
					if ( ! $is_severe_offscreen && ! $has_spam_kw ) {
						continue;
					}

					if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*([\'"])(https?:\/\/[^\'"]+)\1[^>]*>/i', $inner_html, $links ) ) {
						foreach ( $links[2] as $ext_href ) {
							$link_host = strtolower( (string) wp_parse_url( $ext_href, PHP_URL_HOST ) );
							if ( '' === $link_host || $link_host === $host || isset( $seen_spam_urls[ $ext_href ] ) ) {
								continue;
							}

							$seen_spam_urls[ $ext_href ] = true;
							$findings[]                  = array(
								'view'     => $view_label,
								'severity' => 'CRITICAL',
								'url'      => $ext_href,
								'reason'   => $has_spam_kw
									/* translators: %s: Scanned view label (Visitor DOM or Googlebot DOM). */
									? sprintf( __( 'Hidden SEO Spam Link Detected in %s (Gambling/Pharma Keywords)', 'fbs-secure-optimize' ), $view_label )
									/* translators: 1: Scanned view label, 2: Inline CSS style attribute. */
									: sprintf( __( 'Off-Screen Hidden External Link Detected in %1$s (%2$s)', 'fbs-secure-optimize' ), $view_label, $style_attr ),
								'preview'  => substr( trim( preg_replace( '/\s+/', ' ', $full_blk ) ), 0, 420 ),
							);
						}
					}
				}
			}
		}

		// 2. Compare Visitor vs Googlebot external link & script domains to catch conditional search-engine cloaking.
		if ( '' !== $bot_html && '' !== $visitor_html ) {
			$visitor_domains = self::extract_external_domains( $visitor_html, $host );
			$bot_domains     = self::extract_external_domains( $bot_html, $host );
			$cloaked_domains = array_diff( $bot_domains, $visitor_domains );

			if ( ! empty( $cloaked_domains ) ) {
				$findings[] = array(
					'view'     => 'Googlebot Cloaking Diff',
					'severity' => 'CRITICAL',
					'url'      => implode( ', ', array_slice( $cloaked_domains, 0, 5 ) ),
					'reason'   => __( 'Conditional Search-Engine Cloaking: Googlebot receives external domains hidden from normal visitors', 'fbs-secure-optimize' ),
					'preview'  => 'Cloaked external domains served exclusively to Googlebot: ' . implode( ', ', $cloaked_domains ),
				);
			}
		}

		return $findings;
	}

	/**
	 * Extract unique external hostnames from <a href> and <script src> tags in an HTML document.
	 *
	 * @since 2.3.0
	 * @param string $html      Raw HTML string.
	 * @param string $site_host Current site hostname.
	 * @return array<int, string>
	 */
	private static function extract_external_domains( $html, $site_host ) {
		$domains = array();
		if ( preg_match_all( '/<(?:a|script)\b[^>]*\b(?:href|src)\s*=\s*([\'"])(https?:\/\/[^\'"]+)\1/i', $html, $matches ) ) {
			foreach ( $matches[2] as $url ) {
				$dhost = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
				if ( '' !== $dhost && $dhost !== $site_host ) {
					$domains[ $dhost ] = true;
				}
			}
		}
		return array_keys( $domains );
	}

	/**
	 * Verify whether a flagged plugin file inside wp-content/plugins/{slug}/ matches the official
	 * cryptographic checksum on downloads.wordpress.org/plugin-checksums/{slug}/{version}.json.
	 *
	 * Eliminates false positives on bundled vendor test files (e.g. Elementor's Twig IntegrationTestCase.php)
	 * while still catching any modified/tampered plugin file.
	 *
	 * @since 2.3.0
	 * @param string $full_path Local absolute path to the flagged PHP file.
	 * @return bool True if verified 100% identical to the official WordPress.org release.
	 */
	public static function is_verified_official_wporg_plugin_file( $full_path ) {
		$norm_full    = wp_normalize_path( $full_path );
		$plugins_root = wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . 'plugins/' );

		if ( 0 !== strpos( $norm_full, $plugins_root ) || ! is_file( $full_path ) || ! is_readable( $full_path ) ) {
			return false;
		}

		$rel_from_plugins = substr( $norm_full, strlen( $plugins_root ) );
		$slash_pos        = strpos( $rel_from_plugins, '/' );
		if ( false === $slash_pos ) {
			return false;
		}

		$slug              = substr( $rel_from_plugins, 0, $slash_pos );
		$rel_inside_plugin = substr( $rel_from_plugins, $slash_pos + 1 );

		if ( '' === $slug || '' === $rel_inside_plugin ) {
			return false;
		}

		$version = self::get_installed_plugin_version( $slug );
		if ( '' === $version ) {
			return false;
		}

		$checksums = self::get_wporg_plugin_checksums( $slug, $version );
		if ( ! is_array( $checksums ) || ! isset( $checksums[ $rel_inside_plugin ] ) || ! is_array( $checksums[ $rel_inside_plugin ] ) ) {
			return false;
		}

		$entry      = $checksums[ $rel_inside_plugin ];
		$valid_md5  = isset( $entry['md5'] ) ? (array) $entry['md5'] : array();
		$valid_sha  = isset( $entry['sha256'] ) ? (array) $entry['sha256'] : array();
		$local_md5  = (string) md5_file( $full_path );
		$local_sha  = function_exists( 'hash_file' ) ? (string) hash_file( 'sha256', $full_path ) : '';

		if ( ( '' !== $local_sha && in_array( $local_sha, $valid_sha, true ) ) || in_array( $local_md5, $valid_md5, true ) ) {
			return true;
		}

		// Normalize Windows CRLF -> LF line endings and re-verify hashes.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file checksum verification.
		$raw_content = file_get_contents( $full_path );
		if ( is_string( $raw_content ) ) {
			$lf_content = str_replace( "\r\n", "\n", $raw_content );
			if ( in_array( md5( $lf_content ), $valid_md5, true ) ) {
				return true;
			}
			if ( function_exists( 'hash' ) && in_array( hash( 'sha256', $lf_content ), $valid_sha, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Look up the installed version of a plugin folder slug.
	 *
	 * @since 2.3.0
	 * @param string $slug Plugin folder name.
	 * @return string Version string or empty string.
	 */
	private static function get_installed_plugin_version( $slug ) {
		if ( null === self::$installed_plugins_map ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			self::$installed_plugins_map = array();
			$all_plugins                 = get_plugins();
			foreach ( $all_plugins as $basename => $data ) {
				$parts = explode( '/', $basename );
				if ( count( $parts ) >= 2 && ! empty( $data['Version'] ) ) {
					self::$installed_plugins_map[ $parts[0] ] = array(
						'version' => trim( (string) $data['Version'] ),
					);
				}
			}
		}

		return isset( self::$installed_plugins_map[ $slug ]['version'] )
			? self::$installed_plugins_map[ $slug ]['version']
			: '';
	}

	/**
	 * Fetch and cache official WordPress.org plugin checksums JSON for a given slug and version.
	 *
	 * @since 2.3.0
	 * @param string $slug    Plugin directory slug.
	 * @param string $version Plugin version.
	 * @return array<string, mixed>|false
	 */
	private static function get_wporg_plugin_checksums( $slug, $version ) {
		$cache_key = 'fbsseop_pchk_' . substr( md5( $slug . '|' . $version ), 0, 20 );
		$cached    = get_transient( $cache_key );

		if ( 'none' === $cached ) {
			return false;
		}
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = sprintf(
			'https://downloads.wordpress.org/plugin-checksums/%s/%s.json',
			rawurlencode( $slug ),
			rawurlencode( $version )
		);

		$response = wp_remote_get( $url, array( 'timeout' => 6 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			set_transient( $cache_key, 'none', DAY_IN_SECONDS );
			return false;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['files'] ) || ! is_array( $decoded['files'] ) ) {
			set_transient( $cache_key, 'none', DAY_IN_SECONDS );
			return false;
		}

		set_transient( $cache_key, $decoded['files'], 7 * DAY_IN_SECONDS );
		return $decoded['files'];
	}

	/**
	 * Audit active & parent theme critical template files (functions.php, header.php, footer.php):
	 * - If the theme is hosted on WordPress.org, verify critical template checksums against official
	 *   themes.svn.wordpress.org release files.
	 * - If it is a child or custom theme, scan critical templates against our expanded database/rootkit/spam signatures.
	 *
	 * @since 2.3.0
	 * @return array<int, array<string, mixed>>
	 */
	public static function audit_active_and_parent_themes() {
		$findings       = array();
		$stylesheet_obj = wp_get_theme();
		$themes_to_scan = array();

		if ( $stylesheet_obj && $stylesheet_obj->exists() ) {
			$themes_to_scan[ $stylesheet_obj->get_stylesheet() ] = $stylesheet_obj;
			$parent_obj                                          = $stylesheet_obj->parent();
			if ( $parent_obj && $parent_obj->exists() ) {
				$themes_to_scan[ $parent_obj->get_stylesheet() ] = $parent_obj;
			}
		}

		$critical_templates = array( 'functions.php', 'header.php', 'footer.php' );

		foreach ( $themes_to_scan as $theme_slug => $theme_obj ) {
			$theme_dir = $theme_obj->get_stylesheet_directory();
			$version   = (string) $theme_obj->get( 'Version' );
			$is_wporg  = self::is_wporg_hosted_theme( $theme_slug, $version );

			foreach ( $critical_templates as $tpl ) {
				$local_file = trailingslashit( $theme_dir ) . $tpl;
				if ( ! is_file( $local_file ) || ! is_readable( $local_file ) ) {
					continue;
				}

				if ( $is_wporg && '' !== $version ) {
					$official_body = self::get_wporg_theme_file( $theme_slug, $version, $tpl );
					if ( false !== $official_body ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$local_body = (string) file_get_contents( $local_file );
						$norm_local = str_replace( "\r\n", "\n", $local_body );
						$norm_off   = str_replace( "\r\n", "\n", $official_body );

						if ( md5( $norm_local ) !== md5( $norm_off ) ) {
							$findings[] = array(
								'type'       => 'theme_modified',
								'severity'   => 'HIGH',
								'file'       => FBS_Secure_Optimize_Scanner::normalize_rel_path( $local_file ),
								'size'       => (int) filesize( $local_file ),
								'mtime'      => gmdate( 'Y-m-d H:i:s', (int) filemtime( $local_file ) ),
								'reason'     => sprintf(
									/* translators: 1: Theme slug, 2: Theme version. */
									__( 'Modified official WordPress.org theme template (differs from %1$s v%2$s)', 'fbs-secure-optimize' ),
									$theme_slug,
									$version
								),
								'can_delete' => false,
								'preview'    => FBS_Secure_Optimize_Scanner::get_file_snippet( $local_file, 400 ),
							);
						}
						continue;
					}
				}

				// Child or custom theme: inspect critical templates for rootkit/rogue-admin/SEO-spam signatures.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$custom_code = (string) file_get_contents( $local_file );
				$eval_res    = self::evaluate_code_payload( $custom_code );
				if ( ! empty( $eval_res['matched'] ) ) {
					$findings[] = array(
						'type'       => 'child_theme_suspicious',
						'severity'   => $eval_res['severity'],
						'file'       => FBS_Secure_Optimize_Scanner::normalize_rel_path( $local_file ),
						'size'       => (int) filesize( $local_file ),
						'mtime'      => gmdate( 'Y-m-d H:i:s', (int) filemtime( $local_file ) ),
						'reason'     => 'Theme Template Signature: ' . implode( ' + ', $eval_res['matched'] ),
						'can_delete' => false,
						'preview'    => FBS_Secure_Optimize_Scanner::get_file_snippet( $local_file, 400 ),
					);
				}
			}
		}

		return $findings;
	}

	/**
	 * Check whether a theme slug & version exists on official WordPress.org SVN.
	 *
	 * @since 2.3.0
	 * @param string $theme_slug Theme folder slug.
	 * @param string $version    Theme version.
	 * @return bool
	 */
	private static function is_wporg_hosted_theme( $theme_slug, $version ) {
		if ( '' === $theme_slug || '' === $version || false !== strpos( $theme_slug, 'child' ) ) {
			return false;
		}

		// Never treat custom studio theme as WP.org hosted.
		if ( 'fbsfusion' === $theme_slug ) {
			return false;
		}

		$cache_key = 'fbsseop_tchk_' . substr( md5( $theme_slug . '|' . $version ), 0, 20 );
		$cached    = get_transient( $cache_key );
		if ( 'yes' === $cached ) {
			return true;
		}
		if ( 'no' === $cached ) {
			return false;
		}

		$url      = sprintf( 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=%s', rawurlencode( $theme_slug ) );
		$response = wp_remote_get( $url, array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, 'no', DAY_IN_SECONDS );
			return false;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$exists = is_array( $body ) && ! empty( $body['slug'] ) && $body['slug'] === $theme_slug;
		set_transient( $cache_key, $exists ? 'yes' : 'no', 7 * DAY_IN_SECONDS );

		return $exists;
	}

	/**
	 * Fetch and cache an official theme template file from themes.svn.wordpress.org.
	 *
	 * @since 2.3.0
	 * @param string $theme_slug Theme slug.
	 * @param string $version    Theme version.
	 * @param string $rel_file   Relative filename (e.g. 'functions.php').
	 * @return string|false
	 */
	private static function get_wporg_theme_file( $theme_slug, $version, $rel_file ) {
		$cache_key = 'fbsseop_tf_' . substr( md5( $theme_slug . '|' . $version . '|' . $rel_file ), 0, 20 );
		$cached    = get_transient( $cache_key );
		if ( '__missing__' === $cached ) {
			return false;
		}
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$url = sprintf(
			'https://themes.svn.wordpress.org/%s/%s/%s',
			rawurlencode( $theme_slug ),
			rawurlencode( $version ),
			ltrim( $rel_file, '/' )
		);

		$res = wp_remote_get( $url, array( 'timeout' => 5 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			set_transient( $cache_key, '__missing__', DAY_IN_SECONDS );
			return false;
		}

		$body = (string) wp_remote_retrieve_body( $res );
		set_transient( $cache_key, $body, 7 * DAY_IN_SECONDS );
		return $body;
	}

	/**
	 * AJAX handler: View Full Code for a database snippet, CPT injection, or wp_options row.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_view_db_code() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		global $wpdb;
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$hook   = isset( $_POST['hook_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hook_name'] ) ) : '';

		$title   = '';
		$content = '';

		if ( ( 'wp_snippets' === $source || 'ms_snippets' === $source ) && $row_id > 0 ) {
			$table = ( 'ms_snippets' === $source ) ? $wpdb->base_prefix . 'ms_snippets' : $wpdb->prefix . 'snippets';
			if ( self::table_exists( $table ) ) {
				if ( 'ms_snippets' === $source ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, scope, active, code FROM {$wpdb->base_prefix}ms_snippets WHERE id = %d", $row_id ) );
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, scope, active, code FROM {$wpdb->prefix}snippets WHERE id = %d", $row_id ) );
				}
				if ( $row ) {
					$title   = sprintf( '%s #%d — %s (%s)', $table, (int) $row->id, (string) $row->name, ! empty( $row->active ) ? 'ACTIVE' : 'INACTIVE' );
					$content = (string) $row->code;
				}
			}
		} elseif ( 'hfcm_scripts' === $source && $row_id > 0 ) {
			$table = $wpdb->prefix . 'hfcm_scripts';
			if ( self::table_exists( $table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT script_id, name, status, snippet FROM {$wpdb->prefix}hfcm_scripts WHERE script_id = %d", $row_id ) );
				if ( $row ) {
					$title   = sprintf( 'HFCM #%d — %s (%s)', (int) $row->script_id, (string) $row->name, (string) $row->status );
					$content = (string) $row->snippet;
				}
			}
		} elseif ( 'xyz_ips' === $source && $row_id > 0 ) {
			$table = $wpdb->prefix . 'xyz_ips_short_code';
			if ( self::table_exists( $table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, content FROM {$wpdb->prefix}xyz_ips_short_code WHERE id = %d", $row_id ) );
				if ( $row ) {
					$title   = sprintf( 'XYZ PHP #%d — %s', (int) $row->id, (string) $row->title );
					$content = (string) $row->content;
				}
			}
		} elseif ( ( 'wp_posts' === $source || 'wp_posts_content' === $source ) && $row_id > 0 ) {
			$post = get_post( $row_id );
			if ( $post ) {
				$title   = sprintf( '%s #%d — %s (%s)', $post->post_type, $post->ID, $post->post_title, $post->post_status );
				$content = (string) $post->post_content;
				foreach ( array( '_elementor_code', '_wpcode_snippet_code', 'wbcr_inp_snippet_code' ) as $mk ) {
					$mv = get_post_meta( $row_id, $mk, true );
					if ( is_string( $mv ) && '' !== $mv ) {
						$content .= "\n\n/* Meta: {$mk} */\n" . $mv;
					}
				}
			}
		} elseif ( 'wp_options' === $source && $row_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", $row_id ) );
			if ( $row ) {
				$title   = 'wp_options #' . $row_id . ' — ' . (string) $row->option_name;
				$content = (string) $row->option_value;
			}
		} elseif ( 'wp_cron' === $source && '' !== $hook ) {
			$title   = 'WP-Cron Hook: ' . $hook;
			$content = 'Scheduled WP-Cron Hook Name: ' . $hook;
		}

		if ( '' === $title && '' === $content ) {
			wp_send_json_error( array( 'message' => __( 'Database record no longer exists.', 'fbs-secure-optimize' ) ) );
		}

		wp_send_json_success(
			array(
				'title'   => $title,
				'content' => substr( $content, 0, 65536 ),
			)
		);
	}

	/**
	 * AJAX handler: 1-Click Direct-DB Deactivate or Permanently Delete a malicious snippet, option, or cron hook.
	 *
	 * Executes direct $wpdb queries so self-hiding rootkit hooks in snippet plugins cannot block removal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_remediate_snippet() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		global $wpdb;
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$hook   = isset( $_POST['hook_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hook_name'] ) ) : '';

		if ( ! in_array( $mode, array( 'deactivate', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid remediation mode.', 'fbs-secure-optimize' ) ) );
		}

		$msg = '';

		if ( ( 'wp_snippets' === $source || 'ms_snippets' === $source ) && $row_id > 0 ) {
			$table = ( 'ms_snippets' === $source ) ? $wpdb->base_prefix . 'ms_snippets' : $wpdb->prefix . 'snippets';
			if ( ! self::table_exists( $table ) ) {
				wp_send_json_error( array( 'message' => __( 'Target snippet table not found.', 'fbs-secure-optimize' ) ) );
			}

			if ( 'deactivate' === $mode ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, array( 'active' => 0 ), array( 'id' => $row_id ), array( '%d' ), array( '%d' ) );
				/* translators: %d: Database snippet ID. */
				$msg = sprintf( __( 'Snippet #%d deactivated directly in MySQL.', 'fbs-secure-optimize' ), $row_id );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
				/* translators: %d: Database snippet ID. */
				$msg = sprintf( __( 'Snippet #%d permanently deleted from MySQL.', 'fbs-secure-optimize' ), $row_id );
			}
			wp_cache_flush();
		} elseif ( 'hfcm_scripts' === $source && $row_id > 0 ) {
			$table = $wpdb->prefix . 'hfcm_scripts';
			if ( ! self::table_exists( $table ) ) {
				wp_send_json_error( array( 'message' => __( 'Target HFCM table not found.', 'fbs-secure-optimize' ) ) );
			}

			if ( 'deactivate' === $mode ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, array( 'status' => 'inactive' ), array( 'script_id' => $row_id ), array( '%s' ), array( '%d' ) );
				/* translators: %d: HFCM snippet ID. */
				$msg = sprintf( __( 'HFCM Snippet #%d deactivated.', 'fbs-secure-optimize' ), $row_id );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table, array( 'script_id' => $row_id ), array( '%d' ) );
				/* translators: %d: HFCM snippet ID. */
				$msg = sprintf( __( 'HFCM Snippet #%d permanently deleted.', 'fbs-secure-optimize' ), $row_id );
			}
			wp_cache_flush();
		} elseif ( 'xyz_ips' === $source && $row_id > 0 ) {
			$table = $wpdb->prefix . 'xyz_ips_short_code';
			if ( ! self::table_exists( $table ) ) {
				wp_send_json_error( array( 'message' => __( 'Target XYZ table not found.', 'fbs-secure-optimize' ) ) );
			}

			if ( 'deactivate' === $mode ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, array( 'status' => 0 ), array( 'id' => $row_id ), array( '%d' ), array( '%d' ) );
				/* translators: %d: XYZ snippet ID. */
				$msg = sprintf( __( 'XYZ Snippet #%d deactivated.', 'fbs-secure-optimize' ), $row_id );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
				/* translators: %d: XYZ snippet ID. */
				$msg = sprintf( __( 'XYZ Snippet #%d permanently deleted.', 'fbs-secure-optimize' ), $row_id );
			}
			wp_cache_flush();
		} elseif ( 'wp_posts' === $source && $row_id > 0 ) {
			$post = get_post( $row_id );
			if ( ! $post || ! in_array( $post->post_type, array( 'elementor_snippet', 'wpcode', 'wbcr-snippets' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid custom snippet post target.', 'fbs-secure-optimize' ) ) );
			}

			if ( 'deactivate' === $mode ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $row_id ), array( '%s' ), array( '%d' ) );
				clean_post_cache( $row_id );
				/* translators: %d: Post ID. */
				$msg = sprintf( __( 'Custom code post #%d switched to Draft (inactive).', 'fbs-secure-optimize' ), $row_id );
			} else {
				wp_delete_post( $row_id, true );
				/* translators: %d: Post ID. */
				$msg = sprintf( __( 'Custom code post #%d permanently deleted.', 'fbs-secure-optimize' ), $row_id );
			}
		} elseif ( 'wp_options' === $source && 'delete' === $mode && $row_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$opt_row = $wpdb->get_row( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d", $row_id ) );
			if ( ! $opt_row || in_array( (string) $opt_row->option_name, self::PROTECTED_CORE_OPTIONS, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Protected WordPress core options cannot be deleted.', 'fbs-secure-optimize' ) ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->options, array( 'option_id' => $row_id ), array( '%d' ) );
			wp_cache_flush();
			/* translators: 1: Option name, 2: Option ID. */
			$msg = sprintf( __( 'Option "%1$s" (#%2$d) deleted from wp_options.', 'fbs-secure-optimize' ), (string) $opt_row->option_name, $row_id );
		} elseif ( 'wp_cron' === $source && 'delete' === $mode && '' !== $hook ) {
			wp_clear_scheduled_hook( $hook );
			/* translators: %s: WP-Cron hook name. */
			$msg = sprintf( __( 'Scheduled WP-Cron hook "%s" unscheduled.', 'fbs-secure-optimize' ), $hook );
		} else {
			wp_send_json_error( array( 'message' => __( 'Unsupported database remediation target.', 'fbs-secure-optimize' ) ) );
		}

		FBS_Secure_Optimize_Controller::purge_all_caches( false );

		wp_send_json_success( array( 'message' => $msg ) );
	}

	/**
	 * AJAX handler: Force-Delete a rogue/hidden administrator user directly in MySQL.
	 *
	 * Bypasses load-user-edit.php and delete_user hooks where rootkit snippets call wp_die('Invalid user ID.'),
	 * reassigns any authored posts to the current administrator, destroys sessions, and flushes object cache.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_force_delete_user() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		global $wpdb;
		$target_uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$current_id = get_current_user_id();

		if ( $target_uid <= 0 || $target_uid === $current_id ) {
			wp_send_json_error( array( 'message' => __( 'Safety guard: You cannot force-delete your own active administrator account.', 'fbs-secure-optimize' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$target_row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_login FROM {$wpdb->users} WHERE ID = %d", $target_uid ) );
		if ( ! $target_row ) {
			wp_send_json_error( array( 'message' => __( 'User record not found in database.', 'fbs-secure-optimize' ) ) );
		}

		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $target_uid )->destroy_all();
		}

		// Reassign any authored posts/links to current admin so no content is orphaned.
		if ( $current_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->posts, array( 'post_author' => $current_id ), array( 'post_author' => $target_uid ), array( '%d' ), array( '%d' ) );
		}

		// Direct MySQL deletion of usermeta and users rows (bypasses malware delete_user / load-user-edit.php hooks).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $target_uid ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->users, array( 'ID' => $target_uid ), array( '%d' ) );

		clean_user_cache( $target_uid );
		wp_cache_flush();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Username, 2: User ID. */
					__( 'Rogue user "%1$s" (ID #%2$d) permanently removed via direct database deletion and sessions destroyed.', 'fbs-secure-optimize' ),
					(string) $target_row->user_login,
					$target_uid
				),
			)
		);
	}

	/**
	 * AJAX handler: 1-Click Revoke an Application Password for a user.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_revoke_app_password() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$uuid    = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : '';

		if ( $user_id <= 0 || '' === $uuid ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Application Password identifier.', 'fbs-secure-optimize' ) ) );
		}

		$deleted = false;
		if ( class_exists( 'WP_Application_Passwords' ) ) {
			$res     = WP_Application_Passwords::delete_application_password( $user_id, $uuid );
			$deleted = ( true === $res );
		}

		if ( ! $deleted ) {
			$existing = get_user_meta( $user_id, '_application_passwords', true );
			if ( is_array( $existing ) ) {
				$filtered = array();
				foreach ( $existing as $item ) {
					if ( ! isset( $item['uuid'] ) || $item['uuid'] !== $uuid ) {
						$filtered[] = $item;
					} else {
						$deleted = true;
					}
				}
				update_user_meta( $user_id, '_application_passwords', $filtered );
			}
		}

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Application Password could not be found or was already revoked.', 'fbs-secure-optimize' ) ) );
		}

		clean_user_cache( $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'Application Password revoked immediately.', 'fbs-secure-optimize' ),
			)
		);
	}

	/**
	 * AJAX handler: Post-Cleanup 1-Click Clear All Caches & Invalidate All Other User Sessions.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_post_cleanup_purge() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		global $wpdb;
		$current_id = get_current_user_id();

		// Destroy session_tokens for all users except the currently logged-in administrator.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$invalidated = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens' AND user_id != %d",
				$current_id
			)
		);

		$cleared = FBS_Secure_Optimize_Controller::purge_all_caches( true );

		wp_send_json_success(
			array(
				'cleared'     => $cleared,
				'invalidated' => $invalidated,
				'message'     => sprintf(
					/* translators: 1: Cache layers cleared, 2: User session rows invalidated. */
					__( 'Post-cleanup complete! Purged caches (%1$s) and invalidated %2$d other active user session(s).', 'fbs-secure-optimize' ),
					implode( ', ', $cleared ),
					$invalidated
				),
			)
		);
	}
}
