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
            
            if (!confirm(fbsOptAdmin.strings.confirmCleanup)) {
                return;
            }
            
            var $btn = $(this);
            var $status = $('#fbs-opt-cleanup-status');
            
            // Disable button and show loading
            $btn.prop('disabled', true);
            $status.html('<span class="fbs-opt-loading"></span> ' + fbsOptAdmin.strings.cleaning);
            
            // Perform AJAX request
            $.ajax({
                url: fbsOptAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbs_opt_cleanup_database',
                    nonce: fbsOptAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="fbs-opt-cleanup-status success">✓ ' + response.data.message + '</span>');
                        
                        // Refresh statistics if on statistics tab
                        if ($('.nav-tab-active').data('tab') === 'statistics') {
                            FBSOptimizeAdmin.refreshStatistics();
                        }
                    } else {
                        $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + fbsOptAdmin.strings.cleanupError + '</span>');
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
                url: fbsOptAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbs_opt_clear_cache',
                    nonce: fbsOptAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="fbs-opt-cleanup-status success">✓ ' + response.data.message + '</span>');
                        
                        // Refresh statistics if on statistics tab
                        if ($('.nav-tab-active').data('tab') === 'statistics') {
                            FBSOptimizeAdmin.refreshStatistics();
                        }
                    } else {
                        $status.html('<span class="fbs-opt-cleanup-status error">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span class="fbs-opt-cleanup-status error">✗ Failed to clear cache</span>');
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
                
                if (name && name.startsWith('fbs_opt_settings')) {
                    var value = $field.val();
                    
                    if ($field.attr('type') === 'checkbox') {
                        value = $field.is(':checked') ? 1 : 0;
                    }
                    
                    // Parse nested setting structure
                    var parts = name.match(/fbs_opt_settings\[([^\]]+)\]\[([^\]]+)\]/);
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
                url: fbsOptAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbs_opt_import_settings',
                    nonce: fbsOptAdmin.nonce,
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
                url: fbsOptAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fbs_opt_get_stats',
                    nonce: fbsOptAdmin.nonce
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
         * Show notification
         * @since 1.0.0
         * @author Fazle Bari <fazlebarisn@gmail.com>
         */
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="fbs-opt-notice ' + type + '">' + message + '</div>');
            
            $('.wrap h1').after($notification);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
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
