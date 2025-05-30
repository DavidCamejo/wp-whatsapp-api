<?php
/**
 * WPWA Customer Manager Class
 *
 * Handles customer management for WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Customer Manager
 */
class WPWA_Customer_Manager {
    /**
     * Customer table name
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpwa_customers';
        
        // Only hook table creation during normal operation, not during plugin activation
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            // Use plugins_loaded instead of init to ensure dependencies are loaded first
            add_action('plugins_loaded', array($this, 'maybe_create_tables'), 20);
        }
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Customer management hooks
        add_action('woocommerce_checkout_update_order_meta', array($this, 'maybe_link_whatsapp_customer'), 10, 2);
        add_action('wpwa_process_incoming_order', array($this, 'process_customer_data'), 5);
        
        // Admin hooks
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_whatsapp_info_to_order'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wpwa_admin_send_customer_message', array($this, 'ajax_send_customer_message'));
        add_action('wp_ajax_wpwa_vendor_get_customer_info', array($this, 'ajax_get_customer_info'));
    }
    
    /**
     * Create necessary database tables if they don't exist
     */
    public function maybe_create_tables() {
        if (get_option('wpwa_customer_db_version') === '1.0') {
            return; // Already created
        }
        
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            wc_customer_id BIGINT(20) UNSIGNED NULL,
            session_id VARCHAR(100) NOT NULL,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            opt_in TINYINT(1) NOT NULL DEFAULT 1,
            opt_in_date DATETIME NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY phone_session (phone, session_id),
            KEY phone (phone),
            KEY wc_customer_id (wc_customer_id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        update_option('wpwa_customer_db_version', '1.0');
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wpwa/v1', '/customers', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_customer_update'),
            'permission_callback' => array($this, 'verify_api_permission')
        ));
        
        register_rest_route('wpwa/v1', '/customers/(?P<phone>[\w\d\+]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer'),
            'permission_callback' => array($this, 'verify_api_permission')
        ));
    }
    
    /**
     * Verify API permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool Whether the permission is granted
     */
    public function verify_api_permission($request) {
        $api_key = $request->get_header('X-WPWA-API-Key');
        
        if (empty($api_key)) {
            return false;
        }
        
        $stored_api_key = get_option('wpwa_api_key');
        
        return $stored_api_key && hash_equals($stored_api_key, $api_key);
    }
    
    /**
     * Handle customer update request
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_customer_update($request) {
        $params = $request->get_json_params();
        
        if (empty($params['phone']) || empty($params['session_id'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters'
            ), 400);
        }
        
        $phone = sanitize_text_field($params['phone']);
        $session_id = sanitize_text_field($params['session_id']);
        
        // Optional customer data
        $first_name = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $metadata = isset($params['metadata']) && is_array($params['metadata']) ? $params['metadata'] : array();
        
        // Save customer
        $result = $this->save_customer($phone, $session_id, array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'metadata' => $metadata
        ));
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to save customer data'
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Customer data updated successfully',
            'customer_id' => $result
        ), 200);
    }
    
    /**
     * Get customer data
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_customer($request) {
        $phone = $request->get_param('phone');
        
        if (empty($phone)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing phone parameter'
            ), 400);
        }
        
        $session_id = $request->get_param('session_id');
        
        // Get customer data
        $customer = $this->get_customer_data($phone, $session_id);
        
        if ($customer === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Customer not found'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'customer' => $customer
        ), 200);
    }
    
    /**
     * Save customer data
     *
     * @param string $phone Customer phone
     * @param string $session_id Session ID
     * @param array $data Customer data
     * @return bool|int False on failure, customer ID on success
     */
    public function save_customer($phone, $session_id, $data = array()) {
        global $wpdb;
        
        // Format phone number (remove any non-numeric characters except +)
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Check if customer exists
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE phone = %s AND session_id = %s",
            $phone,
            $session_id
        ), ARRAY_A);
        
        $update_data = array(
            'last_activity' => current_time('mysql')
        );
        
        // Add optional data if provided
        if (!empty($data['first_name'])) {
            $update_data['first_name'] = $data['first_name'];
        }
        
        if (!empty($data['last_name'])) {
            $update_data['last_name'] = $data['last_name'];
        }
        
        if (isset($data['wc_customer_id']) && $data['wc_customer_id'] > 0) {
            $update_data['wc_customer_id'] = $data['wc_customer_id'];
        }
        
        if (isset($data['opt_in'])) {
            $update_data['opt_in'] = $data['opt_in'] ? 1 : 0;
            if ($data['opt_in']) {
                $update_data['opt_in_date'] = current_time('mysql');
            }
        }
        
        if (!empty($data['metadata'])) {
            if ($customer && !empty($customer['metadata'])) {
                $existing_metadata = maybe_unserialize($customer['metadata']);
                $new_metadata = array_merge($existing_metadata ?: array(), $data['metadata']);
            } else {
                $new_metadata = $data['metadata'];
            }
            $update_data['metadata'] = maybe_serialize($new_metadata);
        }
        
        if ($customer) {
            // Update existing customer
            $result = $wpdb->update(
                $this->table_name,
                $update_data,
                array(
                    'id' => $customer['id']
                ),
                null,
                array('%d')
            );
            
            return $result !== false ? $customer['id'] : false;
        } else {
            // Create new customer
            $insert_data = array_merge(array(
                'phone' => $phone,
                'session_id' => $session_id,
                'created_at' => current_time('mysql')
            ), $update_data);
            
            $result = $wpdb->insert(
                $this->table_name,
                $insert_data
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Get customer data
     *
     * @param string $phone Customer phone
     * @param string $session_id Session ID (optional)
     * @return array|bool Customer data or false if not found
     */
    public function get_customer_data($phone, $session_id = null) {
        global $wpdb;
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        $query = "SELECT * FROM {$this->table_name} WHERE phone = %s";
        $params = array($phone);
        
        if ($session_id) {
            $query .= " AND session_id = %s";
            $params[] = $session_id;
        }
        
        $query .= " ORDER BY last_activity DESC LIMIT 1";
        
        $customer = $wpdb->get_row($wpdb->prepare($query, $params), ARRAY_A);
        
        if (!$customer) {
            return false;
        }
        
        // Update last activity
        $wpdb->update(
            $this->table_name,
            array('last_activity' => current_time('mysql')),
            array('id' => $customer['id']),
            array('%s'),
            array('%d')
        );
        
        // Process metadata
        $metadata = !empty($customer['metadata']) ? maybe_unserialize($customer['metadata']) : array();
        
        // Get WooCommerce customer data if linked
        $wc_customer_data = array();
        if (!empty($customer['wc_customer_id'])) {
            $wc_customer = new WC_Customer($customer['wc_customer_id']);
            if ($wc_customer && $wc_customer->get_id()) {
                $wc_customer_data = array(
                    'id' => $wc_customer->get_id(),
                    'email' => $wc_customer->get_email(),
                    'first_name' => $wc_customer->get_first_name(),
                    'last_name' => $wc_customer->get_last_name(),
                    'billing_address' => array(
                        'address_1' => $wc_customer->get_billing_address_1(),
                        'address_2' => $wc_customer->get_billing_address_2(),
                        'city' => $wc_customer->get_billing_city(),
                        'state' => $wc_customer->get_billing_state(),
                        'postcode' => $wc_customer->get_billing_postcode(),
                        'country' => $wc_customer->get_billing_country()
                    )
                );
            }
        }
        
        return array(
            'id' => $customer['id'],
            'phone' => $customer['phone'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'full_name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'session_id' => $customer['session_id'],
            'wc_customer' => !empty($wc_customer_data) ? $wc_customer_data : null,
            'opt_in' => (bool)$customer['opt_in'],
            'opt_in_date' => $customer['opt_in_date'],
            'metadata' => $metadata,
            'last_activity' => $customer['last_activity'],
            'created_at' => $customer['created_at']
        );
    }
    
    /**
     * Process customer data from WhatsApp order
     *
     * @param array $data Order data from WhatsApp
     */
    public function process_customer_data($data) {
        if (empty($data['customer']) || empty($data['session_id'])) {
            return;
        }
        
        $customer = $data['customer'];
        $phone = isset($customer['phone']) ? $customer['phone'] : '';
        
        if (empty($phone)) {
            return;
        }
        
        // Save customer data
        $this->save_customer($phone, $data['session_id'], array(
            'first_name' => isset($customer['first_name']) ? $customer['first_name'] : '',
            'last_name' => isset($customer['last_name']) ? $customer['last_name'] : '',
            'metadata' => array(
                'last_order_date' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Maybe link WhatsApp customer to WooCommerce customer
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted checkout data
     */
    public function maybe_link_whatsapp_customer($order_id, $posted_data) {
        // Check if this is a WhatsApp order
        $is_whatsapp_order = get_post_meta($order_id, '_wpwa_order', true) === 'yes';
        
        if (!$is_whatsapp_order) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get phone and session ID
        $phone = $order->get_billing_phone();
        $session_id = get_post_meta($order_id, '_wpwa_session_id', true);
        
        if (empty($phone) || empty($session_id)) {
            return;
        }
        
        // Get customer ID if available
        $customer_id = $order->get_customer_id();
        
        // Update WhatsApp customer record
        $this->save_customer($phone, $session_id, array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'wc_customer_id' => $customer_id,
            'metadata' => array(
                'last_order_id' => $order_id,
                'last_order_date' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Add WhatsApp info to order admin page
     *
     * @param WC_Order $order Order object
     */
    public function add_whatsapp_info_to_order($order) {
        // Check if this is a WhatsApp order
        $is_whatsapp_order = get_post_meta($order->get_id(), '_wpwa_order', true) === 'yes';
        
        if (!$is_whatsapp_order) {
            return;
        }
        
        $session_id = get_post_meta($order->get_id(), '_wpwa_session_id', true);
        $vendor_id = get_post_meta($order->get_id(), '_wpwa_vendor_id', true);
        
        echo '<div class="wpwa-order-info">';
        echo '<h4>' . __('WhatsApp Order', 'wp-whatsapp-api') . '</h4>';
        
        // Get customer info
        $phone = $order->get_billing_phone();
        $customer = $this->get_customer_data($phone, $session_id);
        
        if ($customer) {
            echo '<p><strong>' . __('Customer WhatsApp:', 'wp-whatsapp-api') . '</strong> ' . esc_html($phone) . '</p>';
            
            // Check if current user can send message
            $can_send = false;
            $current_user_id = get_current_user_id();
            
            if (current_user_can('manage_woocommerce') || $current_user_id == $vendor_id) {
                $can_send = true;
            }
            
            if ($can_send) {
                echo '<div class="wpwa-send-message">';
                echo '<textarea id="wpwa_customer_message" rows="4" placeholder="' . esc_attr__('Type a message to send to this customer...', 'wp-whatsapp-api') . '"></textarea>';
                echo '<button type="button" class="button" id="wpwa_send_customer_message" data-phone="' . esc_attr($phone) . '" data-session="' . esc_attr($session_id) . '">' . __('Send WhatsApp Message', 'wp-whatsapp-api') . '</button>';
                echo '<div id="wpwa_message_result"></div>';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        global $post_type;
        
        // Only on order pages
        if ($post_type !== 'shop_order') {
            return;
        }
        
        wp_enqueue_script(
            'wpwa-admin-customer',
            plugins_url('assets/js/admin-customer.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('wpwa-admin-customer', 'wpwa', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_admin_nonce'),
            'i18n' => array(
                'send_message' => __('Send Message', 'wp-whatsapp-api'),
                'sending' => __('Sending...', 'wp-whatsapp-api'),
                'message_required' => __('Please enter a message', 'wp-whatsapp-api'),
            )
        ));
    }
    
    /**
     * AJAX handler for sending customer message
     */
    public function ajax_send_customer_message() {
        check_ajax_referer('wpwa_admin_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (empty($message) || empty($phone) || empty($session_id)) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters', 'wp-whatsapp-api')
            ));
        }
        
        // Get vendor ID from session
        $vendor_id = $this->get_vendor_id_from_session($session_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array(
                'message' => __('Invalid session', 'wp-whatsapp-api')
            ));
        }
        
        // Check permission
        $current_user_id = get_current_user_id();
        if (!current_user_can('manage_woocommerce') && $current_user_id != $vendor_id) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'wp-whatsapp-api')
            ));
        }
        
        // Send message
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array(
                'message' => __('API client not available', 'wp-whatsapp-api')
            ));
        }
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Send message via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $session_id . '/messages/send', array(
            'recipient' => $phone,
            'message' => $message
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Message sent successfully', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * AJAX handler for getting customer info
     */
    public function ajax_get_customer_info() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (empty($phone)) {
            wp_send_json_error(array(
                'message' => __('Phone number is required', 'wp-whatsapp-api')
            ));
        }
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array(
                'message' => __('Invalid vendor', 'wp-whatsapp-api')
            ));
        }
        
        // If session ID is not provided, get it from vendor
        if (empty($session_id)) {
            $session_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        }
        
        if (empty($session_id)) {
            wp_send_json_error(array(
                'message' => __('No active session found', 'wp-whatsapp-api')
            ));
        }
        
        // Get customer info
        $customer = $this->get_customer_data($phone, $session_id);
        
        if (!$customer) {
            // Create basic customer record
            $customer_id = $this->save_customer($phone, $session_id);
            
            if ($customer_id) {
                $customer = $this->get_customer_data($phone, $session_id);
            }
            
            if (!$customer) {
                wp_send_json_error(array(
                    'message' => __('Failed to retrieve customer information', 'wp-whatsapp-api')
                ));
                return;
            }
        }
        
        // Get recent orders
        $args = array(
            'customer' => $phone,
            'limit' => 5,
            'return' => 'ids',
        );
        
        $recent_orders = wc_get_orders($args);
        $orders = array();
        
        foreach ($recent_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[] = array(
                    'id' => $order->get_id(),
                    'number' => $order->get_order_number(),
                    'date' => $order->get_date_created()->date_i18n(get_option('date_format')),
                    'status' => $order->get_status(),
                    'total' => strip_tags($order->get_formatted_order_total()),
                    'url' => $order->get_edit_order_url()
                );
            }
        }
        
        wp_send_json_success(array(
            'customer' => $customer,
            'orders' => $orders
        ));
    }
    
    /**
     * Get vendor ID from session ID
     *
     * @param string $session_id Session ID
     * @return int|null Vendor ID or null if not found
     */
    private function get_vendor_id_from_session($session_id) {
        global $wpdb;
        
        $vendor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = 'wpwa_session_client_id'
            AND meta_value = %s",
            $session_id
        ));
        
        return $vendor_id ? absint($vendor_id) : null;
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
}