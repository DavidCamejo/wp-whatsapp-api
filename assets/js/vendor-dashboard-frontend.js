/**
 * WhatsApp Vendor Dashboard Frontend JavaScript
 * 
 * Handles UI interactions for the vendor dashboard including WhatsApp session management,
 * product synchronization, and log display.
 */
(function($) {
    'use strict';

    // Dashboard state
    const state = {
        vendorId: 0,
        clientId: '',
        sessionStatus: 'disconnected',
        pollingInterval: null,
        qrPollingInterval: null,
        isCheckingStatus: false,
        isSyncingProducts: false
    };

    // DOM elements
    const elements = {
        dashboard: null,
        connectionStatus: null,
        sessionContainer: null,
        qrContainer: null,
        qrCode: null,
        connectButton: null,
        disconnectButton: null,
        syncStatus: null,
        productList: null,
        syncButton: null,
        enableWhatsapp: null,
        logsContainer: null,
        toggleLogsButton: null,
        messages: null
    };

    /**
     * Initialize dashboard
     */
    function init() {
        // Cache DOM elements
        cacheDOMElements();

        // Set initial state
        if (elements.dashboard) {
            state.vendorId = elements.dashboard.data('vendor-id');
        }

        // Bind event listeners
        bindEvents();

        // Load initial data
        loadInitialData();
    }

    /**
     * Cache DOM elements
     */
    function cacheDOMElements() {
        elements.dashboard = $('.wpwa-vendor-dashboard');
        elements.connectionStatus = $('#wpwa-connection-status');
        elements.sessionContainer = $('#wpwa-session-container');
        elements.qrContainer = $('.wpwa-qr-code-container');
        elements.qrCode = $('#wpwa-qr-code');
        elements.connectButton = $('#wpwa-connect-whatsapp');
        elements.disconnectButton = $('#wpwa-disconnect-whatsapp');
        elements.syncStatus = $('#wpwa-sync-status');
        elements.productList = $('#wpwa-product-list-container');
        elements.syncButton = $('#wpwa-sync-products');
        elements.enableWhatsapp = $('#wpwa-enable-whatsapp');
        elements.logsContainer = $('#wpwa-logs-container');
        elements.toggleLogsButton = $('#wpwa-toggle-logs');
        elements.messages = $('#wpwa-dashboard-messages');
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Connect button
        elements.connectButton.on('click', handleConnectWhatsApp);
        
        // Disconnect button
        elements.disconnectButton.on('click', handleDisconnectWhatsApp);
        
        // Sync button
        elements.syncButton.on('click', handleSyncProducts);
        
        // Toggle WhatsApp integration
        elements.enableWhatsapp.on('change', handleToggleIntegration);
        
        // Toggle logs display
        elements.toggleLogsButton.on('click', toggleLogsDisplay);
    }

    /**
     * Load initial data for the dashboard
     */
    function loadInitialData() {
        loadSessionStatus();
        loadSyncStatus();
        loadIntegrationStatus();
    }

    /**
     * Load WhatsApp session status
     */
    function loadSessionStatus() {
        if (!state.vendorId) {
            return;
        }

        elements.sessionContainer.html('<div class="wpwa-loading">' + wpwaVendorDashboard.i18n.loading + '</div>');
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_check_session',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId
            },
            success: function(response) {
                if (response.success) {
                    updateSessionUI(response.data);
                } else {
                    showErrorMessage(response.data.message || wpwaVendorDashboard.i18n.error);
                }
            },
            error: function() {
                showErrorMessage(wpwaVendorDashboard.i18n.error);
            }
        });
    }

    /**
     * Update the session UI based on response data
     */
    function updateSessionUI(data) {
        if (data.sessions && data.sessions.length > 0) {
            // We have an active session
            const session = data.sessions[0];
            state.clientId = session.client_id;
            state.sessionStatus = session.status;

            // Display connection status
            displayConnectionStatus(session.status);
            
            // Update session container
            elements.sessionContainer.html(
                '<div class="wpwa-session-info">' +
                '<div class="wpwa-session-name">' + session.session_name + '</div>' +
                '<div class="wpwa-session-status status-' + session.status + '">' + getStatusLabel(session.status) + '</div>' +
                '<div class="wpwa-session-created">' + session.created_at + '</div>' +
                '</div>'
            );
            
            // Hide QR code and connect button, show disconnect button
            elements.qrContainer.hide();
            elements.connectButton.hide();
            elements.disconnectButton.show();
            
            // Start polling for status updates if connected or initializing
            if (session.status === 'initializing' || session.status === 'pending') {
                startStatusPolling();
            }
        } else {
            // No active session
            state.clientId = '';
            state.sessionStatus = 'disconnected';
            
            // Display connection status
            displayConnectionStatus('disconnected');
            
            // Update session container
            elements.sessionContainer.html(
                '<div class="wpwa-no-session">' +
                '<p>' + wpwaVendorDashboard.i18n.disconnected + '</p>' +
                '<p class="wpwa-session-hint">' + 'Click "Connect WhatsApp" to link your WhatsApp account' + '</p>' +
                '</div>'
            );
            
            // Show connect button, hide disconnect button and QR code
            elements.qrContainer.hide();
            elements.connectButton.show();
            elements.disconnectButton.hide();
            
            // Stop any polling
            stopStatusPolling();
        }
    }

    /**
     * Display connection status with appropriate styling
     */
    function displayConnectionStatus(status) {
        elements.connectionStatus.removeClass('status-connected status-pending status-initializing status-disconnected status-error');
        elements.connectionStatus.addClass('status-' + status);
        elements.connectionStatus.text(getStatusLabel(status));
    }

    /**
     * Get human-readable status label
     */
    function getStatusLabel(status) {
        const labels = {
            'connected': wpwaVendorDashboard.i18n.sessionConnected,
            'disconnected': wpwaVendorDashboard.i18n.sessionDisconnected,
            'initializing': wpwaVendorDashboard.i18n.sessionInitializing,
            'pending': wpwaVendorDashboard.i18n.sessionPending,
            'error': wpwaVendorDashboard.i18n.error
        };
        
        return labels[status] || status;
    }

    /**
     * Start polling for session status updates
     */
    function startStatusPolling() {
        // Clear any existing polling
        stopStatusPolling();
        
        // Start new polling interval
        state.pollingInterval = setInterval(function() {
            if (state.isCheckingStatus) {
                return;
            }
            
            state.isCheckingStatus = true;
            
            $.ajax({
                url: wpwaVendorDashboard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpwa_check_session',
                    nonce: wpwaVendorDashboard.nonce,
                    vendor_id: state.vendorId,
                    client_id: state.clientId
                },
                success: function(response) {
                    state.isCheckingStatus = false;
                    
                    if (response.success) {
                        // Update status
                        const newStatus = response.data.status || 'disconnected';
                        
                        // Only refresh UI if status has changed
                        if (newStatus !== state.sessionStatus) {
                            state.sessionStatus = newStatus;
                            loadSessionStatus();
                            
                            // If status is now connected, show success message and reload sync status
                            if (newStatus === 'connected') {
                                showSuccessMessage(wpwaVendorDashboard.i18n.connectionSuccess);
                                loadSyncStatus();
                                stopStatusPolling();
                            }
                        }
                    }
                },
                error: function() {
                    state.isCheckingStatus = false;
                }
            });
        }, 5000); // Check every 5 seconds
    }

    /**
     * Stop polling for status updates
     */
    function stopStatusPolling() {
        if (state.pollingInterval) {
            clearInterval(state.pollingInterval);
            state.pollingInterval = null;
        }
        
        if (state.qrPollingInterval) {
            clearInterval(state.qrPollingInterval);
            state.qrPollingInterval = null;
        }
    }

    /**
     * Handle connect WhatsApp button click
     */
    function handleConnectWhatsApp() {
        // Prevent multiple clicks
        if (elements.connectButton.hasClass('wpwa-button-loading')) {
            return;
        }
        
        elements.connectButton.addClass('wpwa-button-loading');
        elements.connectButton.text(wpwaVendorDashboard.i18n.connecting);
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_create_session',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId,
                session_name: 'WhatsApp for ' + state.vendorId
            },
            success: function(response) {
                elements.connectButton.removeClass('wpwa-button-loading');
                elements.connectButton.text(wpwaVendorDashboard.i18n.connectWhatsapp);
                
                if (response.success) {
                    state.clientId = response.data.client_id;
                    displayQRCode(response.data.qr_code);
                    startQRPolling();
                } else {
                    showErrorMessage(response.data.message || wpwaVendorDashboard.i18n.connectionFailed);
                }
            },
            error: function() {
                elements.connectButton.removeClass('wpwa-button-loading');
                elements.connectButton.text(wpwaVendorDashboard.i18n.connectWhatsapp);
                showErrorMessage(wpwaVendorDashboard.i18n.connectionFailed);
            }
        });
    }

    /**
     * Display QR code for scanning
     */
    function displayQRCode(qrData) {
        // Create a simple QR code display
        const qrImg = $('<img>', {
            src: qrData,
            alt: wpwaVendorDashboard.i18n.scanQrCode,
            class: 'wpwa-qr-image'
        });
        
        elements.qrCode.empty().append(qrImg);
        elements.qrContainer.show();
        
        // Add refresh button
        const refreshButton = $('<button>', {
            type: 'button',
            class: 'wpwa-button wpwa-button-secondary wpwa-refresh-qr',
            text: wpwaVendorDashboard.i18n.refreshQrCode
        }).on('click', handleConnectWhatsApp);
        
        elements.qrCode.append(refreshButton);
        
        // Hide connect button while QR is displayed
        elements.connectButton.hide();
    }

    /**
     * Start polling for QR code status
     */
    function startQRPolling() {
        // Clear any existing polling
        if (state.qrPollingInterval) {
            clearInterval(state.qrPollingInterval);
        }
        
        // Start new polling interval for QR code status
        state.qrPollingInterval = setInterval(function() {
            $.ajax({
                url: wpwaVendorDashboard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpwa_check_session',
                    nonce: wpwaVendorDashboard.nonce,
                    vendor_id: state.vendorId,
                    client_id: state.clientId
                },
                success: function(response) {
                    if (response.success) {
                        // If status has changed from pending, reload the session status
                        if (response.data.status && response.data.status !== 'pending') {
                            loadSessionStatus();
                            clearInterval(state.qrPollingInterval);
                            state.qrPollingInterval = null;
                        }
                    }
                }
            });
        }, 3000); // Check every 3 seconds
    }

    /**
     * Handle disconnect WhatsApp button click
     */
    function handleDisconnectWhatsApp() {
        // Confirm disconnect
        if (!confirm(wpwaVendorDashboard.i18n.confirmDisconnect)) {
            return;
        }
        
        // Prevent multiple clicks
        if (elements.disconnectButton.hasClass('wpwa-button-loading')) {
            return;
        }
        
        elements.disconnectButton.addClass('wpwa-button-loading');
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_disconnect_session',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId,
                client_id: state.clientId
            },
            success: function(response) {
                elements.disconnectButton.removeClass('wpwa-button-loading');
                
                if (response.success) {
                    // Reset state and reload session status
                    state.clientId = '';
                    state.sessionStatus = 'disconnected';
                    loadSessionStatus();
                } else {
                    showErrorMessage(response.data.message || wpwaVendorDashboard.i18n.error);
                }
            },
            error: function() {
                elements.disconnectButton.removeClass('wpwa-button-loading');
                showErrorMessage(wpwaVendorDashboard.i18n.error);
            }
        });
    }

    /**
     * Load product sync status
     */
    function loadSyncStatus() {
        if (!state.vendorId) {
            return;
        }

        elements.syncStatus.html('<div class="wpwa-loading">' + wpwaVendorDashboard.i18n.loading + '</div>');
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_get_sync_status',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId
            },
            success: function(response) {
                if (response.success) {
                    updateSyncStatusUI(response.data);
                } else {
                    elements.syncStatus.html('<div class="wpwa-error">' + (response.data.message || wpwaVendorDashboard.i18n.error) + '</div>');
                }
            },
            error: function() {
                elements.syncStatus.html('<div class="wpwa-error">' + wpwaVendorDashboard.i18n.error + '</div>');
            }
        });
    }

    /**
     * Update sync status UI based on response data
     */
    function updateSyncStatusUI(data) {
        // Create status summary
        const statusHTML = 
            '<div class="wpwa-sync-status-summary">' +
            '<div class="wpwa-sync-stat wpwa-sync-total">' +
            '<span class="wpwa-sync-count">' + data.total + '</span>' +
            '<span class="wpwa-sync-label">' + 'Total Products' + '</span>' +
            '</div>' +
            '<div class="wpwa-sync-stat wpwa-sync-synced">' +
            '<span class="wpwa-sync-count">' + data.synced + '</span>' +
            '<span class="wpwa-sync-label">' + 'Synced' + '</span>' +
            '</div>' +
            '<div class="wpwa-sync-stat wpwa-sync-pending">' +
            '<span class="wpwa-sync-count">' + data.pending + '</span>' +
            '<span class="wpwa-sync-label">' + 'Pending' + '</span>' +
            '</div>' +
            '<div class="wpwa-sync-stat wpwa-sync-failed">' +
            '<span class="wpwa-sync-count">' + data.failed + '</span>' +
            '<span class="wpwa-sync-label">' + 'Failed' + '</span>' +
            '</div>' +
            '</div>';
            
        elements.syncStatus.html(statusHTML);
        
        // Render recent products
        if (data.recent_products && data.recent_products.length > 0) {
            let productsHTML = '<div class="wpwa-product-grid">';
            
            data.recent_products.forEach(function(product) {
                productsHTML += 
                    '<div class="wpwa-product-card status-' + product.status + '">' +
                    '<div class="wpwa-product-thumbnail">' +
                    (product.thumbnail ? '<img src="' + product.thumbnail + '" alt="' + product.name + '">' : '<div class="wpwa-no-image">No Image</div>') +
                    '</div>' +
                    '<div class="wpwa-product-info">' +
                    '<div class="wpwa-product-name">' + product.name + '</div>' +
                    '<div class="wpwa-product-status">' + getSyncStatusLabel(product.status) + '</div>' +
                    (product.error ? '<div class="wpwa-product-error">' + product.error + '</div>' : '') +
                    (product.time ? '<div class="wpwa-product-time">' + product.time + '</div>' : '') +
                    '</div>' +
                    '</div>';
            });
            
            productsHTML += '</div>';
            elements.productList.html(productsHTML);
        } else {
            elements.productList.html('<div class="wpwa-no-products">No recently synced products.</div>');
        }
    }

    /**
     * Get human-readable sync status label
     */
    function getSyncStatusLabel(status) {
        const labels = {
            'synced': 'Synced',
            'pending': 'Pending',
            'failed': 'Failed'
        };
        
        return labels[status] || status;
    }

    /**
     * Handle sync products button click
     */
    function handleSyncProducts() {
        // Prevent multiple clicks
        if (state.isSyncingProducts || elements.syncButton.hasClass('wpwa-button-loading')) {
            return;
        }
        
        state.isSyncingProducts = true;
        elements.syncButton.addClass('wpwa-button-loading');
        elements.syncButton.text(wpwaVendorDashboard.i18n.syncingProducts);
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_sync_products',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId
            },
            success: function(response) {
                state.isSyncingProducts = false;
                elements.syncButton.removeClass('wpwa-button-loading');
                elements.syncButton.text(wpwaVendorDashboard.i18n.syncProducts);
                
                if (response.success) {
                    showSuccessMessage(response.data.message || wpwaVendorDashboard.i18n.syncSuccess);
                    
                    // Reload sync status after a short delay to allow products to be queued
                    setTimeout(loadSyncStatus, 1500);
                } else {
                    showErrorMessage(response.data.message || wpwaVendorDashboard.i18n.syncFailed);
                }
            },
            error: function() {
                state.isSyncingProducts = false;
                elements.syncButton.removeClass('wpwa-button-loading');
                elements.syncButton.text(wpwaVendorDashboard.i18n.syncProducts);
                showErrorMessage(wpwaVendorDashboard.i18n.syncFailed);
            }
        });
    }

    /**
     * Load integration status (toggle state)
     */
    function loadIntegrationStatus() {
        if (!state.vendorId || !elements.enableWhatsapp) {
            return;
        }

        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_toggle_integration',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId,
                get_status: true
            },
            success: function(response) {
                if (response.success && response.data.enabled !== undefined) {
                    elements.enableWhatsapp.prop('checked', response.data.enabled);
                }
            }
        });
    }

    /**
     * Handle toggle integration change
     */
    function handleToggleIntegration() {
        const isEnabled = elements.enableWhatsapp.prop('checked');
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_toggle_integration',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId,
                enabled: isEnabled
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);
                } else {
                    showErrorMessage(response.data.message || wpwaVendorDashboard.i18n.error);
                    // Revert the toggle if there was an error
                    elements.enableWhatsapp.prop('checked', !isEnabled);
                }
            },
            error: function() {
                showErrorMessage(wpwaVendorDashboard.i18n.error);
                // Revert the toggle if there was an error
                elements.enableWhatsapp.prop('checked', !isEnabled);
            }
        });
    }

    /**
     * Toggle logs display
     */
    function toggleLogsDisplay() {
        const isVisible = elements.logsContainer.is(':visible');
        
        if (isVisible) {
            elements.logsContainer.slideUp();
            elements.toggleLogsButton.text(wpwaVendorDashboard.i18n.viewLogs);
        } else {
            // Load logs before showing
            loadLogs();
            elements.logsContainer.slideDown();
            elements.toggleLogsButton.text(wpwaVendorDashboard.i18n.hideLogs);
        }
    }

    /**
     * Load vendor logs
     */
    function loadLogs() {
        if (!state.vendorId) {
            return;
        }

        elements.logsContainer.html('<div class="wpwa-loading">' + wpwaVendorDashboard.i18n.loading + '</div>');
        
        $.ajax({
            url: wpwaVendorDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpwa_get_logs',
                nonce: wpwaVendorDashboard.nonce,
                vendor_id: state.vendorId,
                limit: 50
            },
            success: function(response) {
                if (response.success) {
                    renderLogs(response.data.logs);
                } else {
                    elements.logsContainer.html('<div class="wpwa-error">' + (response.data.message || wpwaVendorDashboard.i18n.error) + '</div>');
                }
            },
            error: function() {
                elements.logsContainer.html('<div class="wpwa-error">' + wpwaVendorDashboard.i18n.error + '</div>');
            }
        });
    }

    /**
     * Render logs data
     */
    function renderLogs(logs) {
        if (!logs || logs.length === 0) {
            elements.logsContainer.html('<div class="wpwa-no-logs">' + wpwaVendorDashboard.i18n.noLogsFound + '</div>');
            return;
        }
        
        let logsHTML = '<table class="wpwa-logs-table">';
        logsHTML += '<thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>';
        logsHTML += '<tbody>';
        
        logs.forEach(function(log) {
            logsHTML += 
                '<tr class="log-level-' + log.level + '">' +
                '<td>' + log.time + '</td>' +
                '<td>' + log.level + '</td>' +
                '<td>' + log.message + '</td>' +
                '</tr>';
        });
        
        logsHTML += '</tbody></table>';
        elements.logsContainer.html(logsHTML);
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        showMessage(message, 'success');
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        showMessage(message, 'error');
    }

    /**
     * Show message with type
     */
    function showMessage(message, type) {
        const alertClass = 'wpwa-alert-' + type;
        const alertHTML = '<div class="wpwa-alert ' + alertClass + '">' + message + '</div>';
        
        elements.messages.html(alertHTML);
        elements.messages.show();
        
        // Hide after 5 seconds
        setTimeout(function() {
            elements.messages.fadeOut('slow');
        }, 5000);
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);