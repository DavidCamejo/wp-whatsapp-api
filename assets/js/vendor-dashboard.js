/**
 * WhatsApp API Vendor Dashboard Scripts
 */

jQuery(document).ready(function($) {
    // Variables
    const dashboardContainer = $('#wpwa-vendor-dashboard');
    const sessionContainer = $('#wpwa-session-container');
    const ordersContainer = $('#wpwa-recent-orders');
    const toggleSwitch = $('#wpwa-toggle-whatsapp');
    const qrCodeContainer = $('#wpwa-qr-code-container');
    const sessionStatus = $('#wpwa-session-status');
    const connectBtn = $('#wpwa-connect-whatsapp');
    const disconnectBtn = $('#wpwa-disconnect-whatsapp');
    const sessionNameInput = $('#wpwa-session-name');
    let statusCheckInterval = null;
    let isConnecting = false;
    
    // Init tooltips
    if (typeof $.fn.tooltip === 'function') {
        $('.wpwa-tooltip').tooltip();
    }
    
    // Toggle WhatsApp integration
    toggleSwitch.on('change', function() {
        const isEnabled = $(this).prop('checked');
        
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_toggle_whatsapp',
                nonce: wpwa_vendor.nonce,
                enabled: isEnabled ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    dashboardContainer.toggleClass('wpwa-enabled', isEnabled);
                    sessionContainer.slideToggle(isEnabled);
                } else {
                    alert(response.data.message || 'Failed to update setting');
                    toggleSwitch.prop('checked', !isEnabled);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
                toggleSwitch.prop('checked', !isEnabled);
            }
        });
    });
    
    // Connect to WhatsApp
    connectBtn.on('click', function() {
        const sessionName = sessionNameInput.val().trim();
        
        if (!sessionName) {
            alert('Please enter a session name');
            sessionNameInput.focus();
            return;
        }
        
        if (isConnecting) return;
        isConnecting = true;
        
        // Show loading state
        connectBtn.prop('disabled', true).text('Connecting...');
        sessionStatus.html('<span class="wpwa-status-indicator wpwa-status-warning"></span> Initializing connection...');
        
        // Create session
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_create_session',
                nonce: wpwa_vendor.nonce,
                session_name: sessionName
            },
            success: function(response) {
                if (response.success) {
                    // Show QR code
                    qrCodeContainer.html('<img src="data:image/png;base64,' + response.data.qr_code + '" alt="WhatsApp QR Code">');
                    qrCodeContainer.slideDown();
                    
                    // Update status
                    sessionStatus.html('<span class="wpwa-status-indicator wpwa-status-warning"></span> Scan QR code with your phone');
                    
                    // Enable disconnect button
                    disconnectBtn.prop('disabled', false);
                    
                    // Start checking status
                    startStatusCheck();
                } else {
                    alert(response.data.message || 'Failed to create session');
                    connectBtn.prop('disabled', false).text('Connect WhatsApp');
                    isConnecting = false;
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
                connectBtn.prop('disabled', false).text('Connect WhatsApp');
                isConnecting = false;
            }
        });
    });
    
    // Disconnect WhatsApp
    disconnectBtn.on('click', function() {
        if (!confirm('Are you sure you want to disconnect your WhatsApp session?')) {
            return;
        }
        
        $(this).prop('disabled', true).text('Disconnecting...');
        
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_disconnect_session',
                nonce: wpwa_vendor.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Stop status check
                    stopStatusCheck();
                    
                    // Reset UI
                    qrCodeContainer.slideUp().empty();
                    sessionStatus.html('<span class="wpwa-status-indicator wpwa-status-inactive"></span> Disconnected');
                    connectBtn.prop('disabled', false).text('Connect WhatsApp');
                    disconnectBtn.prop('disabled', true).text('Disconnect');
                    sessionNameInput.val('');
                    isConnecting = false;
                    
                    // Refresh orders
                    loadRecentOrders();
                } else {
                    alert(response.data.message || 'Failed to disconnect session');
                    disconnectBtn.prop('disabled', false).text('Disconnect');
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
                disconnectBtn.prop('disabled', false).text('Disconnect');
            }
        });
    });
    
    // Check session status
    function checkSessionStatus() {
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_check_session',
                nonce: wpwa_vendor.nonce
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    const statusLabel = response.data.status_label;
                    
                    // Update status indicator
                    let statusClass = 'wpwa-status-warning';
                    
                    if (status === 'ready') {
                        statusClass = 'wpwa-status-active';
                        qrCodeContainer.slideUp();
                        connectBtn.prop('disabled', true);
                        isConnecting = false;
                    } else if (status === 'disconnected' || status === 'failed') {
                        statusClass = 'wpwa-status-error';
                        connectBtn.prop('disabled', false).text('Connect WhatsApp');
                        isConnecting = false;
                        stopStatusCheck();
                    }
                    
                    sessionStatus.html('<span class="wpwa-status-indicator ' + statusClass + '"></span> ' + statusLabel);
                }
            }
        });
    }
    
    // Start status check interval
    function startStatusCheck() {
        stopStatusCheck();
        statusCheckInterval = setInterval(checkSessionStatus, 5000);
        checkSessionStatus();
    }
    
    // Stop status check interval
    function stopStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
    }
    
    // Load recent orders
    function loadRecentOrders() {
        ordersContainer.html('<p>Loading recent orders...</p>');
        
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_get_recent_orders',
                nonce: wpwa_vendor.nonce
            },
            success: function(response) {
                if (response.success) {
                    ordersContainer.html(response.data.html);
                } else {
                    ordersContainer.html('<p>Failed to load recent orders.</p>');
                }
            },
            error: function() {
                ordersContainer.html('<p>Failed to load recent orders. Please try again.</p>');
            }
        });
    }
    
    // Send test message
    $('#wpwa-send-test-message').on('click', function() {
        const phone = $('#wpwa-test-phone').val().trim();
        const message = $('#wpwa-test-message').val().trim();
        
        if (!phone) {
            alert('Please enter a phone number');
            $('#wpwa-test-phone').focus();
            return;
        }
        
        if (!message) {
            alert('Please enter a message');
            $('#wpwa-test-message').focus();
            return;
        }
        
        $(this).prop('disabled', true).text('Sending...');
        
        $.ajax({
            url: wpwa_vendor.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_vendor_send_test_message',
                nonce: wpwa_vendor.nonce,
                phone: phone,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    alert('Message sent successfully!');
                    $('#wpwa-test-message').val('');
                } else {
                    alert(response.data.message || 'Failed to send message');
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $('#wpwa-send-test-message').prop('disabled', false).text('Send Test Message');
            }
        });
    });
    
    // Template selection
    $('#wpwa-template-select').on('change', function() {
        const templateId = $(this).val();
        
        if (templateId) {
            const templates = wpwa_vendor.templates || {};
            if (templates[templateId]) {
                $('#wpwa-test-message').val(templates[templateId].content);
            }
        }
    });
    
    // Initialize - load recent orders and check status if connected
    loadRecentOrders();
    
    if (dashboardContainer.hasClass('wpwa-connected')) {
        startStatusCheck();
    }
});