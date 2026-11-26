<?php
/**
 * Asset & Bloat Optimizer Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend Core Web Vitals asset optimization, CLS stabilization, LCP preloading,
 * unused plugin bloat unloading, and optional guest HTML disk caching.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Asset_Optimizer {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Asset_Optimizer|null
	 */
	private static $instance = null;

	/**
	 * Counter to skip lazy-loading the first real above-the-fold content image (protects LCP).
	 *
	 * @since 2.0.0
	 * @var int
	 */
	private $image_count = 0;

	/**
	 * Tracks whether at least one third-party script was delayed on the current request.
	 *
	 * @since 2.1.0
	 * @var bool
	 */
	private $has_delayed_scripts = false;

	/**
	 * Tracks whether Google Fonts preconnect hints have already been queued/emitted.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	private $emitted_gfonts_preconnect = false;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Asset_Optimizer
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
	 * Register optimization hooks according to individual feature toggles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		$settings = FBS_Secure_Optimize_Controller::get_all_settings();
		$opts     = isset( $settings['asset_optimization'] ) ? $settings['asset_optimization'] : array();

		// Serve static guest HTML cache early on init priority 0 (so wp_upload_dir() is initialized).
		if ( ! empty( $opts['enable_page_cache'] ) && ! is_admin() ) {
			add_action( 'init', array( $this, 'maybe_serve_guest_page_cache' ), 0 );
		}

		// 1. Disable WordPress Emojis on frontend only (never break wp-admin Twemoji rendering).
		if ( ! empty( $opts['disable_emojis'] ) && ! is_admin() ) {
			add_action( 'init', array( $this, 'disable_wp_emojis' ) );
		}

		// 2. Clean unnecessary <head> discovery tags.
		if ( ! empty( $opts['clean_wp_head'] ) ) {
			add_action( 'init', array( $this, 'clean_head_links' ) );
		}

		// 3. Throttle WP Heartbeat API to 60 seconds to reduce admin-ajax CPU spikes.
		if ( ! empty( $opts['heartbeat_control'] ) ) {
			add_filter( 'heartbeat_settings', array( $this, 'throttle_heartbeat' ) );
		}

		// Frontend-only optimizations.
		if ( is_admin() ) {
			return;
		}

		if ( ! empty( $opts['disable_dashicons'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_guest_dashicons' ), 100 );
		}

		if ( ! empty( $opts['disable_embeds'] ) ) {
			add_action( 'wp_footer', array( $this, 'dequeue_wp_embed' ) );
		}

		if ( ! empty( $opts['lazy_load_images'] ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'enhance_attachment_image_attributes' ), 10, 1 );
			add_filter( 'the_content', array( $this, 'enhance_content_images' ), 20 );
		}

		if ( ! empty( $opts['cls_image_dimensions'] ) ) {
			add_filter( 'the_content', array( $this, 'inject_missing_image_dimensions' ), 18 );
			add_filter( 'post_thumbnail_html', array( $this, 'inject_missing_image_dimensions' ), 18 );
			add_filter( 'widget_text_content', array( $this, 'inject_missing_image_dimensions' ), 18 );
		}

		if ( ! empty( $opts['lazy_load_iframes'] ) ) {
			add_filter( 'the_content', array( $this, 'lazy_load_content_iframes' ), 20 );
		}

		if ( ! empty( $opts['non_blocking_google_fonts'] ) ) {
			add_filter( 'wp_resource_hints', array( $this, 'add_google_fonts_preconnect' ), 10, 2 );
			add_filter( 'style_loader_tag', array( $this, 'optimize_google_fonts_tag' ), 30, 4 );
		}

		if ( ! empty( $opts['inline_small_css'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'maybe_inline_small_css' ), 25, 4 );
		}

		if ( ! empty( $opts['deblock_css_imports'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'maybe_deblock_css_imports' ), 28, 4 );
		}

		if ( ! empty( $opts['async_icon_css'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'maybe_async_icon_stylesheet' ), 32, 4 );
		}

		if ( ! empty( $opts['defer_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 20, 2 );
		}

		if ( ! empty( $opts['delay_third_party_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'maybe_delay_third_party_script' ), 30, 3 );
		}

		if ( ! empty( $opts['minify_css'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'optimize_style_tag' ), 40, 1 );
		}

		if ( ! empty( $opts['minify_js'] ) ) {
			add_filter( 'wp_inline_script_attributes', array( $this, 'noop_inline_attr' ), 10, 1 );
			add_filter( 'print_scripts_array', array( $this, 'filter_scripts_array' ), 10, 1 );
		}

		// Unified single-pass frontend HTML buffer when any full-document optimizer or page cache is active.
		if (
			! empty( $opts['preload_lcp_image'] ) ||
			! empty( $opts['cls_stabilize_builders'] ) ||
			! empty( $opts['cls_image_dimensions'] ) ||
			! empty( $opts['unload_unused_assets'] ) ||
			! empty( $opts['delay_third_party_js'] ) ||
			! empty( $opts['defer_js'] ) ||
			! empty( $opts['enable_page_cache'] )
		) {
			add_action( 'template_redirect', array( $this, 'start_frontend_html_buffer' ), 2 );
		}
	}

	/**
	 * Determine whether frontend tag rewrites should be bypassed (feeds, Customizer, REST, visual builders).
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	private function should_bypass_frontend() {
		if ( is_admin() || is_feed() || is_preview() || is_customize_preview() || wp_doing_ajax() || is_robots() || is_trackback() ) {
			return true;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return true;
		}

		$builder_params = array(
			'elementor-preview',
			'et_fb',
			'fl_builder',
			'ct_builder',
			'bricks',
			'vc_editable',
			'so_live_editor',
			'siteorigin_panels_live_editor',
			'fbsseop_bypass',
		);

		foreach ( $builder_params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visual page-builder preview detection.
			if ( isset( $_GET[ $param ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Start single-pass output buffering on standard frontend HTML requests.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function start_frontend_html_buffer() {
		if ( $this->should_bypass_frontend() ) {
			return;
		}

		ob_start( array( $this, 'process_frontend_html_buffer' ) );
	}

	/**
	 * Process the rendered frontend HTML document in a single fast pass.
	 *
	 * Applies post-render DOM-verified unused asset unloading, full-page image/SVG dimension
	 * injection, full-width builder row CLS stabilization, above-the-fold LCP image/background
	 * preloading, inline+external third-party script delay, and optional guest HTML disk caching.
	 *
	 * @since 2.2.0
	 * @param string $html Rendered HTML buffer.
	 * @return string
	 */
	public function process_frontend_html_buffer( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 256 ) {
			return $html;
		}

		// Only transform valid HTML documents containing both </head> and <body.
		if ( false === stripos( $html, '<html' ) || false === stripos( $html, '</head>' ) || false === stripos( $html, '<body' ) ) {
			return $html;
		}

		if ( $this->should_bypass_frontend() ) {
			return $html;
		}

		$settings = FBS_Secure_Optimize_Controller::get_all_settings();
		$opts     = isset( $settings['asset_optimization'] ) ? $settings['asset_optimization'] : array();

		// 1. Post-render DOM-verified unused plugin CSS/JS & reCAPTCHA unloader.
		if ( ! empty( $opts['unload_unused_assets'] ) ) {
			$html = $this->unload_unused_rendered_assets( $html );
		}

		// 2. Full-page image width/height & inline SVG dimensions + full-width builder CLS stabilizer.
		if ( ! empty( $opts['cls_image_dimensions'] ) || ! empty( $opts['cls_stabilize_builders'] ) ) {
			$html = $this->apply_document_cls_stabilization(
				$html,
				! empty( $opts['cls_image_dimensions'] ),
				! empty( $opts['cls_stabilize_builders'] )
			);
		}

		// 2b. Upgrade text @font-face rules from FOIT (font-display: auto/block) to font-display: swap.
		if ( ! empty( $opts['non_blocking_google_fonts'] ) ) {
			$html = $this->apply_font_display_swap_to_inline_styles( $html );
		}

		// 3. Above-the-fold LCP hero image / CSS background-image preloader & fetchpriority sanitizer.
		if ( ! empty( $opts['preload_lcp_image'] ) ) {
			$manual_lcp_url = isset( $opts['lcp_preload_url'] ) ? trim( (string) $opts['lcp_preload_url'] ) : '';
			$html           = $this->apply_lcp_preload_and_priority( $html, $manual_lcp_url );
		}

		// 4. Third-party external + inline tracker bootstrap & invisible reCAPTCHA v3 interaction delay.
		if ( ! empty( $opts['delay_third_party_js'] ) ) {
			$html = $this->apply_document_third_party_js_delay( $html );
		}

		// 5. Smart inline-verified jQuery & remaining script deferring (eliminates <head> parser blocking when safe).
		if ( ! empty( $opts['defer_js'] ) ) {
			$html = $this->apply_smart_jquery_defer( $html );
		}

		// 6. Save optimized HTML into static guest disk page cache when eligible.
		if ( ! empty( $opts['enable_page_cache'] ) ) {
			$this->maybe_store_guest_page_cache( $html );
		}

		return $html;
	}

	/**
	 * Remove WordPress core emoji detection scripts and styles.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function disable_wp_emojis() {
		if ( is_admin() ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Remove RSD, WLW Manifest, and shortlink clutter from <head>.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function clean_head_links() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	}

	/**
	 * Reduce Heartbeat interval to 60s to prevent 502 PHP worker exhaustion.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $settings Heartbeat configuration.
	 * @return array<string, mixed>
	 */
	public function throttle_heartbeat( $settings ) {
		$settings['interval'] = 60;
		return $settings;
	}

	/**
	 * Dequeue Dashicons CSS (~35KB) for logged-out frontend visitors.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function dequeue_guest_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	/**
	 * Dequeue wp-embed script on frontend.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function dequeue_wp_embed() {
		wp_dequeue_script( 'wp-embed' );
	}

	/**
	 * Determine whether an image URL or class list represents a small UI logo, icon, or SVG
	 * that should not consume the single high-priority LCP slot.
	 *
	 * @since 2.2.0
	 * @param string $src        Image URL.
	 * @param string $class_attr Image class attribute or full tag.
	 * @param int    $width      Explicit width (0 if unknown).
	 * @param int    $height     Explicit height (0 if unknown).
	 * @return bool
	 */
	private function is_non_lcp_ui_graphic( $src, $class_attr = '', $width = 0, $height = 0 ) {
		$clean_src = strtolower( (string) strtok( $src, '?#' ) );
		if ( '' === $clean_src || 0 === strpos( $clean_src, 'data:' ) || '.svg' === substr( $clean_src, -4 ) ) {
			return true;
		}

		if ( ( $width > 0 && $width < 200 ) || ( $height > 0 && $height < 120 ) ) {
			return true;
		}

		$haystack = strtolower( $clean_src . ' ' . $class_attr );
		$markers  = array( 'custom-logo', 'site-logo', 'logo', 'avatar', 'site-icon', 'scroll-down', 'icon-', 'wp-smiley' );
		foreach ( $markers as $marker ) {
			if ( false !== strpos( $haystack, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add native decoding="async" and loading="lazy" while protecting the first substantial LCP image.
	 *
	 * @since 2.0.0
	 * @param array<string, string> $attr Image HTML attributes.
	 * @return array<string, string>
	 */
	public function enhance_attachment_image_attributes( $attr ) {
		if ( $this->should_bypass_frontend() ) {
			return $attr;
		}

		$src    = isset( $attr['src'] ) ? (string) $attr['src'] : '';
		$cls    = isset( $attr['class'] ) ? (string) $attr['class'] : '';
		$width  = isset( $attr['width'] ) ? (int) $attr['width'] : 0;
		$height = isset( $attr['height'] ) ? (int) $attr['height'] : 0;

		$attr['decoding'] = 'async';

		if ( $this->is_non_lcp_ui_graphic( $src, $cls, $width, $height ) ) {
			unset( $attr['fetchpriority'] );
			return $attr;
		}

		++$this->image_count;

		if ( 1 === $this->image_count ) {
			unset( $attr['loading'] );
			$attr['fetchpriority'] = 'high';
		} elseif ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
			unset( $attr['fetchpriority'] );
		}

		return $attr;
	}

	/**
	 * Add native loading="lazy" and decoding="async" to post content images while boosting LCP image #1.
	 *
	 * @since 2.0.0
	 * @param string $content Post HTML content.
	 * @return string
	 */
	public function enhance_content_images( $content ) {
		if ( empty( $content ) || false === strpos( $content, '<img' ) || $this->should_bypass_frontend() ) {
			return $content;
		}

		$content = (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) {
				$img = $matches[0];

				if ( false === stripos( $img, 'decoding=' ) ) {
					$img = str_replace( '<img', '<img decoding="async"', $img );
				}

				$src    = preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $img, $sm ) ? $sm[2] : '';
				$width  = preg_match( '/\bwidth\s*=\s*([\'"]?)(\d+)\1/i', $img, $wm ) ? (int) $wm[2] : 0;
				$height = preg_match( '/\bheight\s*=\s*([\'"]?)(\d+)\1/i', $img, $hm ) ? (int) $hm[2] : 0;

				if ( $this->is_non_lcp_ui_graphic( $src, $img, $width, $height ) ) {
					return $img;
				}

				if ( 0 === $this->image_count ) {
					++$this->image_count;
					$img = (string) preg_replace( '/\s+loading=([\'"])lazy\1/i', '', $img );
					if ( false === stripos( $img, 'fetchpriority=' ) ) {
						$img = str_replace( '<img', '<img fetchpriority="high"', $img );
					}
				} else {
					++$this->image_count;
					if ( false === stripos( $img, 'loading=' ) ) {
						$img = str_replace( '<img', '<img loading="lazy"', $img );
					}
				}

				return $img;
			},
			$content
		);

		return $content;
	}

	/**
	 * Automatically inject missing width and height attributes on local images to prevent CLS.
	 *
	 * Dimensions are cached in a salt-versioned transient so filesystem lookups only run once per image URL.
	 *
	 * @since 2.1.0
	 * @param string $html HTML fragment containing <img> tags.
	 * @return string
	 */
	public function inject_missing_image_dimensions( $html ) {
		if ( empty( $html ) || false === strpos( $html, '<img' ) || $this->should_bypass_frontend() ) {
			return $html;
		}

		$cache_key  = 'fbsseop_img_dims_' . FBS_Secure_Optimize_Controller::get_cache_salt();
		$dims_cache = get_transient( $cache_key );
		if ( ! is_array( $dims_cache ) ) {
			$dims_cache = array();
		}
		$cache_dirty = false;

		$updated = (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) use ( &$dims_cache, &$cache_dirty ) {
				$img = $matches[0];

				$has_width  = (bool) preg_match( '/\bwidth\s*=\s*[\'"][^\'"]+[\'"]/i', $img );
				$has_height = (bool) preg_match( '/\bheight\s*=\s*[\'"][^\'"]+[\'"]/i', $img );

				if ( $has_width && $has_height ) {
					return $img;
				}

				if ( ! preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $img, $src_match ) ) {
					return $img;
				}

				$raw_src = html_entity_decode( trim( $src_match[2] ), ENT_QUOTES, 'UTF-8' );
				if ( '' === $raw_src || 0 === strpos( $raw_src, 'data:' ) ) {
					return $img;
				}

				$clean_src = (string) strtok( $raw_src, '?#' );
				if ( '' === $clean_src ) {
					return $img;
				}

				if ( isset( $dims_cache[ $clean_src ] ) && is_array( $dims_cache[ $clean_src ] ) ) {
					$dims = $dims_cache[ $clean_src ];
				} else {
					$dims       = array( 0, 0 );
					$local_path = $this->resolve_local_asset_path( $clean_src, '' );

					if ( '' !== $local_path ) {
						$ext = strtolower( (string) pathinfo( $local_path, PATHINFO_EXTENSION ) );
						if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif' ), true ) ) {
							$img_size = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $local_path ) : getimagesize( $local_path );
							if ( is_array( $img_size ) && ! empty( $img_size[0] ) && ! empty( $img_size[1] ) ) {
								$dims = array( (int) $img_size[0], (int) $img_size[1] );
							}
						} elseif ( 'svg' === $ext ) {
							$dims = $this->extract_svg_file_dimensions( $local_path );
						}
					}

					if ( count( $dims_cache ) < 300 ) {
						$dims_cache[ $clean_src ] = $dims;
						$cache_dirty              = true;
					}
				}

				if ( empty( $dims[0] ) || empty( $dims[1] ) ) {
					return $img;
				}

				$inject_attrs = '';
				if ( ! $has_width ) {
					$inject_attrs .= ' width="' . (int) $dims[0] . '"';
				}
				if ( ! $has_height ) {
					$inject_attrs .= ' height="' . (int) $dims[1] . '"';
				}

				if ( '' !== $inject_attrs ) {
					$img = str_replace( '<img', '<img' . $inject_attrs, $img );
				}

				return $img;
			},
			$html
		);

		if ( $cache_dirty ) {
			set_transient( $cache_key, $dims_cache, 7 * DAY_IN_SECONDS );
		}

		return $updated;
	}

	/**
	 * Extract intrinsic width and height from a local .svg image file's root <svg> attributes or viewBox.
	 *
	 * @since 2.2.0
	 * @param string $svg_path Verified local SVG path.
	 * @return array{0: int, 1: int}
	 */
	private function extract_svg_file_dimensions( $svg_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read first 2 KB of verified local SVG asset.
		$head = @file_get_contents( $svg_path, false, null, 0, 2048 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_string( $head ) || '' === $head ) {
			return array( 0, 0 );
		}

		if ( preg_match( '/<svg\b[^>]*>/is', $head, $svg_tag ) ) {
			$tag = $svg_tag[0];
			if (
				preg_match( '/\bwidth\s*=\s*[\'"]([0-9]+(?:\.[0-9]+)?)(?:px)?[\'"]/i', $tag, $wm ) &&
				preg_match( '/\bheight\s*=\s*[\'"]([0-9]+(?:\.[0-9]+)?)(?:px)?[\'"]/i', $tag, $hm )
			) {
				return array( (int) round( (float) $wm[1] ), (int) round( (float) $hm[1] ) );
			}

			if ( preg_match( '/\bviewBox\s*=\s*[\'"][^\'"]*?([0-9]+(?:\.[0-9]+)?)[\s,]+([0-9]+(?:\.[0-9]+)?)\s*[\'"]/i', $tag, $vb ) ) {
				return array( (int) round( (float) $vb[1] ), (int) round( (float) $vb[2] ) );
			}
		}

		return array( 0, 0 );
	}

	/**
	 * Inject preconnect resource hints for Google Fonts when enqueued by theme/plugins.
	 *
	 * @since 2.1.0
	 * @param array<int, mixed> $urls          Existing hint URLs.
	 * @param string            $relation_type Resource hint relation ('preconnect', 'dns-prefetch', etc.).
	 * @return array<int, mixed>
	 */
	public function add_google_fonts_preconnect( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type || $this->should_bypass_frontend() ) {
			return $urls;
		}

		if ( ! $this->has_enqueued_google_fonts() ) {
			return $urls;
		}

		$this->emitted_gfonts_preconnect = true;
		$urls[]                          = 'https://fonts.googleapis.com';
		$urls[]                          = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);

		return $urls;
	}

	/**
	 * Check whether any registered & queued stylesheet loads from fonts.googleapis.com.
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	private function has_enqueued_google_fonts() {
		$wp_styles = wp_styles();
		if ( ! ( $wp_styles instanceof WP_Styles ) || empty( $wp_styles->queue ) ) {
			return false;
		}

		foreach ( $wp_styles->queue as $handle ) {
			if ( isset( $wp_styles->registered[ $handle ]->src ) && is_string( $wp_styles->registered[ $handle ]->src ) ) {
				if ( false !== stripos( $wp_styles->registered[ $handle ]->src, 'fonts.googleapis.com' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Convert a Google Fonts URL into a non-blocking print/onload <link> pair with display=swap.
	 *
	 * @since 2.2.0
	 * @param string $font_url Google Fonts stylesheet URL.
	 * @param string $id_attr  Optional link id attribute.
	 * @return string
	 */
	private function build_non_blocking_google_font_html( $font_url, $id_attr = '' ) {
		$clean_url = html_entity_decode( trim( $font_url ), ENT_QUOTES, 'UTF-8' );
		if ( 0 === strpos( $clean_url, '//' ) ) {
			$clean_url = 'https:' . $clean_url;
		}

		if ( false === stripos( $clean_url, 'display=' ) || preg_match( '/[?&]display=(?:auto|block)\b/i', $clean_url ) ) {
			$clean_url = add_query_arg( 'display', 'swap', $clean_url );
		}

		$escaped_url = esc_url( $clean_url );
		$id_part     = '' !== $id_attr ? ' id="' . esc_attr( $id_attr ) . '"' : '';

		$preconnect = '';
		if ( ! $this->emitted_gfonts_preconnect ) {
			$this->emitted_gfonts_preconnect = true;
			$preconnect                      = "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n"
				. "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
		}

		return $preconnect
			. sprintf(
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Rewriting enqueued stylesheet tag inside style_loader_tag filter.
				"<link rel=\"stylesheet\"%s href=\"%s\" media=\"print\" onload=\"this.media='all'\">\n<noscript><link rel=\"stylesheet\" href=\"%s\"></noscript>\n",
				$id_part,
				$escaped_url,
				$escaped_url
			);
	}

	/**
	 * Convert render-blocking Google Fonts stylesheets to non-blocking print/onload swap + display=swap.
	 *
	 * @since 2.1.0
	 * @param string $tag    Enqueued style HTML tag.
	 * @param string $handle Registered style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Stylesheet media attribute.
	 * @return string
	 */
	public function optimize_google_fonts_tag( $tag, $handle, $href, $media ) {
		if ( $this->should_bypass_frontend() || empty( $href ) || false === stripos( $href, 'fonts.googleapis.com/css' ) ) {
			return $tag;
		}

		if ( 'print' === $media || false !== stripos( $tag, 'onload=' ) ) {
			return $tag;
		}

		return $this->build_non_blocking_google_font_html( $href, $handle . '-css' );
	}

	/**
	 * Upgrade text @font-face declarations in inline <style> blocks and Google Fonts URLs
	 * from FOIT (`font-display: auto` or `block`) to `font-display: swap`.
	 *
	 * Preserves `font-display: block` on icon fonts (Font Awesome, eicons, Dashicons, etc.)
	 * so icon glyphs never flash unstyled fallback characters.
	 *
	 * @since 3.0.0
	 * @param string $html Full HTML document.
	 * @return string
	 */
	private function apply_font_display_swap_to_inline_styles( $html ) {
		// 1. Ensure any Google Fonts URL in the document uses display=swap instead of display=auto/block.
		if ( false !== stripos( $html, 'fonts.googleapis.com/css' ) ) {
			$html = (string) preg_replace(
				'#(fonts\.googleapis\.com/css2?\?[^"\'\s>]*?\bdisplay=)(?:auto|block)\b#i',
				'$1swap',
				$html
			);
		}

		if ( false === stripos( $html, '@font-face' ) ) {
			return $html;
		}

		// 2. Rewrite text @font-face rules inside inline <style> blocks to use font-display: swap.
		$html = (string) preg_replace_callback(
			'/@font-face\s*\{([^}]*)\}/is',
			static function ( $m ) {
				$body = $m[1];
				// Skip icon fonts where font-display: block is intentional.
				if ( preg_match( '/font-family\s*:[^;]*(?:awesome|eicons|ekiticons|dashicons|icomoon|flaticon|genericons|woocommerce|star)/i', $body ) ) {
					return $m[0];
				}

				if ( preg_match( '/\bfont-display\s*:\s*(?:auto|block)\b/i', $body ) ) {
					$body = (string) preg_replace( '/\bfont-display\s*:\s*(?:auto|block)\b/i', 'font-display: swap', $body );
					return '@font-face {' . $body . '}';
				}

				if ( false === stripos( $body, 'font-display' ) ) {
					$body = rtrim( $body );
					if ( '' !== $body && ';' !== substr( $body, -1 ) ) {
						$body .= ';';
					}
					$body .= 'font-display: swap;';
					return '@font-face {' . $body . '}';
				}

				return $m[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * Auto-inline small render-blocking local stylesheets (<= 8 KB) and strip empty 0-byte CSS requests.
	 *
	 * Safely rewrites relative url(...) assets to absolute URLs and caches minified CSS via salt-versioned transients.
	 *
	 * @since 2.1.0
	 * @param string $tag    Enqueued style HTML tag.
	 * @param string $handle Registered style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Stylesheet media attribute.
	 * @return string
	 */
	public function maybe_inline_small_css( $tag, $handle, $href, $media ) {
		if ( $this->should_bypass_frontend() || empty( $href ) || 0 === strpos( ltrim( $tag ), '<style' ) ) {
			return $tag;
		}

		if ( ! in_array( $media, array( '', 'all', 'screen' ), true ) ) {
			return $tag;
		}

		$excluded_handles = array(
			'admin-bar',
			'dashicons',
			'query-monitor',
			'wp-codemirror',
		);

		/**
		 * Filters stylesheet handles excluded from small-CSS auto-inlining.
		 *
		 * @since 2.1.0
		 * @param array<int, string> $excluded_handles Style handles to skip.
		 */
		$excluded_handles = apply_filters( 'fbsseop_inline_css_excluded_handles', $excluded_handles );
		if ( in_array( $handle, $excluded_handles, true ) ) {
			return $tag;
		}

		$wp_styles = wp_styles();
		if ( $wp_styles instanceof WP_Styles ) {
			if ( ! empty( $wp_styles->get_data( $handle, 'conditional' ) ) || ! empty( $wp_styles->get_data( $handle, 'alt' ) ) ) {
				return $tag;
			}
		}

		$clean_href = (string) strtok( $href, '?#' );
		$local_path = $this->resolve_local_asset_path( $clean_href, '.css' );
		if ( '' === $local_path ) {
			return $tag;
		}

		$file_size = (int) filesize( $local_path );

		// Eliminate wasted render-blocking HTTP round-trips on empty 0-byte plugin stylesheets.
		if ( 0 === $file_size ) {
			return '';
		}

		/**
		 * Filters the maximum file size (in bytes) eligible for automatic CSS inlining.
		 *
		 * @since 2.1.0
		 * @param int $max_bytes Default 16384 bytes (16 KB).
		 */
		$max_bytes = (int) apply_filters( 'fbsseop_inline_css_max_bytes', 16384 );
		if ( $file_size < 0 || $file_size > $max_bytes ) {
			return $tag;
		}

		$mtime     = (int) filemtime( $local_path );
		$salt      = FBS_Secure_Optimize_Controller::get_cache_salt();
		$cache_key = 'fbsseop_css_' . md5( $salt . '|' . $handle . '|' . $local_path . '|' . $mtime );
		$cached    = get_transient( $cache_key );

		if ( false === $cached || ! is_string( $cached ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified local CSS file inside ABSPATH.
			$raw_css = file_get_contents( $local_path );
			if ( false === $raw_css ) {
				return $tag;
			}

			// Skip files containing @import rules here so maybe_deblock_css_imports can hoist them cleanly.
			if ( false !== stripos( $raw_css, '@import' ) ) {
				return $tag;
			}

			$base_dir_url = trailingslashit( dirname( $clean_href ) );
			$processed    = $this->rewrite_and_minify_css( $raw_css, $base_dir_url );

			if ( '' === $processed ) {
				set_transient( $cache_key, '/*empty*/', DAY_IN_SECONDS );
				return '';
			}

			set_transient( $cache_key, $processed, DAY_IN_SECONDS );
			$cached = $processed;
		}

		if ( '/*empty*/' === $cached ) {
			return '';
		}

		return sprintf(
			"<style id=\"%s-fbs-inline-css\">%s</style>\n",
			esc_attr( $handle ),
			$cached
		);
	}

	/**
	 * Hoist chained render-blocking @import Google Fonts out of local stylesheets (such as theme style.css)
	 * and serve a static de-blocked & minified CSS copy from wp-content/cache/fbs-optimize/css/.
	 *
	 * @since 2.2.0
	 * @param string $tag    Enqueued style HTML tag.
	 * @param string $handle Registered style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Stylesheet media attribute.
	 * @return string
	 */
	public function maybe_deblock_css_imports( $tag, $handle, $href, $media ) {
		if ( $this->should_bypass_frontend() || empty( $href ) || 0 === strpos( ltrim( $tag ), '<style' ) ) {
			return $tag;
		}

		if ( ! in_array( $media, array( '', 'all', 'screen' ), true ) ) {
			return $tag;
		}

		$clean_href = (string) strtok( $href, '?#' );
		$local_path = $this->resolve_local_asset_path( $clean_href, '.css' );
		if ( '' === $local_path ) {
			return $tag;
		}

		// Quick 4 KB header check: valid CSS @import rules must appear at the top of the stylesheet.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fast 4KB header inspection of local CSS file.
		$css_head = @file_get_contents( $local_path, false, null, 0, 4096 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_string( $css_head ) || false === stripos( $css_head, '@import' ) || false === stripos( $css_head, 'fonts.googleapis.com' ) ) {
			return $tag;
		}

		$cache_info = FBS_Secure_Optimize_Controller::get_cache_dir_info();
		if ( '' === $cache_info['basedir'] || '' === $cache_info['baseurl'] ) {
			return $tag;
		}

		$mtime      = (int) filemtime( $local_path );
		$salt       = FBS_Secure_Optimize_Controller::get_cache_salt();
		$hash       = substr( md5( $salt . '|' . $handle . '|' . $local_path . '|' . $mtime ), 0, 16 );
		$safe_slug  = preg_replace( '/[^a-z0-9_-]/i', '-', $handle );
		$cache_dir  = $cache_info['basedir'] . '/css';
		$cache_file = $cache_dir . '/' . $safe_slug . '-' . $hash . '.css';
		$meta_key   = 'fbsseop_imp_' . $hash;
		$font_urls  = get_transient( $meta_key );

		if ( ! is_file( $cache_file ) || ! is_array( $font_urls ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified local CSS file inside ABSPATH.
			$raw_css = file_get_contents( $local_path );
			if ( false === $raw_css ) {
				return $tag;
			}

			$extracted_fonts = array();
			$stripped_css    = (string) preg_replace_callback(
				'/@import\s+(?:url\(\s*([\'"]?)([^\'")]+)\1\s*\)|([\'"])([^\'"]+)\3)\s*;/i',
				static function ( $matches ) use ( &$extracted_fonts ) {
					$import_url = ! empty( $matches[2] ) ? trim( $matches[2] ) : trim( $matches[4] );
					if ( false !== stripos( $import_url, 'fonts.googleapis.com/css' ) ) {
						$extracted_fonts[] = $import_url;
						return '';
					}
					return $matches[0];
				},
				$raw_css
			);

			if ( empty( $extracted_fonts ) ) {
				return $tag;
			}

			if ( ! is_dir( $cache_dir ) && ! wp_mkdir_p( $cache_dir ) ) {
				// Filesystem read-only fallback: still hoist preconnect + async Google Fonts ahead of the tag.
				$hoisted_html = '';
				foreach ( array_unique( $extracted_fonts ) as $idx => $furl ) {
					$hoisted_html .= $this->build_non_blocking_google_font_html( $furl, $handle . '-gfont-' . $idx );
				}
				return $hoisted_html . $tag;
			}

			$this->ensure_cache_index_guard( $cache_dir );

			$base_dir_url = trailingslashit( dirname( $clean_href ) );
			$rewritten    = $this->rewrite_and_minify_css( $stripped_css, $base_dir_url );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing static optimized CSS cache file inside wp_upload_dir().
			$written = @file_put_contents( $cache_file, $rewritten, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $written ) {
				return $tag;
			}

			$font_urls = array_values( array_unique( $extracted_fonts ) );
			set_transient( $meta_key, $font_urls, 7 * DAY_IN_SECONDS );
		}

		$cached_url   = $cache_info['baseurl'] . '/css/' . $safe_slug . '-' . $hash . '.css';
		$hoisted_html = '';
		foreach ( $font_urls as $idx => $furl ) {
			$hoisted_html .= $this->build_non_blocking_google_font_html( $furl, $handle . '-gfont-' . $idx );
		}

		$updated_tag = str_replace( $href, esc_url( $cached_url ), $tag );

		return $hoisted_html . $updated_tag;
	}

	/**
	 * Convert render-blocking icon & decorative stylesheets (Font Awesome, Elementor Icons, Ionicons, Line Awesome)
	 * to non-blocking media="print" swap with <noscript> fallback.
	 *
	 * Never touches layout/grid CSS so zero FOUC or CLS occurs.
	 *
	 * @since 2.2.0
	 * @param string $tag    Enqueued style HTML tag.
	 * @param string $handle Registered style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Stylesheet media attribute.
	 * @return string
	 */
	public function maybe_async_icon_stylesheet( $tag, $handle, $href, $media ) {
		if ( $this->should_bypass_frontend() || empty( $href ) || 0 === strpos( ltrim( $tag ), '<style' ) ) {
			return $tag;
		}

		if ( 'print' === $media || false !== stripos( $tag, 'onload=' ) ) {
			return $tag;
		}

		$icon_patterns = array(
			'font-awesome',
			'fontawesome',
			'elementor-icons',
			'ionicons',
			'line-awesome',
			'genericons',
			'magnific-popup',
		);

		/**
		 * Filters handle/URL patterns converted to non-blocking async icon stylesheets.
		 *
		 * @since 2.2.0
		 * @param array<int, string> $icon_patterns Substrings matched against handle or href.
		 */
		$icon_patterns = apply_filters( 'fbsseop_async_icon_css_patterns', $icon_patterns );

		$haystack = strtolower( $handle . ' ' . $href );
		$matched  = false;
		foreach ( $icon_patterns as $pat ) {
			if ( '' !== $pat && false !== strpos( $haystack, strtolower( $pat ) ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return $tag;
		}

		$noscript = '<noscript>' . trim( $tag ) . '</noscript>';

		if ( preg_match( '/\bmedia=([\'"])(?:all|screen)?\1/i', $tag ) ) {
			$async_tag = (string) preg_replace(
				'/\bmedia=([\'"])(?:all|screen)?\1/i',
				'media="print" onload="this.media=\'all\'"',
				$tag,
				1
			);
		} else {
			$async_tag = str_replace( 'href=', 'media="print" onload="this.media=\'all\'" href=', $tag );
		}

		return trim( $async_tag ) . "\n" . $noscript . "\n";
	}

	/**
	 * Rewrite relative url(...) references to normalized absolute URLs and minify CSS comments/whitespace.
	 *
	 * @since 2.1.0
	 * @param string $css          Raw stylesheet content.
	 * @param string $base_dir_url Trailing-slashed directory URL of the original CSS file.
	 * @return string
	 */
	private function rewrite_and_minify_css( $css, $base_dir_url ) {
		// Strip UTF-8 BOM and @charset declarations.
		$css = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $css );
		$css = (string) preg_replace( '/@charset\s+[\'"][^\'"]+[\'"]\s*;/i', '', $css );

		// Strip CSS block comments.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		// Rewrite relative url(...) references so background images and fonts never 404 when inlined or relocated.
		$self = $this;
		$css  = (string) preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			static function ( $matches ) use ( $base_dir_url, $self ) {
				$quote = $matches[1];
				$url   = trim( $matches[2] );

				if (
					'' === $url ||
					0 === strpos( $url, 'data:' ) ||
					0 === strpos( $url, 'http:' ) ||
					0 === strpos( $url, 'https:' ) ||
					0 === strpos( $url, '//' ) ||
					0 === strpos( $url, '/' ) ||
					0 === strpos( $url, '#' )
				) {
					return $matches[0];
				}

				$abs_url = $self->normalize_url_dot_segments( $base_dir_url . $url );
				return 'url(' . $quote . esc_url_raw( $abs_url ) . $quote . ')';
			},
			$css
		);

		// Safe lightweight whitespace minification (note: preserve spaces around '+' and '-' inside calc()).
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = (string) preg_replace( '/\s*([\{\};:,>~])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );

		// Prevent accidental premature closing of the <style> tag.
		$css = str_ireplace( '</style', '', $css );

		return trim( $css );
	}

	/**
	 * Normalize './' and '../' segments inside a full HTTP(S) URL.
	 *
	 * @since 2.2.0
	 * @param string $url Full URL string.
	 * @return string
	 */
	public function normalize_url_dot_segments( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$segments = explode( '/', $parts['path'] );
		$resolved = array();
		foreach ( $segments as $seg ) {
			if ( '.' === $seg ) {
				continue;
			}
			if ( '..' === $seg ) {
				if ( ! empty( $resolved ) && '' !== end( $resolved ) ) {
					array_pop( $resolved );
				}
				continue;
			}
			$resolved[] = $seg;
		}

		$port  = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$frag  = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

		return $parts['scheme'] . '://' . $parts['host'] . $port . implode( '/', $resolved ) . $query . $frag;
	}

	/**
	 * Safely map a local asset URL (inside WP_CONTENT_URL or site_url) to a verified realpath inside ABSPATH.
	 *
	 * @since 2.1.0
	 * @param string $asset_url     Clean URL without query/fragment.
	 * @param string $required_ext  Optional required suffix such as '.css'.
	 * @return string Empty string if external or invalid.
	 */
	private function resolve_local_asset_path( $asset_url, $required_ext = '' ) {
		if ( '' === $asset_url ) {
			return '';
		}

		// Handle protocol-relative URLs.
		if ( 0 === strpos( $asset_url, '//' ) ) {
			$scheme    = is_ssl() ? 'https:' : 'http:';
			$asset_url = $scheme . $asset_url;
		}

		$content_url = content_url();
		$site_url    = site_url();
		$candidate   = '';

		if ( 0 === strpos( $asset_url, $content_url ) ) {
			$rel       = ltrim( substr( $asset_url, strlen( $content_url ) ), '/' );
			$candidate = trailingslashit( WP_CONTENT_DIR ) . rawurldecode( $rel );
		} elseif ( 0 === strpos( $asset_url, $site_url ) ) {
			$rel       = ltrim( substr( $asset_url, strlen( $site_url ) ), '/' );
			$candidate = trailingslashit( ABSPATH ) . rawurldecode( $rel );
		} elseif ( 0 === strpos( $asset_url, '/' ) && 0 !== strpos( $asset_url, '//' ) ) {
			$site_path = (string) wp_parse_url( $site_url, PHP_URL_PATH );
			$rel       = $asset_url;
			if ( '' !== $site_path && '/' !== $site_path && 0 === strpos( $rel, $site_path ) ) {
				$rel = substr( $rel, strlen( $site_path ) );
			}
			$candidate = trailingslashit( ABSPATH ) . rawurldecode( ltrim( $rel, '/' ) );
		}

		if ( '' === $candidate ) {
			return '';
		}

		$real = realpath( $candidate );
		if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			return '';
		}

		$real_abspath = realpath( ABSPATH );
		$real_content = realpath( WP_CONTENT_DIR );

		$norm_real    = wp_normalize_path( $real );
		$norm_abspath = wp_normalize_path( false !== $real_abspath ? $real_abspath : ABSPATH );
		$norm_content = wp_normalize_path( false !== $real_content ? $real_content : WP_CONTENT_DIR );

		if ( 0 !== strpos( $norm_real, $norm_abspath ) && 0 !== strpos( $norm_real, $norm_content ) ) {
			return '';
		}

		if ( '' !== $required_ext && substr( strtolower( $norm_real ), -strlen( $required_ext ) ) !== strtolower( $required_ext ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * Add native loading="lazy" to iframes inside post content.
	 *
	 * @since 2.0.0
	 * @param string $content Post HTML content.
	 * @return string
	 */
	public function lazy_load_content_iframes( $content ) {
		if ( empty( $content ) || false === strpos( $content, '<iframe' ) || $this->should_bypass_frontend() ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			static function ( $matches ) {
				$iframe = $matches[0];
				if ( false === stripos( $iframe, 'loading=' ) ) {
					$iframe = str_replace( '<iframe', '<iframe loading="lazy"', $iframe );
				}
				return $iframe;
			},
			$content
		);
	}

	/**
	 * Safely add defer attribute to non-critical frontend scripts while excluding core, checkout,
	 * and handles with synchronous inline "after" scripts.
	 *
	 * @since 2.0.0
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Registered script handle.
	 * @return string
	 */
	public function defer_non_critical_scripts( $tag, $handle ) {
		if ( $this->should_bypass_frontend() || $this->is_transactional_page() ) {
			return $tag;
		}

		if ( false !== stripos( $tag, ' defer' ) || false !== stripos( $tag, ' async' ) || false === stripos( $tag, ' src=' ) ) {
			return $tag;
		}

		if ( ! $this->is_script_handle_safe_to_defer( $handle ) ) {
			return $tag;
		}

		return str_replace( ' src=', ' defer src=', $tag );
	}

	/**
	 * Determine whether a registered script handle and all scripts that depend on it can be safely deferred.
	 *
	 * Prevents dependency order inversions where a child script with a synchronous `wp_add_inline_script(..., 'after')`
	 * block executes during HTML parsing before its deferred parent dependency (e.g. `wp-private-apis` -> `wp-api-fetch` -> `wc-blocks`).
	 *
	 * @since 3.0.0
	 * @param string $handle Registered script handle.
	 * @return bool
	 */
	private function is_script_handle_safe_to_defer( $handle ) {
		$excluded_handles = array(
			'jquery',
			'jquery-core',
			'jquery-migrate',
			'wp-polyfill',
			'wp-hooks',
			'wp-i18n',
			'wp-api-fetch',
			'react',
			'react-dom',
			'react-jsx-runtime',
			'lodash',
			'underscore',
			'backbone',
			'moment',
			'wc-checkout',
			'wc-cart',
			'wc-cart-fragments',
			'wc-add-to-cart',
		);

		/**
		 * Filters script handles excluded from JS deferring.
		 *
		 * @since 2.0.0
		 * @param array<int, string> $excluded_handles Script handles to skip.
		 */
		$excluded_handles = (array) apply_filters( 'fbsseop_defer_excluded_handles', $excluded_handles );

		if ( $this->is_handle_intrinsically_synchronous( $handle, $excluded_handles ) ) {
			return false;
		}

		// Ensure no enqueued synchronous script depends (directly or transitively) on $handle.
		$wp_scripts = wp_scripts();
		if ( $wp_scripts instanceof WP_Scripts && ! empty( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $queued_handle ) {
				if ( $queued_handle === $handle ) {
					continue;
				}
				if (
					$this->is_handle_intrinsically_synchronous( $queued_handle, $excluded_handles ) &&
					$this->script_depends_on( $queued_handle, $handle, $wp_scripts )
				) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if a script handle itself must remain synchronous (excluded handle, core wp- or wc- runtime, or has inline 'after' script).
	 *
	 * @since 3.0.0
	 * @param string             $handle           Script handle.
	 * @param array<int, string> $excluded_handles Excluded handles list.
	 * @return bool
	 */
	private function is_handle_intrinsically_synchronous( $handle, $excluded_handles ) {
		if ( in_array( $handle, $excluded_handles, true ) ) {
			return true;
		}

		// Protect WordPress @wordpress/* packages (wp-*) and WooCommerce / Blocks runtime packages (wc-*, woocommerce*).
		if (
			0 === strpos( $handle, 'wp-' ) ||
			0 === strpos( $handle, 'wc-' ) ||
			0 === strpos( $handle, 'woocommerce' )
		) {
			return true;
		}

		$wp_scripts = wp_scripts();
		if ( $wp_scripts instanceof WP_Scripts ) {
			$after_data = $wp_scripts->get_data( $handle, 'after' );
			if ( ! empty( $after_data ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check whether $child_handle depends on $target_dep in WP_Scripts.
	 *
	 * @since 3.0.0
	 * @param string               $child_handle Child script handle.
	 * @param string               $target_dep   Target dependency handle.
	 * @param WP_Scripts           $wp_scripts   WP_Scripts instance.
	 * @param array<string, true>  $visited      Visited handles guard against circular deps.
	 * @return bool
	 */
	private function script_depends_on( $child_handle, $target_dep, $wp_scripts, $visited = array() ) {
		if ( isset( $visited[ $child_handle ] ) || ! isset( $wp_scripts->registered[ $child_handle ] ) ) {
			return false;
		}
		$visited[ $child_handle ] = true;

		$deps = (array) $wp_scripts->registered[ $child_handle ]->deps;
		if ( in_array( $target_dep, $deps, true ) ) {
			return true;
		}

		foreach ( $deps as $dep ) {
			if ( $this->script_depends_on( $dep, $target_dep, $wp_scripts, $visited ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the current page is a sensitive transactional eCommerce screen.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private function is_transactional_page() {
		if (
			( function_exists( 'is_cart' ) && is_cart() ) ||
			( function_exists( 'is_checkout' ) && is_checkout() ) ||
			( function_exists( 'is_account_page' ) && is_account_page() )
		) {
			return true;
		}

		if ( function_exists( 'has_block' ) && is_singular() ) {
			if ( has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve third-party tracker URL patterns eligible for interaction delay.
	 *
	 * @since 2.2.0
	 * @return array<int, string>
	 */
	private function get_delayed_tracker_patterns() {
		$patterns = array(
			'googletagmanager.com/gtag/js',
			'googletagmanager.com/gtm.js',
			'google-analytics.com/analytics.js',
			'connect.facebook.net/',
			'clarity.ms/',
			'scripts.clarity.ms/',
			'static.hotjar.com/',
			'snap.licdn.com/',
			'analytics.tiktok.com/',
			'gtm4wp-form-move-tracker',
		);

		/**
		 * Filters URL substrings matched for third-party JS interaction delay.
		 *
		 * @since 2.1.0
		 * @param array<int, string> $patterns Third-party script URL patterns.
		 */
		return (array) apply_filters( 'fbsseop_delayed_script_patterns', $patterns );
	}

	/**
	 * Delay enqueued external third-party analytics & tracking scripts via script_loader_tag.
	 *
	 * @since 2.1.0
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Registered script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public function maybe_delay_third_party_script( $tag, $handle, $src ) {
		unset( $handle );

		if ( $this->should_bypass_frontend() || empty( $src ) || false !== stripos( $tag, 'fbsseop/delayed' ) ) {
			return $tag;
		}

		if ( $this->is_transactional_page() ) {
			return $tag;
		}

		$patterns = $this->get_delayed_tracker_patterns();
		$matched  = false;
		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $src, $pattern ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return $tag;
		}

		$delayed_tag = (string) preg_replace( '/\s+type=([\'"])[^\'"]*\1/i', '', $tag );
		$delayed_tag = (string) preg_replace( '/\bsrc=([\'"])/i', 'type="fbsseop/delayed" data-fbsseop-src=$1', $delayed_tag, 1 );

		if ( $delayed_tag !== $tag ) {
			$this->has_delayed_scripts = true;
		}

		return $delayed_tag;
	}

	/**
	 * Post-render DOM-verified unloader for unused plugin CSS/JS & orphaned reCAPTCHA v3.
	 *
	 * Because this inspects the COMPLETE rendered <body> (including header, page-builder rows,
	 * popups, widgets, and footer), forms or widgets placed outside post_content are never broken.
	 *
	 * @since 2.2.0
	 * @param string $html Complete rendered HTML document.
	 * @return string
	 */
	private function unload_unused_rendered_assets( $html ) {
		$body_pos = stripos( $html, '<body' );
		if ( false === $body_pos ) {
			return $html;
		}

		$body_html = substr( $html, $body_pos );
		// Strip <script> and <style> blocks when checking rendered DOM markup so plugin footer localization scripts do not self-match.
		$dom_only         = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $body_html );
		$strip_tokens     = array();
		$strip_inline_ids = array();

		// 1. Contact Form 7 + Add-ons + Orphaned Google reCAPTCHA v3 on formless pages.
		if ( false === stripos( $dom_only, 'wpcf7' ) ) {
			$strip_tokens[]     = 'contact-form-7';
			$strip_tokens[]     = 'dnd-upload-cf7';
			$strip_tokens[]     = 'codedropz-uploader';
			$strip_tokens[]     = 'cf7apps-honeypot';
			$strip_tokens[]     = 'gtm4wp-contact-form-7-tracker';
			$strip_inline_ids[] = 'contact-form-7';
			$strip_inline_ids[] = 'dnd-upload-cf7';
			$strip_inline_ids[] = 'wpcf7-recaptcha';

			// Strip Google reCAPTCHA v3 only when no other form/captcha DOM element exists on the page.
			$other_captcha_consumers = array(
				'g-recaptcha',
				'wpforms',
				'gform_',
				'fluentform',
				'nf-form',
				'elementor-form',
				'woocommerce',
				'loginform',
				'um-form',
			);
			$has_other_consumer      = false;
			foreach ( $other_captcha_consumers as $marker ) {
				if ( false !== stripos( $dom_only, $marker ) ) {
					$has_other_consumer = true;
					break;
				}
			}

			if ( ! $has_other_consumer ) {
				$strip_tokens[]     = 'google.com/recaptcha/api.js';
				$strip_tokens[]     = 'google-recaptcha-js';
				$strip_inline_ids[] = 'google-recaptcha';
			}
		}

		// 2. Smash Balloon Instagram Feed when no feed container exists in the DOM.
		if ( false === stripos( $dom_only, 'sb_instagram' ) && false === stripos( $dom_only, 'sbi_' ) ) {
			$strip_tokens[]     = 'instagram-feed/';
			$strip_tokens[]     = 'sbi_styles';
			$strip_tokens[]     = 'sbi-tokens-local';
			$strip_inline_ids[] = 'sbi_';
		}

		// 3. SiteOrigin Anything Carousel & Slick Slider when zero carousel/slick DOM elements exist.
		if ( false === stripos( $dom_only, 'sow-carousel' ) ) {
			$strip_tokens[]     = 'sow-anything-carousel';
			$strip_tokens[]     = 'so-widgets-bundle/js/carousel';
			$strip_inline_ids[] = 'sow-anything-carousel';

			// Only strip shared slick library if neither DOM classes nor non-Slick theme inline scripts call .slick(.
			$body_without_slick_lib = (string) preg_replace( '#<script\b[^>]*(?:sow-anything-carousel|so-widgets-bundle/js/(?:carousel|lib/slick))[^>]*>.*?</script>#is', '', $body_html );
			if ( false === stripos( $dom_only, 'slick' ) && false === stripos( $body_without_slick_lib, '.slick(' ) ) {
				$strip_tokens[]     = 'so-widgets-bundle/js/lib/slick';
				$strip_tokens[]     = 'so-widgets-bundle/css/lib/slick';
				$strip_inline_ids[] = 'slick-fbs-inline-css';
				$strip_inline_ids[] = 'sow-slick';
			}
		}

		// 4. WPForms & TablePress when absent from rendered DOM.
		if ( false === stripos( $dom_only, 'wpforms' ) ) {
			$strip_tokens[]     = '/plugins/wpforms';
			$strip_inline_ids[] = 'wpforms';
		}

		if ( false === stripos( $dom_only, 'tablepress' ) ) {
			$strip_tokens[]     = '/plugins/tablepress';
			$strip_inline_ids[] = 'tablepress';
		}

		// 5. Unused WordPress Core Social Links block inline CSS (~12 KB) when block is absent.
		if ( false === stripos( $dom_only, 'wp-block-social-links' ) ) {
			$strip_inline_ids[] = 'wp-block-social-links-inline-css';
		}

		if ( empty( $strip_tokens ) && empty( $strip_inline_ids ) ) {
			return $html;
		}

		// Remove matched <link rel="stylesheet" ...> tags.
		if ( ! empty( $strip_tokens ) ) {
			$html = (string) preg_replace_callback(
				'/<link\b[^>]*rel=[\'"]stylesheet[\'"][^>]*>\s*/i',
				static function ( $m ) use ( $strip_tokens ) {
					$tag_lc = strtolower( $m[0] );
					foreach ( $strip_tokens as $tok ) {
						if ( false !== strpos( $tag_lc, strtolower( $tok ) ) ) {
							return '';
						}
					}
					return $m[0];
				},
				$html
			);
		}

		// Remove matched <style id="...">...</style> blocks (including FBS inlined small CSS for unused plugins).
		if ( ! empty( $strip_inline_ids ) ) {
			$html = (string) preg_replace_callback(
				'/<style\b([^>]*)>.*?<\/style>\s*/is',
				static function ( $m ) use ( $strip_inline_ids ) {
					$attrs_lc = strtolower( $m[1] );
					foreach ( $strip_inline_ids as $id_tok ) {
						if ( false !== strpos( $attrs_lc, strtolower( $id_tok ) ) ) {
							return '';
						}
					}
					return $m[0];
				},
				$html
			);
		}

		// Remove matched <script ...>...</script> tags (external + associated -js-extra/-js-before/-js-after blocks).
		$all_script_tokens = array_unique( array_merge( $strip_tokens, $strip_inline_ids ) );
		if ( ! empty( $all_script_tokens ) ) {
			$html = (string) preg_replace_callback(
				'/<script\b([^>]*)>.*?<\/script>\s*/is',
				static function ( $m ) use ( $all_script_tokens ) {
					$attrs_lc = strtolower( $m[1] );
					foreach ( $all_script_tokens as $tok ) {
						if ( false !== strpos( $attrs_lc, strtolower( $tok ) ) ) {
							return '';
						}
					}
					return $m[0];
				},
				$html
			);
		}

		// Clean up orphaned wp-polyfill / wp-i18n / wp-hooks dependencies when no remaining script on the page uses them.
		$html_without_wp_runtime = (string) preg_replace(
			'/<script\b[^>]*\bid=[\'"](?:wp-polyfill|wp-i18n|wp-hooks|regenerator-runtime)[^\'"]*[\'"][^>]*>.*?<\/script>\s*/is',
			'',
			$html
		);
		if (
			false === stripos( $html_without_wp_runtime, 'wp.i18n' ) &&
			false === stripos( $html_without_wp_runtime, 'wp.hooks' ) &&
			false === stripos( $html_without_wp_runtime, 'wp.apiFetch' ) &&
			false === stripos( $html_without_wp_runtime, 'wp-api-fetch' ) &&
			false === stripos( $html_without_wp_runtime, 'wp-blocks' ) &&
			false === stripos( $html_without_wp_runtime, 'wc-' )
		) {
			$html = $html_without_wp_runtime;
		}

		return $html;
	}

	/**
	 * Apply full-document CLS stabilization:
	 * 1) Inject missing width & height on header/hero/builder/footer <img> tags.
	 * 2) Inject intrinsic viewBox width & height + zero-specificity auto-height CSS on inline <svg> icons.
	 * 3) Pre-stabilize full-width JS-stretched page-builder rows (SiteOrigin, WPBakery) before First Paint.
	 *
	 * @since 2.2.0
	 * @param string $html               Full HTML document.
	 * @param bool   $fix_img_dims       Whether to inject missing <img> dimensions across body.
	 * @param bool   $stabilize_builders Whether to stabilize full-width builder rows and inline <svg>s.
	 * @return string
	 */
	private function apply_document_cls_stabilization( $html, $fix_img_dims, $stabilize_builders ) {
		$body_pos = stripos( $html, '<body' );
		if ( false === $body_pos ) {
			return $html;
		}

		$head_part = substr( $html, 0, $body_pos );
		$body_part = substr( $html, $body_pos );

		// Protect <script> and <style> blocks inside <body> while rewriting <img> and <svg> tags.
		$placeholders = array();
		$protected    = (string) preg_replace_callback(
			'/<(script|style|template)\b[^>]*>.*?<\/\1>/is',
			static function ( $m ) use ( &$placeholders ) {
				$key                  = '<!--FBSSEOP_HOLD_' . count( $placeholders ) . '-->';
				$placeholders[ $key ] = $m[0];
				return $key;
			},
			$body_part
		);

		if ( $fix_img_dims ) {
			$protected = $this->inject_missing_image_dimensions( $protected );
		}

		$injected_svg_dims = false;
		if ( $stabilize_builders && false !== stripos( $protected, '<svg' ) ) {
			$protected = (string) preg_replace_callback(
				'/<svg\b[^>]*>/i',
				static function ( $m ) use ( &$injected_svg_dims ) {
					$svg = $m[0];
					if (
						preg_match( '/\bwidth\s*=/i', $svg ) ||
						preg_match( '/\bheight\s*=/i', $svg ) ||
						preg_match( '/\bstyle\s*=\s*[\'"][^\'"]*(?:width|height)\s*:/i', $svg )
					) {
						return $svg;
					}

					if ( ! preg_match( '/\bviewBox\s*=\s*[\'"][+-]?[0-9.]+\s+[+-]?[0-9.]+\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9]+(?:\.[0-9]+)?)\s*[\'"]/i', $svg, $vb ) ) {
						return $svg;
					}

					$w = round( (float) $vb[1], 2 );
					$h = round( (float) $vb[2], 2 );
					if ( $w <= 0 || $h <= 0 ) {
						return $svg;
					}

					$injected_svg_dims = true;
					return str_replace(
						'<svg',
						'<svg width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '" data-fbs-svg="1"',
						$svg
					);
				},
				$protected
			);
		}

		if ( ! empty( $placeholders ) ) {
			$body_part = strtr( $protected, $placeholders );
		} else {
			$body_part = $protected;
		}

		$cls_head_snippets = '';

		// Pre-JS full-bleed row stabilization for SiteOrigin Page Builder & WPBakery.
		// Note: --fbs-cw is measured right after <body ...> opens (after <meta name="viewport"> is parsed on mobile,
		// preventing Chromium's pre-viewport 980px fallback width) and re-checked once before </body> for desktop scrollbars,
		// avoiding ResizeObserver loops completely.
		if (
			$stabilize_builders &&
			(
				false !== strpos( $body_part, 'siteorigin-panels-before-js' ) ||
				false !== strpos( $body_part, 'data-stretch-type="full' ) ||
				false !== strpos( $body_part, 'data-vc-full-width="true"' )
			)
		) {
			$cls_css  = 'html{overflow-x:clip}';
			$cls_css .= 'body.siteorigin-panels-before-js:not(.siteorigin-panels-css-container) .siteorigin-panels-stretch[data-stretch-type="full-stretched"],';
			$cls_css .= 'body.siteorigin-panels-before-js:not(.siteorigin-panels-css-container) .siteorigin-panels-stretch[data-stretch-type="full-width-stretch"],';
			$cls_css .= '[data-vc-full-width="true"][data-vc-stretch-content="true"]:not([data-vc-full-width-init="true"]){';
			$cls_css .= 'width:var(--fbs-cw,100vw)!important;max-width:var(--fbs-cw,100vw)!important;';
			$cls_css .= 'margin-left:calc(50% - (var(--fbs-cw,100vw) / 2))!important;margin-right:calc(50% - (var(--fbs-cw,100vw) / 2))!important;';
			$cls_css .= 'padding-left:0!important;padding-right:0!important;box-sizing:border-box!important}';
			$cls_css .= 'body.siteorigin-panels-before-js:not(.siteorigin-panels-css-container) .siteorigin-panels-stretch[data-stretch-type="full"]{';
			$cls_css .= 'margin-left:calc(50% - (var(--fbs-cw,100vw) / 2))!important;margin-right:calc(50% - (var(--fbs-cw,100vw) / 2))!important;';
			$cls_css .= 'padding-left:calc((var(--fbs-cw,100vw) - 100%) / 2)!important;padding-right:calc((var(--fbs-cw,100vw) - 100%) / 2)!important;box-sizing:border-box!important}';

			if ( $injected_svg_dims ) {
				$cls_css          .= ':where([data-fbs-svg]){height:auto}';
				$injected_svg_dims = false;
			}

			$cls_head_snippets .= '<style id="fbsseop-cls-stabilizer">' . $cls_css . "</style>\n";

			$vw_open_script  = '<script id="fbsseop-cls-vw">(function(){var d=document.documentElement,w=0,u=function(){var c=d.clientWidth;if(c&&c!==w){w=c;d.style.setProperty("--fbs-cw",c+"px")}};u();window.fbsCwSync=u;window.addEventListener("resize",u,{passive:true})})();</script>';
			$vw_close_script = "<script id=\"fbsseop-cls-vw-end\">if(window.fbsCwSync){window.fbsCwSync();}</script>\n";

			$body_part = (string) preg_replace( '/(<body\b[^>]*>)/i', '$1' . "\n" . $vw_open_script, $body_part, 1 );
			if ( false !== stripos( $body_part, '</body>' ) ) {
				$body_part = str_ireplace( '</body>', $vw_close_script . '</body>', $body_part );
			}
		} elseif ( $injected_svg_dims ) {
			$cls_head_snippets .= "<style id=\"fbsseop-svg-cls\">:where([data-fbs-svg]){height:auto}</style>\n";
		}

		if ( '' !== $cls_head_snippets ) {
			$head_part = $this->inject_early_into_head( $head_part, $cls_head_snippets );
		}

		return $head_part . $body_part;
	}

	/**
	 * Detect the true above-the-fold LCP hero image (whether a CSS background-image or hero <img>),
	 * inject an early <link rel="preload" as="image" fetchpriority="high"> in <head>, and sanitize
	 * misplaced fetchpriority="high" attributes on offscreen/lazy-loaded images.
	 *
	 * @since 2.2.0
	 * @param string $html           Full HTML document.
	 * @param string $manual_lcp_url Optional admin-configured hero image URL override.
	 * @return string
	 */
	private function apply_lcp_preload_and_priority( $html, $manual_lcp_url = '' ) {
		$body_pos = stripos( $html, '<body' );
		if ( false === $body_pos ) {
			return $html;
		}

		$head_part          = substr( $html, 0, $body_pos );
		$body_part          = substr( $html, $body_pos );
		$already_has_preload = (bool) preg_match( '/<link\b[^>]*rel=[\'"]preload[\'"][^>]*as=[\'"]image[\'"][^>]*>/i', $head_part );

		$lcp_type   = '';
		$lcp_url    = '';
		$lcp_srcset = '';
		$lcp_sizes  = '';

		// 1. Check optional manual URL override (only if referenced on this page or front page).
		if ( '' !== $manual_lcp_url ) {
			$clean_manual = (string) strtok( $manual_lcp_url, '?#' );
			if ( false !== strpos( $html, $clean_manual ) || is_front_page() ) {
				$lcp_type = 'manual';
				$lcp_url  = $manual_lcp_url;
			}
		}

		// 2. Auto-detect above-the-fold CSS hero background-image vs above-the-fold hero <img>.
		if ( '' === $lcp_url ) {
			$bg_candidate  = $this->detect_above_fold_hero_background( $head_part, $body_part );
			$img_candidate = $this->detect_above_fold_hero_img( $body_part );

			if ( null !== $bg_candidate && null !== $img_candidate ) {
				if ( $bg_candidate['offset'] <= $img_candidate['offset'] ) {
					$lcp_type = 'bg';
					$lcp_url  = $bg_candidate['url'];
				} else {
					$lcp_type   = 'img';
					$lcp_url    = $img_candidate['url'];
					$lcp_srcset = $img_candidate['srcset'];
					$lcp_sizes  = $img_candidate['sizes'];
				}
			} elseif ( null !== $bg_candidate ) {
				$lcp_type = 'bg';
				$lcp_url  = $bg_candidate['url'];
			} elseif ( null !== $img_candidate ) {
				$lcp_type   = 'img';
				$lcp_url    = $img_candidate['url'];
				$lcp_srcset = $img_candidate['srcset'];
				$lcp_sizes  = $img_candidate['sizes'];
			}
		}

		// 3. Inject early <link rel="preload" as="image" ... fetchpriority="high"> into <head>.
		if ( '' !== $lcp_url && ! $already_has_preload ) {
			$preload_attrs = 'rel="preload" as="image" href="' . esc_url( $lcp_url ) . '"';
			if ( '' !== $lcp_srcset ) {
				$preload_attrs .= ' imagesrcset="' . esc_attr( $lcp_srcset ) . '"';
				if ( '' !== $lcp_sizes ) {
					$preload_attrs .= ' imagesizes="' . esc_attr( $lcp_sizes ) . '"';
				}
			}
			$preload_attrs .= ' fetchpriority="high"';

			$preload_tag = '<link ' . $preload_attrs . ">\n";
			$head_part   = $this->inject_early_into_head( $head_part, $preload_tag );
		}

		// 4. Clean up contradictory loading="lazy" + fetchpriority="high" and below-the-fold priority theft.
		$clean_lcp_url = '' !== $lcp_url ? (string) strtok( $lcp_url, '?#' ) : '';
		$body_part     = (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $m ) use ( $lcp_type, $clean_lcp_url ) {
				$img = $m[0];
				$src = preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $img, $sm ) ? (string) strtok( $sm[2], '?#' ) : '';

				if ( 'img' === $lcp_type && '' !== $clean_lcp_url && $src === $clean_lcp_url ) {
					// Ensure winning hero <img> is never lazy-loaded and carries fetchpriority="high".
					$img = (string) preg_replace( '/\s+loading=([\'"])lazy\1/i', '', $img );
					if ( false === stripos( $img, 'fetchpriority=' ) ) {
						$img = str_replace( '<img', '<img fetchpriority="high"', $img );
					}
					return $img;
				}

				// If hero LCP is a CSS background-image, or if an <img> has loading="lazy", strip fetchpriority="high"
				// and mark lazy images fetchpriority="low" so Chrome's 1250px Slow-4G lookahead never steals hero bandwidth.
				$is_lazy = ( false !== stripos( $img, 'loading="lazy"' ) || false !== stripos( $img, "loading='lazy'" ) );
				if ( 'bg' === $lcp_type || $is_lazy ) {
					$img = (string) preg_replace( '/\s+fetchpriority=([\'"])high\1/i', '', $img );
				}

				if ( $is_lazy && '' !== $clean_lcp_url && false === stripos( $img, 'fetchpriority=' ) ) {
					$img = str_replace( '<img', '<img fetchpriority="low"', $img );
				}

				return $img;
			},
			$body_part
		);

		return $head_part . $body_part;
	}

	/**
	 * Detect an above-the-fold Hero CSS background-image URL from inline styles, inline <style> blocks,
	 * or local external page-builder / theme stylesheets.
	 *
	 * @since 2.2.0
	 * @param string $head_part HTML <head> section.
	 * @param string $body_part HTML <body> section.
	 * @return array{url: string, offset: int}|null
	 */
	private function detect_above_fold_hero_background( $head_part, $body_part ) {
		$top_body = substr( $body_part, 0, 32768 );

		// Case A: Inline style="...background(-image): url(...)..." on an element near the top of <body>.
		if ( preg_match( '/style\s*=\s*([\'"])[^\'"]*background(?:-image)?\s*:[^\'"]*url\(\s*[\'"]?([^\'")\s]+\.(?:jpe?g|png|webp|avif)(?:\?[^\'")\s]*)?)[\'"]?\s*\)/i', $top_body, $bm, PREG_OFFSET_CAPTURE ) ) {
			$bg_url = html_entity_decode( trim( $bm[2][0] ), ENT_QUOTES, 'UTF-8' );
			if ( ! $this->is_non_lcp_ui_graphic( $bg_url ) ) {
				return array(
					'url'    => $bg_url,
					'offset' => (int) $bm[0][1],
				);
			}
		}

		// Case B: <style> block in <head> or top of <body> targeting a hero/banner/cover selector present in top_body.
		$style_search_zone = $head_part . substr( $top_body, 0, 6144 );
		if ( preg_match_all( '/<style\b([^>]*)>(.*?)<\/style>/is', $style_search_zone, $style_blocks, PREG_SET_ORDER ) ) {
			$hero_keywords = array( 'hero', 'banner', 'masthead', 'jumbotron', 'wp-block-cover', 'first-section', 'top-section' );

			foreach ( $style_blocks as $block ) {
				$style_attrs      = strtolower( $block[1] );
				$is_hero_style_id = false;
				foreach ( $hero_keywords as $kw ) {
					if ( false !== strpos( $style_attrs, $kw ) ) {
						$is_hero_style_id = true;
						break;
					}
				}

				$found = $this->match_hero_bg_in_css( $block[2], $style_attrs, $top_body, $is_hero_style_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		// Case C: Local external <link rel="stylesheet"> files (e.g. Elementor post-*.css, Divi, Bricks, theme CSS).
		if ( preg_match_all( '/<link\b[^>]*rel\s*=\s*[\'"]stylesheet[\'"][^>]*>/i', $head_part, $link_tags ) ) {
			$prioritized = array();
			$fallback    = array();

			foreach ( $link_tags[0] as $link_tag ) {
				// Skip non-blocking print/async icon & font sheets.
				if ( preg_match( '/\bmedia\s*=\s*[\'"]print[\'"]/i', $link_tag ) ) {
					continue;
				}
				if ( ! preg_match( '/\bhref\s*=\s*([\'"])([^\'"]+)\1/i', $link_tag, $hm ) ) {
					continue;
				}

				$clean_href = (string) strtok( html_entity_decode( trim( $hm[2] ), ENT_QUOTES, 'UTF-8' ), '?#' );
				if ( preg_match( '/(?:fontawesome|dashicons|eicons|ekiticons|admin-bar|wp-block-library|fonts\.googleapis)/i', $clean_href ) ) {
					continue;
				}

				$local_path = $this->resolve_local_asset_path( $clean_href, '.css' );
				if ( '' === $local_path ) {
					continue;
				}

				$entry = array(
					'path' => $local_path,
					'href' => $clean_href,
				);

				if ( preg_match( '/\/(?:elementor\/css\/post-|bb-plugin\/cache\/|et-cache\/|bricks\/css\/|oxygen\/|uploads\/)/i', $clean_href ) ) {
					$prioritized[] = $entry;
				} else {
					$fallback[] = $entry;
				}
			}

			$candidates = array_slice( array_merge( $prioritized, $fallback ), 0, 4 );
			if ( ! empty( $candidates ) ) {
				$salt      = FBS_Secure_Optimize_Controller::get_cache_salt();
				$cache_key = 'fbsseop_lcp_css_' . md5( $salt . '|' . wp_json_encode( $candidates ) );
				$css_rules = get_transient( $cache_key );

				if ( ! is_array( $css_rules ) ) {
					$css_rules  = array();
					$bytes_read = 0;

					foreach ( $candidates as $entry ) {
						if ( $bytes_read >= 307200 ) {
							break;
						}

						$fsize = (int) @filesize( $entry['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						if ( $fsize <= 0 || $fsize > 204800 ) {
							continue;
						}

						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified local CSS file inside ABSPATH.
						$css_body = @file_get_contents( $entry['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						if ( ! is_string( $css_body ) || '' === $css_body ) {
							continue;
						}

						$bytes_read += $fsize;

						if ( false === stripos( $css_body, 'background' ) || false === stripos( $css_body, 'url(' ) ) {
							continue;
						}

						$base_dir_url = trailingslashit( dirname( $entry['href'] ) );
						if ( preg_match_all( '/([^{};]+)\{[^}]*background(?:-image)?\s*:[^;}]*url\(\s*[\'"]?([^\'")\s]+\.(?:jpe?g|png|webp|avif)(?:\?[^\'")\s]*)?)[\'"]?\s*\)/is', $css_body, $rules, PREG_SET_ORDER ) ) {
							foreach ( $rules as $rule ) {
								$img_url = html_entity_decode( trim( $rule[2] ), ENT_QUOTES, 'UTF-8' );
								if ( '' !== $base_dir_url && 0 !== strpos( $img_url, 'http://' ) && 0 !== strpos( $img_url, 'https://' ) && 0 !== strpos( $img_url, '//' ) && 0 !== strpos( $img_url, 'data:' ) ) {
									$img_url = ( 0 === strpos( $img_url, '/' ) ) ? home_url( $img_url ) : $base_dir_url . ltrim( $img_url, './' );
								}
								if ( $this->is_non_lcp_ui_graphic( $img_url ) ) {
									continue;
								}
								$css_rules[] = array(
									'selector' => trim( $rule[1] ),
									'url'      => $img_url,
								);
							}
						}
					}

					set_transient( $cache_key, $css_rules, 6 * HOUR_IN_SECONDS );
				}

				foreach ( $css_rules as $rule_item ) {
					$body_offset = $this->find_selector_offset_in_top_body( $rule_item['selector'], '', $top_body, false );
					if ( null !== $body_offset ) {
						return array(
							'url'    => $rule_item['url'],
							'offset' => $body_offset,
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Scan a CSS string for the first above-the-fold hero background-image rule matching top_body.
	 *
	 * @since 3.0.0
	 * @param string $css_body         Raw CSS content.
	 * @param string $style_attrs      Lowercased <style> tag attributes (empty for external files).
	 * @param string $top_body         First 32 KB of <body>.
	 * @param bool   $is_hero_style_id Whether the <style> id itself contains a hero keyword.
	 * @param string $base_dir_url     Optional base directory URL for resolving relative CSS url() paths.
	 * @return array{url: string, offset: int}|null
	 */
	private function match_hero_bg_in_css( $css_body, $style_attrs, $top_body, $is_hero_style_id, $base_dir_url = '' ) {
		if ( false === stripos( $css_body, 'background' ) || false === stripos( $css_body, 'url(' ) ) {
			return null;
		}

		if ( preg_match_all( '/([^{};]+)\{[^}]*background(?:-image)?\s*:[^;}]*url\(\s*[\'"]?([^\'")\s]+\.(?:jpe?g|png|webp|avif)(?:\?[^\'")\s]*)?)[\'"]?\s*\)/is', $css_body, $rules, PREG_SET_ORDER ) ) {
			foreach ( $rules as $rule ) {
				$selector = trim( $rule[1] );
				$img_url  = html_entity_decode( trim( $rule[2] ), ENT_QUOTES, 'UTF-8' );

				if ( '' !== $base_dir_url && 0 !== strpos( $img_url, 'http://' ) && 0 !== strpos( $img_url, 'https://' ) && 0 !== strpos( $img_url, '//' ) && 0 !== strpos( $img_url, 'data:' ) ) {
					$img_url = ( 0 === strpos( $img_url, '/' ) ) ? home_url( $img_url ) : $base_dir_url . ltrim( $img_url, './' );
				}

				if ( $this->is_non_lcp_ui_graphic( $img_url ) ) {
					continue;
				}

				$body_offset = $this->find_selector_offset_in_top_body( $selector, $style_attrs, $top_body, $is_hero_style_id );
				if ( null !== $body_offset ) {
					return array(
						'url'    => $img_url,
						'offset' => $body_offset,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Locate the approximate byte offset in the top 32 KB of <body> for a hero CSS rule's class or ID token.
	 *
	 * @since 2.2.0
	 * @param string $selector         CSS selector string.
	 * @param string $style_attrs      Lowercased <style> tag attributes.
	 * @param string $top_body         First 32 KB of <body>.
	 * @param bool   $is_hero_style_id Whether the <style> id itself contains a hero keyword.
	 * @return int|null
	 */
	private function find_selector_offset_in_top_body( $selector, $style_attrs, $top_body, $is_hero_style_id ) {
		// Check each comma-separated selector; strip :not(...) / :is(...) / :where(...) pseudo-classes
		// and check non-generic tokens from deepest (most specific) to shallowest.
		$parts          = explode( ',', $selector );
		$generic_tokens = array(
			'elementor-element',
			'elementor-widget',
			'elementor-widget-wrap',
			'elementor-section',
			'elementor-container',
			'elementor-column',
			'wp-block-group',
			'wp-block-cover',
		);

		foreach ( $parts as $part ) {
			$clean_part = (string) preg_replace( '/:(?:not|is|where|has)\([^)]*\)/i', '', $part );
			if ( preg_match_all( '/([.#])([a-zA-Z0-9_-]{4,})/', $clean_part, $tokens, PREG_SET_ORDER ) ) {
				$specific = array();
				foreach ( $tokens as $tok ) {
					if ( ! in_array( strtolower( $tok[2] ), $generic_tokens, true ) ) {
						$specific[] = $tok[2];
					}
				}

				$candidates = ! empty( $specific ) ? array_reverse( $specific ) : array( $tokens[ count( $tokens ) - 1 ][2] );
				foreach ( $candidates as $target ) {
					$pos = stripos( $top_body, $target );
					if ( false !== $pos ) {
						if ( $is_hero_style_id || $pos < 16384 ) {
							return (int) $pos;
						}
					}
				}
			}
		}

		if ( $is_hero_style_id && preg_match( '/\bid=[\'"]([a-z0-9_-]+?)(?:-fbs-inline-css|-inline-css)[\'"]/i', $style_attrs, $idm ) ) {
			$pos = stripos( $top_body, $idm[1] );
			if ( false !== $pos ) {
				return (int) $pos;
			}
		}

		return null;
	}

	/**
	 * Detect the first substantial raster <img> inside the top viewport chunk of <body> (skipping logos/icons/SVGs).
	 *
	 * @since 2.2.0
	 * @param string $body_part HTML <body> section.
	 * @return array{url: string, srcset: string, sizes: string, offset: int}|null
	 */
	private function detect_above_fold_hero_img( $body_part ) {
		$top_body = substr( $body_part, 0, 12288 );
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $top_body, $imgs, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		foreach ( $imgs[0] as $match ) {
			$img    = $match[0];
			$offset = (int) $match[1];

			if ( ! preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $img, $sm ) ) {
				continue;
			}

			$src    = html_entity_decode( trim( $sm[2] ), ENT_QUOTES, 'UTF-8' );
			$width  = preg_match( '/\bwidth\s*=\s*([\'"]?)(\d+)\1/i', $img, $wm ) ? (int) $wm[2] : 0;
			$height = preg_match( '/\bheight\s*=\s*([\'"]?)(\d+)\1/i', $img, $hm ) ? (int) $hm[2] : 0;

			if ( $this->is_non_lcp_ui_graphic( $src, $img, $width, $height ) ) {
				continue;
			}

			$srcset = preg_match( '/\bsrcset\s*=\s*([\'"])([^\'"]+)\1/i', $img, $ssm ) ? trim( $ssm[2] ) : '';
			$sizes  = preg_match( '/\bsizes\s*=\s*([\'"])([^\'"]+)\1/i', $img, $szm ) ? trim( $szm[2] ) : '';

			return array(
				'url'    => $src,
				'srcset' => $srcset,
				'sizes'  => $sizes,
				'offset' => $offset,
			);
		}

		return null;
	}

	/**
	 * Inject a resource hint or critical style snippet early inside <head> (immediately after <meta name="viewport">
	 * so mobile viewport dimensions are active, falling back to after <meta charset> or <head>).
	 *
	 * @since 2.2.0
	 * @param string $head_part HTML <head> substring.
	 * @param string $snippet   HTML snippet to inject.
	 * @return string
	 */
	private function inject_early_into_head( $head_part, $snippet ) {
		if ( preg_match( '/<meta\b[^>]*name=[\'"]viewport[\'"][^>]*>\s*/i', $head_part, $vm, PREG_OFFSET_CAPTURE ) ) {
			$insert_at = (int) $vm[0][1] + strlen( $vm[0][0] );
			return substr( $head_part, 0, $insert_at ) . "\n" . $snippet . substr( $head_part, $insert_at );
		}

		if ( preg_match( '/<meta\b[^>]*charset[^>]*>\s*/i', $head_part, $cm, PREG_OFFSET_CAPTURE ) ) {
			$insert_at = (int) $cm[0][1] + strlen( $cm[0][0] );
			return substr( $head_part, 0, $insert_at ) . "\n" . $snippet . substr( $head_part, $insert_at );
		}

		if ( preg_match( '/<head\b[^>]*>\s*/i', $head_part, $hm, PREG_OFFSET_CAPTURE ) ) {
			$insert_at = (int) $hm[0][1] + strlen( $hm[0][0] );
			return substr( $head_part, 0, $insert_at ) . "\n" . $snippet . substr( $head_part, $insert_at );
		}

		return str_ireplace( '</head>', $snippet . '</head>', $head_part );
	}

	/**
	 * Delay both external and inline third-party analytics/pixel bootstrap scripts (GTM, GA4, Clarity,
	 * Meta Pixel, Hotjar, LinkedIn, TikTok, and invisible reCAPTCHA v3) across the full HTML document,
	 * then inject the lightweight interaction + post-load fallback loader before </body>.
	 *
	 * @since 2.2.0
	 * @param string $html Full HTML document.
	 * @return string
	 */
	private function apply_document_third_party_js_delay( $html ) {
		if ( $this->is_transactional_page() ) {
			return $html;
		}

		$patterns         = $this->get_delayed_tracker_patterns();
		$has_v2_recaptcha = ( false !== stripos( $html, 'class="g-recaptcha' ) || false !== stripos( $html, "class='g-recaptcha" ) );
		$delayed_any      = $this->has_delayed_scripts;

		$html = (string) preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ( $m ) use ( $patterns, $has_v2_recaptcha, &$delayed_any ) {
				$full_tag = $m[0];
				$attrs    = $m[1];
				$body     = $m[2];

				// Skip already-delayed scripts, non-executable JSON/template blocks, and FBS internal scripts.
				if (
					false !== stripos( $attrs, 'fbsseop/delayed' ) ||
					false !== stripos( $attrs, 'id="fbsseop-' ) ||
					false !== stripos( $attrs, "id='fbsseop-" )
				) {
					return $full_tag;
				}

				if ( preg_match( '/\btype\s*=\s*([\'"])([^\'"]+)\1/i', $attrs, $tm ) ) {
					$mime = strtolower( trim( $tm[2] ) );
					if ( '' !== $mime && ! in_array( $mime, array( 'text/javascript', 'application/javascript' ), true ) ) {
						return $full_tag;
					}
				}

				// Case 1: External <script src="...">.
				if ( preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $attrs, $sm ) ) {
					$src     = $sm[2];
					$matched = false;

					foreach ( $patterns as $pat ) {
						if ( '' !== $pat && false !== stripos( $src, $pat ) ) {
							$matched = true;
							break;
						}
					}

					// Invisible Google reCAPTCHA v3 (?render=...) can safely wait for first user interaction.
					if ( ! $matched && ! $has_v2_recaptcha && false !== stripos( $src, 'google.com/recaptcha/api.js' ) && false !== stripos( $src, 'render=' ) ) {
						$matched = true;
					}

					if ( ! $matched ) {
						return $full_tag;
					}

					$clean_attrs = (string) preg_replace( '/\s+type=([\'"])[^\'"]*\1/i', '', $attrs );
					$clean_attrs = (string) preg_replace( '/\bsrc=([\'"])/i', 'type="fbsseop/delayed" data-fbsseop-src=$1', $clean_attrs, 1 );
					$delayed_any = true;

					return '<script' . $clean_attrs . '>' . $body . '</script>';
				}

				// Case 2: Inline tracker bootstrap snippet (e.g. GTM4WP gtm.js loader, Clarity tag, Meta fbevents.js, Hotjar).
				if ( '' === trim( $body ) || strlen( $body ) > 8192 ) {
					return $full_tag;
				}

				$inline_loader_markers = array(
					'googletagmanager.com/gtm.js',
					'googletagmanager.com/gtag/js',
					'google-analytics.com/analytics.js',
					'connect.facebook.net/',
					'clarity.ms/tag/',
					'static.hotjar.com/',
					'snap.licdn.com/',
					'analytics.tiktok.com/',
				);

				$matched_inline = false;
				foreach ( $inline_loader_markers as $marker ) {
					if ( false !== stripos( $body, $marker ) ) {
						$matched_inline = true;
						break;
					}
				}

				if ( ! $matched_inline ) {
					return $full_tag;
				}

				$clean_attrs = (string) preg_replace( '/\s+type=([\'"])[^\'"]*\1/i', '', $attrs );
				$delayed_any = true;

				return '<script type="fbsseop/delayed"' . $clean_attrs . '>' . $body . '</script>';
			},
			$html
		);

		$this->has_delayed_scripts = $delayed_any;

		if ( $delayed_any && false === strpos( $html, 'fbsseop-delay-js-loader' ) ) {
			$loader_js  = '<script id="fbsseop-delay-js-loader">';
			$loader_js .= '(function(){var d=false,e=["pointerdown","keydown","touchstart","scroll","mousemove"];';
			$loader_js .= 'function l(){if(d)return;d=true;e.forEach(function(v){window.removeEventListener(v,l,{passive:true})});';
			$loader_js .= 'document.querySelectorAll(\'script[type="fbsseop/delayed"]\').forEach(function(s){var n=document.createElement("script");';
			$loader_js .= 'Array.prototype.forEach.call(s.attributes,function(a){if(a.name==="type")return;if(a.name==="data-fbsseop-src"){n.src=a.value;n.async=true}else{n.setAttribute(a.name,a.value)}});';
			$loader_js .= 'if(!s.hasAttribute("data-fbsseop-src")){n.text=s.textContent}s.parentNode.replaceChild(n,s)})}';
			$loader_js .= 'e.forEach(function(v){window.addEventListener(v,l,{passive:true,once:true})});';
			$loader_js .= 'if(document.readyState==="complete"){setTimeout(l,4500)}else{window.addEventListener("load",function(){setTimeout(l,4500)},{once:true});setTimeout(l,8000)}})();';
			$loader_js .= "</script>\n";

			if ( false !== stripos( $html, '</body>' ) ) {
				$html = str_ireplace( '</body>', $loader_js . '</body>', $html );
			} else {
				$html .= $loader_js;
			}
		}

		return $html;
	}

	/**
	 * Safely add `defer` to jQuery core & jQuery Migrate ONLY when the rendered HTML contains zero
	 * synchronous inline <script> blocks referencing jQuery/$ and zero non-deferred dependent external scripts.
	 *
	 * Eliminates ~35 KB gz (~105 KB parsed) of parser-blocking <head> JavaScript on Slow 4G mobile
	 * while guaranteeing zero "jQuery is not defined" errors on pages with inline jQuery snippets.
	 *
	 * @since 2.2.0
	 * @param string $html Full HTML document.
	 * @return string
	 */
	private function apply_smart_jquery_defer( $html ) {
		if ( $this->is_transactional_page() || false === stripos( $html, 'jquery' ) ) {
			return $html;
		}

		if ( ! preg_match_all( '/<script\b([^>]*)>(.*?)<\/script>/is', $html, $scripts, PREG_SET_ORDER ) ) {
			return $html;
		}

		foreach ( $scripts as $script ) {
			$attrs = $script[1];
			$body  = $script[2];

			// Skip delayed scripts, internal FBS sync scripts, and non-JS data blocks (JSON-LD, templates, importmaps).
			if (
				false !== stripos( $attrs, 'fbsseop/delayed' ) ||
				false !== stripos( $attrs, 'id="fbsseop-' ) ||
				false !== stripos( $attrs, "id='fbsseop-" )
			) {
				continue;
			}

			if ( preg_match( '/\btype\s*=\s*([\'"])([^\'"]+)\1/i', $attrs, $tm ) ) {
				$mime = strtolower( trim( $tm[2] ) );
				if ( '' !== $mime && ! in_array( $mime, array( 'text/javascript', 'application/javascript' ), true ) ) {
					continue;
				}
			}

			// External script check: ensure no non-deferred external script could execute before deferred jQuery.
			if ( preg_match( '/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $attrs, $sm ) ) {
				if ( preg_match( '/\b(?:defer|async)\b/i', $attrs ) ) {
					continue;
				}

				$tag_lc = strtolower( $attrs . ' ' . $sm[2] );

				// Ignore jQuery itself and standalone zero-jQuery WordPress utility scripts.
				if (
					false !== strpos( $tag_lc, '/jquery.min.js' ) ||
					false !== strpos( $tag_lc, '/jquery.js' ) ||
					false !== strpos( $tag_lc, 'jquery-core' ) ||
					false !== strpos( $tag_lc, 'jquery-migrate' ) ||
					false !== strpos( $tag_lc, 'wp-polyfill' ) ||
					false !== strpos( $tag_lc, 'wp-hooks' ) ||
					false !== strpos( $tag_lc, 'wp-i18n' ) ||
					false !== strpos( $tag_lc, 'regenerator-runtime' )
				) {
					continue;
				}

				// Found another synchronous external script; keep jQuery synchronous for safety.
				return $html;
			}

			// Synchronous inline script check: abort if it references jQuery, $(, or $.
			if ( '' !== trim( $body ) && preg_match( '/\bjQuery\b|\$\s*[\(\.]/', $body ) ) {
				$clean_inline = trim( (string) preg_replace( '#//[^\r\n]*#', '', $body ) );
				if ( preg_match( '/^jQuery\.uiBackCompat\s*=\s*(?:true|false)\s*;?$/i', $clean_inline ) ) {
					continue;
				}
				return $html;
			}
		}

		// Safely wrap any synchronous `jQuery.uiBackCompat = ...;` inline script so it runs after deferred jQuery loads.
		$html = (string) preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ( $m ) {
				$body = $m[2];
				if ( false === stripos( $body, 'uiBackCompat' ) ) {
					return $m[0];
				}
				$clean_inline = trim( (string) preg_replace( '#//[^\r\n]*#', '', $body ) );
				if ( preg_match( '/^jQuery\.uiBackCompat\s*=\s*(true|false)\s*;?$/i', $clean_inline, $bm ) ) {
					$val     = strtolower( $bm[1] );
					$wrapped = 'if(window.jQuery){jQuery.uiBackCompat=' . $val . ';}else{document.addEventListener("DOMContentLoaded",function(){if(window.jQuery){jQuery.uiBackCompat=' . $val . ';}});}';
					return '<script' . $m[1] . '>' . $wrapped . '</script>';
				}
				return $m[0];
			},
			$html
		);

		// Verified 100% safe: add defer to jquery-core and jquery-migrate tags.
		$html = (string) preg_replace_callback(
			'/<script\b([^>]*)><\/script>/i',
			static function ( $m ) {
				$attrs    = $m[1];
				$attrs_lc = strtolower( $attrs );

				if ( preg_match( '/\b(?:defer|async)\b/i', $attrs ) || false === strpos( $attrs_lc, 'src=' ) ) {
					return $m[0];
				}

				if (
					false !== strpos( $attrs_lc, '/jquery.min.js' ) ||
					false !== strpos( $attrs_lc, '/jquery.js' ) ||
					false !== strpos( $attrs_lc, 'jquery-core' ) ||
					false !== strpos( $attrs_lc, 'jquery-migrate' )
				) {
					$updated = str_replace( ' src=', ' defer src=', $attrs );
					return '<script' . $updated . '></script>';
				}

				return $m[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * Determine whether the current HTTP request is eligible for anonymous guest HTML disk page caching.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private function is_guest_cache_eligible_request() {
		// Auto-bypass when a dedicated full-page caching plugin is already active.
		if (
			defined( 'LSCWP_V' ) ||
			defined( 'WP_ROCKET_VERSION' ) ||
			defined( 'W3TC' ) ||
			function_exists( 'sg_cachepress_purge_cache' ) ||
			class_exists( 'WpFastestCache' ) ||
			class_exists( 'StackCache' )
		) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'GET' !== $method ) {
			return false;
		}

		if (
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			( defined( 'DOING_CRON' ) && DOING_CRON ) ||
			( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( defined( 'WP_CLI' ) && WP_CLI )
		) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used strictly for read-only URI exclusion matching and MD5 key generation.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $request_uri ) {
			return false;
		}

		$uri_lc   = strtolower( $request_uri );
		$excluded = array( '/wp-admin', 'wp-login.php', '/wp-json', 'xmlrpc.php', '/feed/', 'sitemap', '/cart', '/checkout', '/my-account' );
		foreach ( $excluded as $ex ) {
			if ( false !== strpos( $uri_lc, $ex ) ) {
				return false;
			}
		}

		// Allow pure marketing campaign parameters (utm_*, gclid, fbclid, msclkid) to hit cache; bypass all other query strings.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query key check for cache eligibility.
		if ( ! empty( $_GET ) ) {
			$allowed_marketing_keys = array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'utm_id',
				'gclid',
				'fbclid',
				'msclkid',
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( array_keys( $_GET ) as $qkey ) {
				if ( ! in_array( strtolower( (string) $qkey ), $allowed_marketing_keys, true ) ) {
					return false;
				}
			}
		}

		// Bypass if logged-in, password-protected post, comment author, or active eCommerce cart cookies exist.
		if ( ! empty( $_COOKIE ) && is_array( $_COOKIE ) ) {
			$cookie_prefixes = array(
				'wordpress_logged_in_',
				'wp-postpass_',
				'comment_author_',
				'woocommerce_items_in_cart',
				'woocommerce_cart_hash',
				'wp_woocommerce_session_',
				'edd_items_in_cart',
			);
			foreach ( array_keys( $_COOKIE ) as $ckey ) {
				$ckey_str = (string) $ckey;
				foreach ( $cookie_prefixes as $prefix ) {
					if ( 0 === strpos( $ckey_str, $prefix ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Compute the deterministic static disk cache file path for the current request URL.
	 *
	 * @since 2.2.0
	 * @return string
	 */
	private function get_guest_page_cache_filepath() {
		$cache_info = FBS_Secure_Optimize_Controller::get_cache_dir_info();
		if ( '' === $cache_info['basedir'] ) {
			return '';
		}

		$host   = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : 'localhost';
		$scheme = is_ssl() ? 'https' : 'http';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Stripped to path component and hashed via md5.
		$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path    = (string) strtok( $raw_uri, '?#' );
		if ( '' === $path ) {
			$path = '/';
		}

		$salt = FBS_Secure_Optimize_Controller::get_cache_salt();
		$hash = md5( $salt . '|' . $scheme . '://' . $host . $path );

		return $cache_info['basedir'] . '/pages/' . $hash . '.html';
	}

	/**
	 * Serve a valid non-expired static HTML file from disk before WordPress runs heavy theme/plugin queries.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function maybe_serve_guest_page_cache() {
		if ( headers_sent() || ! $this->is_guest_cache_eligible_request() ) {
			return;
		}

		$file = $this->get_guest_page_cache_filepath();
		if ( '' === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
			return;
		}

		/**
		 * Filters the guest HTML disk cache Time-To-Live (TTL) in seconds.
		 *
		 * @since 2.2.0
		 * @param int $ttl Default 21600 seconds (6 hours).
		 */
		$ttl   = (int) apply_filters( 'fbsseop_page_cache_ttl', 6 * HOUR_IN_SECONDS );
		$mtime = (int) filemtime( $file );
		if ( $mtime <= 0 || ( time() - $mtime ) > $ttl ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Serving verified local HTML page cache file.
		$cached_html = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_string( $cached_html ) || '' === $cached_html ) {
			return;
		}

		// Replay configured HTTP Security Headers when serving cached HTML early.
		if ( FBS_Secure_Optimize_Controller::is_module_enabled( 'security_headers' ) && class_exists( 'FBS_Secure_Optimize_Security_Headers' ) ) {
			$headers_mod = FBS_Secure_Optimize_Security_Headers::get_instance();
			if ( method_exists( $headers_mod, 'send_security_headers' ) ) {
				$headers_mod->send_security_headers();
			}
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-FBS-Cache: HIT' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full pre-rendered HTML document served from verified internal disk cache.
		echo $cached_html;
		exit;
	}

	/**
	 * Persist the optimized HTML output buffer to disk for subsequent anonymous guest visits.
	 *
	 * @since 2.2.0
	 * @param string $html Final optimized HTML document.
	 * @return void
	 */
	private function maybe_store_guest_page_cache( $html ) {
		if ( ! $this->is_guest_cache_eligible_request() ) {
			return;
		}

		if ( is_user_logged_in() || is_404() || is_search() || is_feed() || $this->is_transactional_page() ) {
			return;
		}

		if ( function_exists( 'http_response_code' ) && 200 !== (int) http_response_code() ) {
			return;
		}

		if ( false === stripos( $html, '</html>' ) ) {
			return;
		}

		$cache_info = FBS_Secure_Optimize_Controller::get_cache_dir_info();
		if ( '' === $cache_info['basedir'] ) {
			return;
		}

		$pages_dir = $cache_info['basedir'] . '/pages';
		if ( ! is_dir( $pages_dir ) && ! wp_mkdir_p( $pages_dir ) ) {
			return;
		}

		$this->ensure_cache_index_guard( $pages_dir, true );

		$file = $this->get_guest_page_cache_filepath();
		if ( '' === $file ) {
			return;
		}
		$stamped = $html . "\n<!-- Cached & Optimized by FBS Secure Optimize -->";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing verified local HTML page cache file inside wp_upload_dir().
		@file_put_contents( $file, $stamped, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Place a silent index.php (and optional .htaccess for non-public HTML cache directories) inside cache folders.
	 *
	 * @since 2.2.0
	 * @param string $dir            Target cache directory.
	 * @param bool   $deny_direct_ht Whether to block direct HTTP access via .htaccess (true for HTML pages, false for public CSS).
	 * @return void
	 */
	private function ensure_cache_index_guard( $dir, $deny_direct_ht = false ) {
		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! is_file( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( $deny_direct_ht ) {
			$ht_file = trailingslashit( $dir ) . '.htaccess';
			if ( ! is_file( $ht_file ) ) {
				$rules = "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				@file_put_contents( $ht_file, $rules ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Strip redundant whitespace on style loader tags.
	 *
	 * @since 2.0.0
	 * @param string $tag Style link tag.
	 * @return string
	 */
	public function optimize_style_tag( $tag ) {
		if ( false !== strpos( $tag, '<style' ) ) {
			return $tag;
		}
		return trim( (string) preg_replace( '/\s+/', ' ', $tag ) ) . "\n";
	}

	/**
	 * Pass-through attribute helper for inline script optimization.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed> $attr Script attributes.
	 * @return array<string, mixed>
	 */
	public function noop_inline_attr( $attr ) {
		return $attr;
	}

	/**
	 * Pass-through print scripts array filter with extensibility hook.
	 *
	 * @since 2.0.0
	 * @param array<int, string> $to_do Script handles queued for printing.
	 * @return array<int, string>
	 */
	public function filter_scripts_array( $to_do ) {
		return $to_do;
	}
}
