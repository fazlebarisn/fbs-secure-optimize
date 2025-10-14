/**
 * Lazy Loading JavaScript for FBS Secure Optimize plugin
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @package FBS_Optimize
 */

(function() {
    'use strict';

    // Lazy loading implementation
    var FBSOptimizeLazyLoad = {
        
        /**
         * Initialize lazy loading
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        init: function() {
            this.observeImages();
            this.observeIframes();
            this.observeBackgroundImages();
        },

        /**
         * Observe images for lazy loading
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        observeImages: function() {
            var images = document.querySelectorAll('img[data-src]');
            
            if (images.length === 0) {
                return;
            }

            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        FBSOptimizeLazyLoad.loadImage(img);
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            images.forEach(function(img) {
                imageObserver.observe(img);
            });
        },

        /**
         * Observe iframes for lazy loading
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        observeIframes: function() {
            var iframes = document.querySelectorAll('iframe[data-src]');
            
            if (iframes.length === 0) {
                return;
            }

            var iframeObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var iframe = entry.target;
                        FBSOptimizeLazyLoad.loadIframe(iframe);
                        observer.unobserve(iframe);
                    }
                });
            }, {
                rootMargin: '100px 0px',
                threshold: 0.01
            });

            iframes.forEach(function(iframe) {
                iframeObserver.observe(iframe);
            });
        },

        /**
         * Observe background images for lazy loading
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        observeBackgroundImages: function() {
            var elements = document.querySelectorAll('[data-bg]');
            
            if (elements.length === 0) {
                return;
            }

            var bgObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var element = entry.target;
                        FBSOptimizeLazyLoad.loadBackgroundImage(element);
                        observer.unobserve(element);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            elements.forEach(function(element) {
                bgObserver.observe(element);
            });
        },

        /**
         * Load image
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        loadImage: function(img) {
            var src = img.getAttribute('data-src');
            var srcset = img.getAttribute('data-srcset');
            var sizes = img.getAttribute('data-sizes');
            
            if (src) {
                // Add loading class
                img.classList.add('fbs-opt-loading');
                
                // Create new image to preload
                var newImg = new Image();
                
                newImg.onload = function() {
                    // Set the actual src
                    img.src = src;
                    
                    if (srcset) {
                        img.srcset = srcset;
                    }
                    
                    if (sizes) {
                        img.sizes = sizes;
                    }
                    
                    // Remove loading class and add loaded class
                    img.classList.remove('fbs-opt-loading');
                    img.classList.add('fbs-opt-loaded');
                    
                    // Trigger custom event
                    img.dispatchEvent(new CustomEvent('fbs-opt-image-loaded', {
                        detail: { img: img }
                    }));
                };
                
                newImg.onerror = function() {
                    img.classList.remove('fbs-opt-loading');
                    img.classList.add('fbs-opt-error');
                    
                    // Trigger custom event
                    img.dispatchEvent(new CustomEvent('fbs-opt-image-error', {
                        detail: { img: img }
                    }));
                };
                
                newImg.src = src;
            }
        },

        /**
         * Load iframe
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        loadIframe: function(iframe) {
            var src = iframe.getAttribute('data-src');
            
            if (src) {
                // Add loading class
                iframe.classList.add('fbs-opt-loading');
                
                iframe.onload = function() {
                    iframe.classList.remove('fbs-opt-loading');
                    iframe.classList.add('fbs-opt-loaded');
                    
                    // Trigger custom event
                    iframe.dispatchEvent(new CustomEvent('fbs-opt-iframe-loaded', {
                        detail: { iframe: iframe }
                    }));
                };
                
                iframe.onerror = function() {
                    iframe.classList.remove('fbs-opt-loading');
                    iframe.classList.add('fbs-opt-error');
                    
                    // Trigger custom event
                    iframe.dispatchEvent(new CustomEvent('fbs-opt-iframe-error', {
                        detail: { iframe: iframe }
                    }));
                };
                
                iframe.src = src;
            }
        },

        /**
         * Load background image
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        loadBackgroundImage: function(element) {
            var bgSrc = element.getAttribute('data-bg');
            
            if (bgSrc) {
                // Add loading class
                element.classList.add('fbs-opt-loading');
                
                // Create new image to preload
                var newImg = new Image();
                
                newImg.onload = function() {
                    element.style.backgroundImage = 'url(' + bgSrc + ')';
                    element.classList.remove('fbs-opt-loading');
                    element.classList.add('fbs-opt-loaded');
                    
                    // Trigger custom event
                    element.dispatchEvent(new CustomEvent('fbs-opt-bg-loaded', {
                        detail: { element: element }
                    }));
                };
                
                newImg.onerror = function() {
                    element.classList.remove('fbs-opt-loading');
                    element.classList.add('fbs-opt-error');
                    
                    // Trigger custom event
                    element.dispatchEvent(new CustomEvent('fbs-opt-bg-error', {
                        detail: { element: element }
                    }));
                };
                
                newImg.src = bgSrc;
            }
        },

        /**
         * Manually load all lazy elements
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        loadAll: function() {
            var images = document.querySelectorAll('img[data-src]');
            var iframes = document.querySelectorAll('iframe[data-src]');
            var bgElements = document.querySelectorAll('[data-bg]');
            
            images.forEach(this.loadImage);
            iframes.forEach(this.loadIframe);
            bgElements.forEach(this.loadBackgroundImage);
        },

        /**
         * Refresh lazy loading (useful after dynamic content changes)
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        refresh: function() {
            this.init();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            FBSOptimizeLazyLoad.init();
        });
    } else {
        FBSOptimizeLazyLoad.init();
    }

    // Expose to global scope for manual control
    window.FBSOptimizeLazyLoad = FBSOptimizeLazyLoad;

})();
