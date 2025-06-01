<?php
/**
 * WPWA AJAX Handler Class
 *
 * Handles AJAX requests for the WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA AJAX Handler
 */
class WPWA_AJAX_Handler {
    /**
     * Constructor
     */
    public function __construct() {
        // Vendor AJAX actions
        add_action('wp_ajax_wpwa_toggle_whatsapp', array($this, 'ajax_toggle_whatsapp'));
        add_action('wp_ajax_wpwa_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_wpwa_check_session', array($this, 'ajax_check_session'));
        add_action('wp_ajax_wpwa_disconnect_session', array($this, 'ajax_disconnect_session'));
        add_action('wp_ajax_wpwa_send_test_message', array($this, 'ajax_send_test_message'));
        add_action('wp_ajax_wpwa_get_recent_orders', array($this, 'ajax_get_recent_orders'));
        
        // Admin AJAX actions
        add_action('wp_ajax_wpwa_admin_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_wpwa_admin_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_wpwa_generate_jwt_secret', array($this, 'ajax_generate_jwt_secret'));
        
        // Frontend admin panel actions
        add_action('wp_ajax_wpwa_frontend_save_settings', array($this, 'ajax_frontend_save_settings'));
        add_action('wp_ajax_wpwa_validate_api_credentials', array($this, 'ajax_validate_api_credentials'));
        add_action('wp_ajax_wpwa_generate_api_key', array($this, 'ajax_generate_api_key'));
    }
    
    /**
     * AJAX handler for toggling WhatsApp integration
     */
    public function ajax_toggle_whatsapp() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        update_user_meta($vendor_id, 'wpwa_enable_whatsapp', $enabled ? '1' : '0');
        
        wp_send_json_success(array(
            'message' => $enabled 
                ? __('WhatsApp integration enabled', 'wp-whatsapp-api')
                : __('WhatsApp integration disabled', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * AJAX handler for creating a WhatsApp session
     */
    public function ajax_create_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        $session_name = isset($_POST['session_name']) ? sanitize_text_field($_POST['session_name']) : '';
        
        if (empty($session_name)) {
            wp_send_json_error(array('message' => __('Session name is required', 'wp-whatsapp-api')));
        }
        
        // Check if vendor already has a session
        $existing_session = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if ($existing_session) {
            wp_send_json_error(array(
                'message' => __('You already have an active session. Please disconnect it first.', 'wp-whatsapp-api')
            ));
        }
        
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        // Create session via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/create', array(
            'session_name' => $session_name,
            'vendor_id' => $vendor_id,
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        if (empty($response['client_id']) || empty($response['qr_code'])) {
            wp_send_json_error(array('message' => __('Failed to create session', 'wp-whatsapp-api')));
        }
        
        // Store session data in user meta
        update_user_meta($vendor_id, 'wpwa_session_client_id', $response['client_id']);
        update_user_meta($vendor_id, 'wpwa_session_name', $session_name);
        update_user_meta($vendor_id, 'wpwa_session_created', current_time('mysql'));
        update_user_meta($vendor_id, 'wpwa_session_status', 'qr_ready');
        
        wp_send_json_success(array(
            'client_id' => $response['client_id'],
            'qr_code' => $response['qr_code']
        ));
    }
    
    /**
     * AJAX handler for checking session status
     */
    public function ajax_check_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get session ID
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        // Check session status via API
        $response = $wp_whatsapp_api->api_client->get('/sessions/' . $client_id . '/status');
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        if (empty($response['status'])) {
            wp_send_json_error(array('message' => __('Failed to check session status', 'wp-whatsapp-api')));
        }
        
        // Update session status in user meta
        update_user_meta($vendor_id, 'wpwa_session_status', $response['status']);
        
        // Get status label
        $status_labels = array(
            'initializing' => __('Initializing...', 'wp-whatsapp-api'),
            'qr_ready' => __('QR Code Ready - Please scan with your phone', 'wp-whatsapp-api'),
            'authenticated' => __('Authenticated', 'wp-whatsapp-api'),
            'ready' => __('Connected', 'wp-whatsapp-api'),
            'disconnected' => __('Disconnected', 'wp-whatsapp-api'),
            'failed' => __('Connection Failed', 'wp-whatsapp-api')
        );
        
        $status_label = isset($status_labels[$response['status']]) 
            ? $status_labels[$response['status']] 
            : $response['status'];
        
        wp_send_json_success(array(
            'status' => $response['status'],
            'status_label' => $status_label
        ));
    }
    
    /**
     * AJAX handler for disconnecting a session
     */
    public function ajax_disconnect_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get session ID
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        // Disconnect session via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/disconnect');
        
        // Delete session data from user meta
        delete_user_meta($vendor_id, 'wpwa_session_client_id');
        delete_user_meta($vendor_id, 'wpwa_session_name');
        delete_user_meta($vendor_id, 'wpwa_session_created');
        delete_user_meta($vendor_id, 'wpwa_session_status');
        
        wp_send_json_success(array(
            'message' => __('Session disconnected successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * AJAX handler for sending a test message
     */
    public function ajax_send_test_message() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get session ID
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        // Get phone and message
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Phone number is required', 'wp-whatsapp-api')));
        }
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message is required', 'wp-whatsapp-api')));
        }
        
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        // Format phone number (remove any non-numeric characters)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Send message via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/messages/send', array(
            'recipient' => $phone,
            'message' => $message
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Message sent successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * AJAX handler for getting recent orders
     */
    public function ajax_get_recent_orders() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get recent WhatsApp orders for this vendor
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'meta_key' => '_wpwa_vendor_id',
            'meta_value' => $vendor_id,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $orders = get_posts($args);
        
        if (empty($orders)) {
            wp_send_json_success(array(
                'html' => '<p>' . __('No recent orders from WhatsApp', 'wp-whatsapp-api') . '</p>'
            ));
            return;
        }
        
        ob_start();
        ?>
        <table class="wpwa-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Order', 'wp-whatsapp-api'); ?></th>
                    <th><?php _e('Date', 'wp-whatsapp-api'); ?></th>
                    <th><?php _e('Status', 'wp-whatsapp-api'); ?></th>
                    <th><?php _e('Total', 'wp-whatsapp-api'); ?></th>
                    <th><?php _e('Actions', 'wp-whatsapp-api'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $post) : ?>
                    <?php $order = wc_get_order($post->ID); ?>
                    <?php if (!$order) continue; ?>
                    <tr>
                        <td>
                            <?php echo esc_html('#' . $order->get_order_number()); ?>
                            <div><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></div>
                        </td>
                        <td>
                            <?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
                        </td>
                        <td>
                            <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($order->get_edit_order_url()); ?>" target="_blank">
                                <?php _e('View', 'wp-whatsapp-api'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order&wpwa_orders=1')); ?>" class="wpwa-view-all-link">
            <?php _e('View all WhatsApp orders', 'wp-whatsapp-api'); ?>
        </a>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for getting admin logs
     */
    public function ajax_get_logs() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        global $wpwa_logger;
        
        if (!$wpwa_logger) {
            wp_send_json_error(array('message' => __('Logger not available', 'wp-whatsapp-api')));
        }
        
        $logs = $wpwa_logger->get_logs_for_admin(100);
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    /**
     * AJAX handler for clearing admin logs
     */
    public function ajax_clear_logs() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        global $wpwa_logger;
        
        if (!$wpwa_logger) {
            wp_send_json_error(array('message' => __('Logger not available', 'wp-whatsapp-api')));
        }
        
        $wpwa_logger->clear_logs();
        
        wp_send_json_success(array('message' => __('Logs cleared successfully', 'wp-whatsapp-api')));
    }
    
    /**
     * AJAX handler for generating a new JWT Secret
     */
    public function ajax_generate_jwt_secret() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->auth_manager)) {
            // If global auth manager is not available, create a temporary instance
            if (class_exists('WPWA_Auth_Manager')) {
                $auth_manager = new WPWA_Auth_Manager();
                $new_jwt_secret = $auth_manager->generate_jwt_secret();
            } else {
                // Fallback if class doesn't exist - should never happen, but just in case
                $new_jwt_secret = wp_generate_password(64, true, true);
                update_option('wpwa_jwt_secret', $new_jwt_secret);
            }
        } else {
            // Use the global instance
            $new_jwt_secret = $wp_whatsapp_api->auth_manager->generate_jwt_secret();
        }
        
        // Log this activity
        if ($wp_whatsapp_api && isset($wp_whatsapp_api->logger)) {
            $wp_whatsapp_api->logger->info('New JWT secret generated');
        }
        
        // Generate a test token to validate the secret works properly
        try {
            $test_payload = array(
                'test' => true,
                'iat' => time(),
                'exp' => time() + 60 // 1 minute
            );
            
            // This will throw an exception if something is wrong with the JWT library
            $test_token = \Firebase\JWT\JWT::encode($test_payload, $new_jwt_secret, 'HS256');
            
            // Try decoding it to ensure it works
            $decoded = \Firebase\JWT\JWT::decode(
                $test_token, 
                new \Firebase\JWT\Key($new_jwt_secret, 'HS256')
            );
            
            // Token generation and validation successful
            wp_send_json_success(array(
                'jwt_secret' => $new_jwt_secret,
                'message' => __('JWT Secret generated and verified successfully', 'wp-whatsapp-api')
            ));
        } catch (Exception $e) {
            // Something went wrong with token generation/validation
            wp_send_json_error(array(
                'message' => __('JWT Secret generated but verification failed: ', 'wp-whatsapp-api') . $e->getMessage()
            ));
        }
    }
    
    /**
     * Get vendor ID from user ID
     *
     * @param int $user_id User ID
     * @return int|boolean Vendor ID or false if not found
     */
    private function get_vendor_id($user_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            return wcfm_get_vendor_id_by_user($user_id);
        }
        
        // Dokan (vendor ID is typically user ID)
        if (function_exists('dokan_is_user_seller') && dokan_is_user_seller($user_id)) {
            return $user_id;
        }
        
        // WC Vendors (vendor ID is typically user ID)
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($user_id)) {
            return $user_id;
        }
        
        // Non-marketplace site, use admin as vendor
        if (!class_exists('WCV_Vendors') && !function_exists('wcfm_get_vendor_id_by_user') && !function_exists('dokan_is_user_seller')) {
            if (user_can($user_id, 'manage_woocommerce')) {
                return $user_id;
            }
        }
        
        return false;
    }
    
    /**
     * AJAX handler for frontend save settings
     */
    public function ajax_frontend_save_settings() {
        check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        // Get and sanitize settings
        $api_url = isset($_POST['wpwa_api_url']) ? esc_url_raw($_POST['wpwa_api_url']) : '';
        $api_key = isset($_POST['wpwa_api_key']) ? sanitize_text_field($_POST['wpwa_api_key']) : '';
        $connection_timeout = isset($_POST['wpwa_connection_timeout']) ? absint($_POST['wpwa_connection_timeout']) : 30;
        $max_retries = isset($_POST['wpwa_max_retries']) ? absint($_POST['wpwa_max_retries']) : 3;
        $debug_mode = isset($_POST['wpwa_debug_mode']) ? absint($_POST['wpwa_debug_mode']) : 0;
        $usage_tracking = isset($_POST['wpwa_usage_tracking']) ? absint($_POST['wpwa_usage_tracking']) : 0;
        
        // Validate required fields
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(array('message' => __('API URL and API Key are required', 'wp-whatsapp-api')));
        }
        
        // Validate URL format
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => __('Please enter a valid URL for the API server', 'wp-whatsapp-api')));
        }
        
        // Validate timeout and retries
        if ($connection_timeout < 5 || $connection_timeout > 120) {
            $connection_timeout = 30; // Reset to default if invalid
        }
        
        if ($max_retries < 0 || $max_retries > 10) {
            $max_retries = 3; // Reset to default if invalid
        }
        
        // Save settings
        update_option('wpwa_api_url', $api_url);
        update_option('wpwa_api_key', $api_key);
        update_option('wpwa_connection_timeout', $connection_timeout);
        update_option('wpwa_max_retries', $max_retries);
        update_option('wpwa_debug_mode', $debug_mode);
        update_option('wpwa_usage_tracking', $usage_tracking);
        
        // Log the settings update
        global $wpwa_logger;
        if ($wpwa_logger) {
            $wpwa_logger->info('Settings updated via frontend admin panel');
        }
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'wp-whatsapp-api')
        ));
    }

    /**
     * AJAX handler for validating API credentials
     */
    public function ajax_validate_api_credentials() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        $api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(array('message' => __('API URL and API Key are required', 'wp-whatsapp-api')));
        }
        
        // Create a temporary API client for validation
        if (class_exists('WPWA_API_Client')) {
            $api_client = new WPWA_API_Client($api_url, $api_key);
            
            // Try to connect to the API
            $response = $api_client->get('/status');
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }
            
            // Check for successful connection
            if (!empty($response['status']) && $response['status'] === 'ok') {
                // Log successful connection
                global $wpwa_logger;
                if ($wpwa_logger) {
                    $wpwa_logger->info('API connection validated successfully via frontend admin panel');
                }
                
                wp_send_json_success(array(
                    'message' => __('API connection successful', 'wp-whatsapp-api')
                ));
            }
            
            wp_send_json_error(array('message' => __('API connection failed: Invalid response from API server', 'wp-whatsapp-api')));
        } else {
            wp_send_json_error(array('message' => __('API client class not available', 'wp-whatsapp-api')));
        }
    }

    /**
     * AJAX handler for generating API key
     */
    public function ajax_generate_api_key() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        // Generate a new API key (32 characters)
        $api_key = wp_generate_password(32, false, false);
        
        // Save it to options
        update_option('wpwa_api_key', $api_key);
        
        // Log generation
        global $wpwa_logger;
        if ($wpwa_logger) {
            $wpwa_logger->info('New API key generated via frontend admin panel');
        }
        
        wp_send_json_success(array(
            'api_key' => $api_key,
            'message' => __('API key generated successfully', 'wp-whatsapp-api')
        ));
    }

    /**
     * AJAX handler for getting the JWT secret
     */
    public function ajax_get_jwt_secret() {
        // Check if this is a frontend request which uses different nonce name
        if (isset($_POST['wpwa_frontend_nonce'])) {
            check_ajax_referer('wpwa_frontend_nonce', 'wpwa_frontend_nonce');
        } else {
            check_ajax_referer('wpwa_nonce', 'nonce');
        }
        
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-whatsapp-api')));
        }
        
        $jwt_secret = get_option('wpwa_jwt_secret', '');
        
        wp_send_json_success(array('jwt_secret' => $jwt_secret));
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