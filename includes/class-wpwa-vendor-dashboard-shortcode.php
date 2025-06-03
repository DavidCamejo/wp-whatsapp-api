<?php
/**
 * WPWA Vendor Dashboard Shortcode
 *
 * Creates and manages the vendor dashboard shortcode for WhatsApp integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA_Vendor_Dashboard_Shortcode Class
 */
class WPWA_Vendor_Dashboard_Shortcode {
    /**
     * User permissions
     *
     * @var array
     */
    private $user_permissions = array();

    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode
        $this->register_shortcode();

        // Register frontend assets
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
    }

    /**
     * Register shortcode
     */
    private function register_shortcode() {
        add_shortcode('wpwa_vendor_dashboard', array($this, 'render_dashboard'));
    }

    /**
     * Register frontend assets
     */
    public function register_frontend_assets() {
        // Register styles
        wp_register_style(
            'wpwa-vendor-dashboard-css',
            WPWA_ASSETS_URL . 'css/vendor-dashboard-frontend.css',
            array(),
            WPWA_VERSION
        );

        // Register scripts
        wp_register_script(
            'wpwa-vendor-dashboard-js',
            WPWA_ASSETS_URL . 'js/vendor-dashboard-frontend.js',
            array('jquery'),
            WPWA_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wpwa-vendor-dashboard-js', 'wpwaVendorDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_vendor_dashboard_nonce'),
            'i18n' => array(
                'connecting' => __('Connecting...', 'wp-whatsapp-api'),
                'connected' => __('Connected', 'wp-whatsapp-api'),
                'disconnected' => __('Disconnected', 'wp-whatsapp-api'),
                'error' => __('Error', 'wp-whatsapp-api'),
                'syncingProducts' => __('Syncing products...', 'wp-whatsapp-api'),
                'syncComplete' => __('Sync complete', 'wp-whatsapp-api'),
                'scanQrCode' => __('Scan the QR code with WhatsApp on your phone', 'wp-whatsapp-api'),
                'refreshQrCode' => __('Refresh QR Code', 'wp-whatsapp-api'),
                'connectWhatsapp' => __('Connect WhatsApp', 'wp-whatsapp-api'),
                'disconnectWhatsapp' => __('Disconnect', 'wp-whatsapp-api'),
                'syncProducts' => __('Sync Products', 'wp-whatsapp-api'),
                'enableWhatsapp' => __('Enable WhatsApp for Sales', 'wp-whatsapp-api'),
                'loading' => __('Loading...', 'wp-whatsapp-api'),
                'qrExpired' => __('QR code expired. Please refresh.', 'wp-whatsapp-api'),
                'confirmDisconnect' => __('Are you sure you want to disconnect your WhatsApp account?', 'wp-whatsapp-api'),
                'connectionSuccess' => __('WhatsApp connection successful!', 'wp-whatsapp-api'),
                'connectionFailed' => __('WhatsApp connection failed. Please try again.', 'wp-whatsapp-api'),
                'syncSuccess' => __('Products successfully synced with WhatsApp!', 'wp-whatsapp-api'),
                'syncFailed' => __('Product synchronization failed. Please try again.', 'wp-whatsapp-api'),
                'viewLogs' => __('View Logs', 'wp-whatsapp-api'),
                'hideLogs' => __('Hide Logs', 'wp-whatsapp-api'),
                'noLogsFound' => __('No logs found.', 'wp-whatsapp-api'),
                'sessionInitializing' => __('Initializing...', 'wp-whatsapp-api'),
                'sessionPending' => __('Waiting for scan...', 'wp-whatsapp-api'),
                'sessionConnected' => __('Connected', 'wp-whatsapp-api'),
                'sessionDisconnected' => __('Disconnected', 'wp-whatsapp-api')
            )
        ));
    }

    /**
     * Render the vendor dashboard
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_dashboard($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'title' => __('WhatsApp Integration', 'wp-whatsapp-api'),
            'show_logs' => 'yes'
        ), $atts);

        // Check if user has permission
        if (!$this->check_user_permissions()) {
            return $this->render_unauthorized_message();
        }

        // Enqueue required assets
        wp_enqueue_style('wpwa-vendor-dashboard-css');
        wp_enqueue_script('wpwa-vendor-dashboard-js');

        // Get vendor data
        $vendor_data = $this->get_vendor_data();
        if (!$vendor_data) {
            return $this->render_error_message(
                __('Could not retrieve vendor information.', 'wp-whatsapp-api')
            );
        }

        // Start output buffer
        ob_start();

        // Dashboard container
        echo '<div class="wpwa-vendor-dashboard" data-vendor-id="' . esc_attr($vendor_data['vendor_id']) . '">';
        
        // Header
        echo '<div class="wpwa-vendor-dashboard-header">';
        echo '<h2>' . esc_html($atts['title']) . '</h2>';
        echo '</div>';
        
        // WhatsApp Connection Status
        echo '<div class="wpwa-vendor-dashboard-section wpwa-connection-section">';
        echo '<h3>' . __('WhatsApp Connection Status', 'wp-whatsapp-api') . '</h3>';
        echo '<div class="wpwa-connection-status-wrapper">';
        echo '<div id="wpwa-connection-status">' . __('Loading...', 'wp-whatsapp-api') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // WhatsApp Session Management
        echo '<div class="wpwa-vendor-dashboard-section wpwa-session-section">';
        echo '<h3>' . __('WhatsApp Session', 'wp-whatsapp-api') . '</h3>';
        echo '<div id="wpwa-session-container">';
        echo '<div class="wpwa-loading">' . __('Loading session status...', 'wp-whatsapp-api') . '</div>';
        echo '</div>';
        
        echo '<div class="wpwa-qr-code-container" style="display: none;">';
        echo '<div class="wpwa-qr-code-wrapper">';
        echo '<div id="wpwa-qr-code"></div>';
        echo '<p class="wpwa-qr-instructions">' . __('Scan this QR code with your WhatsApp to connect', 'wp-whatsapp-api') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="wpwa-session-actions">';
        echo '<button type="button" id="wpwa-connect-whatsapp" class="wpwa-button wpwa-button-primary">' . __('Connect WhatsApp', 'wp-whatsapp-api') . '</button>';
        echo '<button type="button" id="wpwa-disconnect-whatsapp" class="wpwa-button wpwa-button-danger" style="display: none;">' . __('Disconnect', 'wp-whatsapp-api') . '</button>';
        echo '</div>';
        echo '</div>';
        
        // Product Synchronization
        echo '<div class="wpwa-vendor-dashboard-section wpwa-products-section">';
        echo '<h3>' . __('Product Synchronization', 'wp-whatsapp-api') . '</h3>';
        echo '<div id="wpwa-sync-status">';
        echo '<div class="wpwa-loading">' . __('Loading sync status...', 'wp-whatsapp-api') . '</div>';
        echo '</div>';
        
        echo '<div class="wpwa-product-list">';
        echo '<h4>' . __('Recently Synced Products', 'wp-whatsapp-api') . '</h4>';
        echo '<div id="wpwa-product-list-container"></div>';
        echo '</div>';
        
        echo '<div class="wpwa-sync-actions">';
        echo '<button type="button" id="wpwa-sync-products" class="wpwa-button wpwa-button-secondary">' . __('Sync Products', 'wp-whatsapp-api') . '</button>';
        echo '</div>';
        echo '</div>';
        
        // Configuration Options
        echo '<div class="wpwa-vendor-dashboard-section wpwa-config-section">';
        echo '<h3>' . __('WhatsApp Settings', 'wp-whatsapp-api') . '</h3>';
        
        echo '<div class="wpwa-toggle-group">';
        echo '<label for="wpwa-enable-whatsapp">' . __('Enable WhatsApp for Sales', 'wp-whatsapp-api') . '</label>';
        echo '<div class="wpwa-toggle-switch">';
        echo '<input type="checkbox" id="wpwa-enable-whatsapp" name="wpwa_enable_whatsapp" value="1" />';
        echo '<span class="wpwa-toggle-slider"></span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Logs Section (optional)
        if ($atts['show_logs'] === 'yes') {
            echo '<div class="wpwa-vendor-dashboard-section wpwa-logs-section">';
            echo '<div class="wpwa-logs-header">';
            echo '<h3>' . __('Activity Logs', 'wp-whatsapp-api') . '</h3>';
            echo '<button type="button" id="wpwa-toggle-logs" class="wpwa-button wpwa-button-text">' . __('View Logs', 'wp-whatsapp-api') . '</button>';
            echo '</div>';
            
            echo '<div id="wpwa-logs-container" class="wpwa-logs-container" style="display: none;"></div>';
            echo '</div>';
        }
        
        // Dashboard Messages
        echo '<div id="wpwa-dashboard-messages" class="wpwa-dashboard-messages"></div>';
        
        echo '</div>'; // End dashboard container
        
        // Get the contents of the buffer and clean it
        $content = ob_get_clean();
        
        return $content;
    }

    /**
     * Check if user has permission to access the vendor dashboard
     *
     * @return bool
     */
    private function check_user_permissions() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $allowed_roles = $this->get_allowed_vendor_roles();
        
        // Store permissions for later use
        $this->user_permissions['user_id'] = $user->ID;
        $this->user_permissions['roles'] = $user->roles;
        $this->user_permissions['is_vendor'] = false;
        
        // Check if user has any allowed role
        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles)) {
                $this->user_permissions['is_vendor'] = true;
                return true;
            }
        }
        
        // Check if user is admin or shop manager (they can also access the vendor dashboard)
        if (user_can($user->ID, 'manage_woocommerce') || user_can($user->ID, 'manage_options')) {
            $this->user_permissions['is_admin'] = true;
            return true;
        }
        
        return false;
    }

    /**
     * Get allowed vendor roles
     *
     * @return array
     */
    private function get_allowed_vendor_roles() {
        $default_roles = array(
            'vendor',
            'wcfm_vendor',
            'dc_vendor',
            'seller',
            'dokan_vendor',
            'yith_vendor',
            'wc_product_vendors_admin_vendor',
            'wc_product_vendors_manager_vendor',
            'shop_manager'
        );
        
        // Allow customization via filter
        return apply_filters('wpwa_allowed_vendor_roles', $default_roles);
    }

    /**
     * Get vendor data for current user
     *
     * @return array|false Vendor data or false on failure
     */
    private function get_vendor_data() {
        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $vendor_id = $user_id; // Default: vendor ID is user ID
        $store_name = '';
        $store_url = '';
        
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            $vendor_id = wcfm_get_vendor_id_by_user($user_id);
            if ($vendor_id && function_exists('wcfm_get_vendor_store_name')) {
                $store_name = wcfm_get_vendor_store_name($vendor_id);
                $store_url = wcfm_get_vendor_store_url($vendor_id);
            }
        } 
        // Dokan
        elseif (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($user_id);
            $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : '';
            $store_url = function_exists('dokan_get_store_url') ? dokan_get_store_url($user_id) : '';
        } 
        // WC Vendors
        elseif (class_exists('WCV_Vendors')) {
            $store_name = get_user_meta($user_id, 'pv_shop_name', true);
            $store_url = class_exists('WCV_Vendors') ? WCV_Vendors::get_vendor_shop_page($user_id) : '';
        }
        
        // Default to user display name
        if (empty($store_name)) {
            $user_info = get_userdata($user_id);
            $store_name = $user_info->display_name;
            $store_url = get_author_posts_url($user_id);
        }
        
        return array(
            'vendor_id' => $vendor_id,
            'user_id' => $user_id,
            'store_name' => $store_name,
            'store_url' => $store_url
        );
    }

    /**
     * Render unauthorized message
     *
     * @return string
     */
    private function render_unauthorized_message() {
        $output = '<div class="wpwa-vendor-dashboard wpwa-unauthorized">';
        $output .= '<div class="wpwa-error-message">';
        $output .= '<p>' . __('You do not have permission to access this dashboard.', 'wp-whatsapp-api') . '</p>';
        $output .= '<p>' . __('Please login with a vendor account to access the WhatsApp integration.', 'wp-whatsapp-api') . '</p>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @return string
     */
    private function render_error_message($message) {
        $output = '<div class="wpwa-vendor-dashboard">';
        $output .= '<div class="wpwa-error-message">';
        $output .= '<p>' . esc_html($message) . '</p>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get human-readable status label
     * 
     * @param string $status Status code
     * @return string Readable status
     */
    private function get_status_label($status) {
        $statuses = array(
            'initializing' => __('Initializing', 'wp-whatsapp-api'),
            'pending' => __('Waiting for scan', 'wp-whatsapp-api'),
            'connected' => __('Connected', 'wp-whatsapp-api'),
            'disconnected' => __('Disconnected', 'wp-whatsapp-api'),
            'error' => __('Error', 'wp-whatsapp-api')
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
}