<?php
/**
 * WPWA Cart Manager Class
 *
 * Handles temporary shopping carts for WhatsApp customers
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Cart Manager
 */
class WPWA_Cart_Manager {
    /**
     * Cart table name
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpwa_carts';
        
        // Add support for WooCommerce HPOS
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            // Declare compatibility with HPOS
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
        
        // Only hook table creation during normal operation, not during plugin activation
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            // Use plugins_loaded instead of init to ensure dependencies are loaded first
            add_action('plugins_loaded', array($this, 'maybe_create_tables'), 20);
        }
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Cart expiration
        add_action('wpwa_cleanup_expired_carts', array($this, 'cleanup_expired_carts'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wpwa_cleanup_expired_carts')) {
            wp_schedule_event(time(), 'daily', 'wpwa_cleanup_expired_carts');
        }
    }
    
    /**
     * Create necessary database tables if they don't exist
     */
    public function maybe_create_tables() {
        if (get_option('wpwa_cart_db_version') === '1.0') {
            return; // Already created
        }
        
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_phone VARCHAR(20) NOT NULL,
            session_id VARCHAR(100) NOT NULL,
            cart_data LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_phone (customer_phone),
            KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        update_option('wpwa_cart_db_version', '1.0');
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wpwa/v1', '/cart', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_cart_update'),
            'permission_callback' => array($this, 'verify_api_permission')
        ));
        
        register_rest_route('wpwa/v1', '/cart/(?P<phone>[\w\d\+]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cart'),
            'permission_callback' => array($this, 'verify_api_permission')
        ));
        
        register_rest_route('wpwa/v1', '/cart/(?P<phone>[\w\d\+]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clear_cart'),
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
     * Handle cart update request
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_cart_update($request) {
        $params = $request->get_json_params();
        
        if (empty($params['phone']) || empty($params['session_id'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters'
            ), 400);
        }
        
        $phone = sanitize_text_field($params['phone']);
        $session_id = sanitize_text_field($params['session_id']);
        $cart_data = isset($params['cart_data']) ? $params['cart_data'] : array();
        
        // Validate cart data
        $validated_cart = $this->validate_cart_items($cart_data, $session_id);
        
        // Save cart
        $result = $this->save_cart($phone, $session_id, $validated_cart);
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to save cart'
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Cart updated successfully'
        ), 200);
    }
    
    /**
     * Get cart for a customer
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_cart($request) {
        $phone = $request->get_param('phone');
        
        if (empty($phone)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing phone parameter'
            ), 400);
        }
        
        $session_id = $request->get_param('session_id');
        
        // Get cart data
        $cart = $this->get_customer_cart($phone, $session_id);
        
        if ($cart === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Cart not found'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'cart' => $cart
        ), 200);
    }
    
    /**
     * Clear cart for a customer
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function clear_cart($request) {
        $phone = $request->get_param('phone');
        
        if (empty($phone)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing phone parameter'
            ), 400);
        }
        
        $session_id = $request->get_param('session_id');
        
        // Delete cart
        $result = $this->delete_customer_cart($phone, $session_id);
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to delete cart'
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Cart deleted successfully'
        ), 200);
    }
    
    /**
     * Validate cart items against available products
     *
     * @param array $cart_items Cart items to validate
     * @param string $session_id Session ID
     * @return array Validated cart items
     */
    private function validate_cart_items($cart_items, $session_id) {
        $validated_cart = array();
        
        if (!is_array($cart_items)) {
            return $validated_cart;
        }
        
        // Get vendor ID from session ID
        $vendor_id = $this->get_vendor_id_from_session($session_id);
        
        foreach ($cart_items as $item) {
            if (empty($item['product_id']) && empty($item['sku'])) {
                continue; // Skip items without an identifier
            }
            
            // First try to get product by ID
            $product = null;
            if (!empty($item['product_id'])) {
                $product = wc_get_product(absint($item['product_id']));
            }
            
            // If not found by ID, try by SKU
            if (!$product && !empty($item['sku'])) {
                $product_id = wc_get_product_id_by_sku(sanitize_text_field($item['sku']));
                if ($product_id) {
                    $product = wc_get_product($product_id);
                }
            }
            
            // Skip if product not found or not available
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                continue;
            }
            
            // For marketplace, check if product belongs to this vendor
            if ($vendor_id && function_exists('wcfm_get_vendor_id_by_post')) {
                $product_vendor = wcfm_get_vendor_id_by_post($product->get_id());
                if ($product_vendor != $vendor_id) {
                    continue; // Skip products from other vendors
                }
            }
            
            // Add to validated cart
            $validated_cart[] = array(
                'product_id' => $product->get_id(),
                'variation_id' => isset($item['variation_id']) ? absint($item['variation_id']) : 0,
                'quantity' => isset($item['quantity']) ? max(1, absint($item['quantity'])) : 1,
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'subtotal' => $product->get_price() * (isset($item['quantity']) ? absint($item['quantity']) : 1),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: '',
                'url' => get_permalink($product->get_id())
            );
        }
        
        return $validated_cart;
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
     * Save customer cart
     *
     * @param string $phone Customer phone
     * @param string $session_id Session ID
     * @param array $cart_data Cart data
     * @return bool|int False on failure, cart ID on success
     */
    public function save_cart($phone, $session_id, $cart_data) {
        global $wpdb;
        
        // Format phone number (remove any non-numeric characters except +)
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Check if cart exists
        $cart_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
            WHERE customer_phone = %s AND session_id = %s",
            $phone,
            $session_id
        ));
        
        // Set expiration to 30 days from now
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        if ($cart_id) {
            // Update existing cart
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'cart_data' => maybe_serialize($cart_data),
                    'updated_at' => current_time('mysql'),
                    'expires_at' => $expires_at
                ),
                array(
                    'id' => $cart_id
                ),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            return $result !== false ? $cart_id : false;
        } else {
            // Create new cart
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'customer_phone' => $phone,
                    'session_id' => $session_id,
                    'cart_data' => maybe_serialize($cart_data),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'expires_at' => $expires_at
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Get customer cart
     *
     * @param string $phone Customer phone
     * @param string $session_id Session ID (optional)
     * @return array|bool Cart data or false if not found
     */
    public function get_customer_cart($phone, $session_id = null) {
        global $wpdb;
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        $query = "SELECT * FROM {$this->table_name} WHERE customer_phone = %s";
        $params = array($phone);
        
        if ($session_id) {
            $query .= " AND session_id = %s";
            $params[] = $session_id;
        }
        
        $query .= " AND expires_at > %s ORDER BY updated_at DESC LIMIT 1";
        $params[] = current_time('mysql');
        
        $cart = $wpdb->get_row($wpdb->prepare($query, $params), ARRAY_A);
        
        if (!$cart) {
            return false;
        }
        
        // Update cart access time
        $wpdb->update(
            $this->table_name,
            array('updated_at' => current_time('mysql')),
            array('id' => $cart['id']),
            array('%s'),
            array('%d')
        );
        
        // Calculate cart totals
        $cart_data = maybe_unserialize($cart['cart_data']);
        
        if (!is_array($cart_data)) {
            $cart_data = array();
        }
        
        $items_count = 0;
        $cart_total = 0;
        
        foreach ($cart_data as $item) {
            $items_count += isset($item['quantity']) ? absint($item['quantity']) : 0;
            $cart_total += isset($item['subtotal']) ? floatval($item['subtotal']) : 0;
        }
        
        return array(
            'session_id' => $cart['session_id'],
            'items' => $cart_data,
            'items_count' => $items_count,
            'total' => $cart_total,
            'total_formatted' => wc_price($cart_total),
            'created_at' => $cart['created_at'],
            'updated_at' => $cart['updated_at']
        );
    }
    
    /**
     * Delete customer cart
     *
     * @param string $phone Customer phone
     * @param string $session_id Session ID (optional)
     * @return bool Whether the cart was deleted
     */
    public function delete_customer_cart($phone, $session_id = null) {
        global $wpdb;
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        $query = "DELETE FROM {$this->table_name} WHERE customer_phone = %s";
        $params = array($phone);
        
        if ($session_id) {
            $query .= " AND session_id = %s";
            $params[] = $session_id;
        }
        
        $result = $wpdb->query($wpdb->prepare($query, $params));
        
        return $result !== false;
    }
    
    /**
     * Cleanup expired carts
     */
    public function cleanup_expired_carts() {
        global $wpdb, $wpwa_logger;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE expires_at < %s",
            current_time('mysql')
        ));
        
        if ($wpwa_logger && $result !== false) {
            $wpwa_logger->log(sprintf(
                'Cleaned up %d expired WhatsApp carts',
                $result
            ), 'info');
        }
    }
}