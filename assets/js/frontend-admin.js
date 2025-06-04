/**
 * Frontend Admin Panel JavaScript
 * 
 * Handles AJAX requests, form submissions, and UI interactions
 * for the WhatsApp API settings management in the frontend.
 */

(function($) {
    'use strict';

    // Console debug for script loading
    console.log('WPWA Frontend Admin script loaded');

    // Initialize tabs when document is ready
    $(document).ready(function() {
        console.log('WPWA Frontend Admin document ready');
        initTabs();
        setupFormHandlers();
        setupButtonHandlers();
        loadInitialData();
        createNotificationArea();
    });

    /**
     * Initialize jQuery UI tabs
     */
    function initTabs() {
        console.log('Initializing tabs, tab element exists:', $('#wpwa-admin-tabs').length > 0);
        
        // Wait for DOM to be fully ready
        setTimeout(function() {
            try {
                // Make sure jQuery UI is loaded
                if (typeof $.fn.tabs !== 'function') {
                    console.error('jQuery UI Tabs not loaded!');
                    console.log('Available jQuery methods:', Object.keys($.fn).join(', '));
                    
                    // Try to load jQuery UI dynamically
                    var script = document.createElement('script');
                    script.src = 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js';
                    script.onload = function() {
                        console.log('jQuery UI loaded dynamically');
                        initTabsAfterLoad();
                    };
                    document.head.appendChild(script);
                    
                    // Also add jQuery UI CSS
                    var cssLink = document.createElement('link');
                    cssLink.rel = 'stylesheet';
                    cssLink.type = 'text/css';
                    cssLink.href = 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css';
                    document.head.appendChild(cssLink);
                    
                    // Add a fallback in case the script doesn't load
                    setTimeout(function() {
                        if (typeof $.fn.tabs !== 'function') {
                            console.log('jQuery UI still not loaded after timeout, implementing simple tab solution');
                            implementSimpleTabs();
                        }
                    }, 3000);
                    
                    return;
                }
                
                initTabsAfterLoad();
            } catch(e) {
                console.error('Error initializing tabs:', e);
                // Fallback to simple tabs if there's an error
                implementSimpleTabs();
            }
        }, 500);
    }
    
    function initTabsAfterLoad() {
        // Check if tabs element exists
        if ($('#wpwa-admin-tabs').length === 0) {
            console.error('Tabs container not found in DOM');
            var bodyContent = $('body').html().substring(0, 500) + '... (truncated)';
            console.log('First 500 chars of body content:', bodyContent);
            return;
        }
        
        // Debug DOM structure in detail
        console.log('Tabs DOM structure:', {
            'tabs': $('#wpwa-admin-tabs').html(),
            'tab-nav': $('.wpwa-tab-nav').length ? $('.wpwa-tab-nav').html() : 'Nav not found',
            'tab-count': $('#wpwa-admin-tabs > div').length,
            'tab-ids': Array.from($('#wpwa-admin-tabs > div')).map(el => el.id).join(', '),
            'settings-tab': $('#wpwa-tab-settings').length ? 'exists' : 'missing',
            'logs-tab': $('#wpwa-tab-logs').length ? 'exists' : 'missing',
            'sessions-tab': $('#wpwa-tab-sessions').length ? 'exists' : 'missing'
        });
        
        try {
            $('#wpwa-admin-tabs').tabs({
                create: function(event, ui) {
                    console.log('Tabs created successfully');
                },
                activate: function(event, ui) {
                    console.log('Tab activated:', ui.newPanel.attr('id'));
                    // Load logs when logs tab is activated
                    if (ui.newPanel.attr('id') === 'wpwa-tab-logs') {
                        loadLogs();
                    }
                }
            });
            console.log('Tabs initialization completed');
        } catch (e) {
            console.error('Error in tabs initialization:', e);
        }
    }

    /**
     * Set up form submission handlers
     */
    function setupFormHandlers() {
        // Settings form
        $('#wpwa-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings($(this));
        });
    }

    /**
     * Set up button click handlers
     */
    function setupButtonHandlers() {
        // Generate API key
        $('#wpwa-generate-api-key').on('click', function() {
            generateApiKey();
        });

        // Validate API credentials
        $('#wpwa-validate-api').on('click', function() {
            validateApiCredentials();
        });

        // Generate new JWT secret
        $('#wpwa-generate-jwt-secret').on('click', function() {
            generateJwtSecret();
        });

        // Show/hide JWT secret
        $('#wpwa-show-jwt-secret').on('click', function() {
            toggleJwtSecretVisibility($(this));
        });

        // Refresh logs
        $('#wpwa-refresh-logs').on('click', function() {
            loadLogs();
        });

        // Clear logs
        $('#wpwa-clear-logs').on('click', function() {
            clearLogs();
        });
    }

    /**
     * Implement a simple tab system if jQuery UI tabs are not available
     */
    function implementSimpleTabs() {
        console.log('Implementing simple tab system');
        if ($('#wpwa-admin-tabs').length === 0) {
            console.error('Cannot implement simple tabs: container not found');
            return;
        }
        
        // Hide all tab content initially
        $('#wpwa-admin-tabs > div[id^="wpwa-tab-"]').hide();
        
        // Show the first tab
        $('#wpwa-admin-tabs > div[id^="wpwa-tab-"]:first').show();
        
        // Add active class to first nav item
        $('.wpwa-tab-nav li:first').addClass('wpwa-tab-active');
        
        // Handle tab clicks
        $('.wpwa-tab-nav li a').on('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            $('.wpwa-tab-nav li').removeClass('wpwa-tab-active');
            
            // Add active class to current tab
            $(this).parent().addClass('wpwa-tab-active');
            
            // Hide all tab content
            $('#wpwa-admin-tabs > div[id^="wpwa-tab-"]').hide();
            
            // Show selected tab content
            var tabId = $(this).attr('href');
            $(tabId).show();
            
            // If logs tab is activated, load logs
            if (tabId === '#wpwa-tab-logs') {
                loadLogs();
            }
            // If sessions tab is activated, load sessions
            else if (tabId === '#wpwa-tab-sessions') {
                loadSessions();
            }
            
            console.log('Simple tabs: Activated tab', tabId);
            return false;
        });
        
        console.log('Simple tab system implemented');
    }
    
    function loadInitialData() {
        console.log('Loading initial data, available frontend data:', wpwaFrontend);
        
        // Load actual JWT secret (replacing placeholder)
        loadJwtSecret();
        
        // Load logs if logs tab is active
        if ($('#wpwa-tab-logs').length && $('#wpwa-tab-logs').is(':visible')) {
            console.log('Logs tab is visible, loading logs');
            loadLogs();
        } else {
            console.log('Logs tab is not visible or does not exist');
        }

        // Load sessions if sessions tab is active
        if ($('#wpwa-tab-sessions').is(':visible')) {
            loadSessions();
        }
    }

    /**
     * Save API settings
     * 
     * @param {jQuery} form The form element
     */
    function saveSettings(form) {
        // Show loading state
        showNotification(wpwaFrontend.texts.saving, 'info');
        
        var data = form.serialize();
        data += '&action=wpwa_frontend_save_settings';
        data += '&wpwa_frontend_nonce=' + wpwaFrontend.frontendNonce;
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message || wpwaFrontend.texts.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save settings error:', error);
                showNotification(wpwaFrontend.texts.error, 'error');
            }
        });
    }

    /**
     * Generate API key
     */
    function generateApiKey() {
        // Show loading state
        showNotification(wpwaFrontend.texts.generating, 'info');
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_generate_api_key',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpwa_api_key').val(response.data.api_key);
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                showNotification(wpwaFrontend.texts.error, 'error');
            }
        });
    }

    /**
     * Validate API credentials
     */
    function validateApiCredentials() {
        // Get API URL and key from form
        var apiUrl = $('#wpwa_api_url').val();
        var apiKey = $('#wpwa_api_key').val();
        
        if (!apiUrl || !apiKey) {
            showNotification('Please enter API URL and API Key', 'error');
            return;
        }
        
        // Show loading state
        showNotification(wpwaFrontend.texts.validating, 'info');
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_validate_api_credentials',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce,
                api_url: apiUrl,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                showNotification(wpwaFrontend.texts.error, 'error');
            }
        });
    }

    /**
     * Generate JWT secret
     */
    function generateJwtSecret() {
        // Confirm before generating new secret
        if (!confirm('Generating a new JWT secret will invalidate all existing tokens. Are you sure you want to continue?')) {
            return;
        }
        
        // Show loading state
        showNotification(wpwaFrontend.texts.generating, 'info');
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_generate_jwt_secret',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the JWT secret field
                    $('#wpwa_jwt_secret').val(response.data.jwt_secret);
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                showNotification(wpwaFrontend.texts.error, 'error');
            }
        });
    }

    /**
     * Load actual JWT secret
     */
    function loadJwtSecret() {
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_get_jwt_secret',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
            success: function(response) {
                if (response.success && response.data.jwt_secret) {
                    // Store the actual JWT secret
                    $('#wpwa_jwt_secret').data('jwt-secret', response.data.jwt_secret);
                }
            }
        });
    }

    /**
     * Toggle JWT secret visibility
     * 
     * @param {jQuery} button The toggle button element
     */
    function toggleJwtSecretVisibility(button) {
        var input = $('#wpwa_jwt_secret');
        var icon = button.find('.dashicons');
        
        if (input.attr('type') === 'text' && input.val() !== '••••••••••••••••') {
            // Currently showing actual secret, hide it
            input.val('••••••••••••••••');
            input.attr('type', 'text');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        } else {
            // Currently showing masked secret, show actual secret
            var secretValue = input.data('jwt-secret') || '';
            input.val(secretValue);
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        }
    }

    /**
     * Load sessions
     */
    function loadSessions() {
        var sessionsContainer = $('#wpwa-sessions-content');
        sessionsContainer.html('<div class="wpwa-loading">' + 'Loading sessions...' + '</div>');
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_admin_get_sessions',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.html) {
                        sessionsContainer.html(response.data.html);
                    } else {
                        sessionsContainer.html('<div class="wpwa-info">No active sessions found.</div>');
                    }
                } else {
                    sessionsContainer.html('<div class="wpwa-error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                sessionsContainer.html('<div class="wpwa-error">Error loading sessions</div>');
            }
        });
    }

    /**
     * Load logs
     */
    function loadLogs() {
        var logsContainer = $('#wpwa-logs-container');
        logsContainer.html('<div class="wpwa-loading">Loading logs...</div>');
        
        // Check if container exists after setting content
        if ($('#wpwa-logs-container').length === 0) {
            console.error('Error: Logs container not found in DOM after setting loading message');
        }
        
        // Debug info
        console.log('Loading logs with:', {
            'AJAX URL': wpwaFrontend.ajaxUrl,
            'Frontend Nonce': wpwaFrontend.frontendNonce,
            'Regular Nonce': wpwaFrontend.nonce,
            'Both Nonces Present': !!(wpwaFrontend.frontendNonce && wpwaFrontend.nonce)
        });
        
        // Prepare data with all possible nonce variations
        var ajaxData = {
            action: 'wpwa_admin_get_logs'
        };
        
        // Add all possible nonce options to increase chances of success
        if (wpwaFrontend.frontendNonce) {
            ajaxData.wpwa_frontend_nonce = wpwaFrontend.frontendNonce;
        }
        if (wpwaFrontend.nonce) {
            ajaxData.wpwa_nonce = wpwaFrontend.nonce;
            // Fallback in case the handler expects this format
            ajaxData._wpnonce = wpwaFrontend.nonce;
        }
        
        console.log('AJAX request data:', ajaxData);
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    displayLogs(response.data.logs);
                } else {
                    logsContainer.html('<div class="wpwa-error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                logsContainer.html('<div class="wpwa-error">Error loading logs</div>');
            }
        });
    }

    /**
     * Display logs in the logs container
     * 
     * @param {Array} logs Array of log entries
     */
    function displayLogs(logs) {
        var logsContainer = $('#wpwa-logs-container');
        
        if (!logs || logs.length === 0) {
            logsContainer.html('<p>No logs found</p>');
            return;
        }
        
        var html = '<table class="wpwa-logs-table">';
        html += '<thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>';
        html += '<tbody>';
        
        $.each(logs, function(i, log) {
            // Handle different log formats for compatibility
            var timestamp = log.timestamp || log.time || '';
            var level = log.level || 'INFO';
            var message = log.message || '';
            
            html += '<tr class="wpwa-log-' + level.toLowerCase() + '">';
            html += '<td>' + timestamp + '</td>';
            html += '<td>' + level + '</td>';
            html += '<td>' + message + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        logsContainer.html(html);
    }

    /**
     * Clear logs
     */
    function clearLogs() {
        // Confirm before clearing
        if (!confirm(wpwaFrontend.texts.confirmClearLogs)) {
            return;
        }
        
        // Show loading state
        var logsContainer = $('#wpwa-logs-container');
        logsContainer.html('<div class="wpwa-loading">Clearing logs...</div>');
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_admin_clear_logs',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    logsContainer.html('<p>No logs found</p>');
                } else {
                    showNotification(response.data.message, 'error');
                    loadLogs(); // Reload logs in case of error
                }
            },
            error: function() {
                showNotification('Error clearing logs', 'error');
                loadLogs(); // Reload logs in case of error
            }
        });
    }

    /**
     * Create notification area if it doesn't exist
     */
    function createNotificationArea() {
        if ($('#wpwa-notification').length === 0) {
            $('body').append('<div id="wpwa-notification" class="wpwa-notification" style="display:none;"></div>');
        }
    }

    /**
     * Show notification message
     * 
     * @param {string} message Message to display
     * @param {string} type Message type (success, error, info)
     */
    function showNotification(message, type) {
        var notification = $('#wpwa-notification');
        
        // Clear previous classes and add appropriate class
        notification.removeClass('wpwa-notification-success wpwa-notification-error wpwa-notification-info');
        notification.addClass('wpwa-notification-' + type);
        
        // Set message and show
        notification.html(message).fadeIn();
        
        // Hide after 5 seconds
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }

})(jQuery);