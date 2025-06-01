/**
 * Frontend Admin Panel JavaScript
 * 
 * Handles AJAX requests, form submissions, and UI interactions
 * for the WhatsApp API settings management in the frontend.
 */

(function($) {
    'use strict';

    // Initialize tabs when document is ready
    $(document).ready(function() {
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
        $('#wpwa-admin-tabs').tabs({
            activate: function(event, ui) {
                // Load logs when logs tab is activated
                if (ui.newPanel.attr('id') === 'wpwa-tab-logs') {
                    loadLogs();
                }
            }
        });
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
     * Load initial data when page loads
     */
    function loadInitialData() {
        // Load actual JWT secret (replacing placeholder)
        loadJwtSecret();
        
        // Load logs if logs tab is active
        if ($('#wpwa-tab-logs').is(':visible')) {
            loadLogs();
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
        
        $.ajax({
            url: wpwaFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_admin_get_logs',
                wpwa_frontend_nonce: wpwaFrontend.frontendNonce
            },
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