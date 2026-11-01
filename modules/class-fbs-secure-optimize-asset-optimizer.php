<?php
/**
 * Asset & Bloat Optimizer Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend Core Web Vitals asset optimization and WordPress bloat removal.
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
	 * Counter to skip lazy-loading the first above-the-fold image (protects LCP).
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

		// 1. Disable WordPress Emojis (saves extra JS + DNS request on every page).
		if ( ! empty( $opts['disable_emojis'] ) ) {
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

		if ( ! empty( $opts['defer_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 20, 2 );
		}

		if ( ! empty( $opts['delay_third_party_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'maybe_delay_third_party_script' ), 30, 3 );
			add_action( 'wp_footer', array( $this, 'print_delayed_scripts_loader' ), 99 );
		}

		if ( ! empty( $opts['minify_css'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'optimize_style_tag' ), 40, 1 );
		}

		if ( ! empty( $opts['minify_js'] ) ) {
			add_filter( 'wp_inline_script_attributes', array( $this, 'noop_inline_attr' ), 10, 1 );
			add_filter( 'print_scripts_array', array( $this, 'filter_scripts_array' ), 10, 1 );
		}
	}

	/**
	 * Determine whether frontend tag rewrites should be bypassed (feeds, Customizer, REST, visual builders).
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	private function should_bypass_frontend() {
		if ( is_admin() || is_feed() || is_preview() || is_customize_preview() || wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		$builder_params = array(
			'elementor-preview',
			'et_fb',
			'fl_builder',
			'ct_builder',
			'bricks',
			'vc_editable',
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
	 * Remove WordPress core emoji detection scripts and styles.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function disable_wp_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
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
	 * Add native decoding="async" and loading="lazy" while protecting the first LCP image.
	 *
	 * @since 2.0.0
	 * @param array<string, string> $attr Image HTML attributes.
	 * @return array<string, string>
	 */
	public function enhance_attachment_image_attributes( $attr ) {
		if ( $this->should_bypass_frontend() ) {
			return $attr;
		}

		++$this->image_count;
		$attr['decoding'] = 'async';

		if ( 1 === $this->image_count ) {
			unset( $attr['loading'] );
			$attr['fetchpriority'] = 'high';
		} elseif ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
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

		$index   = 0;
		$content = (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) use ( &$index ) {
				++$index;
				$img = $matches[0];

				if ( false === stripos( $img, 'decoding=' ) ) {
					$img = str_replace( '<img', '<img decoding="async"', $img );
				}

				if ( 1 === $index && 0 === $this->image_count ) {
					++$this->image_count;
					// Strip any theme/editor-injected loading="lazy" on the first LCP image.
					$img = (string) preg_replace( '/\s+loading=([\'"])lazy\1/i', '', $img );
					if ( false === stripos( $img, 'fetchpriority=' ) ) {
						$img = str_replace( '<img', '<img fetchpriority="high"', $img );
					}
				} elseif ( $index > 1 && false === stripos( $img, 'loading=' ) ) {
					$img = str_replace( '<img', '<img loading="lazy"', $img );
				}

				return $img;
			},
			$content
		);

		return $content;
	}

	/**
	 * Feature 3: Automatically inject missing width and height attributes on local images to prevent CLS.
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
						}
					}

					if ( count( $dims_cache ) < 250 ) {
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
	 * Feature 1a: Inject preconnect resource hints for Google Fonts when enqueued by theme/plugins.
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

		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
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
	 * Feature 1b: Convert render-blocking Google Fonts stylesheets to non-blocking print/onload swap + display=swap.
	 *
	 * @since 2.1.0
	 * @param string $tag    Enqueued style HTML tag.
	 * @param string $handle Registered style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Stylesheet media attribute.
	 * @return string
	 */
	public function optimize_google_fonts_tag( $tag, $handle, $href, $media ) {
		unset( $handle );

		if ( $this->should_bypass_frontend() || empty( $href ) || false === stripos( $href, 'fonts.googleapis.com/css' ) ) {
			return $tag;
		}

		if ( 'print' === $media || false !== stripos( $tag, 'onload=' ) ) {
			return $tag;
		}

		// Ensure font-display: swap is active to prevent invisible text (FOIT) and CLS shifts.
		if ( false === stripos( $href, 'display=' ) ) {
			$swap_href = add_query_arg( 'display', 'swap', $href );
			$tag       = str_replace( $href, esc_url( $swap_href ), $tag );
		}

		$noscript_fallback = '<noscript>' . trim( $tag ) . '</noscript>';

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

		return trim( $async_tag ) . "\n" . $noscript_fallback . "\n";
	}

	/**
	 * Feature 2: Auto-inline small render-blocking local stylesheets (<= 8 KB) to eliminate network round-trips.
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

		/**
		 * Filters the maximum file size (in bytes) eligible for automatic CSS inlining.
		 *
		 * @since 2.1.0
		 * @param int $max_bytes Default 8192 bytes (8 KB).
		 */
		$max_bytes = (int) apply_filters( 'fbsseop_inline_css_max_bytes', 8192 );
		$file_size = (int) filesize( $local_path );
		if ( $file_size <= 0 || $file_size > $max_bytes ) {
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

			// Skip files containing @import rules, since @import must precede other rules or stay external.
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
	 * Rewrite relative url(...) references to absolute URLs and minify CSS comments/whitespace.
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

		// Rewrite relative url(...) references so background images and fonts never 404 when inlined.
		$css = (string) preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			static function ( $matches ) use ( $base_dir_url ) {
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

				$abs_url = $base_dir_url . $url;
				return 'url(' . $quote . esc_url_raw( $abs_url ) . $quote . ')';
			},
			$css
		);

		// Safe lightweight whitespace minification.
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = (string) preg_replace( '/\s*([\{\};:,>~])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );

		// Prevent accidental premature closing of the <style> tag.
		$css = str_ireplace( '</style', '', $css );

		return trim( $css );
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
	 * Safely add defer attribute to non-critical frontend scripts while excluding core & checkout handles.
	 *
	 * @since 2.0.0
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Registered script handle.
	 * @return string
	 */
	public function defer_non_critical_scripts( $tag, $handle ) {
		if ( $this->should_bypass_frontend() ) {
			return $tag;
		}

		$excluded_handles = array(
			'jquery',
			'jquery-core',
			'jquery-migrate',
			'wp-hooks',
			'wp-i18n',
			'wp-api-fetch',
			'wc-checkout',
			'wc-cart-fragments',
			'wc-add-to-cart',
		);

		/**
		 * Filters script handles excluded from JS deferring.
		 *
		 * @since 2.0.0
		 * @param array<int, string> $excluded_handles Script handles to skip.
		 */
		$excluded_handles = apply_filters( 'fbsseop_defer_excluded_handles', $excluded_handles );

		if ( in_array( $handle, $excluded_handles, true ) ) {
			return $tag;
		}

		if ( false !== stripos( $tag, ' defer' ) || false !== stripos( $tag, ' async' ) || false === stripos( $tag, ' src=' ) ) {
			return $tag;
		}

		return str_replace( ' src=', ' defer src=', $tag );
	}

	/**
	 * Feature 4a: Delay heavy third-party analytics & tracking scripts until first user interaction or 3.5s timer.
	 *
	 * Automatically bypasses WooCommerce cart, checkout, and account pages to protect conversion tracking.
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

		// Protect eCommerce transactional pages (cart, checkout, customer account).
		if (
			( function_exists( 'is_cart' ) && is_cart() ) ||
			( function_exists( 'is_checkout' ) && is_checkout() ) ||
			( function_exists( 'is_account_page' ) && is_account_page() )
		) {
			return $tag;
		}

		$patterns = array(
			'googletagmanager.com/gtag/js',
			'googletagmanager.com/gtm.js',
			'google-analytics.com/analytics.js',
			'connect.facebook.net/',
			'clarity.ms/',
			'static.hotjar.com/',
			'snap.licdn.com/',
			'analytics.tiktok.com/',
		);

		/**
		 * Filters URL substrings matched for third-party JS interaction delay.
		 *
		 * @since 2.1.0
		 * @param array<int, string> $patterns Third-party script URL patterns.
		 */
		$patterns = apply_filters( 'fbsseop_delayed_script_patterns', $patterns );

		$matched = false;
		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $src, $pattern ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			return $tag;
		}

		// Replace existing type="..." if present, then mark as fbsseop/delayed with data-fbsseop-src.
		$delayed_tag = (string) preg_replace( '/\s+type=([\'"])[^\'"]*\1/i', '', $tag );
		$delayed_tag = (string) preg_replace( '/\bsrc=([\'"])/i', 'type="fbsseop/delayed" data-fbsseop-src=$1', $delayed_tag, 1 );

		if ( $delayed_tag !== $tag ) {
			$this->has_delayed_scripts = true;
		}

		return $delayed_tag;
	}

	/**
	 * Feature 4b: Print lightweight (<500B) interaction + 3.5s fallback loader only when scripts were delayed.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function print_delayed_scripts_loader() {
		if ( ! $this->has_delayed_scripts ) {
			return;
		}

		$loader_js = '(function(){var d=false,e=["pointerdown","keydown","touchstart","scroll","mousemove"];function l(){if(d)return;d=true;e.forEach(function(v){window.removeEventListener(v,l,{passive:true})});document.querySelectorAll(\'script[type="fbsseop/delayed"]\').forEach(function(s){var n=document.createElement("script");Array.prototype.forEach.call(s.attributes,function(a){if(a.name==="type")return;if(a.name==="data-fbsseop-src"){n.src=a.value;n.async=true}else{n.setAttribute(a.name,a.value)}});s.parentNode.replaceChild(n,s)})}e.forEach(function(v){window.addEventListener(v,l,{passive:true,once:true})});setTimeout(l,3500)})();';

		wp_print_inline_script_tag(
			$loader_js,
			array( 'id' => 'fbsseop-delay-js-loader' )
		);
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
