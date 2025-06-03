# WhatsApp Integration for Multivendor Marketplaces

## Integration Guide for Marketplace Administrators

This guide provides specific implementation instructions for marketplace administrators who need to integrate the WhatsApp Vendor Dashboard into various multivendor platforms.

## Table of Contents

1. [Introduction](#introduction)
2. [Integration Options](#integration-options)
3. [WooCommerce Multivendor Compatibility](#woocommerce-multivendor-compatibility)
4. [Custom User Role Management](#custom-user-role-management)
5. [Advanced UI Integration](#advanced-ui-integration)
6. [Database Considerations](#database-considerations)
7. [Performance Optimization](#performance-optimization)
8. [Troubleshooting](#troubleshooting)

## Introduction

The WhatsApp Vendor Dashboard shortcode `[wpwa_vendor_dashboard]` enables each vendor in your marketplace to manage their own WhatsApp integration. This guide focuses on the technical aspects of embedding this functionality within your specific marketplace platform.

## Integration Options

There are several ways to integrate the vendor dashboard into your marketplace:

### 1. Dedicated Page Integration

Create a dedicated page that vendors can access:

```php
// Create a new page with the vendor dashboard shortcode
function wpwa_create_vendor_dashboard_page() {
    // Only run once
    if (get_option('wpwa_vendor_page_created')) {
        return;
    }
    
    $page_id = wp_insert_post([
        'post_title'    => 'WhatsApp Integration',
        'post_content'  => '[wpwa_vendor_dashboard]',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_type'     => 'page',
    ]);
    
    if (!is_wp_error($page_id)) {
        // Save page ID for reference
        update_option('wpwa_vendor_page_id', $page_id);
        update_option('wpwa_vendor_page_created', true);
    }
}
register_activation_hook(__FILE__, 'wpwa_create_vendor_dashboard_page');
```

### 2. Tab Integration

Add the dashboard as a tab in your existing vendor panel:

```php
/**
 * Add WhatsApp tab to vendor dashboard tabs
 * Compatible with multiple vendor plugins
 */
function wpwa_add_vendor_dashboard_tab($tabs) {
    $tabs['whatsapp'] = [
        'label' => __('WhatsApp', 'wp-whatsapp-api'),
        'content' => '[wpwa_vendor_dashboard]',
        // Additional parameters may vary by platform
    ];
    return $tabs;
}
```

### 3. Endpoint Integration

Register a custom endpoint in the vendor area:

```php
/**
 * Add custom WhatsApp endpoint to vendor dashboard
 */
function wpwa_register_vendor_endpoint() {
    add_rewrite_endpoint('whatsapp', EP_PAGES);
}
add_action('init', 'wpwa_register_vendor_endpoint');

/**
 * Add query vars
 */
function wpwa_add_query_vars($vars) {
    $vars[] = 'whatsapp';
    return $vars;
}
add_filter('query_vars', 'wpwa_add_query_vars');

/**
 * Add WhatsApp menu item to vendor dashboard
 */
function wpwa_add_vendor_menu_item($items) {
    $items['whatsapp'] = __('WhatsApp Integration', 'wp-whatsapp-api');
    return $items;
}

/**
 * Display WhatsApp dashboard content
 */
function wpwa_vendor_endpoint_content() {
    echo do_shortcode('[wpwa_vendor_dashboard]');
}
```

## WooCommerce Multivendor Compatibility

Here are specific integration instructions for popular multivendor plugins:

### WCFM Marketplace

```php
/**
 * Add WhatsApp integration to WCFM vendor dashboard
 */
function wpwa_wcfm_integration() {
    global $WCFM;
    
    // Add new menu
    add_filter('wcfm_menus', 'wpwa_wcfm_menu', 20);
    
    // Add new endpoint
    add_action('init', 'wpwa_wcfm_init', 20);
    
    // Add endpoint content
    add_action('wcfm_whatsapp_integration_endpoint', 'wpwa_wcfm_endpoint_content');
}
add_action('after_setup_theme', 'wpwa_wcfm_integration');

/**
 * Add WhatsApp menu to WCFM
 */
function wpwa_wcfm_menu($menus) {
    $menus['whatsapp-integration'] = [
        'label' => __('WhatsApp', 'wp-whatsapp-api'),
        'url'   => '#',
        'icon'  => 'phone',
        'priority' => 70,
    ];
    return $menus;
}

/**
 * Register WCFM endpoint
 */
function wpwa_wcfm_init() {
    global $WCFM;
    $WCFM->add_new_endpoint('whatsapp-integration', 'whatsapp_integration');
}

/**
 * WCFM endpoint content
 */
function wpwa_wcfm_endpoint_content() {
    echo do_shortcode('[wpwa_vendor_dashboard]');
}
```

### Dokan

```php
/**
 * Add WhatsApp integration to Dokan vendor dashboard
 */
function wpwa_dokan_integration() {
    // Register new Dokan vendor menu
    add_filter('dokan_get_dashboard_nav', 'wpwa_dokan_add_menu');
    
    // Register new Dokan vendor endpoint
    add_filter('dokan_query_var_filter', 'wpwa_dokan_add_endpoint');
    
    // Add endpoint content
    add_action('dokan_whatsapp_integration_content', 'wpwa_dokan_endpoint_content');
}
add_action('plugins_loaded', 'wpwa_dokan_integration');

/**
 * Add WhatsApp menu to Dokan
 */
function wpwa_dokan_add_menu($menu) {
    $menu['whatsapp'] = [
        'title' => __('WhatsApp', 'wp-whatsapp-api'),
        'icon'  => 'fas fa-phone',
        'url'   => dokan_get_navigation_url('whatsapp'),
        'pos'   => 70
    ];
    return $menu;
}

/**
 * Add Dokan endpoint
 */
function wpwa_dokan_add_endpoint($query_vars) {
    $query_vars['whatsapp'] = 'whatsapp';
    return $query_vars;
}

/**
 * Dokan endpoint content
 */
function wpwa_dokan_endpoint_content() {
    echo do_shortcode('[wpwa_vendor_dashboard]');
}
```

### WC Vendors

```php
/**
 * Add WhatsApp integration to WC Vendors dashboard
 */
function wpwa_wc_vendors_integration() {
    // Add new page to Pro dashboard
    add_filter('wcvendors_pro_dashboard_quick_links', 'wpwa_wcv_add_dashboard_page');
}
add_action('plugins_loaded', 'wpwa_wc_vendors_integration');

/**
 * Add WhatsApp page to WC Vendors
 */
function wpwa_wcv_add_dashboard_page($pages) {
    $pages[] = [
        'id'       => 'whatsapp',
        'label'    => __('WhatsApp', 'wp-whatsapp-api'),
        'icon'     => 'phone',
        'app'      => true,
        'url'      => '',
        'slug'     => 'whatsapp',
        'template' => dirname(plugin_dir_path(__FILE__)) . '/templates/wcvendors-whatsapp.php',
    ];
    return $pages;
}
```

Create the template file at `/wp-whatsapp-integration/templates/wcvendors-whatsapp.php`:

```php
<?php
/**
 * WhatsApp integration page for WC Vendors
 */
echo do_shortcode('[wpwa_vendor_dashboard]');
?>
```

## Custom User Role Management

By default, the vendor dashboard is accessible to users with these roles:

- `shop_vendor`
- `vendor`
- `wcfm_vendor`
- `dc_vendor`
- `seller`

You can customize access using the `wpwa_allowed_vendor_roles` filter:

```php
/**
 * Customize which user roles can access the vendor dashboard
 */
function wpwa_custom_vendor_roles($roles) {
    // Add custom role(s)
    $roles[] = 'marketplace_vendor';
    $roles[] = 'custom_seller_role';
    
    // Remove a role if needed
    $key = array_search('seller', $roles);
    if ($key !== false) {
        unset($roles[$key]);
    }
    
    return $roles;
}
add_filter('wpwa_allowed_vendor_roles', 'wpwa_custom_vendor_roles');
```

For more granular control based on capabilities or other factors:

```php
/**
 * Fine-grained control over dashboard access
 */
function wpwa_custom_access_control($can_access, $user_id) {
    // Allow access based on custom criteria
    if (get_user_meta($user_id, 'whatsapp_access_level', true) === 'granted') {
        return true;
    }
    
    // Or restrict based on custom criteria
    if (get_user_meta($user_id, 'account_status', true) === 'suspended') {
        return false;
    }
    
    return $can_access;
}
add_filter('wpwa_vendor_can_access_dashboard', 'wpwa_custom_access_control', 10, 2);
```

## Advanced UI Integration

### Custom Dashboard Styling

To ensure the vendor dashboard matches your marketplace's design:

```php
/**
 * Add custom styling for WhatsApp vendor dashboard
 */
function wpwa_custom_vendor_dashboard_styles() {
    // Only on pages with the shortcode
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wpwa_vendor_dashboard')) {
        wp_enqueue_style(
            'custom-wpwa-vendor-dashboard', 
            get_stylesheet_directory_uri() . '/css/custom-wpwa-vendor.css',
            array('wpwa-vendor-dashboard-style'),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'wpwa_custom_vendor_dashboard_styles', 20);
```

Create `/css/custom-wpwa-vendor.css` in your theme with your styling customizations.

### Template Customization

Override the default dashboard template:

```php
/**
 * Use custom vendor dashboard template from theme
 */
function wpwa_custom_dashboard_template($template_path) {
    $custom_path = get_stylesheet_directory() . '/whatsapp/vendor-dashboard.php';
    
    if (file_exists($custom_path)) {
        return $custom_path;
    }
    
    return $template_path;
}
add_filter('wpwa_vendor_dashboard_template', 'wpwa_custom_dashboard_template');
```

Create a custom template at `/themes/your-theme/whatsapp/vendor-dashboard.php`.

### Extended Information Display

Add custom sections to the dashboard:

```php
/**
 * Add custom stats section to vendor dashboard
 */
function wpwa_add_custom_stats_section() {
    // Get current vendor ID
    $vendor_id = method_exists('WPWA_Vendor_Dashboard_Shortcode', 'get_current_vendor_id') ?
        WPWA_Vendor_Dashboard_Shortcode::get_current_vendor_id() : get_current_user_id();
    
    // Get analytics data from your custom source
    $message_count = get_user_meta($vendor_id, 'wpwa_message_count', true) ?: 0;
    $response_rate = get_user_meta($vendor_id, 'wpwa_response_rate', true) ?: '0%';
    
    // Output custom section
    ?>
    <div class="wpwa-panel wpwa-analytics-panel">
        <h3><?php _e('WhatsApp Analytics', 'wp-whatsapp-api'); ?></h3>
        <div class="wpwa-panel-content">
            <div class="wpwa-analytics-grid">
                <div class="wpwa-stat-item">
                    <span class="wpwa-stat-label"><?php _e('Total Messages', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo esc_html($message_count); ?></span>
                </div>
                <div class="wpwa-stat-item">
                    <span class="wpwa-stat-label"><?php _e('Response Rate', 'wp-whatsapp-api'); ?></span>
                    <span class="wpwa-stat-value"><?php echo esc_html($response_rate); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
}
add_action('wpwa_vendor_dashboard_after_connection', 'wpwa_add_custom_stats_section');
```

## Database Considerations

### Optimizing for Large Marketplaces

For marketplaces with many vendors, consider these optimizations:

```php
/**
 * Optimize session data storage for large marketplaces
 */
function wpwa_optimize_session_storage() {
    global $wpdb;
    
    // Set up a dedicated sessions table if it doesn't exist
    $table_name = $wpdb->prefix . 'wpwa_vendor_sessions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) NOT NULL,
            session_id varchar(255) NOT NULL,
            session_data longtext NOT NULL,
            last_active datetime NOT NULL,
            created datetime NOT NULL,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'wpwa_optimize_session_storage');

/**
 * Override default session storage to use custom table
 */
function wpwa_custom_session_handler($handler, $vendor_id, $session_id) {
    return new WPWA_Custom_Session_Handler($vendor_id, $session_id);
}
add_filter('wpwa_vendor_session_handler', 'wpwa_custom_session_handler', 10, 3);
```

### Scheduled Maintenance

Implement regular maintenance for cleanup:

```php
/**
 * Register periodic cleanup of WhatsApp sessions
 */
function wpwa_register_session_cleanup() {
    if (!wp_next_scheduled('wpwa_cleanup_old_sessions')) {
        wp_schedule_event(time(), 'daily', 'wpwa_cleanup_old_sessions');
    }
}
add_action('wp', 'wpwa_register_session_cleanup');

/**
 * Clean up expired WhatsApp sessions
 */
function wpwa_cleanup_sessions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpwa_vendor_sessions';
    
    // Remove sessions inactive for more than 2 weeks
    $two_weeks_ago = date('Y-m-d H:i:s', strtotime('-14 days'));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE last_active < %s",
        $two_weeks_ago
    ));
}
add_action('wpwa_cleanup_old_sessions', 'wpwa_cleanup_sessions');
```

## Performance Optimization

### Batch Processing for Product Sync

For vendors with large product catalogs:

```php
/**
 * Implement background processing for large product synchronizations
 */
function wpwa_enqueue_background_sync($vendor_id, $product_ids) {
    // If we have many products, use background processing
    if (count($product_ids) > 50) {
        // Create a new background task
        $task = new WPWA_Background_Sync_Task();
        
        // Split products into batches of 50
        $batches = array_chunk($product_ids, 50);
        
        foreach ($batches as $batch) {
            $task->push_to_queue(array(
                'vendor_id'   => $vendor_id,
                'product_ids' => $batch
            ));
        }
        
        // Dispatch the task
        $task->save()->dispatch();
        
        return true;
    }
    
    return false; // Process normally
}
add_filter('wpwa_use_background_sync', 'wpwa_enqueue_background_sync', 10, 2);
```

### Staggered API Request Implementation

To avoid rate-limiting or performance issues with WhatsApp API:

```php
/**
 * Stagger API requests to prevent rate limiting
 */
function wpwa_stagger_api_requests() {
    global $wpwa_request_count;
    
    if (!isset($wpwa_request_count)) {
        $wpwa_request_count = 0;
    }
    
    $wpwa_request_count++;
    
    // After every 5 requests, add a small delay
    if ($wpwa_request_count % 5 == 0) {
        sleep(1); // 1-second delay
    }
    
    // After every 20 requests, add a longer delay
    if ($wpwa_request_count % 20 == 0) {
        sleep(5); // 5-second delay
    }
}
add_action('wpwa_before_api_request', 'wpwa_stagger_api_requests');
```

## Troubleshooting

### Common Integration Issues

#### Issue: Dashboard Not Displaying for Vendors

**Possible solutions:**

```php
/**
 * Debugging vendor dashboard access
 */
function wpwa_debug_vendor_access() {
    // Only add for administrators for testing
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="wpwa-debug-info">';
    echo '<h3>WhatsApp Dashboard Debug Info</h3>';
    
    // Check user roles
    $user = wp_get_current_user();
    echo '<p>Current user roles: ' . implode(', ', $user->roles) . '</p>';
    
    // Check filters
    $allowed_roles = apply_filters('wpwa_allowed_vendor_roles', array(
        'shop_vendor', 'vendor', 'wcfm_vendor', 'dc_vendor', 'seller'
    ));
    echo '<p>Allowed roles: ' . implode(', ', $allowed_roles) . '</p>';
    
    // Check final access determination
    $can_access = false;
    foreach ($allowed_roles as $role) {
        if (in_array($role, $user->roles)) {
            $can_access = true;
            break;
        }
    }
    $can_access = apply_filters('wpwa_vendor_can_access_dashboard', $can_access, $user->ID);
    
    echo '<p>Final access determination: ' . ($can_access ? 'Granted' : 'Denied') . '</p>';
    echo '</div>';
}
add_action('wpwa_vendor_before_dashboard', 'wpwa_debug_vendor_access');
```

#### Issue: AJAX Communication Failures

**Possible solutions:**

```php
/**
 * Add AJAX debugging to vendor dashboard
 */
function wpwa_debug_ajax_issues() {
    // Only for administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add AJAX request debugging
        $(document).ajaxSend(function(event, jqxhr, settings) {
            if (settings.url.indexOf('wpwa_vendor') !== -1) {
                console.log('WhatsApp AJAX Request:', settings);
            }
        });
        
        $(document).ajaxComplete(function(event, jqxhr, settings) {
            if (settings.url.indexOf('wpwa_vendor') !== -1) {
                console.log('WhatsApp AJAX Response:', jqxhr.responseJSON || jqxhr.responseText);
            }
        });
        
        $(document).ajaxError(function(event, jqxhr, settings, error) {
            if (settings.url.indexOf('wpwa_vendor') !== -1) {
                console.error('WhatsApp AJAX Error:', error, jqxhr.responseText);
                
                // Display error on page for admins
                $('.wpwa-vendor-dashboard').prepend(
                    '<div class="wpwa-ajax-error notice notice-error">' +
                    '<p><strong>AJAX Error:</strong> ' + error + '</p>' +
                    '<p><strong>Response:</strong> ' + jqxhr.responseText + '</p>' +
                    '</div>'
                );
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'wpwa_debug_ajax_issues');
```

### Integration Testing

Implement a test mode to verify your integration:

```php
/**
 * Add test mode for vendor dashboard integration
 */
function wpwa_enable_test_mode() {
    // Only accessible with specific query parameter and for admins
    if (!current_user_can('manage_options') || !isset($_GET['wpwa_test_mode'])) {
        return;
    }
    
    // Add test mode indicator
    echo '<div class="wpwa-test-mode-active notice notice-warning">';
    echo '<p><strong>' . __('WhatsApp Test Mode Active', 'wp-whatsapp-api') . '</strong></p>';
    echo '<p>' . __('The dashboard is running in test mode. API calls are simulated.', 'wp-whatsapp-api') . '</p>';
    echo '</div>';
    
    // Add test mode script to simulate API responses
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Override AJAX calls in test mode
        $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
            if (originalOptions.data && typeof originalOptions.data === 'string' && 
                originalOptions.data.indexOf('wpwa_vendor') !== -1) {
                
                var action = '';
                var params = originalOptions.data.split('&');
                
                for (var i = 0; i < params.length; i++) {
                    var param = params[i].split('=');
                    if (param[0] === 'action') {
                        action = param[1];
                        break;
                    }
                }
                
                // Mock API responses based on action
                var mockResponse = wpwaMockResponse(action);
                
                if (mockResponse) {
                    jqXHR.abort();
                    var d = $.Deferred();
                    
                    // Simulate network delay
                    setTimeout(function() {
                        d.resolve(mockResponse);
                    }, 600);
                    
                    return d.promise();
                }
            }
        });
        
        // Generate mock responses
        function wpwaMockResponse(action) {
            switch(action) {
                case 'wpwa_vendor_get_status':
                    return {
                        success: true,
                        data: {
                            connected: true,
                            status: 'connected',
                            session_id: 'test_session_123',
                            last_active: '2025-06-03 14:22:33',
                            phone_number: '+1234567890'
                        }
                    };
                
                case 'wpwa_vendor_connect':
                    return {
                        success: true,
                        data: {
                            qr_code: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                            expires_in: 60
                        }
                    };
                    
                case 'wpwa_vendor_sync_products':
                    return {
                        success: true,
                        data: {
                            total: 10,
                            synced: 10,
                            failed: 0,
                            message: 'All products synced successfully'
                        }
                    };
                    
                default:
                    return null;
            }
        }
    });
    </script>
    <?php
}
add_action('wpwa_vendor_before_dashboard', 'wpwa_enable_test_mode');
```

## Final Integration Checklist

1. ✓ Verify proper user role configuration
2. ✓ Test the vendor dashboard integration in each marketplace location
3. ✓ Ensure all AJAX endpoints are working properly
4. ✓ Confirm WhatsApp connection process works for vendors
5. ✓ Test product synchronization with various product types
6. ✓ Verify session persistence and reconnection capabilities
7. ✓ Check mobile responsiveness of the dashboard interface
8. ✓ Implement appropriate error logging and monitoring
9. ✓ Consider rate limiting to prevent API abuse
10. ✓ Add appropriate documentation for vendors

---

This integration guide provides the technical foundation for implementing the WhatsApp Vendor Dashboard in various multivendor marketplace scenarios. For additional assistance or custom implementations, please contact your plugin provider.