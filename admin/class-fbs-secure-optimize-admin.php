<?php
/**
 * Modern Admin Interface Class for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the modern tabbed dashboard, module toggles, and AJAX endpoints.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Admin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Admin|null
	 */
	private static $instance = null;

	/**
	 * Active tab slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $current_tab = 'overview';

	/**
	 * Registered tabs.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, string>>
	 */
	private $tabs = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Admin
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
		$this->init_tabs();
		$this->init_hooks();
	}

	/**
	 * Configure dashboard tabs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_tabs() {
		$is_pro = FBS_Secure_Optimize_Controller::is_pro_active();

		$this->tabs = array(
			'overview'    => array(
				'title' => __( 'Module Control Center', 'fbs-secure-optimize' ),
				'icon'  => 'dashicons-screenoptions',
			),
			'performance' => array(
				'title' => __( 'Speed & Database', 'fbs-secure-optimize' ),
				'icon'  => 'dashicons-performance',
			),
			'security'    => array(
				'title' => __( 'Headers & Hardening', 'fbs-secure-optimize' ),
				'icon'  => 'dashicons-lock',
			),
			'scanner'     => array(
				'title' => __( '6-Layer Malware Scanner', 'fbs-secure-optimize' ),
				'icon'  => 'dashicons-shield-alt',
			),
			'firewall'    => array(
				'title' => $is_pro ? __( 'WAF & User Guard (Pro)', 'fbs-secure-optimize' ) : __( 'WAF & User Guard (Pro)', 'fbs-secure-optimize' ),
				'icon'  => 'dashicons-superhero-alt',
			),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing parameter.
		if ( isset( $_GET['tab'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( isset( $this->tabs[ $requested_tab ] ) ) {
				$this->current_tab = $requested_tab;
			}
		}
	}

	/**
	 * Register admin menus, settings handlers, and AJAX endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_fbsseop_save_settings', array( $this, 'handle_save_settings_post' ) );
		add_action( 'wp_ajax_fbsseop_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'wp_ajax_fbsseop_cleanup_database', array( $this, 'ajax_cleanup_database' ) );
	}

	/**
	 * Register top-level admin menu and submenus.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'FBS Secure Optimize', 'fbs-secure-optimize' ),
			__( 'FBS Optimize', 'fbs-secure-optimize' ),
			'manage_options',
			'fbs-secure-optimize',
			array( $this, 'render_admin_page' ),
			'dashicons-shield',
			30
		);

		add_submenu_page(
			'fbs-secure-optimize',
			__( 'Control Center', 'fbs-secure-optimize' ),
			__( 'Control Center', 'fbs-secure-optimize' ),
			'manage_options',
			'fbs-secure-optimize',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'fbs-secure-optimize',
			__( '6-Layer Scanner', 'fbs-secure-optimize' ),
			__( 'Malware Scanner', 'fbs-secure-optimize' ),
			'manage_options',
			'fbs-secure-optimize-scanner-shortcut',
			array( $this, 'redirect_to_scanner_tab' )
		);

		add_submenu_page(
			'fbs-secure-optimize',
			__( 'Our Plugins', 'fbs-secure-optimize' ),
			__( 'Our Plugins', 'fbs-secure-optimize' ),
			'manage_options',
			'fbs-secure-optimize-plugins',
			array( $this, 'render_our_plugins_page' )
		);

		add_submenu_page(
			'fbs-secure-optimize',
			__( 'Meet The Author', 'fbs-secure-optimize' ),
			__( 'Meet The Author', 'fbs-secure-optimize' ),
			'manage_options',
			'fbs-secure-optimize-author',
			array( $this, 'render_author_page' )
		);
	}

	/**
	 * Redirect shortcut submenu item to the Malware Scanner tab.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function redirect_to_scanner_tab() {
		wp_safe_redirect( admin_url( 'admin.php?page=fbs-secure-optimize&tab=scanner' ) );
		exit;
	}

	/**
	 * Handle settings form submission safely with per-tab preservation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_save_settings_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'fbs-secure-optimize' ) );
		}

		check_admin_referer( 'fbsseop_save_settings_action', 'fbsseop_nonce' );

		$section_tab = isset( $_POST['settings_tab'] ) ? sanitize_key( wp_unslash( $_POST['settings_tab'] ) ) : 'overview';
		$raw_input   = isset( $_POST['fbsseop'] ) && is_array( $_POST['fbsseop'] ) ? wp_unslash( $_POST['fbsseop'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field-by-field below.

		$current = FBS_Secure_Optimize_Controller::get_all_settings();

		if ( 'overview' === $section_tab ) {
			$module_keys = array_keys( $current['modules'] );
			foreach ( $module_keys as $mkey ) {
				$current['modules'][ $mkey ] = ! empty( $raw_input['modules'][ $mkey ] ) ? 1 : 0;
			}
		} elseif ( 'performance' === $section_tab ) {
			$asset_bools = array(
				'minify_css',
				'minify_js',
				'defer_js',
				'lazy_load_images',
				'lazy_load_iframes',
				'disable_emojis',
				'disable_dashicons',
				'disable_embeds',
				'clean_wp_head',
				'heartbeat_control',
			);
			foreach ( $asset_bools as $ab ) {
				$current['asset_optimization'][ $ab ] = ! empty( $raw_input['asset_optimization'][ $ab ] ) ? 1 : 0;
			}

			$db_bools = array(
				'cleanup_revisions',
				'cleanup_autodrafts',
				'cleanup_spam_comments',
				'cleanup_transients',
				'cleanup_orphaned_meta',
				'optimize_tables',
				'auto_cleanup',
			);
			foreach ( $db_bools as $dbb ) {
				$current['database_cleanup'][ $dbb ] = ! empty( $raw_input['database_cleanup'][ $dbb ] ) ? 1 : 0;
			}

			$freq    = isset( $raw_input['database_cleanup']['cleanup_frequency'] ) ? sanitize_key( $raw_input['database_cleanup']['cleanup_frequency'] ) : 'weekly';
			$allowed = array( 'daily', 'weekly', 'monthly' );
			$current['database_cleanup']['cleanup_frequency'] = in_array( $freq, $allowed, true ) ? $freq : 'weekly';

			wp_clear_scheduled_hook( 'fbsseop_cleanup_database' );
		} elseif ( 'security' === $section_tab ) {
			$header_bools = array(
				'x_content_type_options',
				'x_frame_options',
				'x_xss_protection',
				'referrer_policy',
				'permissions_policy',
				'strict_transport_security',
				'hide_wp_version',
			);
			foreach ( $header_bools as $hb ) {
				$current['security_headers'][ $hb ] = ! empty( $raw_input['security_headers'][ $hb ] ) ? 1 : 0;
			}

			$current['login_security']['limit_login_attempts'] = ! empty( $raw_input['login_security']['limit_login_attempts'] ) ? 1 : 0;
			$current['login_security']['mask_login_errors']    = ! empty( $raw_input['login_security']['mask_login_errors'] ) ? 1 : 0;
			$current['login_security']['max_attempts']         = isset( $raw_input['login_security']['max_attempts'] ) ? max( 2, min( 25, absint( $raw_input['login_security']['max_attempts'] ) ) ) : 5;
			$current['login_security']['lockout_duration']     = isset( $raw_input['login_security']['lockout_duration'] ) ? max( 1, min( 1440, absint( $raw_input['login_security']['lockout_duration'] ) ) ) : 15;
			$current['login_security']['whitelist_ips']        = isset( $raw_input['login_security']['whitelist_ips'] ) ? sanitize_textarea_field( $raw_input['login_security']['whitelist_ips'] ) : '';

			$harden_bools = array(
				'disable_xmlrpc',
				'disable_file_edit',
				'block_author_enum',
				'disable_rest_users',
			);
			foreach ( $harden_bools as $hdb ) {
				$current['wp_hardening'][ $hdb ] = ! empty( $raw_input['wp_hardening'][ $hdb ] ) ? 1 : 0;
			}
		}

		update_option( 'fbsseop_settings', $current );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'fbs-secure-optimize',
					'tab'   => isset( $this->tabs[ $section_tab ] ) ? $section_tab : 'overview',
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX handler: Instant 1-click toggle for an independent module card.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_toggle_module() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$module  = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$enabled = ! empty( $_POST['enabled'] ) ? 1 : 0;

		$current = FBS_Secure_Optimize_Controller::get_all_settings();
		if ( ! array_key_exists( $module, $current['modules'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid module key.', 'fbs-secure-optimize' ) ) );
		}

		$current['modules'][ $module ] = $enabled;
		update_option( 'fbsseop_settings', $current );

		$active_count = array_sum( array_map( 'intval', $current['modules'] ) );

		wp_send_json_success(
			array(
				'module'       => $module,
				'enabled'      => $enabled,
				'active_count' => $active_count,
				'total_count'  => count( $current['modules'] ),
				'message'      => $enabled
					? __( 'Module activated.', 'fbs-secure-optimize' )
					: __( 'Module deactivated (zero runtime overhead).', 'fbs-secure-optimize' ),
			)
		);
	}

	/**
	 * AJAX handler: Run manual database cleanup.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_cleanup_database() {
		check_ajax_referer( 'fbsseop_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fbs-secure-optimize' ) ) );
		}

		$cleaner = FBS_Secure_Optimize_Database_Cleanup::get_instance();
		$results = $cleaner->perform_cleanup();
		$stats   = FBS_Secure_Optimize_Controller::get_stats();

		wp_send_json_success(
			array(
				'results' => $results,
				'stats'   => $stats,
				'message' => sprintf(
					/* translators: 1: Revisions deleted, 2: Transients deleted, 3: Orphaned meta deleted, 4: Tables optimized. */
					__( 'Cleanup complete! Removed %1$d revisions, %2$d expired transients, %3$d orphaned meta rows, and optimized %4$d tables.', 'fbs-secure-optimize' ),
					(int) $results['post_revisions'],
					(int) $results['transients'],
					(int) $results['orphaned_meta'],
					(int) $results['optimized_tables']
				),
			)
		);
	}

	/**
	 * Render main dashboard wrapper and active tab view.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_admin_page() {
		$settings     = FBS_Secure_Optimize_Controller::get_all_settings();
		$stats        = FBS_Secure_Optimize_Controller::get_stats();
		$is_pro       = FBS_Secure_Optimize_Controller::is_pro_active();
		$active_mods  = array_sum( array_map( 'intval', $settings['modules'] ) );
		$total_mods   = count( $settings['modules'] );
		$uploads_safe = FBS_Secure_Optimize_Hardening::is_uploads_protected();
		$last_scan    = get_option( 'fbsseop_last_scan_summary', array() );
		?>
		<div class="wrap fbsseop-wrap">
			<!-- Top SaaS Hero Banner -->
			<div class="fbsseop-hero">
				<div class="fbsseop-hero__brand">
					<div class="fbsseop-hero__logo" aria-hidden="true">
						<span class="dashicons dashicons-shield-alt"></span>
					</div>
					<div>
						<div class="fbsseop-hero__title-row">
							<h1><?php esc_html_e( 'FBS Secure Optimize', 'fbs-secure-optimize' ); ?></h1>
							<span class="fbsseop-badge fbsseop-badge--ver"><?php echo esc_html( 'v' . FBS_SECURE_OPTIMIZE_VERSION ); ?></span>
							<?php if ( $is_pro ) : ?>
								<span class="fbsseop-badge fbsseop-badge--pro"><?php esc_html_e( 'PRO ACTIVE', 'fbs-secure-optimize' ); ?></span>
							<?php else : ?>
								<span class="fbsseop-badge fbsseop-badge--free"><?php esc_html_e( 'FREE EDITION', 'fbs-secure-optimize' ); ?></span>
							<?php endif; ?>
						</div>
						<p class="fbsseop-hero__subtitle">
							<?php esc_html_e( 'Modular PageSpeed, Database, HTTP Security Headers & Deep Malware Forensics Engine by FBS Fusion.', 'fbs-secure-optimize' ); ?>
						</p>
					</div>
				</div>

				<div class="fbsseop-hero__actions">
					<span class="fbsseop-health-pill" id="fbsseop-module-counter">
						<span class="fbsseop-dot"></span>
						<?php
						printf(
							/* translators: 1: Active module count, 2: Total module count. */
							esc_html__( '%1$d / %2$d Modules Active', 'fbs-secure-optimize' ),
							(int) $active_mods,
							(int) $total_mods
						);
						?>
					</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fbs-secure-optimize&tab=scanner' ) ); ?>" class="fbsseop-btn fbsseop-btn--glass">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Run 6-Layer Scan', 'fbs-secure-optimize' ); ?>
					</a>
					<?php if ( ! $is_pro ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fbs-secure-optimize&tab=firewall' ) ); ?>" class="fbsseop-btn fbsseop-btn--emerald">
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e( 'Unlock Pro WAF & Guard', 'fbs-secure-optimize' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only saved toast flag.
			if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) :
				?>
				<div class="fbsseop-toast fbsseop-toast--success">
					<span class="dashicons dashicons-yes-alt"></span>
					<span><?php esc_html_e( 'Settings saved and synchronized successfully.', 'fbs-secure-optimize' ); ?></span>
				</div>
			<?php endif; ?>

			<div id="fbsseop-ajax-toast" class="fbsseop-toast" style="display:none;"></div>

			<!-- Navigation Tabs -->
			<nav class="fbsseop-tabs" aria-label="<?php esc_attr_e( 'Plugin Sections', 'fbs-secure-optimize' ); ?>">
				<?php foreach ( $this->tabs as $tab_key => $tab_meta ) : ?>
					<?php
					$is_active = ( $this->current_tab === $tab_key );
					$tab_url   = add_query_arg(
						array(
							'page' => 'fbs-secure-optimize',
							'tab'  => $tab_key,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="fbsseop-tab <?php echo $is_active ? 'is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $tab_meta['icon'] ); ?>"></span>
						<span><?php echo esc_html( $tab_meta['title'] ); ?></span>
						<?php if ( 'firewall' === $tab_key && ! $is_pro ) : ?>
							<span class="fbsseop-pro-chip">PRO</span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Tab Body -->
			<div class="fbsseop-body">
				<?php
				switch ( $this->current_tab ) {
					case 'performance':
						$this->render_performance_tab( $settings, $stats );
						break;
					case 'security':
						$this->render_security_tab( $settings, $uploads_safe );
						break;
					case 'scanner':
						$this->render_scanner_tab( $settings, $last_scan, $is_pro );
						break;
					case 'firewall':
						$this->render_firewall_tab( $is_pro );
						break;
					case 'overview':
					default:
						$this->render_overview_tab( $settings, $stats, $uploads_safe, $last_scan );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 1: Overview & Independent Module Control Center.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $settings     Plugin settings.
	 * @param array<string, mixed> $stats        Telemetry stats.
	 * @param bool                 $uploads_safe Uploads .htaccess status.
	 * @param array<string, mixed> $last_scan    Last scan summary.
	 * @return void
	 */
	private function render_overview_tab( $settings, $stats, $uploads_safe, $last_scan ) {
		$cleanable_total = (int) $stats['post_revisions'] + (int) $stats['auto_drafts'] + (int) $stats['spam_comments'] + (int) $stats['transients'];

		$module_cards = array(
			'asset_optimizer'  => array(
				'title'   => __( 'Asset & Bloat Optimizer', 'fbs-secure-optimize' ),
				'badge'   => __( 'Speed', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-performance',
				'desc'    => __( 'LCP-safe native lazy loading, JS deferring, Emoji/Dashicons removal, and Heartbeat API throttling.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'performance',
			),
			'database_cleanup' => array(
				'title'   => __( 'Database & Autoload Cleaner', 'fbs-secure-optimize' ),
				'badge'   => __( 'Speed', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-database',
				'desc'    => __( 'Prune revisions, auto-drafts, expired transients, orphaned postmeta, and monitor heavy autoload rows.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'performance',
			),
			'security_headers' => array(
				'title'   => __( 'HTTP Security Headers', 'fbs-secure-optimize' ),
				'badge'   => __( 'Security', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-admin-site-alt3',
				'desc'    => __( 'Enforce Nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS, and hide WP version leaks.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'security',
			),
			'login_security'   => array(
				'title'   => __( 'Login Brute-Force Shield', 'fbs-secure-optimize' ),
				'badge'   => __( 'Security', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-lock',
				'desc'    => __( 'Automatic IP lockouts after failed login attempts, IP allowlisting, and username enumeration masking.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'security',
			),
			'wp_hardening'     => array(
				'title'   => __( 'WP Hardening & Uploads Shield', 'fbs-secure-optimize' ),
				'badge'   => __( 'Security', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-Cue',
				'desc'    => __( 'Disable XML-RPC, lock theme/plugin editors, block ?author=1 scans, and block PHP execution in uploads/.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'security',
			),
			'malware_scanner'  => array(
				'title'   => __( '6-Layer Malware & Admin Scanner', 'fbs-secure-optimize' ),
				'badge'   => __( 'Forensics', 'fbs-secure-optimize' ),
				'icon'    => 'dashicons-shield-alt',
				'desc'    => __( 'Verify official WP.org MD5 checksums, detect root/uploads PHP shells, DB backdoors, and hidden admins.', 'fbs-secure-optimize' ),
				'cfg_tab' => 'scanner',
			),
		);
		?>
		<!-- Live Telemetry Strip -->
		<div class="fbsseop-kpi-grid">
			<div class="fbsseop-kpi-card">
				<span class="fbsseop-kpi-card__label"><?php esc_html_e( 'Cleanable DB Bloat', 'fbs-secure-optimize' ); ?></span>
				<strong class="fbsseop-kpi-card__value" id="fbsseop-kpi-cleanable"><?php echo esc_html( number_format_i18n( $cleanable_total ) ); ?></strong>
				<span class="fbsseop-kpi-card__meta"><?php esc_html_e( 'Revisions, drafts, spam & transients', 'fbs-secure-optimize' ); ?></span>
			</div>

			<div class="fbsseop-kpi-card">
				<span class="fbsseop-kpi-card__label"><?php esc_html_e( 'Autoloaded wp_options', 'fbs-secure-optimize' ); ?></span>
				<strong class="fbsseop-kpi-card__value <?php echo ( (float) $stats['autoload_kb'] > 800 ) ? 'is-warn' : 'is-good'; ?>">
					<?php echo esc_html( $stats['autoload_kb'] . ' KB' ); ?>
				</strong>
				<span class="fbsseop-kpi-card__meta"><?php esc_html_e( 'Recommended target: < 800 KB', 'fbs-secure-optimize' ); ?></span>
			</div>

			<div class="fbsseop-kpi-card">
				<span class="fbsseop-kpi-card__label"><?php esc_html_e( 'Failed Logins (24h)', 'fbs-secure-optimize' ); ?></span>
				<strong class="fbsseop-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $stats['failed_logins'] ) ); ?></strong>
				<span class="fbsseop-kpi-card__meta"><?php esc_html_e( 'Tracked by Brute-Force Shield', 'fbs-secure-optimize' ); ?></span>
			</div>

			<div class="fbsseop-kpi-card">
				<span class="fbsseop-kpi-card__label"><?php esc_html_e( 'Uploads PHP Shield & Scan', 'fbs-secure-optimize' ); ?></span>
				<strong class="fbsseop-kpi-card__value <?php echo $uploads_safe ? 'is-good' : 'is-warn'; ?>">
					<?php echo $uploads_safe ? esc_html__( 'Protected', 'fbs-secure-optimize' ) : esc_html__( 'Unshielded', 'fbs-secure-optimize' ); ?>
				</strong>
				<span class="fbsseop-kpi-card__meta">
					<?php
					if ( ! empty( $last_scan['scanned_at'] ) ) {
						printf(
							/* translators: %d: Issue count from last scan. */
							esc_html__( 'Last scan: %d issue(s)', 'fbs-secure-optimize' ),
							(int) $last_scan['total_issues']
						);
					} else {
						esc_html_e( 'No deep scan run yet', 'fbs-secure-optimize' );
					}
					?>
				</span>
			</div>
		</div>

		<!-- Independent Module Toggles Grid -->
		<div class="fbsseop-section-head">
			<div>
				<h2><?php esc_html_e( 'Independent Module Toggles', 'fbs-secure-optimize' ); ?></h2>
				<p><?php esc_html_e( 'Enable only the engines your site needs. Disabled modules are completely bypassed at boot—zero conflicts with WP Rocket, LiteSpeed Cache, or Cloudflare.', 'fbs-secure-optimize' ); ?></p>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fbsseop_save_settings">
			<input type="hidden" name="settings_tab" value="overview">
			<?php wp_nonce_field( 'fbsseop_save_settings_action', 'fbsseop_nonce' ); ?>

			<div class="fbsseop-modules-grid">
				<?php foreach ( $module_cards as $mkey => $meta ) : ?>
					<?php
					$enabled = ! empty( $settings['modules'][ $mkey ] );
					$cfg_url = add_query_arg(
						array(
							'page' => 'fbs-secure-optimize',
							'tab'  => $meta['cfg_tab'],
						),
						admin_url( 'admin.php' )
					);
					$icon_cls = ( 'dashicons-Cue' === $meta['icon'] ) ? 'dashicons-Lock' : $meta['icon'];
					if ( 'wp_hardening' === $mkey ) {
						$icon_cls = 'dashicons-admin-tools';
					}
					?>
					<div class="fbsseop-module-card <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
						<div class="fbsseop-module-card__top">
							<div class="fbsseop-module-card__icon">
								<span class="dashicons <?php echo esc_attr( $icon_cls ); ?>"></span>
							</div>
							<span class="fbsseop-tag"><?php echo esc_html( $meta['badge'] ); ?></span>
							<label class="fbsseop-switch" aria-label="<?php echo esc_attr( $meta['title'] ); ?>">
								<input
									type="checkbox"
									class="fbsseop-ajax-module-toggle"
									data-module="<?php echo esc_attr( $mkey ); ?>"
									name="fbsseop[modules][<?php echo esc_attr( $mkey ); ?>]"
									value="1"
									<?php checked( $enabled ); ?>
								>
								<span class="fbsseop-switch__slider"></span>
							</label>
						</div>

						<h3><?php echo esc_html( $meta['title'] ); ?></h3>
						<p><?php echo esc_html( $meta['desc'] ); ?></p>

						<div class="fbsseop-module-card__footer">
							<span class="fbsseop-status-text">
								<?php echo $enabled ? esc_html__( '● Active', 'fbs-secure-optimize' ) : esc_html__( '○ Disabled', 'fbs-secure-optimize' ); ?>
							</span>
							<a href="<?php echo esc_url( $cfg_url ); ?>" class="fbsseop-link">
								<?php esc_html_e( 'Configure →', 'fbs-secure-optimize' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="fbsseop-save-bar">
				<button type="submit" class="fbsseop-btn fbsseop-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Module Toggles', 'fbs-secure-optimize' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Tab 2: Speed, Bloat Removal & Database Optimization.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array<string, mixed> $stats    Database statistics.
	 * @return void
	 */
	private function render_performance_tab( $settings, $stats ) {
		$asset_on = ! empty( $settings['modules']['asset_optimizer'] );
		$db_on    = ! empty( $settings['modules']['database_cleanup'] );
		$asset    = $settings['asset_optimization'];
		$db       = $settings['database_cleanup'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fbsseop_save_settings">
			<input type="hidden" name="settings_tab" value="performance">
			<?php wp_nonce_field( 'fbsseop_save_settings_action', 'fbsseop_nonce' ); ?>

			<div class="fbsseop-two-col">
				<!-- Left Column: Asset & Bloat Optimizer -->
				<div class="fbsseop-panel">
					<div class="fbsseop-panel__header">
						<div>
							<h2><?php esc_html_e( '1. Asset & Core Web Vitals Optimizer', 'fbs-secure-optimize' ); ?></h2>
							<p><?php esc_html_e( 'Zero-jQuery frontend speed enhancements designed for 95–100/100 Lighthouse.', 'fbs-secure-optimize' ); ?></p>
						</div>
						<?php if ( ! $asset_on ) : ?>
							<span class="fbsseop-pill-warn"><?php esc_html_e( 'Module Off in Control Center', 'fbs-secure-optimize' ); ?></span>
						<?php endif; ?>
					</div>

					<?php
					$asset_fields = array(
						'lazy_load_images'  => array(
							__( 'LCP-Safe Native Image Lazy Loading', 'fbs-secure-optimize' ),
							__( 'Adds native loading="lazy" & decoding="async" while automatically giving fetchpriority="high" to your first hero image.', 'fbs-secure-optimize' ),
						),
						'lazy_load_iframes' => array(
							__( 'Native Iframe & Video Lazy Loading', 'fbs-secure-optimize' ),
							__( 'Defers offscreen YouTube, Google Maps, and embedded iframes until scrolled near.', 'fbs-secure-optimize' ),
						),
						'disable_emojis'    => array(
							__( 'Remove WP Emoji JS & CSS Bloat', 'fbs-secure-optimize' ),
							__( 'Eliminates wp-emoji-release.min.js and s.w.org DNS lookup on every page.', 'fbs-secure-optimize' ),
						),
						'disable_dashicons' => array(
							__( 'Disable Frontend Dashicons for Guests', 'fbs-secure-optimize' ),
							__( 'Saves ~35 KB of render-blocking CSS for logged-out visitors.', 'fbs-secure-optimize' ),
						),
						'clean_wp_head'     => array(
							__( 'Clean <head> RSD, WLW & Shortlinks', 'fbs-secure-optimize' ),
							__( 'Strips legacy XML-RPC discovery, Windows Live Writer, and redundant meta links.', 'fbs-secure-optimize' ),
						),
						'heartbeat_control' => array(
							__( 'Throttle WP Heartbeat API (60s)', 'fbs-secure-optimize' ),
							__( 'Prevents admin-ajax.php CPU spikes and 502 timeouts on shared/VPS servers.', 'fbs-secure-optimize' ),
						),
						'disable_embeds'    => array(
							__( 'Disable WP oEmbed Script', 'fbs-secure-optimize' ),
							__( 'Dequeues wp-embed.min.js if your site does not embed third-party WordPress posts.', 'fbs-secure-optimize' ),
						),
						'defer_js'          => array(
							__( 'Defer Non-Critical JavaScript', 'fbs-secure-optimize' ),
							__( 'Adds defer attribute to frontend scripts while automatically excluding jQuery & WooCommerce checkout.', 'fbs-secure-optimize' ),
						),
						'minify_css'        => array(
							__( 'Optimize Stylesheet Tags', 'fbs-secure-optimize' ),
							__( 'Cleans redundant whitespace in enqueued stylesheet tags.', 'fbs-secure-optimize' ),
						),
					);
					foreach ( $asset_fields as $fkey => $fmeta ) :
						?>
						<div class="fbsseop-setting-row">
							<div>
								<strong><?php echo esc_html( $fmeta[0] ); ?></strong>
								<span><?php echo esc_html( $fmeta[1] ); ?></span>
							</div>
							<label class="fbsseop-switch">
								<input type="checkbox" name="fbsseop[asset_optimization][<?php echo esc_attr( $fkey ); ?>]" value="1" <?php checked( ! empty( $asset[ $fkey ] ) ); ?>>
								<span class="fbsseop-switch__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Right Column: Database & Autoload Cleaner -->
				<div class="fbsseop-panel">
					<div class="fbsseop-panel__header">
						<div>
							<h2><?php esc_html_e( '2. Database & Autoload Cleaner', 'fbs-secure-optimize' ); ?></h2>
							<p><?php esc_html_e( 'Keep MySQL lean for sub-200ms Server TTFB.', 'fbs-secure-optimize' ); ?></p>
						</div>
						<?php if ( ! $db_on ) : ?>
							<span class="fbsseop-pill-warn"><?php esc_html_e( 'Module Off in Control Center', 'fbs-secure-optimize' ); ?></span>
						<?php endif; ?>
					</div>

					<?php
					$db_fields = array(
						'cleanup_revisions'     => sprintf(
							/* translators: %d: Count of post revisions. */
							__( 'Post Revisions (%d found)', 'fbs-secure-optimize' ),
							(int) $stats['post_revisions']
						),
						'cleanup_autodrafts'    => sprintf(
							/* translators: %d: Count of auto-drafts. */
							__( 'Old Auto-Drafts > 7 Days (%d found)', 'fbs-secure-optimize' ),
							(int) $stats['auto_drafts']
						),
						'cleanup_spam_comments' => sprintf(
							/* translators: %d: Count of spam/trash comments. */
							__( 'Spam & Trashed Comments (%d found)', 'fbs-secure-optimize' ),
							(int) $stats['spam_comments']
						),
						'cleanup_transients'    => sprintf(
							/* translators: %d: Count of transients. */
							__( 'Expired Transients (%d total in DB)', 'fbs-secure-optimize' ),
							(int) $stats['transients']
						),
						'cleanup_orphaned_meta' => __( 'Orphaned Postmeta Rows', 'fbs-secure-optimize' ),
						'optimize_tables'       => __( 'Run MySQL OPTIMIZE TABLE on Core Tables', 'fbs-secure-optimize' ),
						'auto_cleanup'          => __( 'Enable Scheduled Automatic Cleanup (WP-Cron)', 'fbs-secure-optimize' ),
					);
					foreach ( $db_fields as $dkey => $dlabel ) :
						?>
						<div class="fbsseop-setting-row">
							<div><strong><?php echo esc_html( $dlabel ); ?></strong></div>
							<label class="fbsseop-switch">
								<input type="checkbox" name="fbsseop[database_cleanup][<?php echo esc_attr( $dkey ); ?>]" value="1" <?php checked( ! empty( $db[ $dkey ] ) ); ?>>
								<span class="fbsseop-switch__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>

					<div class="fbsseop-setting-row">
						<div><strong><?php esc_html_e( 'Automatic Cleanup Schedule', 'fbs-secure-optimize' ); ?></strong></div>
						<select name="fbsseop[database_cleanup][cleanup_frequency]" class="fbsseop-select">
							<option value="daily" <?php selected( $db['cleanup_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'fbs-secure-optimize' ); ?></option>
							<option value="weekly" <?php selected( $db['cleanup_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly (Recommended)', 'fbs-secure-optimize' ); ?></option>
							<option value="monthly" <?php selected( $db['cleanup_frequency'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'fbs-secure-optimize' ); ?></option>
						</select>
					</div>

					<div class="fbsseop-inline-action">
						<button type="button" id="fbsseop-run-cleanup-btn" class="fbsseop-btn fbsseop-btn--emerald">
							<span class="dashicons dashicons-Trash"></span>
							<?php esc_html_e( 'Clean & Optimize Database Now', 'fbs-secure-optimize' ); ?>
						</button>
					</div>

					<!-- Autoload Bloat Inspector -->
					<div class="fbsseop-subbox">
						<h4>
							<?php
							printf(
								/* translators: %s: Autoload size in KB. */
								esc_html__( 'Top 5 Heaviest Autoloaded Options (Total: %s KB)', 'fbs-secure-optimize' ),
								esc_html( (string) $stats['autoload_kb'] )
							);
							?>
						</h4>
						<?php if ( ! empty( $stats['heavy_autoloads'] ) ) : ?>
							<table class="fbsseop-mini-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Option Name', 'fbs-secure-optimize' ); ?></th>
										<th><?php esc_html_e( 'Size', 'fbs-secure-optimize' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $stats['heavy_autoloads'] as $row ) : ?>
										<tr>
											<td><code><?php echo esc_html( $row['option_name'] ); ?></code></td>
											<td><strong><?php echo esc_html( $row['size_kb'] . ' KB' ); ?></strong></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="fbsseop-save-bar">
				<button type="submit" class="fbsseop-btn fbsseop-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Speed & Database Settings', 'fbs-secure-optimize' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Tab 3: HTTP Security Headers, Login Shield & WP Core Hardening.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $settings     Plugin settings.
	 * @param bool                 $uploads_safe Whether uploads/.htaccess is protected.
	 * @return void
	 */
	private function render_security_tab( $settings, $uploads_safe ) {
		$headers       = $settings['security_headers'];
		$login         = $settings['login_security'];
		$harden        = $settings['wp_hardening'];
		$risky_plugins = FBS_Secure_Optimize_Hardening::get_active_high_risk_plugins();
		$recent_fails  = FBS_Secure_Optimize_Login_Security::get_recent_failed_attempts( 8 );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fbsseop_save_settings">
			<input type="hidden" name="settings_tab" value="security">
			<?php wp_nonce_field( 'fbsseop_save_settings_action', 'fbsseop_nonce' ); ?>

			<div class="fbsseop-two-col">
				<!-- Left: HTTP Security Headers & WP Core Hardening -->
				<div class="fbsseop-panel">
					<div class="fbsseop-panel__header">
						<div>
							<h2><?php esc_html_e( '1. HTTP Security Headers', 'fbs-secure-optimize' ); ?></h2>
							<p><?php esc_html_e( 'Safe, non-breaking browser defense headers for Grade A security posture.', 'fbs-secure-optimize' ); ?></p>
						</div>
					</div>

					<?php
					$header_fields = array(
						'x_content_type_options'    => array( 'X-Content-Type-Options: nosniff', __( 'Prevents MIME-type sniffing attacks on uploaded assets.', 'fbs-secure-optimize' ) ),
						'x_frame_options'           => array( 'X-Frame-Options: SAMEORIGIN', __( 'Blocks clickjacking iframes from external domains.', 'fbs-secure-optimize' ) ),
						'referrer_policy'           => array( 'Referrer-Policy: strict-origin-when-cross-origin', __( 'Protects private admin/checkout URLs from referrer leaks.', 'fbs-secure-optimize' ) ),
						'permissions_policy'        => array( 'Permissions-Policy (Camera/Mic/Geo Off)', __( 'Restricts unauthorized browser sensor APIs.', 'fbs-secure-optimize' ) ),
						'strict_transport_security' => array( 'Strict-Transport-Security (HSTS — HTTPS Only)', __( 'Forces browsers onto HTTPS for 1 year (enable only on valid SSL sites).', 'fbs-secure-optimize' ) ),
						'hide_wp_version'           => array( __( 'Hide WordPress Core Version', 'fbs-secure-optimize' ), __( 'Strips generator tags and core ?ver=6.x query fingerprinting.', 'fbs-secure-optimize' ) ),
					);
					foreach ( $header_fields as $hkey => $hmeta ) :
						?>
						<div class="fbsseop-setting-row">
							<div>
								<strong><?php echo esc_html( $hmeta[0] ); ?></strong>
								<span><?php echo esc_html( $hmeta[1] ); ?></span>
							</div>
							<label class="fbsseop-switch">
								<input type="checkbox" name="fbsseop[security_headers][<?php echo esc_attr( $hkey ); ?>]" value="1" <?php checked( ! empty( $headers[ $hkey ] ) ); ?>>
								<span class="fbsseop-switch__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>

					<hr class="fbsseop-divider">

					<div class="fbsseop-panel__header">
						<div>
							<h2><?php esc_html_e( '2. WP Core Hardening & Uploads Shield', 'fbs-secure-optimize' ); ?></h2>
							<p><?php esc_html_e( 'Eliminate common hacker entry vectors before exploitation.', 'fbs-secure-optimize' ); ?></p>
						</div>
					</div>

					<?php
					$harden_fields = array(
						'disable_xmlrpc'     => array( __( 'Disable XML-RPC & X-Pingback', 'fbs-secure-optimize' ), __( 'Blocks amplified brute-force and pingback DDoS attacks.', 'fbs-secure-optimize' ) ),
						'block_author_enum'  => array( __( 'Block ?author=1 Username Enumeration', 'fbs-secure-optimize' ), __( 'Redirects bot author-archive probes to the homepage.', 'fbs-secure-optimize' ) ),
						'disable_rest_users' => array( __( 'Restrict Unauthenticated REST /wp/v2/users', 'fbs-secure-optimize' ), __( 'Stops bots from harvesting admin usernames via the public REST API.', 'fbs-secure-optimize' ) ),
						'disable_file_edit'  => array( __( 'Lock Theme & Plugin File Editors', 'fbs-secure-optimize' ), __( 'Blocks PHP code editing inside wp-admin via map_meta_cap.', 'fbs-secure-optimize' ) ),
					);
					foreach ( $harden_fields as $hdkey => $hdmeta ) :
						?>
						<div class="fbsseop-setting-row">
							<div>
								<strong><?php echo esc_html( $hdmeta[0] ); ?></strong>
								<span><?php echo esc_html( $hdmeta[1] ); ?></span>
							</div>
							<label class="fbsseop-switch">
								<input type="checkbox" name="fbsseop[wp_hardening][<?php echo esc_attr( $hdkey ); ?>]" value="1" <?php checked( ! empty( $harden[ $hdkey ] ) ); ?>>
								<span class="fbsseop-switch__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>

					<!-- 1-Click Uploads .htaccess PHP Blocker -->
					<div class="fbsseop-subbox">
						<h4><?php esc_html_e( 'wp-content/uploads/ PHP Execution Shield', 'fbs-secure-optimize' ); ?></h4>
						<p id="fbsseop-uploads-status-text">
							<?php if ( $uploads_safe ) : ?>
								<strong style="color:#059669;"><?php esc_html_e( '✓ Protected: Direct PHP script execution inside wp-content/uploads/ is blocked.', 'fbs-secure-optimize' ); ?></strong>
							<?php else : ?>
								<strong style="color:#d97706;"><?php esc_html_e( '⚠ Unprotected: Click below to generate .htaccess rules blocking backdoor PHP execution in uploads/.', 'fbs-secure-optimize' ); ?></strong>
							<?php endif; ?>
						</p>
						<?php if ( ! $uploads_safe ) : ?>
							<button type="button" id="fbsseop-protect-uploads-btn" class="fbsseop-btn fbsseop-btn--emerald">
								<?php esc_html_e( '1-Click Shield wp-content/uploads/', 'fbs-secure-optimize' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $risky_plugins ) ) : ?>
						<div class="fbsseop-subbox fbsseop-subbox--danger">
							<h4><?php esc_html_e( '⚠ High-Risk Active Plugins Detected', 'fbs-secure-optimize' ); ?></h4>
							<?php foreach ( $risky_plugins as $rp ) : ?>
								<div class="fbsseop-risky-item">
									<div>
										<strong><?php echo esc_html( $rp['name'] ); ?></strong>
										<p><?php echo esc_html( $rp['risk'] ); ?></p>
									</div>
									<button type="button" class="fbsseop-btn fbsseop-btn--danger fbsseop-deactivate-risky" data-plugin="<?php echo esc_attr( $rp['basename'] ); ?>">
										<?php esc_html_e( 'Deactivate', 'fbs-secure-optimize' ); ?>
									</button>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Right: Login Brute-Force Shield -->
				<div class="fbsseop-panel">
					<div class="fbsseop-panel__header">
						<div>
							<h2><?php esc_html_e( '3. Unified Login Brute-Force Shield', 'fbs-secure-optimize' ); ?></h2>
							<p><?php esc_html_e( 'Automatic IP lockouts and credential-stuffing protection.', 'fbs-secure-optimize' ); ?></p>
						</div>
					</div>

					<div class="fbsseop-setting-row">
						<div>
							<strong><?php esc_html_e( 'Limit Failed Login Attempts', 'fbs-secure-optimize' ); ?></strong>
							<span><?php esc_html_e( 'Automatically lock out attacking IPs after repeated failures.', 'fbs-secure-optimize' ); ?></span>
						</div>
						<label class="fbsseop-switch">
							<input type="checkbox" name="fbsseop[login_security][limit_login_attempts]" value="1" <?php checked( ! empty( $login['limit_login_attempts'] ) ); ?>>
							<span class="fbsseop-switch__slider"></span>
						</label>
					</div>

					<div class="fbsseop-setting-row">
						<div>
							<strong><?php esc_html_e( 'Mask Login Error Hints', 'fbs-secure-optimize' ); ?></strong>
							<span><?php esc_html_e( 'Hides whether an entered username exists on login failure.', 'fbs-secure-optimize' ); ?></span>
						</div>
						<label class="fbsseop-switch">
							<input type="checkbox" name="fbsseop[login_security][mask_login_errors]" value="1" <?php checked( ! empty( $login['mask_login_errors'] ) ); ?>>
							<span class="fbsseop-switch__slider"></span>
						</label>
					</div>

					<div class="fbsseop-setting-row">
						<div><strong><?php esc_html_e( 'Max Failed Attempts Before Lockout', 'fbs-secure-optimize' ); ?></strong></div>
						<input type="number" min="2" max="25" name="fbsseop[login_security][max_attempts]" value="<?php echo esc_attr( (string) $login['max_attempts'] ); ?>" class="fbsseop-input-num">
					</div>

					<div class="fbsseop-setting-row">
						<div><strong><?php esc_html_e( 'Lockout Duration (Minutes)', 'fbs-secure-optimize' ); ?></strong></div>
						<input type="number" min="1" max="1440" name="fbsseop[login_security][lockout_duration]" value="<?php echo esc_attr( (string) $login['lockout_duration'] ); ?>" class="fbsseop-input-num">
					</div>

					<div class="fbsseop-field-block">
						<label for="fbsseop-whitelist-ips"><strong><?php esc_html_e( 'Trusted IP Allowlist (One IP per line)', 'fbs-secure-optimize' ); ?></strong></label>
						<textarea id="fbsseop-whitelist-ips" name="fbsseop[login_security][whitelist_ips]" rows="3" placeholder="<?php echo esc_attr( FBS_Secure_Optimize_Login_Security::get_client_ip() ); ?>"><?php echo esc_textarea( (string) $login['whitelist_ips'] ); ?></textarea>
						<small>
							<?php
							printf(
								/* translators: %s: Current visitor IP. */
								esc_html__( 'Your current IP: %s', 'fbs-secure-optimize' ),
								esc_html( FBS_Secure_Optimize_Login_Security::get_client_ip() )
							);
							?>
						</small>
					</div>

					<div class="fbsseop-subbox">
						<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
							<h4 style="margin:0;"><?php esc_html_e( 'Recent Failed Login Attempts', 'fbs-secure-optimize' ); ?></h4>
							<button type="button" id="fbsseop-clear-lockouts-btn" class="fbsseop-btn fbsseop-btn--sm">
								<?php esc_html_e( 'Clear Lockouts', 'fbs-secure-optimize' ); ?>
							</button>
						</div>
						<?php if ( empty( $recent_fails ) ) : ?>
							<p style="margin:0;color:#64748b;font-size:13px;"><?php esc_html_e( 'No recent failed login attempts recorded.', 'fbs-secure-optimize' ); ?></p>
						<?php else : ?>
							<table class="fbsseop-mini-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'IP Address', 'fbs-secure-optimize' ); ?></th>
										<th><?php esc_html_e( 'Username', 'fbs-secure-optimize' ); ?></th>
										<th><?php esc_html_e( 'Time (UTC)', 'fbs-secure-optimize' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_fails as $fail ) : ?>
										<tr>
											<td><code><?php echo esc_html( $fail['ip_address'] ); ?></code></td>
											<td><?php echo esc_html( $fail['username'] ); ?></td>
											<td><?php echo esc_html( $fail['attempt_time'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="fbsseop-save-bar">
				<button type="submit" class="fbsseop-btn fbsseop-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Security & Hardening Settings', 'fbs-secure-optimize' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Tab 4: 6-Layer Malware, Core Checksum & Hidden-Admin Scanner.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $settings  Plugin settings.
	 * @param array<string, mixed> $last_scan Last scan summary.
	 * @param bool                 $is_pro    Whether Pro add-on is active.
	 * @return void
	 */
	private function render_scanner_tab( $settings, $last_scan, $is_pro ) {
		$scanner_enabled = ! empty( $settings['modules']['malware_scanner'] );
		?>
		<div class="fbsseop-panel">
			<div class="fbsseop-scanner-hero">
				<div>
					<h2><?php esc_html_e( '6-Layer Malware, Core Checksum & Hidden-Admin Scanner', 'fbs-secure-optimize' ); ?></h2>
					<p>
						<?php esc_html_e( 'Inspects unknown root PHP files, official WordPress.org MD5 checksums, zero-tolerance wp-content/uploads/ PHP shells, mu-plugins, plugin/theme backdoor signatures, wp_options injections, and hidden pre_user_query administrator accounts.', 'fbs-secure-optimize' ); ?>
					</p>
					<?php if ( ! empty( $last_scan['scanned_at'] ) ) : ?>
						<p class="fbsseop-scan-meta">
							<?php
							printf(
								/* translators: 1: Timestamp, 2: Scanned PHP file count, 3: Issue count. */
								esc_html__( 'Last Scan: %1$s • %2$d PHP files inspected • %3$d issue(s) flagged', 'fbs-secure-optimize' ),
								esc_html( (string) $last_scan['scanned_at'] ),
								(int) $last_scan['scanned_files'],
								(int) $last_scan['total_issues']
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<div>
					<?php if ( ! $scanner_enabled ) : ?>
						<span class="fbsseop-pill-warn"><?php esc_html_e( 'Enable Malware Scanner in Control Center first', 'fbs-secure-optimize' ); ?></span>
					<?php else : ?>
						<button type="button" id="fbsseop-run-scan-btn" class="fbsseop-btn fbsseop-btn--primary fbsseop-btn--lg">
							<span class="dashicons dashicons-shield-alt"></span>
							<span><?php esc_html_e( 'Run 6-Layer Deep Scan', 'fbs-secure-optimize' ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $is_pro ) : ?>
				<div class="fbsseop-pro-callout">
					<div>
						<strong><?php esc_html_e( 'Want 1-Click Malware Deletion, Auto-Quarantine Uploads & Real-Time Admin Privilege Disarming?', 'fbs-secure-optimize' ); ?></strong>
						<span><?php esc_html_e( 'Upgrade to FBS Secure Optimize Pro or request a Done-For-You Malware Eradication from FBS Fusion.', 'fbs-secure-optimize' ); ?></span>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fbs-secure-optimize&tab=firewall' ) ); ?>" class="fbsseop-btn fbsseop-btn--emerald">
						<?php esc_html_e( 'Explore Pro Features →', 'fbs-secure-optimize' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div id="fbsseop-scan-results" class="fbsseop-scan-results" style="display:none;"></div>

			<!-- Safe Code Preview Drawer -->
			<div id="fbsseop-preview-modal" class="fbsseop-modal" style="display:none;">
				<div class="fbsseop-modal__card">
					<div class="fbsseop-modal__head">
						<strong id="fbsseop-preview-title"><?php esc_html_e( 'File Snippet Preview', 'fbs-secure-optimize' ); ?></strong>
						<button type="button" id="fbsseop-close-modal" class="fbsseop-btn fbsseop-btn--sm"><?php esc_html_e( 'Close ✕', 'fbs-secure-optimize' ); ?></button>
					</div>
					<pre id="fbsseop-preview-code" class="fbsseop-code-box"></pre>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 5: Pro Firewall (WAF), Rate Limiter & Real-Time User Guard (or Upgrade Showcase).
	 *
	 * @since 2.0.0
	 * @param bool $is_pro Whether Pro add-on is active.
	 * @return void
	 */
	private function render_firewall_tab( $is_pro ) {
		if ( $is_pro && has_action( 'fbsseop_render_pro_firewall_tab' ) ) {
			/**
			 * Renders the full FBS Secure Optimize Pro WAF, Rate Limiter, User Guard & Custom CSP interface.
			 *
			 * @since 2.0.0
			 */
			do_action( 'fbsseop_render_pro_firewall_tab' );
			return;
		}
		?>
		<div class="fbsseop-pro-showcase">
			<div class="fbsseop-pro-showcase__head">
				<span class="fbsseop-badge fbsseop-badge--pro"><?php esc_html_e( 'FBS SECURE OPTIMIZE PRO', 'fbs-secure-optimize' ); ?></span>
				<h2><?php esc_html_e( 'Upgrade to Active Firewall, Bot Rate Limiting & Real-Time Admin Privilege Guard', 'fbs-secure-optimize' ); ?></h2>
				<p><?php esc_html_e( 'Engineered by FBS Fusion from real-world WordPress & WooCommerce forensic incident response.', 'fbs-secure-optimize' ); ?></p>
			</div>

			<div class="fbsseop-modules-grid">
				<div class="fbsseop-module-card is-enabled">
					<h3>🛡️ <?php esc_html_e( 'Early-Boot Web Application Firewall (WAF)', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Intercepts SQL Injection (SQLi), Cross-Site Scripting (XSS), directory traversal, malicious bots, and blocked IPs at priority 0—with safe Learning Mode.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-module-card is-enabled">
					<h3>⚡ <?php esc_html_e( '429 Anti-Bot Flood Rate Limiter', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Stops aggressive scraper bots and DDoS request floods from causing 502 Bad Gateway crashes with sliding-window 429 rate limiting.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-module-card is-enabled">
					<h3>👤 <?php esc_html_e( 'Real-Time User & Capability Integrity Guard', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Locks an Approved Admin Whitelist and instantly disarms unauthorized usermeta capability escalation while destroying rogue login sessions.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-module-card is-enabled">
					<h3>🧹 <?php esc_html_e( '1-Click Malware Eradication & Auto-Quarantine', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Delete backdoor PHP files, malicious database snippets, and rogue users directly from the 6-Layer Scanner + daily scheduled email alerts.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-module-card is-enabled">
					<h3>🔒 <?php esc_html_e( 'Custom Content-Security-Policy (CSP) Builder', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Test CSP rules safely in Report-Only mode before enforcing Grade A+ SecurityHeaders.com protection without breaking Google Fonts or Stripe.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-module-card is-enabled">
					<h3>🚀 <?php esc_html_e( 'Need Hands-On Speed or Malware Rescue?', 'fbs-secure-optimize' ); ?></h3>
					<p><?php esc_html_e( 'Hire Fazle Bari & the FBS Fusion engineering studio for guaranteed 95–100/100 Lighthouse optimization and deep server malware cleanup.', 'fbs-secure-optimize' ); ?></p>
				</div>
			</div>

			<div class="fbsseop-pro-showcase__cta">
				<a href="https://fbsfusion.com/plugins/" target="_blank" rel="noopener noreferrer" class="fbsseop-btn fbsseop-btn--emerald fbsseop-btn--lg">
					<?php esc_html_e( 'Get FBS Secure Optimize Pro ↗', 'fbs-secure-optimize' ); ?>
				</a>
				<a href="https://fbsfusion.com/hire-us/" target="_blank" rel="noopener noreferrer" class="fbsseop-btn fbsseop-btn--primary fbsseop-btn--lg">
					<?php esc_html_e( 'Request Free Engineering Audit at FBSFusion.com ↗', 'fbs-secure-optimize' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render "Our Plugins" showcase template.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function render_our_plugins_page() {
		$template = FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'admin/templates/our-plugins.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Render "Meet The Author" profile template.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function render_author_page() {
		$template = FBS_SECURE_OPTIMIZE_PLUGIN_DIR . 'admin/templates/plugin-author.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
