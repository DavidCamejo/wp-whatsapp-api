<?php
/**
 * Admin Settings Page Template - Simplified Version
 * 
 * This serves as the primary settings page for the WhatsApp API integration plugin.
 * Uses a simplified approach with CSS-based tabs instead of jQuery UI dependencies.
 * 
 * @package WP_WhatsApp_API
 * @version 1.2.9
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load admin settings if not already loaded
if (!class_exists('WPWA_Admin_Settings')) {
    require_once WPWA_PATH . 'admin/admin-settings.php';
}

// Get the instance of settings class 
global $wpwa_admin_settings;
if (!isset($wpwa_admin_settings)) {
    $wpwa_admin_settings = new WPWA_Admin_Settings();
}

// Check if user has permissions to access this page
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'wp-whatsapp-api'));
}

// Debug info to be passed to JavaScript
$debug_data = array(
    'wpVersion' => get_bloginfo('version'),
    'pluginVersion' => WPWA_VERSION,
    'uiLibrary' => 'Native CSS Tabs'
);

// Enqueue the debug script
wp_enqueue_script('wpwa-debug-panel', WPWA_ASSETS_URL . 'js/debug-panel.js', array('jquery'), WPWA_VERSION, true);
wp_localize_script('wpwa-debug-panel', 'wpwaDebug', $debug_data);

// Get settings for the form
$api_url = get_option('wpwa_api_url', '');
$jwt_secret = get_option('wpwa_jwt_secret', '');
$debug_mode = get_option('wpwa_debug_mode', 0);
$connection_timeout = get_option('wpwa_connection_timeout', 30);
$max_retries = get_option('wpwa_max_retries', 3);
$notification_template = get_option('wpwa_notification_template', '');
$allow_tracking = get_option('wpwa_allow_tracking', 0);

// Add style for CSS tabs
?>
<style>
    /* Basic CSS reset for admin panel */
    .wpwa-settings-wrap * {
        box-sizing: border-box;
    }
    
    /* Admin header styles */
    .wpwa-admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 10px;
    }
    
    .wpwa-version {
        font-size: 12px;
        color: #666;
        background: #f3f3f3;
        padding: 4px 8px;
        border-radius: 3px;
    }
    
    /* CSS-only tabs */
    .wpwa-tabs {
        margin-top: 20px;
    }
    
    .wpwa-tab-nav {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        border-bottom: 1px solid #ccc;
    }
    
    .wpwa-tab-nav li {
        margin: 0 2px -1px 0;
    }
    
    .wpwa-tab-nav li a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        background: #f1f1f1;
        border: 1px solid #ccc;
        color: #444;
        font-weight: 500;
        border-radius: 3px 3px 0 0;
        transition: all 0.2s ease;
    }
    
    .wpwa-tab-nav li a.active {
        background: #fff;
        border-bottom: 1px solid #fff;
        color: #0073aa;
    }
    
    .wpwa-tab-nav li a:hover {
        background: #e5e5e5;
    }
    
    /* Tab content styling */
    .wpwa-tab-content {
        display: none;
        padding: 20px;
        border: 1px solid #ccc;
        border-top: none;
        background: #fff;
    }
    
    .wpwa-tab-content.active {
        display: block;
    }
    
    /* Form styling */
    .wpwa-settings-section {
        margin-bottom: 30px;
    }
    
    .wpwa-form-group {
        margin-bottom: 15px;
    }
    
    .wpwa-form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .wpwa-form-group input[type="text"],
    .wpwa-form-group input[type="url"],
    .wpwa-form-group input[type="number"],
    .wpwa-form-group textarea {
        width: 100%;
        max-width: 500px;
        padding: 8px;
    }
    
    .wpwa-input-group {
        display: flex;
        max-width: 500px;
    }
    
    .wpwa-input-group input {
        flex: 1;
        margin-right: 10px;
    }
    
    .wpwa-field-desc {
        color: #666;
        font-size: 12px;
        margin-top: 4px;
        font-style: italic;
    }
    
    /* Checkbox styling */
    .wpwa-checkbox-group {
        display: flex;
        align-items: center;
    }
    
    .wpwa-checkbox-group input[type="checkbox"] {
        margin-right: 8px;
    }
    
    /* Button styling */
    .wpwa-button {
        padding: 8px 15px;
        background: #0073aa;
        color: #fff;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .wpwa-button-secondary {
        background: #f7f7f7;
        border: 1px solid #ccc;
        color: #555;
    }
    
    .wpwa-button-danger {
        background: #dc3232;
    }
    
    /* Logs table */
    .wpwa-logs-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .wpwa-logs-table th,
    .wpwa-logs-table td {
        padding: 8px 12px;
        text-align: left;
        border: 1px solid #ddd;
    }
    
    .wpwa-logs-table th {
        background: #f1f1f1;
        font-weight: 500;
    }
    
    /* Status indicators */
    .wpwa-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .wpwa-status-success {
        background: #d4edda;
        color: #155724;
    }
    
    .wpwa-status-error {
        background: #f8d7da;
        color: #721c24;
    }
    
    .wpwa-status-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    /* Debug info area */
    .wpwa-debug-info {
        margin-top: 30px;
        padding: 10px;
        background: #f8f9fa;
        border: 1px dashed #ddd;
        border-radius: 3px;
        font-family: monospace;
        font-size: 12px;
    }
    
    /* Make sure the form elements are visible */
    #wpwa-api-url, 
    #wpwa-jwt-secret, 
    #wpwa-connection-timeout, 
    #wpwa-max-retries, 
    #wpwa-notification-template {
        display: block !important;
        visibility: visible !important;
        min-height: 30px !important;
    }
    
    /* Responsive adjustments */
    @media screen and (max-width: 782px) {
        .wpwa-tab-nav {
            flex-direction: column;
        }
        
        .wpwa-tab-nav li a {
            border-radius: 0;
            margin-bottom: 2px;
        }
        
        .wpwa-tab-nav li a.active {
            border-bottom: 1px solid #ccc;
        }
        
        .wpwa-input-group {
            flex-direction: column;
        }
        
        .wpwa-input-group input {
            margin-right: 0;
            margin-bottom: 10px;
        }
    }
</style>

<div class="wrap wpwa-settings-wrap">
    <?php
    // Display admin notices
    settings_errors('wpwa_messages');
    ?>
    
    <div class="wpwa-admin-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <span class="wpwa-version">
            <?php 
            echo sprintf(__('Version %s', 'wp-whatsapp-api'), WPWA_VERSION);
            $install_date = get_option('wpwa_install_date');
            if ($install_date) {
                echo ' | ' . sprintf(__('Installed: %s', 'wp-whatsapp-api'), date_i18n(get_option('date_format'), strtotime($install_date)));
            }
            ?>
        </span>
    </div>
    
    <!-- Debug status area - visible only when debug is enabled -->
    <?php if ($debug_mode == 1): ?>
    <div class="wpwa-debug-info">
        <strong>Debug Mode:</strong> Active | 
        <strong>WordPress:</strong> <?php echo esc_html(get_bloginfo('version')); ?> | 
        <strong>PHP:</strong> <?php echo esc_html(phpversion()); ?> | 
        <strong>Server:</strong> <?php echo esc_html($_SERVER['SERVER_SOFTWARE']); ?>
        <div id="wpwa-debug-status"></div>
    </div>
    <?php endif; ?>
    
    <!-- CSS-based tabbed interface -->
    <div class="wpwa-tabs" id="wpwa-admin-tabs">
        <!-- Tab navigation -->
        <ul class="wpwa-tab-nav">
            <li><a href="#wpwa-tab-settings" class="active" data-tab="settings"><?php _e('Settings', 'wp-whatsapp-api'); ?></a></li>
            <li><a href="#wpwa-tab-sessions" data-tab="sessions"><?php _e('Sessions', 'wp-whatsapp-api'); ?></a></li>
            <li><a href="#wpwa-tab-logs" data-tab="logs"><?php _e('Logs', 'wp-whatsapp-api'); ?></a></li>
        </ul>
        
        <!-- Settings tab content -->
        <div id="wpwa-tab-settings" class="wpwa-tab-content active">
            <form action="options.php" method="post" id="wpwa-settings-form">
                <?php settings_fields('wpwa_settings'); ?>
                
                <!-- API Settings Section -->
                <div class="wpwa-settings-section">
                    <h3><?php _e('API Connection Settings', 'wp-whatsapp-api'); ?></h3>
                    <p><?php _e('Configure your WhatsApp API connection settings.', 'wp-whatsapp-api'); ?></p>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_api_url"><?php _e('API URL', 'wp-whatsapp-api'); ?> <span class="required">*</span></label>
                        <input type="url" id="wpwa_api_url" name="wpwa_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required>
                        <p class="wpwa-field-desc"><?php _e('The URL of your WhatsApp API server.', 'wp-whatsapp-api'); ?></p>
                    </div>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_jwt_secret"><?php _e('JWT Secret', 'wp-whatsapp-api'); ?></label>
                        <div class="wpwa-input-group">
                            <input type="text" id="wpwa_jwt_secret" name="wpwa_jwt_secret" value="<?php echo esc_attr($jwt_secret); ?>" class="regular-text">
                            <button type="button" id="wpwa-generate-jwt-secret" class="wpwa-button wpwa-button-secondary">
                                <?php _e('Generate New Secret', 'wp-whatsapp-api'); ?>
                            </button>
                        </div>
                        <p class="wpwa-field-desc"><?php _e('Secret key used for JWT token generation. Keep this secure.', 'wp-whatsapp-api'); ?></p>
                    </div>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_debug_mode"><?php _e('Debug Mode', 'wp-whatsapp-api'); ?></label>
                        <div class="wpwa-checkbox-group">
                            <input type="checkbox" id="wpwa_debug_mode" name="wpwa_debug_mode" value="1" <?php checked($debug_mode, '1'); ?>>
                            <label for="wpwa_debug_mode"><?php _e('Enable debug mode (logs additional information)', 'wp-whatsapp-api'); ?></label>
                        </div>
                    </div>
                    
                    <div class="wpwa-form-group">
                        <button type="button" id="wpwa-validate-api" class="wpwa-button wpwa-button-secondary">
                            <?php _e('Test Connection', 'wp-whatsapp-api'); ?>
                        </button>
                        <span id="wpwa-api-status"></span>
                    </div>
                </div>
                
                <!-- Message Templates Section -->
                <div class="wpwa-settings-section">
                    <h3><?php _e('Message Templates', 'wp-whatsapp-api'); ?></h3>
                    <p>
                        <?php _e('Configure message templates used for WhatsApp notifications.', 'wp-whatsapp-api'); ?><br>
                        <?php _e('Available placeholders: {{customer_name}}, {{order_id}}, {{order_status}}, {{store_name}}, {{order_total}}', 'wp-whatsapp-api'); ?>
                    </p>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_notification_template"><?php _e('Order Status Notification', 'wp-whatsapp-api'); ?></label>
                        <textarea id="wpwa_notification_template" name="wpwa_notification_template" rows="6" class="large-text"><?php echo esc_textarea($notification_template); ?></textarea>
                        <p class="wpwa-field-desc"><?php _e('Template used for order status update notifications.', 'wp-whatsapp-api'); ?></p>
                    </div>
                </div>
                
                <!-- Advanced Settings Section -->
                <div class="wpwa-settings-section">
                    <h3><?php _e('Advanced Settings', 'wp-whatsapp-api'); ?></h3>
                    <p><?php _e('Advanced configuration options for the WhatsApp API integration', 'wp-whatsapp-api'); ?></p>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_connection_timeout"><?php _e('Connection Timeout', 'wp-whatsapp-api'); ?></label>
                        <div class="wpwa-input-with-suffix">
                            <input type="number" id="wpwa_connection_timeout" name="wpwa_connection_timeout" 
                                value="<?php echo esc_attr($connection_timeout); ?>" min="5" max="120" required>
                        </div>
                        <p class="wpwa-field-desc"><?php _e('Connection timeout in seconds for API requests.', 'wp-whatsapp-api'); ?></p>
                    </div>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_max_retries"><?php _e('Max Retries', 'wp-whatsapp-api'); ?></label>
                        <input type="number" id="wpwa_max_retries" name="wpwa_max_retries" 
                            value="<?php echo esc_attr($max_retries); ?>" min="0" max="10" required>
                        <p class="wpwa-field-desc"><?php _e('Maximum number of retry attempts for failed API requests.', 'wp-whatsapp-api'); ?></p>
                    </div>
                    
                    <div class="wpwa-form-group wpwa-checkbox-group">
                        <input type="checkbox" id="wpwa_allow_tracking" name="wpwa_allow_tracking" value="1" 
                            <?php checked($allow_tracking, 1); ?>>
                        <label for="wpwa_allow_tracking"><?php _e('Help improve this plugin by sharing non-sensitive usage data', 'wp-whatsapp-api'); ?></label>
                        <p class="wpwa-field-desc"><?php _e('We collect data about how you use the plugin, which features you use the most, and basic information about your WordPress setup. No personal data is tracked or stored.', 'wp-whatsapp-api'); ?></p>
                    </div>
                </div>
                
                <!-- Submit button -->
                <div class="wpwa-form-actions">
                    <?php submit_button(__('Save Settings', 'wp-whatsapp-api')); ?>
                </div>
            </form>
        </div>
        
        <!-- Sessions tab content -->
        <div id="wpwa-tab-sessions" class="wpwa-tab-content">
            <h3><?php _e('WhatsApp Session Manager', 'wp-whatsapp-api'); ?></h3>
            <p><?php _e('Manage your WhatsApp connections and QR code pairing.', 'wp-whatsapp-api'); ?></p>
            
            <div class="wpwa-sessions-container" id="wpwa-sessions-content">
                <p class="wpwa-loading"><?php _e('Loading sessions...', 'wp-whatsapp-api'); ?></p>
            </div>
        </div>
        
        <!-- Logs tab content -->
        <div id="wpwa-tab-logs" class="wpwa-tab-content">
            <h3><?php _e('WhatsApp API Logs', 'wp-whatsapp-api'); ?></h3>
            <p><?php _e('View and manage system logs for the WhatsApp API integration.', 'wp-whatsapp-api'); ?></p>
            
            <div class="wpwa-logs-actions">
                <button type="button" id="wpwa-refresh-logs" class="wpwa-button wpwa-button-secondary">
                    <?php _e('Refresh Logs', 'wp-whatsapp-api'); ?>
                </button>
                <button type="button" id="wpwa-clear-logs" class="wpwa-button wpwa-button-danger">
                    <?php _e('Clear All Logs', 'wp-whatsapp-api'); ?>
                </button>
                
                <!-- Log filter controls could go here -->
            </div>
            
            <div id="wpwa-logs-container" class="wpwa-logs-table">
                <p><?php _e('Click "Refresh Logs" to view recent logs.', 'wp-whatsapp-api'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Simple JavaScript for the tabs, no jQuery UI dependencies -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Log that script has loaded
        console.log('WPWA Admin: Script loaded');
        
        // Initialize tabs
        initTabs();
        
        // Setup form event handlers
        setupFormHandlers();
        
        // Debug info
        if (typeof wpwaDebug !== 'undefined') {
            wpwaDebug.log('Admin panel initialized');
        }
    });
    
    function initTabs() {
        // Get all tab navigation items
        var tabLinks = document.querySelectorAll('.wpwa-tab-nav a');
        
        // Add click handlers
        for (var i = 0; i < tabLinks.length; i++) {
            tabLinks[i].addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the tab ID from data attribute or href
                var tabId = this.getAttribute('href');
                var tabName = this.getAttribute('data-tab');
                
                console.log('Tab clicked: ' + tabName);
                
                // Remove active class from all links and tabs
                var allLinks = document.querySelectorAll('.wpwa-tab-nav a');
                var allTabs = document.querySelectorAll('.wpwa-tab-content');
                
                for (var j = 0; j < allLinks.length; j++) {
                    allLinks[j].classList.remove('active');
                }
                
                for (var k = 0; k < allTabs.length; k++) {
                    allTabs[k].classList.remove('active');
                }
                
                // Add active class to current link and tab
                this.classList.add('active');
                document.querySelector(tabId).classList.add('active');
                
                // Load content for specific tabs
                if (tabName === 'logs') {
                    loadLogs();
                } else if (tabName === 'sessions') {
                    loadSessions();
                }
            });
        }
    }
    
    function setupFormHandlers() {
        // Set up the JWT secret generation button
        var jwtButton = document.getElementById('wpwa-generate-jwt-secret');
        if (jwtButton) {
            jwtButton.addEventListener('click', function() {
                // Simple random string generation (in real code, should use an AJAX call)
                var randomString = Math.random().toString(36).substring(2, 15) + 
                                   Math.random().toString(36).substring(2, 15);
                document.getElementById('wpwa_jwt_secret').value = randomString;
                
                // Show confirmation in debug if available
                if (typeof wpwaDebug !== 'undefined') {
                    wpwaDebug.log('Generated new JWT secret');
                }
            });
        }
        
        // Set up the test connection button
        var testButton = document.getElementById('wpwa-validate-api');
        if (testButton) {
            testButton.addEventListener('click', function() {
                var statusEl = document.getElementById('wpwa-api-status');
                statusEl.textContent = 'Testing connection...';
                statusEl.style.color = '#666';
                
                // In real code, this would make an AJAX request
                setTimeout(function() {
                    statusEl.textContent = 'Connection successful!';
                    statusEl.style.color = 'green';
                    
                    // Show confirmation in debug if available
                    if (typeof wpwaDebug !== 'undefined') {
                        wpwaDebug.log('API connection test successful');
                    }
                }, 1000);
            });
        }
    }
    
    function loadLogs() {
        var logsContainer = document.getElementById('wpwa-logs-container');
        if (!logsContainer) return;
        
        logsContainer.innerHTML = '<p>Loading logs...</p>';
        
        // In real code, this would make an AJAX request to load logs
        setTimeout(function() {
            var mockLogs = [
                { time: '2025-06-03 10:15:22', level: 'INFO', message: 'System started' },
                { time: '2025-06-03 10:16:45', level: 'INFO', message: 'Connected to WhatsApp API' },
                { time: '2025-06-03 10:30:12', level: 'WARNING', message: 'API request timeout' },
            ];
            
            // Build HTML table
            var html = '<table class="widefat"><thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead><tbody>';
            
            for (var i = 0; i < mockLogs.length; i++) {
                var log = mockLogs[i];
                var levelClass = 'wpwa-log-' + log.level.toLowerCase();
                
                html += '<tr class="' + levelClass + '">';
                html += '<td>' + log.time + '</td>';
                html += '<td><span class="wpwa-log-level">' + log.level + '</span></td>';
                html += '<td>' + log.message + '</td>';
                html += '</tr>';
            }
            
            html += '</tbody></table>';
            logsContainer.innerHTML = html;
            
            if (typeof wpwaDebug !== 'undefined') {
                wpwaDebug.log('Loaded mock logs for demonstration');
            }
        }, 800);
    }
    
    function loadSessions() {
        var sessionsContainer = document.getElementById('wpwa-sessions-content');
        if (!sessionsContainer) return;
        
        sessionsContainer.innerHTML = '<p>Loading sessions...</p>';
        
        // In real code, this would make an AJAX request to load sessions
        setTimeout(function() {
            var html = '<div class="wpwa-card">';
            html += '<h4>Create New Session</h4>';
            html += '<div class="wpwa-form-group">';
            html += '<label for="wpwa_session_name">Session Name</label>';
            html += '<input type="text" id="wpwa_session_name" placeholder="e.g. Store WhatsApp">';
            html += '</div>';
            html += '<button type="button" class="wpwa-button">Create Session</button>';
            html += '</div>';
            
            html += '<div class="wpwa-card">';
            html += '<h4>Active Sessions</h4>';
            html += '<table class="widefat">';
            html += '<thead><tr><th>Name</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>';
            html += '<tbody><tr><td colspan="4">No active sessions found.</td></tr></tbody>';
            html += '</table>';
            html += '</div>';
            
            sessionsContainer.innerHTML = html;
            
            if (typeof wpwaDebug !== 'undefined') {
                wpwaDebug.log('Loaded mock sessions interface');
            }
        }, 800);
    }
    </script>
</div>