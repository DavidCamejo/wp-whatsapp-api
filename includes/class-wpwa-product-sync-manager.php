<?php
/**
 * WPWA Product Sync Manager Class
 *
 * Handles synchronization of products between WooCommerce and WhatsApp catalogs
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Product Sync Manager
 */
class WPWA_Product_Sync_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Product creation, update and deletion hooks
        add_action('woocommerce_new_product', array($this, 'queue_product_sync'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'queue_product_sync'), 10, 1);
        add_action('woocommerce_delete_product', array($this, 'queue_product_deletion'), 10, 1);
        
        // Bulk actions
        add_action('admin_init', array($this, 'register_bulk_actions'));
        add_action('admin_action_wpwa_sync_products', array($this, 'handle_bulk_sync'));
        
        // Single product action
        add_action('woocommerce_admin_process_product_object', array($this, 'add_sync_meta_box'));
        add_action('admin_post_wpwa_sync_single_product', array($this, 'handle_single_product_sync'));
        
        // Vendor-specific actions
        add_action('wp_ajax_wpwa_vendor_sync_catalog', array($this, 'ajax_sync_vendor_catalog'));
        add_action('wp_ajax_wpwa_vendor_get_sync_status', array($this, 'ajax_get_sync_status'));
        
        // Background sync process
        add_action('wpwa_sync_product', array($this, 'process_product_sync'), 10, 2);
        add_action('wpwa_delete_product_from_catalog', array($this, 'process_product_deletion'), 10, 2);
    }
    
    /**
     * Register bulk actions
     */
    public function register_bulk_actions() {
        add_filter('bulk_actions-edit-product', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_actions'), 10, 3);
    }
    
    /**
     * Add bulk actions for products
     *
     * @param array $actions Available bulk actions
     * @return array Modified actions
     */
    public function add_bulk_actions($actions) {
        $actions['wpwa_sync_to_whatsapp'] = __('Sync to WhatsApp', 'wp-whatsapp-api');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     *
     * @param string $redirect_to URL to redirect to
     * @param string $action Action name
     * @param array $post_ids Product IDs
     * @return string Modified redirect URL
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'wpwa_sync_to_whatsapp') {
            return $redirect_to;
        }
        
        $product_count = count($post_ids);
        
        // Queue products for sync
        foreach ($post_ids as $product_id) {
            $this->queue_product_sync($product_id);
        }
        
        // Add query args for notice
        $redirect_to = add_query_arg(array(
            'wpwa_synced' => 1,
            'wpwa_count' => $product_count,
        ), $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Add sync meta box to product admin page
     *
     * @param WC_Product $product Product object
     */
    public function add_sync_meta_box($product) {
        if (!$product->get_id()) {
            return; // Skip for new products
        }
        
        add_meta_box(
            'wpwa_product_sync',
            __('WhatsApp Catalog', 'wp-whatsapp-api'),
            array($this, 'render_sync_meta_box'),
            'product',
            'side',
            'default',
            array('product' => $product)
        );
    }
    
    /**
     * Render sync meta box
     *
     * @param WP_Post $post Post object
     * @param array $args Meta box arguments
     */
    public function render_sync_meta_box($post, $args) {
        $product = $args['args']['product'];
        $product_id = $product->get_id();
        
        // Get sync status
        $sync_status = get_post_meta($product_id, '_wpwa_sync_status', true) ?: 'not_synced';
        $last_synced = get_post_meta($product_id, '_wpwa_last_synced', true);
        
        // Status label
        $status_labels = array(
            'not_synced' => __('Not Synced', 'wp-whatsapp-api'),
            'pending' => __('Sync Pending', 'wp-whatsapp-api'),
            'synced' => __('Synced', 'wp-whatsapp-api'),
            'failed' => __('Sync Failed', 'wp-whatsapp-api'),
        );
        
        $status_label = isset($status_labels[$sync_status]) ? $status_labels[$sync_status] : $status_labels['not_synced'];
        $action_url = admin_url('admin-post.php');
        
        // Display status and sync button
        ?>
        <div class="wpwa-product-sync-meta">
            <p>
                <strong><?php _e('Sync Status:', 'wp-whatsapp-api'); ?></strong>
                <span class="wpwa-status wpwa-status-<?php echo esc_attr($sync_status); ?>">
                    <?php echo esc_html($status_label); ?>
                </span>
            </p>
            
            <?php if ($last_synced) : ?>
                <p class="wpwa-last-synced">
                    <span><?php printf(
                        __('Last synced on: %s', 'wp-whatsapp-api'),
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_synced))
                    ); ?></span>
                </p>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="wpwa_sync_single_product">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                <?php wp_nonce_field('wpwa_sync_product', 'wpwa_sync_nonce'); ?>
                
                <button type="submit" class="button">
                    <?php _e('Sync to WhatsApp', 'wp-whatsapp-api'); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle single product sync
     */
    public function handle_single_product_sync() {
        if (!isset($_POST['wpwa_sync_nonce']) || !wp_verify_nonce($_POST['wpwa_sync_nonce'], 'wpwa_sync_product')) {
            wp_die(__('Security check failed', 'wp-whatsapp-api'));
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_die(__('Invalid product', 'wp-whatsapp-api'));
        }
        
        // Queue product sync
        $this->queue_product_sync($product_id);
        
        // Redirect back to product edit page
        wp_redirect(admin_url('post.php?post=' . $product_id . '&action=edit&wpwa_synced=1'));
        exit;
    }
    
    /**
     * Queue product for sync
     *
     * @param int $product_id Product ID
     */
    public function queue_product_sync($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        // Get vendor ID for this product
        $vendor_id = $this->get_product_vendor_id($product_id);
        
        if (!$vendor_id) {
            return; // No vendor associated with this product
        }
        
        // Update sync status to pending
        update_post_meta($product_id, '_wpwa_sync_status', 'pending');
        
        // Schedule background sync
        wp_schedule_single_event(
            time(),
            'wpwa_sync_product',
            array('product_id' => $product_id, 'vendor_id' => $vendor_id)
        );
    }
    
    /**
     * Queue product for deletion from WhatsApp catalog
     *
     * @param int $product_id Product ID
     */
    public function queue_product_deletion($product_id) {
        // Get vendor ID for this product
        $vendor_id = $this->get_product_vendor_id($product_id);
        
        if (!$vendor_id) {
            return; // No vendor associated with this product
        }
        
        // Get WhatsApp catalog item ID if it exists
        $catalog_item_id = get_post_meta($product_id, '_wpwa_catalog_item_id', true);
        
        if (empty($catalog_item_id)) {
            return; // No catalog item to delete
        }
        
        // Schedule background deletion
        wp_schedule_single_event(
            time(),
            'wpwa_delete_product_from_catalog',
            array('product_id' => $product_id, 'vendor_id' => $vendor_id)
        );
    }
    
    /**
     * Process product sync in background
     *
     * @param int $product_id Product ID
     * @param int $vendor_id Vendor ID
     */
    public function process_product_sync($product_id, $vendor_id) {
        global $wpwa_logger, $wp_whatsapp_api;
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Product not found for sync: ' . $product_id, 'error');
            }
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            return;
        }
        
        // Get WhatsApp session client ID
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('No active WhatsApp session for vendor: ' . $vendor_id, 'error');
            }
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            return;
        }
        
        // Check if WhatsApp integration is enabled
        $whatsapp_enabled = get_user_meta($vendor_id, 'wpwa_enable_whatsapp', true) === '1';
        
        if (!$whatsapp_enabled) {
            if ($wpwa_logger) {
                $wpwa_logger->log('WhatsApp integration not enabled for vendor: ' . $vendor_id, 'error');
            }
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            return;
        }
        
        // API client
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('API client not available', 'error');
            }
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            return;
        }
        
        // Prepare product data for WhatsApp catalog
        $catalog_data = $this->prepare_product_data_for_catalog($product);
        
        // Check if product already exists in catalog
        $catalog_item_id = get_post_meta($product_id, '_wpwa_catalog_item_id', true);
        
        if ($catalog_item_id) {
            // Update existing catalog item
            $response = $wp_whatsapp_api->api_client->put('/sessions/' . $client_id . '/catalog/items/' . $catalog_item_id, $catalog_data);
        } else {
            // Create new catalog item
            $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/catalog/items', $catalog_data);
        }
        
        // Handle response
        if (is_wp_error($response)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Failed to sync product: ' . $product_id, 'error', array(
                    'error' => $response->get_error_message()
                ));
            }
            update_post_meta($product_id, '_wpwa_sync_status', 'failed');
            return;
        }
        
        // Update product meta
        if (!empty($response['catalog_item_id'])) {
            update_post_meta($product_id, '_wpwa_catalog_item_id', $response['catalog_item_id']);
        }
        
        update_post_meta($product_id, '_wpwa_sync_status', 'synced');
        update_post_meta($product_id, '_wpwa_last_synced', current_time('mysql'));
        
        if ($wpwa_logger) {
            $wpwa_logger->log('Product synced successfully: ' . $product_id, 'info');
        }
    }
    
    /**
     * Process product deletion from WhatsApp catalog
     *
     * @param int $product_id Product ID
     * @param int $vendor_id Vendor ID
     */
    public function process_product_deletion($product_id, $vendor_id) {
        global $wpwa_logger, $wp_whatsapp_api;
        
        // Get WhatsApp session client ID
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('No active WhatsApp session for vendor: ' . $vendor_id, 'error');
            }
            return;
        }
        
        // Get catalog item ID
        $catalog_item_id = get_post_meta($product_id, '_wpwa_catalog_item_id', true);
        
        if (empty($catalog_item_id)) {
            return; // No catalog item to delete
        }
        
        // API client
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('API client not available', 'error');
            }
            return;
        }
        
        // Delete from catalog
        $response = $wp_whatsapp_api->api_client->delete('/sessions/' . $client_id . '/catalog/items/' . $catalog_item_id);
        
        // Log result
        if (is_wp_error($response)) {
            if ($wpwa_logger) {
                $wpwa_logger->log('Failed to delete product from catalog: ' . $product_id, 'error', array(
                    'error' => $response->get_error_message()
                ));
            }
            return;
        }
        
        if ($wpwa_logger) {
            $wpwa_logger->log('Product deleted from catalog: ' . $product_id, 'info');
        }
        
        // Delete product meta
        delete_post_meta($product_id, '_wpwa_catalog_item_id');
        delete_post_meta($product_id, '_wpwa_sync_status');
        delete_post_meta($product_id, '_wpwa_last_synced');
    }
    
    /**
     * AJAX handler for vendor catalog sync
     */
    public function ajax_sync_vendor_catalog() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Check if vendor has active WhatsApp session
        $client_id = get_user_meta($vendor_id, 'wpwa_session_client_id', true);
        $whatsapp_enabled = get_user_meta($vendor_id, 'wpwa_enable_whatsapp', true) === '1';
        
        if (empty($client_id) || !$whatsapp_enabled) {
            wp_send_json_error(array('message' => __('WhatsApp not connected or not enabled', 'wp-whatsapp-api')));
        }
        
        // Get vendor products
        $products = $this->get_vendor_products($vendor_id, true);
        
        if (empty($products)) {
            wp_send_json_error(array('message' => __('No products found', 'wp-whatsapp-api')));
        }
        
        // Queue products for sync
        $queued_count = 0;
        foreach ($products as $product_id) {
            $this->queue_product_sync($product_id);
            $queued_count++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('%d products queued for synchronization', 'wp-whatsapp-api'),
                $queued_count
            )
        ));
    }
    
    /**
     * AJAX handler for getting vendor sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get vendor products
        $products = $this->get_vendor_products($vendor_id);
        
        if (empty($products)) {
            wp_send_json_success(array(
                'total' => 0,
                'synced' => 0,
                'pending' => 0,
                'failed' => 0,
                'not_synced' => 0
            ));
            return;
        }
        
        // Count sync status
        $stats = array(
            'total' => count($products),
            'synced' => 0,
            'pending' => 0,
            'failed' => 0,
            'not_synced' => 0
        );
        
        foreach ($products as $product_id) {
            $sync_status = get_post_meta($product_id, '_wpwa_sync_status', true) ?: 'not_synced';
            if (isset($stats[$sync_status])) {
                $stats[$sync_status]++;
            } else {
                $stats['not_synced']++; // Default
            }
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * Prepare product data for WhatsApp catalog
     *
     * @param WC_Product $product Product object
     * @return array Product data for catalog
     */
    private function prepare_product_data_for_catalog($product) {
        $data = array(
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'url' => get_permalink($product->get_id()),
            'sku' => $product->get_sku() ?: $product->get_id(),
            'availability' => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
        );
        
        // Add image if available
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $data['image_url'] = $image_url;
            }
        }
        
        return $data;
    }
    
    /**
     * Get product vendor ID
     *
     * @param int $product_id Product ID
     * @return int|boolean Vendor ID or false if not found
     */
    private function get_product_vendor_id($product_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_post')) {
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);
            if ($vendor_id) {
                return $vendor_id;
            }
        }
        
        // Dokan
        if (function_exists('dokan_get_vendor_by_product')) {
            $vendor = dokan_get_vendor_by_product($product_id);
            if ($vendor && $vendor->get_id()) {
                return $vendor->get_id();
            }
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && function_exists('WCV_Vendors::is_vendor_product')) {
            $vendor_id = WCV_Vendors::get_vendor_from_product($product_id);
            if ($vendor_id) {
                return $vendor_id;
            }
        }
        
        // Non-marketplace site, use admin as vendor
        if (!class_exists('WCV_Vendors') && !function_exists('wcfm_get_vendor_id_by_post') && !function_exists('dokan_get_vendor_by_product')) {
            return 1; // Admin user ID
        }
        
        return false;
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
     * Get vendor products
     *
     * @param int $vendor_id Vendor ID
     * @param bool $ids_only Whether to return only product IDs
     * @return array Products or product IDs
     */
    private function get_vendor_products($vendor_id, $ids_only = false) {
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
        
        $query = new WP_Query($args);
        return $query->posts;
    }
}