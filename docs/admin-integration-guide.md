# WhatsApp Vendor Dashboard Integration Guide for Administrators

## Introduction

This guide explains how to integrate the WhatsApp Vendor Dashboard into your multi-vendor marketplace, allowing vendors to manage their WhatsApp connections, synchronize products, and configure their WhatsApp integration settings.

## Prerequisites

Before integrating the vendor dashboard, ensure you have:

1. Version 1.2.0 or higher of the WhatsApp Integration for WooCommerce plugin
2. A compatible multi-vendor marketplace plugin (WCFM, Dokan, WC Vendors, or others)
3. WordPress 5.6+ and WooCommerce 4.0+
4. Configured the core WhatsApp API settings in the admin panel

## Implementation Steps

### Step 1: Create a Vendor Dashboard Page

1. Go to WordPress Admin > Pages > Add New
2. Enter a title for the page (e.g., "WhatsApp Integration Dashboard")
3. In the content editor, add the shortcode: `[wpwa_vendor_dashboard]`
4. Publish the page

### Step 2: Customize the Dashboard (Optional)

The shortcode accepts the following attributes:

- `title`: Custom title for the dashboard (default: "WhatsApp Integration")
- `show_logs`: Control whether to display activity logs ("yes" or "no", default: "yes")

Example with custom attributes:

```
[wpwa_vendor_dashboard title="Your WhatsApp Connection Center" show_logs="yes"]
```

### Step 3: Integrate with Your Vendor Dashboard

Depending on your multi-vendor marketplace solution, you have several options:

#### Option 1: Add as Menu Item in Vendor Dashboard

For WCFM Marketplace:

```php
/**
 * Add WhatsApp Integration menu to WCFM vendor dashboard
 */
function add_wcfm_whatsapp_integration_menu($menus) {
    $menus['whatsapp-integration'] = array(
        'label' => 'WhatsApp',
        'url'   => get_permalink(YOUR_PAGE_ID), // Replace with your page ID
        'icon'  => 'phone',
    );
    return $menus;
}
add_filter('wcfm_menus', 'add_wcfm_whatsapp_integration_menu', 80);
```

For Dokan:

```php
/**
 * Add WhatsApp Integration menu to Dokan vendor dashboard
 */
function add_dokan_whatsapp_integration_menu($menu_items) {
    $menu_items['whatsapp-integration'] = array(
        'title' => __('WhatsApp Integration', 'your-text-domain'),
        'icon'  => '<i class="fas fa-phone"></i>',
        'url'   => get_permalink(YOUR_PAGE_ID), // Replace with your page ID
        'pos'   => 71,
    );
    return $menu_items;
}
add_filter('dokan_get_dashboard_nav', 'add_dokan_whatsapp_integration_menu', 10);
```

#### Option 2: Use an iframe in Existing Dashboard

Alternatively, you can embed the dashboard page as an iframe within your existing vendor dashboard:

```php
/**
 * Display WhatsApp integration dashboard in custom tab
 */
function display_whatsapp_dashboard_tab_content() {
    $page_id = YOUR_PAGE_ID; // Replace with your page ID
    $iframe_url = get_permalink($page_id);
    echo '<div class="whatsapp-dashboard-wrapper">';
    echo '<iframe src="' . esc_url($iframe_url) . '" style="width:100%;min-height:700px;border:none;"></iframe>';
    echo '</div>';
}
```

#### Option 3: Direct Link

Simply provide vendors with a direct link to the dashboard page you created in Step 1.

### Step 4: Control Access Permissions

By default, the dashboard is accessible only to users with vendor roles. You can customize which roles are allowed using a filter:

```php
/**
 * Customize allowed vendor roles for WhatsApp dashboard
 */
function customize_whatsapp_vendor_roles($default_roles) {
    // Add or remove roles as needed
    $default_roles[] = 'custom_vendor_role';
    return $default_roles;
}
add_filter('wpwa_allowed_vendor_roles', 'customize_whatsapp_vendor_roles');
```

## Additional Configuration Options

### Automatic Vendor Setup

You can automate the WhatsApp setup process for new vendors using these hooks:

```php
/**
 * Create WhatsApp session for new vendors
 */
function auto_setup_vendor_whatsapp($vendor_id) {
    global $wp_whatsapp_api;
    if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->vendor_session_manager)) {
        return;
    }
    
    // Get vendor data
    $vendor_data = get_userdata($vendor_id);
    if (!$vendor_data) {
        return;
    }
    
    // Create session name based on store name or user name
    $session_name = $vendor_data->display_name;
    
    // Create initial session (vendor will need to scan QR code later)
    $wp_whatsapp_api->vendor_session_manager->create_vendor_session($vendor_id, $session_name);
}
add_action('wcfm_membership_approval', 'auto_setup_vendor_whatsapp');
```

### Customizing Dashboard Appearance

The dashboard uses its own CSS file, but you can add custom styling:

```php
/**
 * Add custom CSS for WhatsApp vendor dashboard
 */
function custom_whatsapp_dashboard_css() {
    if (is_page('YOUR_PAGE_SLUG')) { // Replace with your page slug
        echo '<style>
            .wpwa-vendor-dashboard {
                /* Your custom styles */
                background: #f7f7f7;
                border-radius: 8px;
                padding: 30px;
            }
            
            .wpwa-button-primary {
                background: #25D366; /* WhatsApp green */
                color: white;
            }
        </style>';
    }
}
add_action('wp_head', 'custom_whatsapp_dashboard_css');
```

## Monitoring Vendor Integration Status

As an administrator, you can monitor the status of vendor WhatsApp integrations from the main plugin admin page. You can also access individual vendor dashboards by appending the vendor ID parameter to the dashboard URL:

```
/your-dashboard-page/?vendor_id=123
```

This allows administrators to assist vendors with troubleshooting and configuration.

## Common Integration Issues

### Vendor Role Detection

If vendors cannot access their dashboard, check:

1. Ensure the user has one of the supported vendor roles
2. Verify that your marketplace plugin is properly setting user roles
3. Use the `wpwa_allowed_vendor_roles` filter to add custom vendor roles

### IFrame Height Issues

When embedding via iframe, you might need to adjust the height dynamically:

```javascript
// Adjust iframe height based on content
function adjustIframeHeight() {
    // Target the iframe containing the WhatsApp dashboard
    var iframe = document.querySelector('.whatsapp-dashboard-wrapper iframe');
    if (iframe) {
        iframe.onload = function() {
            iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
        };
    }
}
document.addEventListener('DOMContentLoaded', adjustIframeHeight);
```

## Security Considerations

1. **Vendor Data Isolation**: The dashboard ensures each vendor can only access their own WhatsApp session and product data

2. **AJAX Nonce Verification**: All AJAX requests are secured with nonce verification

3. **Role-Based Access Control**: Only users with appropriate vendor roles can access the dashboard

4. **API Token Security**: JWT tokens are URL-safe and include vendor-specific claims

## Conclusion

By integrating the WhatsApp Vendor Dashboard, you empower vendors to manage their own WhatsApp integration while maintaining centralized control and configuration. This improves marketplace communication capabilities and provides vendors with a valuable tool for customer engagement.

For further assistance, please refer to the main plugin documentation or contact support.