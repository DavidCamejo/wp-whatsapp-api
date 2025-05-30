<?php
/**
 * WPWA Order Processor Class
 *
 * Handles processing of orders from WhatsApp
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Order Processor
 */
class WPWA_Order_Processor {
    /**
     * Constructor
     */
    public function __construct() {
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Processing hooks
        add_action('wpwa_process_incoming_order', array($this, 'process_order'), 10, 1);
        
        // Status update webhooks
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_status_to_whatsapp'), 10, 4);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wpwa/v1', '/orders', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_incoming_order'),
            'permission_callback' => array($this, 'verify_api_permission')
        ));
        
        register_rest_route('wpwa/v1', '/orders/(?P<order_id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_order_status_update'),
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
     * Handle incoming order from WhatsApp
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_incoming_order($request) {
        $params = $request->get_json_params();
        
        if (empty($params)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No data received'
            ), 400);
        }
        
        global $wpwa_logger;
        if ($wpwa_logger) {
            $wpwa_logger->log('Received incoming order from WhatsApp: ' . json_encode($params), 'info');
        }
        
        // Schedule processing
        $process = do_action('wpwa_process_incoming_order', $params);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Order received and being processed'
        ), 200);
    }
    
    /**
     * Handle order status update
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_order_status_update($request) {
        $order_id = $request->get_param('order_id');
        $params = $request->get_json_params();
        
        if (empty($order_id) || empty($params['status'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters'
            ), 400);
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Order not found'
            ), 404);
        }
        
        // Verify this is a WhatsApp order
        $is_whatsapp_order = get_post_meta($order_id, '_wpwa_order', true) === 'yes';
        
        if (!$is_whatsapp_order) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Not a WhatsApp order'
            ), 400);
        }
        
        $new_status = sanitize_text_field($params['status']);
        
        // Map WhatsApp status to WooCommerce status
        $wc_status = $this->map_whatsapp_status_to_wc($new_status);
        
        if (!$wc_status) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid status'
            ), 400);
        }
        
        // Update order status
        $order->update_status(
            $wc_status, 
            __('Status updated from WhatsApp', 'wp-whatsapp-api'),
            true
        );
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Order status updated'
        ), 200);
    }
    
    /**
     * Process incoming order
     *
     * @param array $data Order data
     * @return int|bool Order ID if created, false otherwise
     */
    public function process_order($data) {
        global $wpwa_logger;
        
        if (empty($data['session_id']) || empty($data['customer']) || empty($data['items'])) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Incomplete order data received: ' . json_encode($data), 'error');
            }
            return false;
        }
        
        // Get vendor ID from session
        $vendor_id = $this->get_vendor_id_from_session($data['session_id']);
        
        if (!$vendor_id) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Vendor not found for session: ' . $data['session_id'], 'error');
            }
            return false;
        }
        
        // Process customer data
        $customer = $data['customer'];
        $customer_phone = isset($customer['phone']) ? $customer['phone'] : '';
        
        if (empty($customer_phone)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Customer phone not provided', 'error');
            }
            return false;
        }
        
        // Format phone number
        $customer_phone = preg_replace('/[^0-9\+]/', '', $customer_phone);
        
        // Check if customer exists in WooCommerce
        $customer_id = 0;
        $existing_user = $this->find_customer_by_phone($customer_phone);
        
        if ($existing_user) {
            $customer_id = $existing_user->ID;
        }
        
        // Create order
        $order = wc_create_order(array(
            'customer_id' => $customer_id,
            'created_via' => 'whatsapp',
        ));
        
        if (is_wp_error($order)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Failed to create order: ' . $order->get_error_message(), 'error');
            }
            return false;
        }
        
        // Add products to order
        foreach ($data['items'] as $item) {
            if (empty($item['product_id'])) {
                continue;
            }
            
            $product_id = absint($item['product_id']);
            $product = wc_get_product($product_id);
            
            if (!$product || !$product->is_purchasable()) {
                continue;
            }
            
            // Check if the product belongs to this vendor in marketplace
            if ($this->is_marketplace_active()) {
                $product_vendor = $this->get_product_vendor($product_id);
                
                if ($product_vendor != $vendor_id) {
                    continue; // Skip products from other vendors
                }
            }
            
            $quantity = isset($item['quantity']) ? absint($item['quantity']) : 1;
            $variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
            
            // Add product to order
            $order->add_product($product, $quantity, array('variation_id' => $variation_id));
        }
        
        // Set order billing details
        $this->set_order_billing_details($order, $customer);
        
        // Set shipping details if provided
        if (!empty($data['shipping_method'])) {
            $this->set_order_shipping_details($order, $data);
        }
        
        // Set payment method
        $payment_method = !empty($data['payment_method']) ? sanitize_text_field($data['payment_method']) : 'cod';
        $this->set_payment_method($order, $payment_method);
        
        // Add order notes
        $order->add_order_note(
            __('Order created from WhatsApp conversation', 'wp-whatsapp-api'),
            false
        );
        
        if (!empty($data['notes'])) {
            $order->add_order_note(
                __('Customer Notes:', 'wp-whatsapp-api') . ' ' . sanitize_text_field($data['notes']),
                false
            );
        }
        
        // Set order meta
        $order->update_meta_data('_wpwa_order', 'yes');
        $order->update_meta_data('_wpwa_session_id', $data['session_id']);
        $order->update_meta_data('_wpwa_vendor_id', $vendor_id);
        $order->update_meta_data('_wpwa_customer_phone', $customer_phone);
        
        // Add timestamp
        $order->update_meta_data('_wpwa_created_at', current_time('mysql'));
        
        // Calculate totals
        $order->calculate_totals();
        
        // Set initial status
        $order->update_status(
            apply_filters('wpwa_initial_order_status', 'pending'),
            __('Order created from WhatsApp', 'wp-whatsapp-api'),
            true
        );
        
        // Save order
        $order->save();
        
        if ($wpwa_logger) {
            $wpwa_logger->log(
                sprintf(
                    'WhatsApp order created successfully. Order ID: %s, Customer: %s',
                    $order->get_id(),
                    $customer_phone
                ),
                'info'
            );
        }
        
        // Send confirmation message to customer
        $this->send_order_confirmation($order, $data['session_id']);
        
        return $order->get_id();
    }
    
    /**
     * Find WooCommerce customer by phone
     *
     * @param string $phone Phone number
     * @return WP_User|null User object if found, null otherwise
     */
    private function find_customer_by_phone($phone) {
        // Format phone
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Search for user with matching phone
        $query = new WP_User_Query(array(
            'meta_query' => array(
                array(
                    'key' => 'billing_phone',
                    'value' => $phone,
                    'compare' => '='
                )
            ),
            'number' => 1
        ));
        
        $users = $query->get_results();
        
        if (!empty($users)) {
            return $users[0];
        }
        
        return null;
    }
    
    /**
     * Set order billing details
     *
     * @param WC_Order $order Order object
     * @param array $customer Customer data
     */
    private function set_order_billing_details($order, $customer) {
        $billing_address = array(
            'first_name' => isset($customer['first_name']) ? sanitize_text_field($customer['first_name']) : '',
            'last_name' => isset($customer['last_name']) ? sanitize_text_field($customer['last_name']) : '',
            'email' => isset($customer['email']) ? sanitize_email($customer['email']) : '',
            'phone' => isset($customer['phone']) ? sanitize_text_field($customer['phone']) : '',
            'address_1' => isset($customer['address']) ? sanitize_text_field($customer['address']) : '',
            'address_2' => isset($customer['address_2']) ? sanitize_text_field($customer['address_2']) : '',
            'city' => isset($customer['city']) ? sanitize_text_field($customer['city']) : '',
            'state' => isset($customer['state']) ? sanitize_text_field($customer['state']) : '',
            'postcode' => isset($customer['postcode']) ? sanitize_text_field($customer['postcode']) : '',
            'country' => isset($customer['country']) ? sanitize_text_field($customer['country']) : ''
        );
        
        $order->set_address($billing_address, 'billing');
        
        // Also set shipping address if not provided separately
        $order->set_address($billing_address, 'shipping');
    }
    
    /**
     * Set order shipping details
     *
     * @param WC_Order $order Order object
     * @param array $data Order data
     */
    private function set_order_shipping_details($order, $data) {
        // Set shipping method if provided
        if (!empty($data['shipping_method'])) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title(sanitize_text_field($data['shipping_method']['name']));
            
            if (isset($data['shipping_method']['cost'])) {
                $shipping_item->set_total(floatval($data['shipping_method']['cost']));
            }
            
            $order->add_item($shipping_item);
        }
        
        // Set shipping address if different from billing
        if (!empty($data['shipping_address'])) {
            $shipping_address = array(
                'first_name' => isset($data['shipping_address']['first_name']) ? sanitize_text_field($data['shipping_address']['first_name']) : '',
                'last_name' => isset($data['shipping_address']['last_name']) ? sanitize_text_field($data['shipping_address']['last_name']) : '',
                'address_1' => isset($data['shipping_address']['address']) ? sanitize_text_field($data['shipping_address']['address']) : '',
                'address_2' => isset($data['shipping_address']['address_2']) ? sanitize_text_field($data['shipping_address']['address_2']) : '',
                'city' => isset($data['shipping_address']['city']) ? sanitize_text_field($data['shipping_address']['city']) : '',
                'state' => isset($data['shipping_address']['state']) ? sanitize_text_field($data['shipping_address']['state']) : '',
                'postcode' => isset($data['shipping_address']['postcode']) ? sanitize_text_field($data['shipping_address']['postcode']) : '',
                'country' => isset($data['shipping_address']['country']) ? sanitize_text_field($data['shipping_address']['country']) : ''
            );
            
            $order->set_address($shipping_address, 'shipping');
        }
    }
    
    /**
     * Set order payment method
     *
     * @param WC_Order $order Order object
     * @param string $payment_method Payment method
     */
    private function set_payment_method($order, $payment_method) {
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        
        // Default to Cash on Delivery if specified method not found
        if (!isset($payment_gateways[$payment_method])) {
            $payment_method = 'cod';
        }
        
        $order->set_payment_method($payment_method);
        $order->set_payment_method_title($payment_gateways[$payment_method]->get_title());
    }
    
    /**
     * Send order confirmation to customer
     *
     * @param WC_Order $order Order object
     * @param string $session_id Session ID
     */
    private function send_order_confirmation($order, $session_id) {
        // Get customer phone
        $phone = $order->get_billing_phone();
        
        if (empty($phone)) {
            return;
        }
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Get templates
        $templates = get_option('wpwa_global_templates', array());
        
        if (empty($templates['order_confirmation'])) {
            return;
        }
        
        // Parse template
        $message = $this->parse_template($templates['order_confirmation']['content'], array(
            'order' => $order
        ));
        
        if (empty($message)) {
            return;
        }
        
        // Send message
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            return;
        }
        
        $wp_whatsapp_api->api_client->post('/sessions/' . $session_id . '/messages/send', array(
            'recipient' => $phone,
            'message' => $message
        ));
    }
    
    /**
     * Sync order status to WhatsApp
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public function sync_order_status_to_whatsapp($order_id, $old_status, $new_status, $order) {
        // Check if this is a WhatsApp order
        $is_whatsapp_order = get_post_meta($order_id, '_wpwa_order', true) === 'yes';
        
        if (!$is_whatsapp_order) {
            return;
        }
        
        $session_id = get_post_meta($order_id, '_wpwa_session_id', true);
        
        if (empty($session_id)) {
            return;
        }
        
        // Map WooCommerce status to WhatsApp
        $whatsapp_status = $this->map_wc_status_to_whatsapp($new_status);
        
        // Send status update to WhatsApp API
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            return;
        }
        
        $wp_whatsapp_api->api_client->post('/sessions/' . $session_id . '/orders/' . $order_id . '/status', array(
            'status' => $whatsapp_status
        ));
        
        // Check if we need to send automatic message
        $this->maybe_send_status_message($order_id, $new_status, $order);
    }
    
    /**
     * Maybe send status message
     *
     * @param int $order_id Order ID
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    private function maybe_send_status_message($order_id, $new_status, $order) {
        // Check if automatic messages are enabled for this status
        $auto_messages = get_option('wpwa_auto_messages', array());
        
        if (empty($auto_messages[$new_status]['enabled']) || empty($auto_messages[$new_status]['template_id'])) {
            return;
        }
        
        // Get template
        $template_id = $auto_messages[$new_status]['template_id'];
        $global_templates = get_option('wpwa_global_templates', array());
        
        if (empty($global_templates[$template_id])) {
            return;
        }
        
        // Get customer phone
        $phone = $order->get_billing_phone();
        
        if (empty($phone)) {
            return;
        }
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Get session ID
        $session_id = get_post_meta($order_id, '_wpwa_session_id', true);
        
        if (empty($session_id)) {
            return;
        }
        
        // Parse template
        $message = $this->parse_template($global_templates[$template_id]['content'], array(
            'order' => $order
        ));
        
        if (empty($message)) {
            return;
        }
        
        // Send message
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            return;
        }
        
        $wp_whatsapp_api->api_client->post('/sessions/' . $session_id . '/messages/send', array(
            'recipient' => $phone,
            'message' => $message
        ));
    }
    
    /**
     * Map WhatsApp status to WooCommerce status
     *
     * @param string $whatsapp_status WhatsApp status
     * @return string|bool WooCommerce status or false if invalid
     */
    private function map_whatsapp_status_to_wc($whatsapp_status) {
        $mapping = array(
            'received' => 'pending',
            'processing' => 'processing',
            'shipped' => 'completed',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
        );
        
        return isset($mapping[$whatsapp_status]) ? $mapping[$whatsapp_status] : false;
    }
    
    /**
     * Map WooCommerce status to WhatsApp status
     *
     * @param string $wc_status WooCommerce status
     * @return string WhatsApp status
     */
    private function map_wc_status_to_whatsapp($wc_status) {
        $mapping = array(
            'pending' => 'received',
            'processing' => 'processing',
            'on-hold' => 'processing',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'trash' => 'cancelled',
        );
        
        return isset($mapping[$wc_status]) ? $mapping[$wc_status] : 'received';
    }
    
    /**
     * Parse template with variables
     *
     * @param string $template Template content
     * @param array $data Data for variables
     * @return string Parsed template
     */
    private function parse_template($template, $data = array()) {
        // Replace order variables
        if (isset($data['order']) && $data['order'] instanceof WC_Order) {
            $order = $data['order'];
            
            $replacements = array(
                '{customer_name}' => $order->get_formatted_billing_full_name(),
                '{order_number}' => $order->get_order_number(),
                '{order_status}' => wc_get_order_status_name($order->get_status()),
                '{order_total}' => strip_tags($order->get_formatted_order_total()),
                '{shop_name}' => get_bloginfo('name'),
                '{store_url}' => home_url(),
            );
            
            // Build order items list
            $items_text = '';
            foreach ($order->get_items() as $item) {
                $items_text .= sprintf(
                    "- %s x%d (%s)\n",
                    $item->get_name(),
                    $item->get_quantity(),
                    strip_tags(wc_price($item->get_total()))
                );
            }
            $replacements['{order_items}'] = $items_text;
            
            // Replace variables in template
            $template = str_replace(array_keys($replacements), array_values($replacements), $template);
        }
        
        // Replace general variables
        $general_replacements = array(
            '{shop_name}' => get_bloginfo('name'),
            '{store_url}' => home_url(),
            '{date}' => date_i18n(get_option('date_format')),
            '{time}' => date_i18n(get_option('time_format')),
        );
        
        return str_replace(array_keys($general_replacements), array_values($general_replacements), $template);
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
     * Check if marketplace plugin is active
     *
     * @return bool True if marketplace is active
     */
    private function is_marketplace_active() {
        return (
            class_exists('WCFMmp') || 
            class_exists('WeDevs_Dokan') || 
            class_exists('WC_Vendors')
        );
    }
    
    /**
     * Get product vendor
     *
     * @param int $product_id Product ID
     * @return int|null Vendor ID or null if not found
     */
    private function get_product_vendor($product_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_post')) {
            return wcfm_get_vendor_id_by_post($product_id);
        }
        
        // Dokan
        if (function_exists('dokan_get_vendor_by_product')) {
            $vendor = dokan_get_vendor_by_product($product_id);
            if ($vendor) {
                return $vendor->get_id();
            }
        }
        
        // WC Vendors
        if (function_exists('WCV_Vendors::get_vendor_from_product')) {
            return WCV_Vendors::get_vendor_from_product($product_id);
        }
        
        return null;
    }
}