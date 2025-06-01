<?php
/**
 * Order Manager Class
 *
 * Handles creation and management of orders placed through WhatsApp.
 *
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Manager Class
 */
class WPWA_Order_Manager {
    /**
     * Logger instance
     *
     * @var WPWA_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param WPWA_Logger $logger Logger instance
     */
    public function __construct($logger) {
        $this->logger = $logger;
        
        // Add support for WooCommerce HPOS
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            // Declare compatibility with HPOS
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
        
        // Register hooks for order management
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_order_refunded', array($this, 'handle_order_refund'), 10, 2);
        
        // Custom order statuses for WhatsApp orders
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
    }
    
    /**
     * Register custom order statuses for WhatsApp orders
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-whatsapp-pending', array(
            'label'                     => _x('WhatsApp Pending', 'Order status', 'wp-whatsapp-api'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('WhatsApp Pending <span class="count">(%s)</span>', 'WhatsApp Pending <span class="count">(%s)</span>', 'wp-whatsapp-api')
        ));
        
        register_post_status('wc-whatsapp-confirmed', array(
            'label'                     => _x('WhatsApp Confirmed', 'Order status', 'wp-whatsapp-api'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('WhatsApp Confirmed <span class="count">(%s)</span>', 'WhatsApp Confirmed <span class="count">(%s)</span>', 'wp-whatsapp-api')
        ));
    }
    
    /**
     * Add custom order statuses to WooCommerce order statuses
     *
     * @param array $order_statuses Existing order statuses
     * @return array Modified order statuses
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_statuses = array(
            'wc-whatsapp-pending'   => _x('WhatsApp Pending', 'Order status', 'wp-whatsapp-api'),
            'wc-whatsapp-confirmed' => _x('WhatsApp Confirmed', 'Order status', 'wp-whatsapp-api')
        );
        
        // Insert after 'wc-pending'
        $position = array_search('wc-pending', array_keys($order_statuses));
        
        if ($position !== false) {
            $position++;
            $order_statuses = array_slice($order_statuses, 0, $position, true) +
                              $new_statuses +
                              array_slice($order_statuses, $position, count($order_statuses) - $position, true);
        } else {
            $order_statuses = array_merge($order_statuses, $new_statuses);
        }
        
        return $order_statuses;
    }
    
    /**
     * Create an order from WhatsApp request (REST API endpoint)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function create_order_from_whatsapp($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        $required_fields = array('vendor_id', 'customer_phone', 'products');
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('Missing required field: %s', 'wp-whatsapp-api'), $field),
                    array('status' => 400)
                );
            }
        }
        
        // Get vendor info
        $vendor_id = absint($data['vendor_id']);
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            return new WP_Error(
                'invalid_vendor',
                __('Invalid vendor ID', 'wp-whatsapp-api'),
                array('status' => 400)
            );
        }
        
        // Validate products
        $products = $data['products'];
        if (!is_array($products) || empty($products)) {
            return new WP_Error(
                'invalid_products',
                __('Invalid or empty products list', 'wp-whatsapp-api'),
                array('status' => 400)
            );
        }
        
        // Sanitize phone number (remove spaces, +, etc.)
        $customer_phone = preg_replace('/[^0-9]/', '', $data['customer_phone']);
        
        // Try to get existing customer by phone
        $customer_id = $this->get_customer_by_phone($customer_phone);
        
        // Customer details
        $customer_data = array(
            'first_name' => isset($data['customer_name']) ? sanitize_text_field($data['customer_name']) : '',
            'phone'      => $customer_phone,
            'email'      => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : ''
        );
        
        // Address data if provided
        $shipping_address = array();
        if (isset($data['shipping_address']) && is_array($data['shipping_address'])) {
            $address_fields = array('address_1', 'address_2', 'city', 'state', 'postcode', 'country');
            foreach ($address_fields as $field) {
                if (isset($data['shipping_address'][$field])) {
                    $shipping_address[$field] = sanitize_text_field($data['shipping_address'][$field]);
                }
            }
        }
        
        // Create order
        try {
            // Start transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            // Create an order
            $order = wc_create_order(array(
                'status'      => 'whatsapp-pending',
                'customer_id' => $customer_id,
                'created_via' => 'whatsapp',
            ));
            
            if (is_wp_error($order)) {
                throw new Exception($order->get_error_message());
            }
            
            // Add products to order
            foreach ($products as $product_data) {
                if (!isset($product_data['id']) || !isset($product_data['quantity'])) {
                    continue;
                }
                
                $product_id = absint($product_data['id']);
                $quantity = absint($product_data['quantity']);
                
                if ($quantity < 1) {
                    continue;
                }
                
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }
                
                // Verify this product belongs to this vendor
                $product_vendor_id = $this->get_vendor_from_product($product_id);
                if ($product_vendor_id != $vendor_id) {
                    continue;
                }
                
                // Add product to order
                $item_id = $order->add_product($product, $quantity);
                
                // Add variation data if provided
                if (isset($product_data['variation_id']) && $product_data['variation_id'] > 0) {
                    // Get variation attributes
                    $variation = wc_get_product($product_data['variation_id']);
                    if ($variation && $variation instanceof WC_Product_Variation) {
                        wc_update_order_item_meta($item_id, '_variation_id', $product_data['variation_id']);
                        
                        $variation_data = $variation->get_variation_attributes();
                        foreach ($variation_data as $key => $value) {
                            wc_update_order_item_meta($item_id, $key, $value);
                        }
                    }
                }
            }
            
            // Calculate totals
            $order->calculate_totals();
            
            // Set order meta to indicate it's from WhatsApp
            $order->update_meta_data('_wpwa_order_source', 'whatsapp');
            $order->update_meta_data('_wpwa_vendor_id', $vendor_id);
            $order->update_meta_data('_wpwa_customer_phone', $customer_phone);
            $order->update_meta_data('_wpwa_order_notes', isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '');
            
            // Set order status
            $order->set_status('whatsapp-pending');
            
            // Set billing info
            if (!empty($customer_data['first_name'])) {
                $order->set_billing_first_name($customer_data['first_name']);
            }
            if (!empty($customer_data['phone'])) {
                $order->set_billing_phone($customer_data['phone']);
            }
            if (!empty($customer_data['email'])) {
                $order->set_billing_email($customer_data['email']);
            }
            
            // Set shipping address if provided
            if (!empty($shipping_address)) {
                foreach ($shipping_address as $key => $value) {
                    $setter = "set_shipping_$key";
                    if (method_exists($order, $setter)) {
                        $order->$setter($value);
                    }
                }
            }
            
            // Save the order
            $order->save();
            
            // Add order note
            $order->add_order_note(
                __('Order created via WhatsApp API', 'wp-whatsapp-api'),
                0,
                true
            );
            
            // Create customer if necessary
            if (!$customer_id && !empty($customer_data['email'])) {
                $this->create_customer_from_order($order, $customer_data);
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Log the order creation
            $this->logger->info('Created order from WhatsApp', array(
                'order_id'      => $order->get_id(),
                'vendor_id'     => $vendor_id,
                'customer_phone' => $customer_phone,
                'product_count' => count($products)
            ));
            
            // Trigger action for other plugins
            do_action('wpwa_order_created', $order->get_id(), $vendor_id, $data);
            
            // Return success response
            return new WP_REST_Response(array(
                'success'  => true,
                'order_id' => $order->get_id(),
                'total'    => $order->get_total(),
                'currency' => $order->get_currency(),
                'status'   => $order->get_status(),
                'message'  => __('Order created successfully', 'wp-whatsapp-api')
            ), 201);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            
            $this->logger->error('Failed to create order from WhatsApp', array(
                'error'         => $e->getMessage(),
                'vendor_id'     => $vendor_id,
                'customer_phone' => $customer_phone
            ));
            
            return new WP_Error(
                'order_creation_failed',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Handle order status change
     *
     * @param int    $order_id   Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param object $order      Order object
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Check if this is a WhatsApp order
        $order_source = $order->get_meta('_wpwa_order_source');
        if ($order_source !== 'whatsapp') {
            return;
        }
        
        $vendor_id = $order->get_meta('_wpwa_vendor_id');
        $customer_phone = $order->get_meta('_wpwa_customer_phone');
        
        if (empty($vendor_id) || empty($customer_phone)) {
            return;
        }
        
        $this->logger->info('WhatsApp order status changed', array(
            'order_id'      => $order_id,
            'old_status'    => $old_status,
            'new_status'    => $new_status,
            'vendor_id'     => $vendor_id,
            'customer_phone' => $customer_phone
        ));
        
        // Get vendor's WhatsApp session
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if (!$user_id) {
            return;
        }
        
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        if (empty($client_id)) {
            return;
        }
        
        // Only supported status changes
        $supported_statuses = array(
            'processing', 'completed', 'cancelled', 'refunded',
            'whatsapp-confirmed', 'on-hold'
        );
        
        if (!in_array($new_status, $supported_statuses)) {
            return;
        }
        
        // Send notification through WhatsApp API
        $this->send_order_status_notification($order, $client_id, $customer_phone, $new_status);
    }
    
    /**
     * Handle new order creation
     *
     * @param int      $order_id Order ID
     * @param WC_Order $order    Order object
     */
    public function handle_new_order($order_id, $order) {
        // This is handled in handle_order_status_change
    }
    
    /**
     * Handle order refund
     *
     * @param int $order_id   Order ID
     * @param int $refund_id  Refund ID
     */
    public function handle_order_refund($order_id, $refund_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if this is a WhatsApp order
        $order_source = $order->get_meta('_wpwa_order_source');
        if ($order_source !== 'whatsapp') {
            return;
        }
        
        $vendor_id = $order->get_meta('_wpwa_vendor_id');
        $customer_phone = $order->get_meta('_wpwa_customer_phone');
        
        if (empty($vendor_id) || empty($customer_phone)) {
            return;
        }
        
        $refund = new WC_Order_Refund($refund_id);
        $refund_amount = $refund->get_amount();
        $refund_reason = $refund->get_reason();
        
        $this->logger->info('WhatsApp order refunded', array(
            'order_id'      => $order_id,
            'refund_id'     => $refund_id,
            'refund_amount' => $refund_amount,
            'vendor_id'     => $vendor_id,
            'customer_phone' => $customer_phone
        ));
        
        // Get vendor's WhatsApp session
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if (!$user_id) {
            return;
        }
        
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        if (empty($client_id)) {
            return;
        }
        
        // Send refund notification through WhatsApp API
        $this->send_refund_notification($order, $refund, $client_id, $customer_phone);
    }
    
    /**
     * Send order status notification to customer via WhatsApp
     *
     * @param WC_Order $order         Order object
     * @param string   $client_id     WhatsApp session client ID
     * @param string   $customer_phone Customer phone number
     * @param string   $status        New status
     * @return boolean Success status
     */
    private function send_order_status_notification($order, $client_id, $customer_phone, $status) {
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            return false;
        }
        
        // Format the phone number for WhatsApp (remove leading 0, add country code if missing)
        $formatted_phone = $this->format_phone_for_whatsapp($customer_phone);
        
        // Prepare status message
        $status_labels = array(
            'processing'         => __('Your order is now being processed', 'wp-whatsapp-api'),
            'completed'          => __('Your order has been completed', 'wp-whatsapp-api'),
            'cancelled'          => __('Your order has been cancelled', 'wp-whatsapp-api'),
            'refunded'           => __('Your order has been refunded', 'wp-whatsapp-api'),
            'whatsapp-confirmed' => __('Your order has been confirmed', 'wp-whatsapp-api'),
            'on-hold'            => __('Your order is on hold', 'wp-whatsapp-api'),
        );
        
        $status_message = isset($status_labels[$status]) ? $status_labels[$status] : __('Your order status has been updated', 'wp-whatsapp-api');
        
        // Build the message
        $store_name = get_bloginfo('name');
        $order_total = $order->get_formatted_order_total();
        
        $message = sprintf(
            /* translators: 1: store name, 2: order number, 3: status message, 4: order total */
            __("*%1\$s*\n\nOrder #%2\$s: %3\$s\n\nTotal: %4\$s\n\nThank you for your order!", 'wp-whatsapp-api'),
            $store_name,
            $order->get_order_number(),
            $status_message,
            $order_total
        );
        
        // Add order items if needed
        if ($status === 'whatsapp-confirmed' || $status === 'processing') {
            $message .= "\n\n" . __('Order Items:', 'wp-whatsapp-api');
            $items = $order->get_items();
            
            foreach ($items as $item) {
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $total = $order->get_formatted_line_subtotal($item);
                
                $message .= sprintf("\n- %s × %d: %s", $product_name, $quantity, $total);
            }
        }
        
        // Send message via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/messages/send', array(
            'recipient' => $formatted_phone,
            'message'   => $message
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to send order status notification', array(
                'order_id' => $order->get_id(),
                'status'   => $status,
                'error'    => $response->get_error_message()
            ));
            return false;
        }
        
        $this->logger->info('Sent order status notification', array(
            'order_id' => $order->get_id(),
            'status'   => $status
        ));
        
        return true;
    }
    
    /**
     * Send refund notification to customer via WhatsApp
     *
     * @param WC_Order        $order         Order object
     * @param WC_Order_Refund $refund        Refund object
     * @param string          $client_id     WhatsApp session client ID
     * @param string          $customer_phone Customer phone number
     * @return boolean Success status
     */
    private function send_refund_notification($order, $refund, $client_id, $customer_phone) {
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            return false;
        }
        
        // Format the phone number for WhatsApp
        $formatted_phone = $this->format_phone_for_whatsapp($customer_phone);
        
        // Build the message
        $store_name = get_bloginfo('name');
        $refund_amount = wc_price($refund->get_amount(), array('currency' => $order->get_currency()));
        $refund_reason = $refund->get_reason();
        
        $message = sprintf(
            /* translators: 1: store name, 2: order number, 3: refund amount, 4: refund reason */
            __("*%1\$s*\n\nOrder #%2\$s has been refunded.\n\nRefund Amount: %3\$s\n\nReason: %4\$s", 'wp-whatsapp-api'),
            $store_name,
            $order->get_order_number(),
            $refund_amount,
            $refund_reason ?: __('Not specified', 'wp-whatsapp-api')
        );
        
        // Send message via API
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/messages/send', array(
            'recipient' => $formatted_phone,
            'message'   => $message
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to send refund notification', array(
                'order_id'  => $order->get_id(),
                'refund_id' => $refund->get_id(),
                'error'     => $response->get_error_message()
            ));
            return false;
        }
        
        $this->logger->info('Sent refund notification', array(
            'order_id'  => $order->get_id(),
            'refund_id' => $refund->get_id()
        ));
        
        return true;
    }
    
    /**
     * Get customer ID by phone number
     *
     * @param string $phone Phone number
     * @return int|null Customer ID or null if not found
     */
    private function get_customer_by_phone($phone) {
        // Search for customers with matching phone number
        $customer_query = new WP_User_Query(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'billing_phone',
                    'value'   => $phone,
                    'compare' => '='
                ),
                array(
                    'key'     => 'billing_phone',
                    'value'   => '+' . $phone,
                    'compare' => '='
                )
            ),
            'role'      => 'customer',
            'number'    => 1
        ));
        
        $customers = $customer_query->get_results();
        if (!empty($customers)) {
            return $customers[0]->ID;
        }
        
        return null;
    }
    
    /**
     * Create a new customer from order data
     *
     * @param WC_Order $order        Order object
     * @param array    $customer_data Customer data
     * @return int|WP_Error New customer ID or error
     */
    private function create_customer_from_order($order, $customer_data) {
        // Ensure we have email
        if (empty($customer_data['email'])) {
            return false;
        }
        
        // Check if the email is already in use
        if (email_exists($customer_data['email'])) {
            // Update existing user instead of creating a new one
            $user = get_user_by('email', $customer_data['email']);
            
            // Update phone number if provided
            if (!empty($customer_data['phone'])) {
                update_user_meta($user->ID, 'billing_phone', $customer_data['phone']);
            }
            
            return $user->ID;
        }
        
        // Generate username from email
        $username = sanitize_user(current(explode('@', $customer_data['email'])), true);
        
        // Ensure username is unique
        $suffix = 1;
        $original_username = $username;
        while (username_exists($username)) {
            $username = $original_username . $suffix;
            $suffix++;
        }
        
        // Generate random password
        $password = wp_generate_password();
        
        // Create the new user
        $user_id = wc_create_new_customer(
            $customer_data['email'],
            $username,
            $password
        );
        
        if (is_wp_error($user_id)) {
            $this->logger->error('Failed to create customer from order', array(
                'error' => $user_id->get_error_message(),
                'email' => $customer_data['email']
            ));
            return $user_id;
        }
        
        // Update customer data
        if (!empty($customer_data['first_name'])) {
            update_user_meta($user_id, 'first_name', $customer_data['first_name']);
            update_user_meta($user_id, 'billing_first_name', $customer_data['first_name']);
        }
        
        if (!empty($customer_data['phone'])) {
            update_user_meta($user_id, 'billing_phone', $customer_data['phone']);
        }
        
        // Link the order to this new customer
        $order->set_customer_id($user_id);
        $order->save();
        
        $this->logger->info('Created new customer from order', array(
            'customer_id' => $user_id,
            'order_id'    => $order->get_id()
        ));
        
        return $user_id;
    }
    
    /**
     * Format phone number for WhatsApp
     *
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function format_phone_for_whatsapp($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading zero if present
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }
        
        // Add country code if not present (default to site country)
        if (strlen($phone) <= 10) {
            $wc_countries = new WC_Countries();
            $country_code = $wc_countries->get_base_country();
            $country_calling_code = WC()->countries->get_country_calling_code($country_code);
            
            if ($country_calling_code) {
                // Remove + if present
                $country_calling_code = str_replace('+', '', $country_calling_code);
                $phone = $country_calling_code . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Get vendor ID from product
     *
     * @param int $product_id Product ID
     * @return int|boolean Vendor ID or false if not found
     */
    private function get_vendor_from_product($product_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_post')) {
            return wcfm_get_vendor_id_by_post($product_id);
        }
        
        // Dokan
        if (function_exists('dokan_get_vendor_by_product')) {
            $vendor = dokan_get_vendor_by_product($product_id);
            return $vendor ? $vendor->get_id() : false;
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && function_exists('WCV_Vendors::get_vendor_from_product')) {
            return WCV_Vendors::get_vendor_from_product($product_id);
        }
        
        return false;
    }
    
    /**
     * Get user ID from vendor ID
     *
     * @param int $vendor_id Vendor ID
     * @return int|boolean User ID or false if not found
     */
    private function get_user_id_from_vendor($vendor_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_vendor')) {
            return wcfm_get_vendor_id_by_vendor($vendor_id);
        }
        
        // Dokan (vendor ID is typically user ID)
        if (function_exists('dokan_is_user_seller')) {
            if (dokan_is_user_seller($vendor_id)) {
                return $vendor_id;
            }
        }
        
        // WC Vendors (vendor ID is typically user ID)
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($vendor_id)) {
            return $vendor_id;
        }
        
        return false;
    }
}