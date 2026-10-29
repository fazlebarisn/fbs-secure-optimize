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

		if ( ! empty( $opts['lazy_load_iframes'] ) ) {
			add_filter( 'the_content', array( $this, 'lazy_load_content_iframes' ), 20 );
		}

		if ( ! empty( $opts['defer_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 20, 2 );
		}

		if ( ! empty( $opts['minify_css'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'optimize_style_tag' ), 20, 1 );
		}

		if ( ! empty( $opts['minify_js'] ) ) {
			add_filter( 'wp_inline_script_attributes', array( $this, 'noop_inline_attr' ), 10, 1 );
			add_filter( 'print_scripts_array', array( $this, 'filter_scripts_array' ), 10, 1 );
		}
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
	 * Dequeue Dashicons CSS(~35KB) for logged-out frontend visitors.
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
		$this->image_count++;
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
	 * Add native loading="lazy" and decoding="async" to post content images (skipping first image).
	 *
	 * @since 2.0.0
	 * @param string $content Post HTML content.
	 * @return string
	 */
	public function enhance_content_images( $content ) {
		if ( empty( $content ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		$index   = 0;
		$content = (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $matches ) use ( &$index ) {
				$index++;
				$img = $matches[0];

				if ( false === stripos( $img, 'decoding=' ) ) {
					$img = str_replace( '<img', '<img decoding="async"', $img );
				}

				if ( $index > 1 && false === stripos( $img, 'loading=' ) ) {
					$img = str_replace( '<img', '<img loading="lazy"', $img );
				}

				return $img;
			},
			$content
		);

		return $content;
	}

	/**
	 * Add native loading="lazy" to iframes inside post content.
	 *
	 * @since 2.0.0
	 * @param string $content Post HTML content.
	 * @return string
	 */
	public function lazy_load_content_iframes( $content ) {
		if ( empty( $content ) || false === strpos( $content, '<iframe' ) ) {
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
		if ( is_user_logged_in() && is_customize_preview() ) {
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
	 * Strip redundant whitespace on style loader tags.
	 *
	 * @since 2.0.0
	 * @param string $tag Style link tag.
	 * @return string
	 */
	public function optimize_style_tag( $tag ) {
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
