/**
 * Admin JavaScript for FBS Secure Optimize plugin
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 * @package FBS_Optimize
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        FBSOptimizeAdmin.init();
    });

    // Also initialize after a short delay to ensure all elements are rendered
    $(window).on('load', function() {
        setTimeout(function() {
            FBSOptimizeAdmin.initDependentOptions();
        }, 100);
    });

    // Main admin object
    window.FBSOptimizeAdmin = {
        
        /**
         * Initialize admin functionality
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initTooltips();
            this.initDependentOptions();
        },

        /**
         * Bind event handlers
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        bindEvents: function() {
            // Database cleanup button
            $(document).on('click', '#fbs-opt-cleanup-btn', this.handleCleanupClick);
            
            // Cache clear button
            $(document).on('click', '#fbs-opt-clear-cache-btn', this.handleCacheClearClick);
            
            // Form submission handlers
            $(document).on('submit', '.fbs-opt-form', this.handleFormSubmit);
            
            // Window resize handler for popup positioning
            $(window).on('resize', this.handleWindowResize);
            
            // Settings form submission
            $(document).on('submit', 'form[action*="options.php"]', this.handleFormSubmit);
            
            // Toggle switches
            $(document).on('change', '.fbs-opt-toggle input', this.handleToggleChange);
            $(document).on('click', '.fbs-opt-toggle', this.handleToggleClick);
            
            // Export/Import buttons
            $(document).on('click', '.fbs-opt-export-btn', this.handleExportClick);
            $(document).on('click', '.fbs-opt-import-btn', this.handleImportClick);
            
        },

        /**
         * Initialize tabs
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var tab = $this.data('tab');
                
                if (tab) {
                    FBSOptimizeAdmin.switchTab(tab);
                }
            });
        },

        /**
         * Switch to a specific tab
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        switchTab: function(tab) {
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.fbs-opt-tab-content').removeClass('active');
            $('.fbs-opt-tab-content[data-tab="' + tab + '"]').addClass('active');
            
            // Update URL without page reload
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        },

        /**
         * Initialize tooltips
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $this = $(this);
                var tooltip = $this.data('tooltip');
                
                $this.attr('title', tooltip);
            });
        },

        /**
         * Handle cleanup button click
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleCleanupClick: function(e) {
            e.preventDefault();
            
            if (!confirm(fbsseopAdmin.strings.confirmCleanup)) {
                return;
            }
            
            var $btn = $(this);
            var $status = $('#fbs-opt-cleanup-status');
            
            // Disable button and show loading
            $btn.prop('disabled', true);
            $status.html('<span class="fbs-opt-loading"></span> ' + fbsseopAdmin.strings.cleaning);
            
            // Perform AJAX request
            $.ajax({
                url: fbsseopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbsseop_cleanup_database',
                    nonce: fbsseopAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="fbs-opt-cleanup-status success">✓ ' + response.data.message + '</span>');
                        FBSOptimizeAdmin.showSuccess(response.data.message, 4000);
                        
                        // Refresh statistics if on statistics tab
                        if ($('.nav-tab-active').data('tab') === 'statistics') {
                            FBSOptimizeAdmin.refreshStatistics();
                        }
                    } else {
                        $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + response.data.message + '</span>');
                        FBSOptimizeAdmin.showError(response.data.message, 5000);
                    }
                },
                error: function() {
                    $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + fbsseopAdmin.strings.cleanupError + '</span>');
                    FBSOptimizeAdmin.showError(fbsseopAdmin.strings.cleanupError, 5000);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    
                    // Hide status after 5 seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 5000);
                }
            });
        },

        /**
         * Handle cache clear button click
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleCacheClearClick: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the asset cache? This will force regeneration of optimized files.')) {
                return;
            }
            
            var $btn = $(this);
            var $status = $('#fbs-opt-cache-status');
            
            // Make sure status is visible
            $status.show();
            
            // Disable button and show loading
            $btn.prop('disabled', true);
            $status.html('<span class="fbs-opt-loading"></span> Clearing cache...');
            
            // Perform AJAX request
            $.ajax({
                url: fbsseopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbsseop_clear_cache',
                    nonce: fbsseopAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="fbs-opt-cleanup-status success">✓ ' + response.data.message + '</span>');
                        FBSOptimizeAdmin.showSuccess(response.data.message, 4000);
                        
                        // Refresh statistics if on statistics tab
                        if ($('.nav-tab-active').data('tab') === 'statistics') {
                            FBSOptimizeAdmin.refreshStatistics();
                        }
                    } else {
                        $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + response.data.message + '</span>');
                        FBSOptimizeAdmin.showError(response.data.message, 5000);
                    }
                },
                error: function() {
                    $status.html('<span class="fbs-opt-cleanup-status error">✗ Failed to clear cache</span>');
                    FBSOptimizeAdmin.showError('Failed to clear cache. Please try again.', 5000);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    
                    // Hide status after 5 seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 5000);
                }
            });
        },

        /**
         * Handle form submission
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            
            // Show loading indicator
            $form.find('input[type="submit"]').prop('disabled', true).val('Saving...');
            
            // Re-enable after a short delay
            setTimeout(function() {
                $form.find('input[type="submit"]').prop('disabled', false).val('Save Changes');
            }, 2000);
        },

        /**
         * Handle toggle switch change
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleToggleChange: function() {
            var $toggle = $(this);
            var $container = $toggle.closest('.fbs-opt-toggle');
            
            // Add visual feedback
            $container.addClass('changing');
            setTimeout(function() {
                $container.removeClass('changing');
            }, 200);
            
            // Handle dependent options
            FBSOptimizeAdmin.updateDependentOptions($toggle);
        },

        /**
         * Handle toggle switch click
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleToggleClick: function(e) {
            var $toggle = $(this);
            var $input = $toggle.find('input[type="checkbox"]');
            
            if ($input.length > 0) {
                $input.prop('checked', !$input.prop('checked')).trigger('change');
            }
        },

        /**
         * Handle export button click
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleExportClick: function(e) {
            e.preventDefault();
            
            // Create and download export file
            var settings = FBSOptimizeAdmin.getCurrentSettings();
            var dataStr = JSON.stringify(settings, null, 2);
            var dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            var link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'fbs-optimize-settings.json';
            link.click();
        },

        /**
         * Handle import button click
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleImportClick: function(e) {
            e.preventDefault();
            
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            
            input.onchange = function(event) {
                var file = event.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            var settings = JSON.parse(e.target.result);
                            FBSOptimizeAdmin.importSettings(settings);
                        } catch (error) {
                            alert('Invalid settings file.');
                        }
                    };
                    reader.readAsText(file);
                }
            };
            
            input.click();
        },


        /**
         * Get current settings
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        getCurrentSettings: function() {
            var settings = {};
            
            $('form[action*="options.php"]').find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                
                if (name && name.startsWith('fbsseop_settings')) {
                    var value = $field.val();
                    
                    if ($field.attr('type') === 'checkbox') {
                        value = $field.is(':checked') ? 1 : 0;
                    }
                    
                    // Parse nested setting structure
                    var parts = name.match(/fbsseop_settings\[([^\]]+)\]\[([^\]]+)\]/);
                    if (parts) {
                        var section = parts[1];
                        var option = parts[2];
                        
                        if (!settings[section]) {
                            settings[section] = {};
                        }
                        settings[section][option] = value;
                    }
                }
            });
            
            return settings;
        },

        /**
         * Import settings
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        importSettings: function(settings) {
            if (!confirm('This will overwrite your current settings. Continue?')) {
                return;
            }
            
            $.ajax({
                url: fbsseopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbsseop_import_settings',
                    nonce: fbsseopAdmin.nonce,
                    settings: JSON.stringify(settings)
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to import settings.');
                    }
                },
                error: function() {
                    alert('Failed to import settings.');
                }
            });
        },

        /**
         * Refresh statistics
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        refreshStatistics: function() {
            $.ajax({
                url: fbsseopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbsseop_get_stats',
                    nonce: fbsseopAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FBSOptimizeAdmin.updateStatisticsDisplay(response.data);
                    }
                }
            });
        },

        /**
         * Update statistics display
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        updateStatisticsDisplay: function(stats) {
            // Update database statistics
            $('.fbs-opt-stat-item').each(function() {
                var $item = $(this);
                var statType = $item.data('stat');
                
                if (stats[statType] !== undefined) {
                    $item.find('.fbs-opt-stat-number').text(stats[statType].toLocaleString());
                }
            });
        },

        /**
         * Show beautiful popup notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            
            // Create popup container if it doesn't exist
            if (!$('#fbs-opt-popup-container').length) {
                $('body').append('<div id="fbs-opt-popup-container"></div>');
                this.adjustPopupPosition();
            }
            
            // Get icon and colors based on type
            var iconClass, bgColor, borderColor, textColor;
            switch(type) {
                case 'success':
                    iconClass = 'dashicons-yes-alt';
                    bgColor = '#d4edda';
                    borderColor = '#28a745';
                    textColor = '#155724';
                    break;
                case 'error':
                    iconClass = 'dashicons-warning';
                    bgColor = '#f8d7da';
                    borderColor = '#dc3545';
                    textColor = '#721c24';
                    break;
                case 'warning':
                    iconClass = 'dashicons-warning';
                    bgColor = '#fff3cd';
                    borderColor = '#ffc107';
                    textColor = '#856404';
                    break;
                default: // info
                    iconClass = 'dashicons-info';
                    bgColor = '#d1ecf1';
                    borderColor = '#17a2b8';
                    textColor = '#0c5460';
            }
            
            // Create popup element
            var popupId = 'fbs-opt-popup-' + Date.now();
            var $popup = $('<div class="fbs-opt-popup" id="' + popupId + '">' +
                '<div class="fbs-opt-popup-content">' +
                    '<div class="fbs-opt-popup-icon">' +
                        '<span class="dashicons ' + iconClass + '"></span>' +
                    '</div>' +
                    '<div class="fbs-opt-popup-message">' + message + '</div>' +
                    '<button class="fbs-opt-popup-close" type="button">' +
                        '<span class="dashicons dashicons-no-alt"></span>' +
                    '</button>' +
                '</div>' +
                '<div class="fbs-opt-popup-progress"></div>' +
            '</div>');
            
            // Set colors
            $popup.css({
                'background-color': bgColor,
                'border-left-color': borderColor,
                'color': textColor
            });
            
            // Add to container
            $('#fbs-opt-popup-container').append($popup);
            
            // Set initial state (off-screen to the right)
            $popup.css({
                'transform': 'translateX(100%)',
                'opacity': '0',
                'transition': 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
            });
            
            // Force a reflow to ensure initial state is applied
            $popup[0].offsetHeight;
            
            // Animate in (slide from right to left)
            setTimeout(function() {
                $popup.css({
                    'transform': 'translateX(0)',
                    'opacity': '1'
                });
            }, 10);
            
            // Start progress bar animation
            $popup.find('.fbs-opt-popup-progress').animate({
                'width': '100%'
            }, duration, 'linear');
            
            // Auto-hide after duration
            var hideTimeout = setTimeout(function() {
                FBSOptimizeAdmin.hideNotification(popupId);
            }, duration);
            
            // Close button click
            $popup.find('.fbs-opt-popup-close').on('click', function() {
                clearTimeout(hideTimeout);
                FBSOptimizeAdmin.hideNotification(popupId);
            });
            
            // Click to dismiss
            $popup.on('click', function(e) {
                if (!$(e.target).closest('.fbs-opt-popup-close').length) {
                    clearTimeout(hideTimeout);
                    FBSOptimizeAdmin.hideNotification(popupId);
                }
            });
        },

        /**
         * Hide notification popup
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        hideNotification: function(popupId) {
            var $popup = $('#' + popupId);
            if ($popup.length) {
                // Animate out (slide to the right)
                $popup.css({
                    'transform': 'translateX(100%)',
                    'opacity': '0'
                });
                
                // Remove after animation completes
                setTimeout(function() {
                    $popup.remove();
                }, 300);
            }
        },

        /**
         * Show success notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showSuccess: function(message, duration) {
            this.showNotification(message, 'success', duration);
        },

        /**
         * Show error notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showError: function(message, duration) {
            this.showNotification(message, 'error', duration);
        },

        /**
         * Show warning notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showWarning: function(message, duration) {
            this.showNotification(message, 'warning', duration);
        },

        /**
         * Show info notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showInfo: function(message, duration) {
            this.showNotification(message, 'info', duration);
        },

        /**
         * Clear all notifications
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        clearAllNotifications: function() {
            $('#fbs-opt-popup-container .fbs-opt-popup').each(function() {
                var popupId = $(this).attr('id');
                FBSOptimizeAdmin.hideNotification(popupId);
            });
        },

        /**
         * Adjust popup position based on viewport and WordPress admin bar
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        adjustPopupPosition: function() {
            var $container = $('#fbs-opt-popup-container');
            if (!$container.length) return;
            
            var windowWidth = $(window).width();
            var windowHeight = $(window).height();
            var adminBarHeight = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;
            
            // Adjust top position based on admin bar
            var topPosition = Math.max(20, adminBarHeight + 10);
            
            // Reset all positioning first
            $container.css({
                'left': 'auto',
                'right': 'auto',
                'top': 'auto',
                'max-width': 'none',
                'width': 'auto'
            });
            
            // Adjust positioning based on screen size
            if (windowWidth < 600) {
                // On very small screens, use full width with margins
                $container.css({
                    'position': 'fixed',
                    'top': topPosition + 'px',
                    'left': '10px',
                    'right': '10px',
                    'max-width': (windowWidth - 20) + 'px',
                    'width': 'auto'
                });
            } else if (windowWidth < 782) {
                // On mobile screens
                $container.css({
                    'position': 'fixed',
                    'top': topPosition + 'px',
                    'left': '10px',
                    'right': '10px',
                    'max-width': Math.min(400, windowWidth - 20) + 'px',
                    'width': 'auto'
                });
            } else {
                // On desktop screens - ensure it's positioned correctly
                $container.css({
                    'position': 'fixed',
                    'top': topPosition + 'px',
                    'right': '20px',
                    'left': 'auto',
                    'max-width': '400px',
                    'width': 'auto'
                });
            }
            
            // Ensure container doesn't go below viewport
            var containerHeight = $container.outerHeight();
            if (containerHeight > windowHeight - topPosition - 20) {
                $container.css('max-height', (windowHeight - topPosition - 20) + 'px');
                $container.css('overflow-y', 'auto');
            }
        },

        /**
         * Handle window resize for popup positioning
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleWindowResize: function() {
            // Debounce the resize handler
            clearTimeout(FBSOptimizeAdmin.resizeTimeout);
            FBSOptimizeAdmin.resizeTimeout = setTimeout(function() {
                FBSOptimizeAdmin.adjustPopupPosition();
            }, 250);
        },

        /**
         * Handle form submission via AJAX
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var tab = $form.data('tab');
            var $submitBtn = $form.find('button[type="submit"]');
            var $statusSpan = $form.find('.fbs-opt-save-status');
            
            // Disable submit button and show loading state
            $submitBtn.prop('disabled', true).addClass('loading');
            
            // Collect form data
            var formData = $form.serializeArray();
            var settings = {};
            
            // Convert form data to nested object structure
            $.each(formData, function(i, field) {
                var name = field.name;
                var value = field.value;
                
                // Skip WordPress nonce and action fields
                if (name === '_wpnonce' || name === '_wp_http_referer' || name === 'option_page') {
                    return;
                }
                
                // Parse nested field names like "fbsseop_settings[section][field]"
                var matches = name.match(/^fbsseop_settings\[([^\]]+)\]\[([^\]]+)\]$/);
                if (matches) {
                    var section = matches[1];
                    var field = matches[2];
                    
                    if (!settings[section]) {
                        settings[section] = {};
                    }
                    settings[section][field] = value;
                }
            });
            
            // Check if any settings were actually provided
            var hasSettings = false;
            for (var section in settings) {
                if (settings[section] && Object.keys(settings[section]).length > 0) {
                    hasSettings = true;
                    break;
                }
            }
            
            if (!hasSettings) {
                // No settings to save
                $submitBtn.prop('disabled', false).removeClass('loading');
                FBSOptimizeAdmin.showInfo('No settings to save', 2000);
                return;
            }
            
            // Send AJAX request
            $.ajax({
                url: fbsseopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbsseop_save_settings',
                    nonce: fbsseopAdmin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        FBSOptimizeAdmin.showSuccess(response.data.message, 3000);
                    } else {
                        FBSOptimizeAdmin.showError(response.data.message || 'Save failed', 5000);
                    }
                },
                error: function() {
                    FBSOptimizeAdmin.showError('Network error occurred. Please check your connection and try again.', 5000);
                },
                complete: function() {
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Show progress bar
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showProgress: function(container, progress) {
            var $container = $(container);
            var $progressBar = $container.find('.fbs-opt-progress-bar');
            
            if ($progressBar.length === 0) {
                $progressBar = $('<div class="fbs-opt-progress-bar"><div class="fbs-opt-progress-fill"></div></div>');
                $container.append($progressBar);
            }
            
            $progressBar.find('.fbs-opt-progress-fill').css('width', progress + '%');
        },

        /**
         * Initialize dependent options
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        initDependentOptions: function() {
            // Initialize all dependent options based on their parent toggles
            $('.fbs-opt-dependent-option').each(function() {
                var $dependentOption = $(this);
                var dependsOn = $dependentOption.data('depends-on');
                
                if (dependsOn) {
                    var $parentToggle = $('#' + dependsOn);
                    if ($parentToggle.length) {
                        FBSOptimizeAdmin.updateDependentOptions($parentToggle);
                    }
                }
            });
            
            // Also bind change events to all toggle inputs to ensure dependent options update
            $('.fbs-opt-toggle input[type="checkbox"]').on('change', function() {
                FBSOptimizeAdmin.updateDependentOptions($(this));
            });
        },

        /**
         * Update dependent options based on toggle state
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         * @param {jQuery} $toggle The toggle element that controls dependent options
         */
        updateDependentOptions: function($toggle) {
            var toggleId = $toggle.attr('id');
            var isChecked = $toggle.is(':checked');
            
            // Find all dependent options that depend on this toggle
            $('.fbs-opt-dependent-option[data-depends-on="' + toggleId + '"]').each(function() {
                var $dependentOption = $(this);
                
                if (isChecked) {
                    // Enable the dependent option
                    $dependentOption.removeClass('disabled');
                    $dependentOption.find('select, input, textarea').prop('disabled', false);
                    $dependentOption.show(); // Make sure it's visible
                } else {
                    // Disable the dependent option
                    $dependentOption.addClass('disabled');
                    $dependentOption.find('select, input, textarea').prop('disabled', true);
                    $dependentOption.hide(); // Hide when disabled
                }
            });
        },

        /**
         * Validate form
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val();
                
                if (!value || value.trim() === '') {
                    isValid = false;
                    errors.push($field.attr('name') + ' is required.');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Check numeric fields
            $form.find('input[type="number"]').each(function() {
                var $field = $(this);
                var value = parseInt($field.val());
                var min = parseInt($field.attr('min'));
                var max = parseInt($field.attr('max'));
                
                if (isNaN(value) || (min !== undefined && value < min) || (max !== undefined && value > max)) {
                    isValid = false;
                    errors.push($field.attr('name') + ' must be a valid number.');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            if (!isValid) {
                FBSOptimizeAdmin.showNotification('Please fix the following errors: ' + errors.join(', '), 'error');
            }
            
            return isValid;
        }
    };

})(jQuery);
