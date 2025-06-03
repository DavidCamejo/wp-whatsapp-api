# WhatsApp Vendor Dashboard Developer Integration Guide

## Introduction

This document provides technical details for developers who want to extend or customize the WhatsApp Vendor Dashboard functionality. It covers the architecture, key classes, available hooks, and implementation details.

## Architecture Overview

The WhatsApp Vendor Dashboard is built using the following components:

1. **Shortcode Handler**: `WPWA_Vendor_Dashboard_Shortcode` class that renders the dashboard
2. **AJAX Handler**: `WPWA_Vendor_Dashboard_AJAX` class that processes vendor requests
3. **Frontend Assets**: JavaScript and CSS files for the dashboard UI
4. **Integration Layer**: Connections to the WhatsApp API Client

### Component Interaction

```
┌─────────────────────┐      ┌─────────────────────┐
│ WordPress Core      │      │ Vendor Dashboard    │
│                     │      │ Shortcode           │
└─────────┬───────────┘      └─────────┬───────────┘
          │                            │
          │                            │
          ▼                            ▼
┌─────────────────────┐      ┌─────────────────────┐
│ WP WhatsApp API     │◄────►│ Vendor Dashboard    │
│ Core Plugin         │      │ AJAX Handler        │
└─────────┬───────────┘      └─────────┬───────────┘
          │                            │
          │                            │
          ▼                            ▼
┌─────────────────────┐      ┌─────────────────────┐
│ WhatsApp API Client │◄────►│ Frontend JavaScript │
│                     │      │                     │
└─────────────────────┘      └─────────────────────┘
```

## Key Classes and Files

### 1. Shortcode Handler (`class-wpwa-vendor-dashboard-shortcode.php`)

This class registers and processes the `[wpwa_vendor_dashboard]` shortcode.

```php
class WPWA_Vendor_Dashboard_Shortcode {
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('wpwa_vendor_dashboard', array($this, 'render_vendor_dashboard'));
    }
    
    /**
     * Render vendor dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Dashboard HTML output
     */
    public function render_vendor_dashboard($atts) {
        // Process attributes with defaults
        $atts = shortcode_atts(array(
            'title'     => __('WhatsApp Integration', 'wp-whatsapp-api'),
            'show_logs' => 'yes',
        ), $atts, 'wpwa_vendor_dashboard');
        
        // Check user permission
        if (!$this->current_user_can_access()) {
            return $this->render_access_denied();
        }
        
        // Enqueue required scripts and styles
        $this->enqueue_assets();
        
        // Get vendor ID
        $vendor_id = $this->get_current_vendor_id();
        
        // Start output buffering
        ob_start();
        
        // Include template
        include WPWA_PATH . 'templates/vendor-dashboard.php';
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    // Other methods...
}
```

### 2. AJAX Handler (`class-wpwa-vendor-dashboard-ajax.php`)

This class processes AJAX requests from the vendor dashboard.

```php
class WPWA_Vendor_Dashboard_AJAX {
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_wpwa_vendor_get_status', array($this, 'get_vendor_status'));
        add_action('wp_ajax_wpwa_vendor_connect', array($this, 'connect_vendor_account'));
        add_action('wp_ajax_wpwa_vendor_disconnect', array($this, 'disconnect_vendor_account'));
        add_action('wp_ajax_wpwa_vendor_sync_products', array($this, 'sync_vendor_products'));
        add_action('wp_ajax_wpwa_vendor_toggle_integration', array($this, 'toggle_vendor_integration'));
        add_action('wp_ajax_wpwa_vendor_get_logs', array($this, 'get_vendor_logs'));
    }
    
    /**
     * Get vendor WhatsApp status
     */
    public function get_vendor_status() {
        // Check nonce
        $this->verify_ajax_nonce('wpwa_vendor_nonce');
        
        // Get vendor ID
        $vendor_id = $this->get_current_vendor_id();
        
        // Get session status
        $status = $this->get_session_status($vendor_id);
        
        // Send response
        wp_send_json_success($status);
    }
    
    // Other AJAX methods...
}
```

### 3. Frontend JavaScript (`vendor-dashboard-frontend.js`)

The JavaScript handles user interactions and AJAX requests.

```javascript
(function($) {
    'use strict';
    
    const WPWA_Vendor_Dashboard = {
        /**
         * Initialize dashboard
         */
        init: function() {
            this.cacheDom();
            this.bindEvents();
            this.fetchStatus();
        },
        
        /**
         * Cache DOM elements
         */
        cacheDom: function() {
            this.$dashboard = $('.wpwa-vendor-dashboard');
            this.$statusIndicator = this.$dashboard.find('.wpwa-connection-status');
            this.$connectBtn = this.$dashboard.find('.wpwa-connect-btn');
            this.$disconnectBtn = this.$dashboard.find('.wpwa-disconnect-btn');
            this.$qrCodeContainer = this.$dashboard.find('.wpwa-qrcode-container');
            this.$syncProductsBtn = this.$dashboard.find('.wpwa-sync-products-btn');
            this.$toggleIntegration = this.$dashboard.find('.wpwa-toggle-integration');
            this.$logsBtn = this.$dashboard.find('.wpwa-logs-btn');
            this.$logsContainer = this.$dashboard.find('.wpwa-logs-container');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            this.$connectBtn.on('click', this.connectWhatsApp.bind(this));
            this.$disconnectBtn.on('click', this.disconnectWhatsApp.bind(this));
            this.$syncProductsBtn.on('click', this.syncProducts.bind(this));
            this.$toggleIntegration.on('change', this.toggleIntegration.bind(this));
            this.$logsBtn.on('click', this.toggleLogs.bind(this));
        },
        
        // Other methods...
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WPWA_Vendor_Dashboard.init();
    });
})(jQuery);
```

## Available Hooks

### Filters

#### `wpwa_allowed_vendor_roles`

Controls which user roles can access the vendor dashboard.

```php
/**
 * Customize allowed vendor roles
 *
 * @param array $roles Default roles that can access vendor dashboard
 * @return array Modified roles array
 */
function my_custom_vendor_roles($roles) {
    // Add a custom role
    $roles[] = 'custom_vendor_role';
    
    // Or completely replace roles
    // $roles = array('vendor', 'custom_role');
    
    return $roles;
}
add_filter('wpwa_allowed_vendor_roles', 'my_custom_vendor_roles');
```

#### `wpwa_vendor_dashboard_template`

Allows overriding the dashboard template path.

```php
/**
 * Use custom vendor dashboard template
 *
 * @param string $template_path Default template path
 * @return string Custom template path
 */
function my_custom_dashboard_template($template_path) {
    return get_stylesheet_directory() . '/whatsapp/custom-vendor-dashboard.php';
}
add_filter('wpwa_vendor_dashboard_template', 'my_custom_dashboard_template');
```

#### `wpwa_vendor_qrcode_lifetime`

Controls how long QR codes are valid before expiring.

```php
/**
 * Extend QR code validity
 *
 * @param int $seconds Default validity in seconds (default: 60)
 * @return int New validity in seconds
 */
function extend_qrcode_lifetime($seconds) {
    return 120; // 2 minutes
}
add_filter('wpwa_vendor_qrcode_lifetime', 'extend_qrcode_lifetime');
```

### Actions

#### `wpwa_vendor_before_dashboard`

Fires before the vendor dashboard is rendered.

```php
/**
 * Add content before vendor dashboard
 * 
 * @param int $vendor_id Current vendor ID
 */
function my_before_dashboard($vendor_id) {
    echo '<div class="my-custom-notice">Important vendor notice!</div>';
}
add_action('wpwa_vendor_before_dashboard', 'my_before_dashboard');
```

#### `wpwa_vendor_after_dashboard`

Fires after the vendor dashboard is rendered.

```php
/**
 * Add content after vendor dashboard
 * 
 * @param int $vendor_id Current vendor ID
 */
function my_after_dashboard($vendor_id) {
    echo '<div class="my-support-info">Need help? Contact support.</div>';
}
add_action('wpwa_vendor_after_dashboard', 'my_after_dashboard');
```

#### `wpwa_vendor_whatsapp_connected`

Fires when a vendor successfully connects their WhatsApp account.

```php
/**
 * Do something when vendor connects WhatsApp
 * 
 * @param int $vendor_id Vendor ID
 * @param string $session_id WhatsApp session ID
 */
function my_vendor_connected($vendor_id, $session_id) {
    // Send welcome message
    $user_info = get_userdata($vendor_id);
    wp_mail(
        $user_info->user_email,
        'WhatsApp Connected Successfully',
        'Your WhatsApp account has been connected to your vendor dashboard.'
    );
}
add_action('wpwa_vendor_whatsapp_connected', 'my_vendor_connected', 10, 2);
```

#### `wpwa_vendor_products_synced`

Fires after vendor products are synchronized with WhatsApp.

```php
/**
 * Track product synchronization
 * 
 * @param int $vendor_id Vendor ID
 * @param array $product_ids Array of synced product IDs
 * @param bool $success Whether sync was successful
 */
function my_track_product_sync($vendor_id, $product_ids, $success) {
    if ($success) {
        // Log successful sync
        error_log("Vendor $vendor_id synced " . count($product_ids) . " products");
    } else {
        // Alert on sync failure
        error_log("Vendor $vendor_id failed to sync products");
    }
}
add_action('wpwa_vendor_products_synced', 'my_track_product_sync', 10, 3);
```

## Extending the Dashboard

### Adding Custom Sections

You can add custom sections to the vendor dashboard by hooking into the render process:

```php
/**
 * Add custom section to vendor dashboard
 */
function add_custom_dashboard_section() {
    global $wp_whatsapp_api;
    $vendor_id = WPWA_Vendor_Dashboard_Shortcode::get_current_vendor_id();
    
    // Get vendor stats
    $stats = get_user_meta($vendor_id, 'wpwa_vendor_stats', true);
    ?>
    
    <div class="wpwa-panel wpwa-statistics-panel">
        <h3><?php _e('WhatsApp Statistics', 'wp-whatsapp-api'); ?></h3>
        <div class="wpwa-panel-content">
            <div class="wpwa-stats-grid">
                <div class="wpwa-stat-box">
                    <span class="wpwa-stat-label"><?php _e('Messages Received', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo isset($stats['messages_received']) ? $stats['messages_received'] : '0'; ?></span>
                </div>
                <div class="wpwa-stat-box">
                    <span class="wpwa-stat-label"><?php _e('Messages Sent', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo isset($stats['messages_sent']) ? $stats['messages_sent'] : '0'; ?></span>
                </div>
                <div class="wpwa-stat-box">
                    <span class="wpwa-stat-label"><?php _e('Orders via WhatsApp', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo isset($stats['orders']) ? $stats['orders'] : '0'; ?></span>
                </div>
                <div class="wpwa-stat-box">
                    <span class="wpwa-stat-label"><?php _e('Conversion Rate', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo isset($stats['conversion_rate']) ? $stats['conversion_rate'] . '%' : '0%'; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
}
add_action('wpwa_vendor_dashboard_after_products', 'add_custom_dashboard_section');
```

### Adding Custom Settings

```php
/**
 * Add custom settings to vendor dashboard
 */
function add_custom_vendor_settings() {
    $vendor_id = WPWA_Vendor_Dashboard_Shortcode::get_current_vendor_id();
    $welcome_message = get_user_meta($vendor_id, 'wpwa_welcome_message', true);
    ?>
    
    <div class="wpwa-panel wpwa-vendor-settings">
        <h3><?php _e('Custom WhatsApp Settings', 'wp-whatsapp-api'); ?></h3>
        <div class="wpwa-panel-content">
            <div class="wpwa-field-row">
                <label for="wpwa_welcome_message"><?php _e('Welcome Message', 'wp-whatsapp-api'); ?></label>
                <textarea id="wpwa_welcome_message" name="wpwa_welcome_message" rows="3"><?php echo esc_textarea($welcome_message); ?></textarea>
                <p class="wpwa-field-help"><?php _e('This message will be sent when a customer initiates a conversation', 'wp-whatsapp-api'); ?></p>
            </div>
            <div class="wpwa-field-row">
                <button type="button" id="wpwa_save_settings" class="wpwa-button wpwa-button-primary"><?php _e('Save Settings', 'wp-whatsapp-api'); ?></button>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#wpwa_save_settings').on('click', function() {
            $.ajax({
                url: wpwaVendorVars.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpwa_vendor_save_settings',
                    nonce: wpwaVendorVars.nonce,
                    welcome_message: $('#wpwa_welcome_message').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Settings saved successfully!');
                    } else {
                        alert('Failed to save settings. Please try again.');
                    }
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wpwa_vendor_dashboard_after_settings', 'add_custom_vendor_settings');

/**
 * Save custom vendor settings
 */
function save_custom_vendor_settings() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpwa_vendor_nonce')) {
        wp_send_json_error('Invalid security token');
    }
    
    // Get vendor ID
    $vendor_id = WPWA_Vendor_Dashboard_Shortcode::get_current_vendor_id();
    if (!$vendor_id) {
        wp_send_json_error('Not a vendor account');
    }
    
    // Save welcome message
    if (isset($_POST['welcome_message'])) {
        update_user_meta($vendor_id, 'wpwa_welcome_message', sanitize_textarea_field($_POST['welcome_message']));
    }
    
    wp_send_json_success('Settings saved successfully');
}
add_action('wp_ajax_wpwa_vendor_save_settings', 'save_custom_vendor_settings');
```

## Security Considerations

### User Access Control

The plugin implements multiple layers of security:

1. **Role-Based Access Control**: Only users with specified vendor roles can access the dashboard
2. **Nonce Verification**: All AJAX requests require valid nonces
3. **Vendor Isolation**: Vendors can only access their own WhatsApp sessions and data

### Safe Implementation Practices

* Always validate user capabilities before processing actions
* Sanitize all inputs and escape all outputs
* Use WordPress nonces for all forms and AJAX requests
* Verify vendor ownership before accessing or modifying data

## Troubleshooting

### Common Issues

#### QR Code Display Problems

If QR codes aren't displaying properly:

1. Check if the WhatsApp API server is reachable
2. Verify that the vendor has the correct permissions
3. Check browser console for JavaScript errors
4. Ensure the API is returning valid QR code data

#### Session Management Issues

If WhatsApp sessions aren't persisting:

1. Verify the session storage mechanism is working properly
2. Check that the WhatsApp API server is maintaining the session
3. Look for session timeout settings in the WhatsApp API configuration

## API Documentation

### AJAX Endpoints

| Endpoint | Description | Parameters | Response |
|----------|-------------|------------|----------|
| `wpwa_vendor_get_status` | Get vendor WhatsApp connection status | `nonce` | Connection status object |
| `wpwa_vendor_connect` | Connect vendor WhatsApp account | `nonce` | QR code data or status |
| `wpwa_vendor_disconnect` | Disconnect vendor WhatsApp account | `nonce` | Success/failure status |
| `wpwa_vendor_sync_products` | Sync vendor products with WhatsApp | `nonce` | Sync results |
| `wpwa_vendor_toggle_integration` | Enable/disable WhatsApp integration | `nonce`, `enabled` | New status |
| `wpwa_vendor_get_logs` | Get vendor activity logs | `nonce`, `limit` | Array of log entries |

### Data Structures

#### Connection Status Object

```json
{
    "connected": true,
    "status": "connected", // One of: connected, disconnected, initializing, waiting_for_scan, error
    "session_id": "vendor_123_abc",
    "last_active": "2025-06-03 13:45:22",
    "phone_number": "+1234567890", // If available
    "qr_code": null // Base64 encoded QR code image when status is waiting_for_scan
}
```

#### Log Entry Object

```json
{
    "id": 123,
    "timestamp": "2025-06-03 13:45:22",
    "type": "connection", // One of: connection, disconnection, product_sync, error
    "message": "WhatsApp account connected successfully",
    "details": {}, // Additional details specific to the log type
    "status": "success" // One of: success, warning, error, info
}
```

## Conclusion

The WhatsApp Vendor Dashboard offers a comprehensive set of tools for vendors to manage their WhatsApp integration. By using the provided hooks and extension points, developers can customize and extend the dashboard to meet specific business needs.

For additional support or questions, please contact the plugin support team.