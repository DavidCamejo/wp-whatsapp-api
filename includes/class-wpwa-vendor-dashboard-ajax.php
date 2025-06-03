<?php
/**
 * WPWA Vendor Dashboard AJAX Handler
 *
 * Processes AJAX requests for the vendor dashboard
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA_Vendor_Dashboard_AJAX Class
 */
class WPWA_Vendor_Dashboard_AJAX {
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Session management
        add_action('wp_ajax_wpwa_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_wpwa_check_session', array($this, 'ajax_check_session'));
        add_action('wp_ajax_wpwa_disconnect_session', array($this, 'ajax_disconnect_session'));
        
        // Product synchronization
        add_action('wp_ajax_wpwa_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_wpwa_get_sync_status', array($this, 'ajax_get_sync_status'));
        
        // Integration toggle
        add_action('wp_ajax_wpwa_toggle_integration', array($this, 'ajax_toggle_integration'));
        
        // Logs retrieval
        add_action('wp_ajax_wpwa_get_logs', array($this, 'ajax_get_logs'));
        
        // Get product info
        add_action('wp_ajax_wpwa_get_product_info', array($this, 'ajax_get_product_info'));
    }

    /**
     * Create a new WhatsApp session
     */
    public function ajax_create_session() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to create a session for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get session name
        $session_name = isset($_POST['session_name']) ? sanitize_text_field($_POST['session_name']) : '';
        if (empty($session_name)) {
            // Generate a default session name if none provided
            $vendor_data = $this->get_vendor_data($vendor_id);
            $session_name = $vendor_data && !empty($vendor_data['store_name']) ? 
                $vendor_data['store_name'] : 'Vendor ' . $vendor_id;
        }
        
        // Get vendor session manager
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->vendor_session_manager)) {
            wp_send_json_error(array('message' => __('Session manager not available.', 'wp-whatsapp-api')));
        }
        
        // Create session
        $result = $wp_whatsapp_api->vendor_session_manager->create_vendor_session($vendor_id, $session_name);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to create WhatsApp session.', 'wp-whatsapp-api')));
        }
        
        wp_send_json_success(array(
            'client_id' => $result['client_id'],
            'qr_code' => $result['qr_code'],
            'status' => $result['status']
        ));
    }

    /**
     * Check status of a WhatsApp session
     */
    public function ajax_check_session() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID and client ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to access sessions for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get vendor session manager
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->vendor_session_manager)) {
            wp_send_json_error(array('message' => __('Session manager not available.', 'wp-whatsapp-api')));
        }
        
        // Get sessions for vendor
        $sessions = $wp_whatsapp_api->vendor_session_manager->get_vendor_sessions($vendor_id);
        
        // If client_id is provided, check that specific session
        if (!empty($client_id)) {
            $response = $wp_whatsapp_api->vendor_session_manager->check_session_status($client_id);
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }
            
            wp_send_json_success($response);
        }
        
        // Otherwise return all sessions
        wp_send_json_success(array('sessions' => $sessions));
    }

    /**
     * Disconnect a WhatsApp session
     */
    public function ajax_disconnect_session() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID and client ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No client ID provided.', 'wp-whatsapp-api')));
        }
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to disconnect sessions for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get vendor session manager
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->vendor_session_manager)) {
            wp_send_json_error(array('message' => __('Session manager not available.', 'wp-whatsapp-api')));
        }
        
        // Disconnect session
        $result = $wp_whatsapp_api->vendor_session_manager->disconnect_vendor_session($vendor_id, $client_id);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to disconnect WhatsApp session.', 'wp-whatsapp-api')));
        }
        
        wp_send_json_success(array('message' => __('WhatsApp session disconnected successfully.', 'wp-whatsapp-api')));
    }

    /**
     * Synchronize vendor products with WhatsApp catalog
     */
    public function ajax_sync_products() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to sync products for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get global API instance
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->product_sync_manager)) {
            wp_send_json_error(array('message' => __('Product sync manager not available.', 'wp-whatsapp-api')));
        }
        
        // Get product sync manager
        $product_sync_manager = $wp_whatsapp_api->product_sync_manager;
        
        // Get vendor products
        $products = $product_sync_manager->get_vendor_products($vendor_id, true);
        
        if (empty($products)) {
            wp_send_json_error(array('message' => __('No products found for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Queue products for sync
        $queued = 0;
        foreach ($products as $product_id) {
            $product_sync_manager->queue_product_sync($product_id);
            $queued++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('%d products queued for synchronization.', 'wp-whatsapp-api'),
                $queued
            ),
            'products_queued' => $queued
        ));
    }

    /**
     * Get product synchronization status
     */
    public function ajax_get_sync_status() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to access sync status for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get global API instance
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->product_sync_manager)) {
            wp_send_json_error(array('message' => __('Product sync manager not available.', 'wp-whatsapp-api')));
        }
        
        // Get product sync manager
        $product_sync_manager = $wp_whatsapp_api->product_sync_manager;
        
        // Get vendor products
        $products = $product_sync_manager->get_vendor_products($vendor_id);
        
        // Process sync statistics
        $stats = array(
            'total' => count($products),
            'synced' => 0,
            'pending' => 0,
            'failed' => 0,
            'not_synced' => 0,
            'recent_products' => array()
        );
        
        // Process at most 5 products for the recent list
        $recent_count = 0;
        foreach ($products as $product) {
            $sync_status = get_post_meta($product->get_id(), '_wpwa_sync_status', true);
            
            if ($sync_status === 'synced') {
                $stats['synced']++;
            } elseif ($sync_status === 'pending') {
                $stats['pending']++;
            } elseif ($sync_status === 'failed') {
                $stats['failed']++;
            } else {
                $stats['not_synced']++;
            }
            
            // Get recently synced products
            if ($recent_count < 5 && !empty($sync_status)) {
                $sync_time = get_post_meta($product->get_id(), '_wpwa_sync_time', true);
                $error = get_post_meta($product->get_id(), '_wpwa_sync_error', true);
                
                $stats['recent_products'][] = array(
                    'product_id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'status' => $sync_status,
                    'time' => $sync_time,
                    'error' => $error,
                    'thumbnail' => get_the_post_thumbnail_url($product->get_id(), 'thumbnail'),
                    'edit_url' => get_edit_post_link($product->get_id(), 'raw')
                );
                
                $recent_count++;
            }
        }
        
        wp_send_json_success($stats);
    }

    /**
     * Toggle WhatsApp integration status
     */
    public function ajax_toggle_integration() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID and enabled status
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to update settings for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get user ID
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Could not find user associated with this vendor.', 'wp-whatsapp-api')));
        }
        
        // Update user meta
        update_user_meta($user_id, 'wpwa_enable_whatsapp', $enabled ? '1' : '0');
        
        // Log the action
        global $wp_whatsapp_api;
        if ($wp_whatsapp_api && isset($wp_whatsapp_api->logger)) {
            $action = $enabled ? 'enabled' : 'disabled';
            $wp_whatsapp_api->logger->info(sprintf('Vendor %d %s WhatsApp integration', $vendor_id, $action), array(
                'vendor_id' => $vendor_id,
                'user_id' => $user_id
            ));
        }
        
        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? 
                __('WhatsApp integration enabled successfully.', 'wp-whatsapp-api') : 
                __('WhatsApp integration disabled.', 'wp-whatsapp-api')
        ));
    }

    /**
     * Get vendor logs
     */
    public function ajax_get_logs() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get vendor ID
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to access logs for this vendor.', 'wp-whatsapp-api')));
        }
        
        // Get global API instance
        global $wp_whatsapp_api;
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->logger)) {
            wp_send_json_error(array('message' => __('Logger not available.', 'wp-whatsapp-api')));
        }
        
        // Get logs
        $logs = array();
        if (method_exists($wp_whatsapp_api->logger, 'get_logs_for_vendor')) {
            $logs = $wp_whatsapp_api->logger->get_logs_for_vendor($vendor_id, $limit);
        }
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    /**
     * Get product information for a specific product
     */
    public function ajax_get_product_info() {
        // Verify nonce
        if (!$this->validate_vendor_request('wpwa_vendor_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-whatsapp-api')));
        }
        
        // Get product ID and vendor ID
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        // Validate vendor
        if (!$this->validate_vendor_ownership($vendor_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this product.', 'wp-whatsapp-api')));
        }
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found.', 'wp-whatsapp-api')));
        }
        
        // Verify product belongs to vendor
        global $wp_whatsapp_api;
        if ($wp_whatsapp_api && isset($wp_whatsapp_api->product_sync_manager)) {
            $product_vendor_id = $wp_whatsapp_api->product_sync_manager->get_product_vendor_id($product_id);
            
            if ($product_vendor_id != $vendor_id) {
                wp_send_json_error(array('message' => __('You do not have permission to access this product.', 'wp-whatsapp-api')));
            }
        }
        
        // Get sync information
        $sync_status = get_post_meta($product_id, '_wpwa_sync_status', true);
        $sync_error = get_post_meta($product_id, '_wpwa_sync_error', true);
        $sync_time = get_post_meta($product_id, '_wpwa_sync_time', true);
        $catalog_product_id = get_post_meta($product_id, '_wpwa_catalog_product_id', true);
        
        // Product information
        $product_info = array(
            'product_id' => $product_id,
            'name' => $product->get_name(),
            'price' => $product->get_price_html(),
            'stock_status' => $product->get_stock_status(),
            'permalink' => get_permalink($product_id),
            'thumbnail' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
            'sync_status' => $sync_status,
            'sync_error' => $sync_error,
            'sync_time' => $sync_time ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sync_time)) : '',
            'catalog_product_id' => $catalog_product_id
        );
        
        wp_send_json_success($product_info);
    }

    /**
     * Validate vendor request nonce
     *
     * @param string $action Nonce action name
     * @return bool Is valid request
     */
    private function validate_vendor_request($action = 'wpwa_vendor_dashboard_nonce') {
        return isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $action);
    }

    /**
     * Validate vendor ownership
     *
     * @param int $vendor_id Vendor ID to validate
     * @return bool Is current user the owner of this vendor ID
     */
    private function validate_vendor_ownership($vendor_id) {
        if (!$vendor_id) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Admin can access any vendor data
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce')) {
            return true;
        }
        
        // Check WCFM vendor ownership
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            $user_vendor_id = wcfm_get_vendor_id_by_user($user_id);
            return $user_vendor_id == $vendor_id;
        }
        
        // For most marketplace plugins, vendor ID equals user ID
        return $user_id == $vendor_id;
    }

    /**
     * Get user ID from vendor ID
     *
     * @param int $vendor_id Vendor ID
     * @return int|false User ID or false
     */
    private function get_user_id_from_vendor($vendor_id) {
        // For most marketplace plugins, the vendor ID is the user ID
        if (is_numeric($vendor_id)) {
            $user_id = (int) $vendor_id;
            
            // Check if user exists
            if (get_user_by('id', $user_id)) {
                return $user_id;
            }
        }
        
        // WCFM might have a different mapping
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            // Try to find a user that maps to this vendor ID
            global $wpdb;
            
            $users = get_users(array('role__in' => array(
                'administrator', 'shop_manager', 'wcfm_vendor', 'seller', 
                'vendor', 'dc_vendor', 'wc_product_vendors_admin_vendor'
            )));
            
            foreach ($users as $user) {
                $user_vendor_id = wcfm_get_vendor_id_by_user($user->ID);
                if ($user_vendor_id == $vendor_id) {
                    return $user->ID;
                }
            }
        }
        
        return false;
    }

    /**
     * Get vendor data
     *
     * @param int $vendor_id Vendor ID
     * @return array|false Vendor data or false
     */
    private function get_vendor_data($vendor_id) {
        if (!$vendor_id) {
            return false;
        }
        
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            return false;
        }
        
        $store_name = '';
        $store_url = '';
        
        // WCFM
        if (function_exists('wcfm_get_vendor_store_name')) {
            $store_name = wcfm_get_vendor_store_name($vendor_id);
            $store_url = wcfm_get_vendor_store_url($vendor_id);
        } 
        // Dokan
        elseif (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($user_id);
            $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : '';
            $store_url = function_exists('dokan_get_store_url') ? dokan_get_store_url($user_id) : '';
        } 
        // WC Vendors
        elseif (function_exists('get_user_meta')) {
            $store_name = get_user_meta($user_id, 'pv_shop_name', true);
            $store_url = class_exists('WCV_Vendors') ? WCV_Vendors::get_vendor_shop_page($user_id) : '';
        }
        
        // Default to admin
        if (!$store_name && user_can($user_id, 'manage_woocommerce')) {
            $store_name = get_option('blogname');
            $store_url = get_site_url();
        }
        
        return array(
            'vendor_id' => $vendor_id,
            'user_id' => $user_id,
            'store_name' => $store_name,
            'store_url' => $store_url,
        );
    }
}