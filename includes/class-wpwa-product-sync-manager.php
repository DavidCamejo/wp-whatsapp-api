<?php
/**
 * WPWA Product Sync Manager
 *
 * Manages synchronization of WooCommerce products with WhatsApp catalog
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Product Sync Manager
 */
class WPWA_Product_Sync_Manager {
    /**
     * API Client
     *
     * @var WPWA_API_Client
     */
    private $api_client;
    
    /**
     * Logger
     *
     * @var WPWA_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param WPWA_API_Client $api_client API client for WhatsApp API communication
     * @param WPWA_Logger $logger Logger for activity tracking
     */
    public function __construct($api_client, $logger) {
        $this->api_client = $api_client;
        $this->logger = $logger;
        
        // Register hooks
        add_action('wpwa_sync_product', array($this, 'process_product_sync'), 10, 2);
        add_action('woocommerce_update_product', array($this, 'queue_product_sync'), 10);
        add_action('woocommerce_new_product', array($this, 'queue_product_sync'), 10);
    }
    
    /**
     * Queue a product for synchronization
     *
     * @param int $product_id Product ID
     */
    public function queue_product_sync($product_id) {
        // Skip if product is being imported or not a sync operation
        if (defined('WP_IMPORTING') && WP_IMPORTING) {
            return;
        }
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        // Get vendor ID for the product
        $vendor_id = $this->get_product_vendor_id($product_id);
        
        if (!$vendor_id) {
            // Product doesn't belong to a vendor, skip
            return;
        }
        
        // Get user ID associated with vendor
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            return;
        }
        
        // Check if WhatsApp is enabled for this vendor
        $whatsapp_enabled = get_user_meta($user_id, 'wpwa_enable_whatsapp', true) === '1';
        
        if (!$whatsapp_enabled) {
            return;
        }
        
        // Mark product as pending sync
        update_post_meta($product_id, '_wpwa_sync_status', 'pending');
        
        // Schedule synchronization
        if (!wp_next_scheduled('wpwa_sync_product', array('product_id' => $product_id, 'vendor_id' => $vendor_id))) {
            wp_schedule_single_event(
                time() + 30, // Delay 30 seconds to allow other product updates to complete
                'wpwa_sync_product',
                array('product_id' => $product_id, 'vendor_id' => $vendor_id)
            );
            
            $this->logger->info('Product queued for sync', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id
            ));
        }
    }
    
    /**
     * Process synchronization of a product
     *
     * @param int $product_id Product ID
     * @param int $vendor_id Vendor ID
     */
    public function process_product_sync($product_id, $vendor_id) {
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            update_post_meta($product_id, '_wpwa_sync_error', 'Product not found');
            $this->logger->error('Product sync failed - product not found', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id
            ));
            return;
        }
        
        // Verify product belongs to vendor
        $product_vendor_id = $this->get_product_vendor_id($product_id);
        
        if ($product_vendor_id != $vendor_id) {
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            update_post_meta($product_id, '_wpwa_sync_error', 'Product vendor mismatch');
            $this->logger->error('Product sync failed - vendor mismatch', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id,
                'product_vendor_id' => $product_vendor_id
            ));
            return;
        }
        
        // Get client ID for the vendor
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            update_post_meta($product_id, '_wpwa_sync_error', 'Vendor user not found');
            $this->logger->error('Product sync failed - vendor user not found', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id
            ));
            return;
        }
        
        // Get client ID
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            update_post_meta($product_id, '_wpwa_sync_error', 'No WhatsApp session');
            $this->logger->error('Product sync failed - no WhatsApp session', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id
            ));
            return;
        }
        
        // Prepare product data for API
        $product_data = $this->prepare_product_data_for_catalog($product);
        
        // Send to API
        $response = $this->api_client->post(
            '/sessions/' . $client_id . '/products', 
            $product_data
        );
        
        if (is_wp_error($response)) {
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            update_post_meta($product_id, '_wpwa_sync_error', $response->get_error_message());
            update_post_meta($product_id, '_wpwa_sync_time', current_time('mysql'));
            $this->logger->error('Product sync failed - API error', array(
                'product_id' => $product_id,
                'vendor_id' => $vendor_id,
                'error' => $response->get_error_message()
            ));
            return;
        }
        
        // Update meta
        update_post_meta($product_id, '_wpwa_sync_status', 'synced');
        delete_post_meta($product_id, '_wpwa_sync_error');
        update_post_meta($product_id, '_wpwa_sync_time', current_time('mysql'));
        
        if (isset($response['product_id'])) {
            update_post_meta($product_id, '_wpwa_catalog_product_id', $response['product_id']);
        }
        
        $this->logger->info('Product synced successfully', array(
            'product_id' => $product_id,
            'vendor_id' => $vendor_id,
            'whatsapp_product_id' => isset($response['product_id']) ? $response['product_id'] : ''
        ));
    }
    
    /**
     * AJAX handler for syncing a vendor's entire catalog
     */
    public function ajax_sync_vendor_catalog() {
        // This is just a wrapper - actual implementation is in the AJAX handler class
        // We're using this pattern to maintain separation of concerns
        
        // Get products for vendor
        // Queue each product for sync
        // Return statistics
    }
    
    /**
     * AJAX handler for getting sync status
     */
    public function ajax_get_sync_status() {
        // This is just a wrapper - actual implementation is in the AJAX handler class
    }
    
    /**
     * Get products for a vendor
     *
     * @param int $vendor_id Vendor ID
     * @param bool $ids_only Return only product IDs
     * @return array Products or product IDs
     */
    public function get_vendor_products($vendor_id, $ids_only = false) {
        // Query parameters
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => $ids_only ? 'ids' : 'all'
        );
        
        // WCFM
        if (function_exists('wcfm_get_vendor_store_by_vendor')) {
            $args['author'] = $vendor_id;
        }
        
        // Dokan
        if (function_exists('dokan_is_user_seller') && dokan_is_user_seller($vendor_id)) {
            $args['author'] = $vendor_id;
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($vendor_id)) {
            $args['author'] = $vendor_id;
        }
        
        // Simple admin case
        if (user_can($vendor_id, 'manage_woocommerce')) {
            // No author filter for admin - they can access all products
        }
        
        // Run query
        $query = new WP_Query($args);
        
        if ($ids_only) {
            return $query->posts;
        }
        
        // Convert posts to WC_Product objects if needed
        $products = array();
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $products[] = $product;
            }
        }
        
        return $products;
    }
    
    /**
     * Prepare product data for catalog API
     *
     * @param WC_Product $product WooCommerce product
     * @return array Formatted product data
     */
    private function prepare_product_data_for_catalog($product) {
        // Base product data
        $data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description() ?: $product->get_short_description(),
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'url' => get_permalink($product->get_id()),
            'availability' => $product->is_in_stock() ? 'in stock' : 'out of stock',
            'quantity' => $product->get_stock_quantity()
        );
        
        // Add images
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $data['image_url'] = $image_url;
            }
        }
        
        // Add categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        if (!is_wp_error($categories) && !empty($categories)) {
            $data['category'] = implode(', ', $categories);
        }
        
        // Add SKU if available
        $sku = $product->get_sku();
        if ($sku) {
            $data['retailer_id'] = $sku;
        }
        
        // Add sale info if product is on sale
        if ($product->is_on_sale()) {
            $data['sale_price'] = $product->get_sale_price();
            $data['regular_price'] = $product->get_regular_price();
        }
        
        // Add variations if variable product
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $variant_data = array();
            
            foreach ($variations as $variation) {
                $variant = array(
                    'id' => $variation['variation_id'],
                    'attributes' => array()
                );
                
                // Add attributes
                foreach ($variation['attributes'] as $attr_name => $attr_value) {
                    $attribute_name = str_replace('attribute_', '', $attr_name);
                    $taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
                    
                    // Get clean attribute name
                    $clean_name = str_replace('pa_', '', $attribute_name);
                    $clean_name = ucwords(str_replace('-', ' ', $clean_name));
                    
                    // Get attribute value - either from taxonomy or custom attribute
                    if ($attr_value) {
                        if (taxonomy_exists($taxonomy_name)) {
                            $term = get_term_by('slug', $attr_value, $taxonomy_name);
                            if ($term) {
                                $attr_value = $term->name;
                            }
                        }
                        
                        $variant['attributes'][$clean_name] = $attr_value;
                    }
                }
                
                // Add price
                $variation_obj = wc_get_product($variation['variation_id']);
                if ($variation_obj) {
                    $variant['price'] = $variation_obj->get_price();
                    $variant['sku'] = $variation_obj->get_sku();
                    $variant['availability'] = $variation_obj->is_in_stock() ? 'in stock' : 'out of stock';
                }
                
                $variant_data[] = $variant;
            }
            
            if (!empty($variant_data)) {
                $data['variants'] = $variant_data;
            }
        }
        
        // Filter data before sending
        return apply_filters('wpwa_product_catalog_data', $data, $product);
    }
    
    /**
     * Get vendor ID for a product
     *
     * @param int $product_id Product ID
     * @return int|false Vendor ID or false
     */
    private function get_product_vendor_id($product_id) {
        $post = get_post($product_id);
        
        if (!$post) {
            return false;
        }
        
        $author_id = $post->post_author;
        
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            $vendor_id = wcfm_get_vendor_id_by_user($author_id);
            if ($vendor_id) {
                return $vendor_id;
            }
        }
        
        // Dokan
        if (function_exists('dokan_is_user_seller') && dokan_is_user_seller($author_id)) {
            return $author_id;
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($author_id)) {
            return $author_id;
        }
        
        // If user is admin or shop manager, return user ID
        if (user_can($author_id, 'manage_woocommerce')) {
            return $author_id;
        }
        
        return false;
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
}
