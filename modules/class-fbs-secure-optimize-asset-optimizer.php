<?php
/**
 * Asset Optimizer module for FBS Secure Optimize plugin
 *
 * @package FBS_Optimize
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Asset Optimizer class
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */
class FBS_Secure_Optimize_Asset_Optimizer {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Asset_Optimizer
     */
    private static $instance = null;

    /**
     * Combined CSS files
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var array
     */
    private $combined_css = array();

    /**
     * Combined JS files
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var array
     */
    private $combined_js = array();

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Asset_Optimizer
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        // Only run on frontend
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'optimize_assets'), 999);
            add_action('wp_head', array($this, 'add_lazy_loading_script'), 1);
            add_filter('script_loader_tag', array($this, 'add_lazy_loading_attributes'), 10, 2);
            add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading_to_images'), 10, 3);
        }
    }

    /**
     * Optimize assets based on settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function optimize_assets() {
        $settings = get_option('fbs_opt_settings', array());
        $asset_settings = isset($settings['asset_optimization']) ? $settings['asset_optimization'] : array();

        // Minify CSS
        if (isset($asset_settings['minify_css']) && $asset_settings['minify_css']) {
            add_action('wp_head', array($this, 'minify_css'), 999);
        }

        // Minify JS
        if (isset($asset_settings['minify_js']) && $asset_settings['minify_js']) {
            add_action('wp_footer', array($this, 'minify_js'), 999);
        }

        // Combine CSS
        if (isset($asset_settings['combine_css']) && $asset_settings['combine_css']) {
            add_action('wp_head', array($this, 'combine_css'), 999);
        }

        // Combine JS
        if (isset($asset_settings['combine_js']) && $asset_settings['combine_js']) {
            add_action('wp_footer', array($this, 'combine_js'), 999);
        }
    }

    /**
     * Add lazy loading script to head
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function add_lazy_loading_script() {
        $settings = get_option('fbs_opt_settings', array());
        $asset_settings = isset($settings['asset_optimization']) ? $settings['asset_optimization'] : array();

        if (isset($asset_settings['lazy_load_images']) && $asset_settings['lazy_load_images']) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Simple lazy loading implementation
                const lazyImages = document.querySelectorAll('img[data-src]');
                const lazyIframes = document.querySelectorAll('iframe[data-src]');
                
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            img.classList.add('lazy-loaded');
                            observer.unobserve(img);
                        }
                    });
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
                lazyIframes.forEach(iframe => imageObserver.observe(iframe));
            });
            </script>
            <?php
        }
    }

    /**
     * Add lazy loading attributes to script tags
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public function add_lazy_loading_attributes($tag, $handle) {
        $settings = get_option('fbs_opt_settings', array());
        $asset_settings = isset($settings['asset_optimization']) ? $settings['asset_optimization'] : array();

        if (isset($asset_settings['lazy_load_iframes']) && $asset_settings['lazy_load_iframes']) {
            // Add loading="lazy" to iframe scripts if needed
            if (strpos($tag, 'iframe') !== false) {
                $tag = str_replace('<iframe', '<iframe loading="lazy"', $tag);
            }
        }

        return $tag;
    }

    /**
     * Add lazy loading to images
    * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $attr Image attributes
     * @param object $attachment Attachment object
     * @param string $size Image size
     * @return array Modified attributes
     */
    public function add_lazy_loading_to_images($attr, $attachment, $size) {
        $settings = get_option('fbs_opt_settings', array());
        $asset_settings = isset($settings['asset_optimization']) ? $settings['asset_optimization'] : array();

        if (isset($asset_settings['lazy_load_images']) && $asset_settings['lazy_load_images']) {
            // Add lazy loading attributes
            if (isset($attr['src'])) {
                $attr['data-src'] = $attr['src'];
                $attr['src'] = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
                $attr['class'] = (isset($attr['class']) ? $attr['class'] . ' ' : '') . 'lazy';
            }
        }

        return $attr;
    }

    /**
     * Minify CSS output
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function minify_css() {
        ob_start(array($this, 'minify_css_callback'));
    }

    /**
     * CSS minification callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $buffer CSS content
     * @return string Minified CSS
     */
    public function minify_css_callback($buffer) {
        // Remove comments
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
        
        // Remove unnecessary whitespace
        $buffer = str_replace(array("\r\n", "\r", "\n", "\t"), '', $buffer);
        $buffer = preg_replace('/\s+/', ' ', $buffer);
        
        // Remove spaces around specific characters
        $buffer = str_replace(array(' {', '{ ', ' }', '} ', '; ', ' ;', ': ', ' :', ', ', ' ,'), array('{', '{', '}', '}', ';', ';', ':', ':', ',', ','), $buffer);
        
        return $buffer;
    }

    /**
     * Minify JS output
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function minify_js() {
        ob_start(array($this, 'minify_js_callback'));
    }

    /**
     * JS minification callback
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $buffer JS content
     * @return string Minified JS
     */
    public function minify_js_callback($buffer) {
        // Remove single-line comments
        $buffer = preg_replace('~//[^\r\n]*~', '', $buffer);
        
        // Remove multi-line comments
        $buffer = preg_replace('~/\*.*?\*/~s', '', $buffer);
        
        // Remove unnecessary whitespace
        $buffer = preg_replace('/\s+/', ' ', $buffer);
        
        // Remove spaces around operators
        $buffer = preg_replace('/\s*([{}();,=+\-*\/])\s*/', '$1', $buffer);
        
        return $buffer;
    }

    /**
     * Combine CSS files
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function combine_css() {
        global $wp_styles;
        
        if (empty($wp_styles->queue)) {
            return;
        }

        $combined_css = array();
        $combined_url = $this->get_combined_css_url();

        // Collect CSS files to combine
        foreach ($wp_styles->queue as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $style = $wp_styles->registered[$handle];
                
                // Skip external URLs and conditional styles
                if (strpos($style->src, 'http') === 0 && strpos($style->src, home_url()) === false) {
                    continue;
                }
                
                if (isset($style->extra['conditional'])) {
                    continue;
                }
                
                $combined_css[] = $style;
                $this->combined_css[] = $handle;
            }
        }

        // Remove original styles
        foreach ($this->combined_css as $handle) {
            wp_dequeue_style($handle);
        }

        // Add combined CSS
        if (!empty($combined_css)) {
            wp_enqueue_style('fbs-opt-combined-css', $combined_url, array(), FBS_SECURE_OPTIMIZE_VERSION);
        }
    }

    /**
     * Combine JS files
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function combine_js() {
        global $wp_scripts;
        
        if (empty($wp_scripts->queue)) {
            return;
        }

        $combined_js = array();
        $combined_url = $this->get_combined_js_url();

        // Collect JS files to combine
        foreach ($wp_scripts->queue as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $script = $wp_scripts->registered[$handle];
                
                // Skip external URLs and conditional scripts
                if (strpos($script->src, 'http') === 0 && strpos($script->src, home_url()) === false) {
                    continue;
                }
                
                if (isset($script->extra['conditional'])) {
                    continue;
                }
                
                $combined_js[] = $script;
                $this->combined_js[] = $handle;
            }
        }

        // Remove original scripts
        foreach ($this->combined_js as $handle) {
            wp_dequeue_script($handle);
        }

        // Add combined JS
        if (!empty($combined_js)) {
            wp_enqueue_script('fbs-opt-combined-js', $combined_url, array(), FBS_SECURE_OPTIMIZE_VERSION, true);
        }
    }

    /**
     * Get combined CSS URL
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return string Combined CSS URL
     */
    private function get_combined_css_url() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/fbs-opt-cache';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $cache_file = $cache_dir . '/combined-' . md5(serialize($this->combined_css)) . '.css';
        $cache_url = $upload_dir['baseurl'] . '/fbs-opt-cache/combined-' . md5(serialize($this->combined_css)) . '.css';
        
        // Generate combined CSS if cache doesn't exist
        if (!file_exists($cache_file)) {
            $this->generate_combined_css($cache_file);
        }
        
        return $cache_url;
    }

    /**
     * Get combined JS URL
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return string Combined JS URL
     */
    private function get_combined_js_url() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/fbs-opt-cache';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $cache_file = $cache_dir . '/combined-' . md5(serialize($this->combined_js)) . '.js';
        $cache_url = $upload_dir['baseurl'] . '/fbs-opt-cache/combined-' . md5(serialize($this->combined_js)) . '.js';
        
        // Generate combined JS if cache doesn't exist
        if (!file_exists($cache_file)) {
            $this->generate_combined_js($cache_file);
        }
        
        return $cache_url;
    }

    /**
     * Generate combined CSS file
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $cache_file Cache file path
     */
    private function generate_combined_css($cache_file) {
        global $wp_styles;
        
        $combined_content = '';
        
        foreach ($this->combined_css as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $style = $wp_styles->registered[$handle];
                $file_path = $this->get_file_path($style->src);
                
                if ($file_path && file_exists($file_path)) {
                    $content = file_get_contents($file_path);
                    
                    // Minify CSS content
                    $content = $this->minify_css_callback($content);
                    
                    $combined_content .= $content . "\n";
                }
            }
        }
        
        // Write combined CSS to cache file
        file_put_contents($cache_file, $combined_content);
    }

    /**
     * Generate combined JS file
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $cache_file Cache file path
     */
    private function generate_combined_js($cache_file) {
        global $wp_scripts;
        
        $combined_content = '';
        
        foreach ($this->combined_js as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $script = $wp_scripts->registered[$handle];
                $file_path = $this->get_file_path($script->src);
                
                if ($file_path && file_exists($file_path)) {
                    $content = file_get_contents($file_path);
                    
                    // Minify JS content
                    $content = $this->minify_js_callback($content);
                    
                    $combined_content .= $content . ";\n";
                }
            }
        }
        
        // Write combined JS to cache file
        file_put_contents($cache_file, $combined_content);
    }

    /**
     * Get file path from URL
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $url File URL
     * @return string|false File path or false if not found
     */
    private function get_file_path($url) {
        $parsed_url = parse_url($url);
        
        if (isset($parsed_url['host']) && $parsed_url['host'] !== parse_url(home_url(), PHP_URL_HOST)) {
            return false; // External URL
        }
        
        $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
        
        return file_exists($file_path) ? $file_path : false;
    }

    /**
     * Clear asset cache
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function clear_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/fbs-opt-cache';
        
        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get cache statistics
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Cache statistics
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function get_cache_stats() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/fbs-opt-cache';
        
        $stats = array(
            'files' => 0,
            'size' => 0,
        );
        
        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . '/*');
            $stats['files'] = count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $stats['size'] += filesize($file);
                }
            }
        }
        
        return $stats;
    }
}
