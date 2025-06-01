<?php
/**
 * WPWA AJAX Handler Frontend Class
 *
 * Handles frontend AJAX requests for the WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA AJAX Handler Frontend
 */
class WPWA_AJAX_Handler_Frontend {
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX actions specifically for frontend use
        add_action('wp_ajax_nopriv_wpwa_frontend_action', array($this, 'ajax_frontend_public_action'));
        add_action('wp_ajax_wpwa_frontend_action', array($this, 'ajax_frontend_public_action'));
        
        // Frontend admin panel actions (only for logged in users)
        add_action('wp_ajax_wpwa_frontend_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_wpwa_generate_api_key', array($this, 'generate_api_key'));
        add_action('wp_ajax_wpwa_validate_api_credentials', array($this, 'validate_api_credentials'));
        add_action('wp_ajax_wpwa_generate_jwt_secret', array($this, 'generate_jwt_secret'));
        add_action('wp_ajax_wpwa_get_jwt_secret', array($this, 'get_jwt_secret'));
        add_action('wp_ajax_wpwa_admin_get_sessions', array($this, 'get_sessions'));
        add_action('wp_ajax_wpwa_admin_get_logs', array($this, 'get_logs'));
        add_action('wp_ajax_wpwa_admin_clear_logs', array($this, 'clear_logs'));
    }
    
    /**
     * Example AJAX handler for frontend public action
     * This is a sample method that can be expanded when needed
     */
    public function ajax_frontend_public_action() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        // Process the request
        $result = array(
            'success' => true,
            'message' => __('Action completed successfully', 'wp-whatsapp-api')
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * Save settings for the frontend admin panel
     */
    public function save_settings() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Get the sanitized form data
        $api_url = isset($_POST['wpwa_api_url']) ? sanitize_url($_POST['wpwa_api_url']) : '';
        $api_key = isset($_POST['wpwa_api_key']) ? sanitize_text_field($_POST['wpwa_api_key']) : '';
        $jwt_secret = isset($_POST['wpwa_jwt_secret']) ? sanitize_text_field($_POST['wpwa_jwt_secret']) : '';
        $debug_mode = isset($_POST['wpwa_debug_mode']) ? (int)$_POST['wpwa_debug_mode'] : 0;
        $connection_timeout = isset($_POST['wpwa_connection_timeout']) ? (int)$_POST['wpwa_connection_timeout'] : 30;
        $max_retries = isset($_POST['wpwa_max_retries']) ? (int)$_POST['wpwa_max_retries'] : 3;
        
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
        
        $this->log('Settings updated from frontend admin panel');
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Generate API key
     */
    public function generate_api_key() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Generate a random API key
        $api_key = wp_generate_password(32, false);
        
        $this->log('New API key generated from frontend admin panel');
        
        wp_send_json_success(array(
            'api_key' => $api_key,
            'message' => __('API Key generated successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Validate API credentials
     */
    public function validate_api_credentials() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        $api_url = isset($_POST['api_url']) ? sanitize_url($_POST['api_url']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(array('message' => __('API URL and API Key are required', 'wp-whatsapp-api')));
            return;
        }
        
        // Call the API client to validate credentials
        if (class_exists('WPWA_API_Client')) {
            $api_client = new WPWA_API_Client($api_url, $api_key);
            $response = $api_client->test_connection();
            
            if ($response['success']) {
                $this->log('API credentials validated successfully from frontend admin panel');
                wp_send_json_success(array('message' => __('API credentials are valid', 'wp-whatsapp-api')));
            } else {
                $this->log('API credential validation failed from frontend admin panel: ' . $response['message'], 'error');
                wp_send_json_error(array('message' => $response['message']));
            }
        } else {
            wp_send_json_error(array('message' => __('API Client class not found', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Generate JWT secret
     */
    public function generate_jwt_secret() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Generate a random JWT secret
        $jwt_secret = wp_generate_password(64, true, true);
        
        // Save the JWT secret
        update_option('wpwa_jwt_secret', $jwt_secret);
        
        $this->log('New JWT secret generated from frontend admin panel');
        
        wp_send_json_success(array(
            'jwt_secret' => $jwt_secret,
            'message' => __('JWT Secret generated successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Get JWT secret
     */
    public function get_jwt_secret() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Get the JWT secret
        $jwt_secret = get_option('wpwa_jwt_secret', '');
        
        wp_send_json_success(array(
            'jwt_secret' => $jwt_secret
        ));
    }
    
    /**
     * Get sessions
     */
    public function get_sessions() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        // Get sessions using the session manager if available
        if (class_exists('WPWA_Vendor_Session_Manager')) {
            $session_manager = new WPWA_Vendor_Session_Manager();
            $sessions = $session_manager->get_all_sessions();
            
            ob_start();
            if (!empty($sessions)) {
                echo '<table class="wpwa-sessions-table">';
                echo '<thead><tr><th>Vendor ID</th><th>Session ID</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($sessions as $session) {
                    $status_class = ($session['status'] === 'active') ? 'wpwa-status-active' : 'wpwa-status-inactive';
                    
                    echo '<tr>';
                    echo '<td>' . esc_html($session['vendor_id']) . '</td>';
                    echo '<td>' . esc_html($session['session_id']) . '</td>';
                    echo '<td><span class="wpwa-status ' . esc_attr($status_class) . '">' . esc_html($session['status']) . '</span></td>';
                    echo '<td>' . esc_html($session['created_at']) . '</td>';
                    echo '<td>';
                    echo '<button class="wpwa-button wpwa-button-small wpwa-refresh-session" data-session-id="' . esc_attr($session['session_id']) . '">Refresh</button> ';
                    echo '<button class="wpwa-button wpwa-button-small wpwa-button-danger wpwa-delete-session" data-session-id="' . esc_attr($session['session_id']) . '">Delete</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<div class="wpwa-info">No active sessions found.</div>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error(array('message' => __('Session Manager class not found', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Get logs
     */
    public function get_logs() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        global $wpwa_logger;
        
        if ($wpwa_logger) {
            $logs = $wpwa_logger->get_logs_for_admin(100); // Get the latest 100 logs
            wp_send_json_success(array('logs' => $logs));
        } else {
            wp_send_json_error(array('message' => __('Logger not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-whatsapp-api')));
            return;
        }
        
        global $wpwa_logger;
        
        if ($wpwa_logger) {
            $result = $wpwa_logger->clean_logs();
            
            if ($result) {
                $this->log('Logs cleared from frontend admin panel');
                wp_send_json_success(array('message' => __('Logs cleared successfully', 'wp-whatsapp-api')));
            } else {
                wp_send_json_error(array('message' => __('Failed to clear logs', 'wp-whatsapp-api')));
            }
        } else {
            wp_send_json_error(array('message' => __('Logger not initialized', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Log activity for debugging
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $level = 'info') {
        global $wpwa_logger;
        
        if ($wpwa_logger) {
            $wpwa_logger->log($message, $level);
        }
    }
}