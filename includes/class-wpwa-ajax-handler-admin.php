<?php
/**
 * WPWA AJAX Handler Admin Class
 *
 * Handles admin AJAX requests for the WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA AJAX Handler Admin
 */
class WPWA_AJAX_Handler_Admin {
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX actions for admin area
        add_action('wp_ajax_wpwa_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_wpwa_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_wpwa_generate_api_key', array($this, 'generate_api_key'));
        add_action('wp_ajax_wpwa_generate_jwt_secret', array($this, 'generate_jwt_secret'));
        add_action('wp_ajax_wpwa_get_sessions', array($this, 'get_sessions'));
        add_action('wp_ajax_wpwa_delete_session', array($this, 'delete_session'));
        add_action('wp_ajax_wpwa_refresh_session', array($this, 'refresh_session'));
        add_action('wp_ajax_wpwa_get_logs', array($this, 'get_logs'));
        add_action('wp_ajax_wpwa_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_wpwa_toggle_debug', array($this, 'toggle_debug_mode'));
        add_action('wp_ajax_wpwa_sync_products', array($this, 'sync_products'));
    }
    
    /**
     * Save plugin settings
     */
    public function save_settings() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Get the sanitized form data
        $api_url = isset($_POST['api_url']) ? sanitize_url($_POST['api_url']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $jwt_secret = isset($_POST['jwt_secret']) ? sanitize_text_field($_POST['jwt_secret']) : '';
        $debug_mode = isset($_POST['debug_mode']) ? (int)$_POST['debug_mode'] : 0;
        $connection_timeout = isset($_POST['connection_timeout']) ? (int)$_POST['connection_timeout'] : 30;
        $max_retries = isset($_POST['max_retries']) ? (int)$_POST['max_retries'] : 3;
        
        // Save the settings
        update_option('wpwa_api_url', $api_url);
        update_option('wpwa_api_key', $api_key);
        update_option('wpwa_debug_mode', $debug_mode);
        update_option('wpwa_connection_timeout', $connection_timeout);
        update_option('wpwa_max_retries', $max_retries);
        
        // Only save JWT secret if it's not the masked value
        if ($jwt_secret !== '••••••••••••••••' && !empty($jwt_secret)) {
            update_option('wpwa_jwt_secret', $jwt_secret);
        }
        
        $this->log('Settings updated from admin panel');
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Get API client instance
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->api_client) {
            $response = $wp_whatsapp_api->api_client->test_connection();
            
            if ($response['success']) {
                $this->log('API connection test successful');
                wp_send_json_success(array('message' => __('Connection successful', 'wp-whatsapp-api')));
            } else {
                $this->log('API connection test failed: ' . $response['message'], 'error');
                wp_send_json_error(array('message' => $response['message']));
            }
        } else {
            wp_send_json_error(array('message' => __('API client not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Generate API key
     */
    public function generate_api_key() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Generate a random API key
        $api_key = wp_generate_password(32, false);
        
        $this->log('New API key generated from admin panel');
        
        wp_send_json_success(array(
            'api_key' => $api_key,
            'message' => __('API Key generated successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Generate JWT secret
     */
    public function generate_jwt_secret() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Generate a random JWT secret (64 characters for stronger security)
        $jwt_secret = wp_generate_password(64, true, true);
        
        // Save the JWT secret
        update_option('wpwa_jwt_secret', $jwt_secret);
        
        $this->log('New JWT secret generated from admin panel');
        
        wp_send_json_success(array(
            'jwt_secret' => $jwt_secret,
            'message' => __('JWT Secret generated successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Get vendor sessions
     */
    public function get_sessions() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->vendor_session_manager) {
            $sessions = $wp_whatsapp_api->vendor_session_manager->get_all_sessions();
            
            $response = array(
                'sessions' => $sessions
            );
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => __('Session manager not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Delete vendor session
     */
    public function delete_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID is required', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->vendor_session_manager) {
            $result = $wp_whatsapp_api->vendor_session_manager->delete_session($session_id);
            
            if ($result) {
                $this->log('Session deleted: ' . $session_id);
                wp_send_json_success(array('message' => __('Session deleted successfully', 'wp-whatsapp-api')));
            } else {
                $this->log('Failed to delete session: ' . $session_id, 'error');
                wp_send_json_error(array('message' => __('Failed to delete session', 'wp-whatsapp-api')));
            }
        } else {
            wp_send_json_error(array('message' => __('Session manager not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Refresh vendor session
     */
    public function refresh_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID is required', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->vendor_session_manager) {
            $result = $wp_whatsapp_api->vendor_session_manager->refresh_session($session_id);
            
            if ($result) {
                $this->log('Session refreshed: ' . $session_id);
                wp_send_json_success(array('message' => __('Session refreshed successfully', 'wp-whatsapp-api')));
            } else {
                $this->log('Failed to refresh session: ' . $session_id, 'error');
                wp_send_json_error(array('message' => __('Failed to refresh session', 'wp-whatsapp-api')));
            }
        } else {
            wp_send_json_error(array('message' => __('Session manager not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Get system logs
     */
    public function get_logs() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->logger) {
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
            $logs = $wp_whatsapp_api->logger->get_logs_for_admin($limit);
            
            wp_send_json_success(array(
                'logs' => $logs
            ));
        } else {
            wp_send_json_error(array('message' => __('Logger not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Clear system logs
     */
    public function clear_logs() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->logger) {
            $result = $wp_whatsapp_api->logger->clean_logs();
            
            if ($result) {
                $this->log('Logs cleared from admin panel');
                wp_send_json_success(array('message' => __('Logs cleared successfully', 'wp-whatsapp-api')));
            } else {
                wp_send_json_error(array('message' => __('Failed to clear logs', 'wp-whatsapp-api')));
            }
        } else {
            wp_send_json_error(array('message' => __('Logger not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Toggle debug mode
     */
    public function toggle_debug_mode() {
        check_ajax_referer('wpwa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        
        update_option('wpwa_debug_mode', $enabled);
        
        $this->log('Debug mode ' . ($enabled ? 'enabled' : 'disabled') . ' from admin panel');
        
        wp_send_json_success(array(
            'enabled' => (bool)$enabled,
            'message' => $enabled ? 
                __('Debug mode enabled', 'wp-whatsapp-api') : 
                __('Debug mode disabled', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Sync products for vendors
     */
    public function sync_products() {
        check_ajax_referer('wpwa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Vendor ID is required', 'wp-whatsapp-api')));
            return;
        }
        
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->product_sync_manager) {
            $result = $wp_whatsapp_api->product_sync_manager->sync_vendor_products($vendor_id);
            
            if ($result['success']) {
                $this->log("Products synced for vendor #$vendor_id: " . $result['count'] . ' products');
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('%d products synced successfully', 'wp-whatsapp-api'),
                        $result['count']
                    ),
                    'products' => $result['products']
                ));
            } else {
                $this->log("Product sync failed for vendor #$vendor_id: " . $result['message'], 'error');
                wp_send_json_error(array('message' => $result['message']));
            }
        } else {
            wp_send_json_error(array('message' => __('Product sync manager not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Log activity for debugging
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $level = 'info') {
        global $wp_whatsapp_api;
        
        if ($wp_whatsapp_api && $wp_whatsapp_api->logger) {
            $wp_whatsapp_api->logger->log($message, $level);
        }
    }
}