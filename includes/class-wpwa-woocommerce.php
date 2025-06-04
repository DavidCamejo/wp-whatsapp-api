<?php
/**
 * WPWA_WooCommerce Class
 *
 * Handles integration between WhatsApp API and WooCommerce
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA_WooCommerce Class
 */
class WPWA_WooCommerce {
    /**
     * API Client instance
     * @var WPWA_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     * @var WPWA_Logger
     */
    private $logger;
    
    /**
     * Message Manager instance
     * @var WPWA_Message_Manager
     */
    private $message_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wp_whatsapp_api;
        
        // Get required components from main plugin
        if ($wp_whatsapp_api) {
            $this->api_client = $wp_whatsapp_api->api_client;
            $this->logger = $wp_whatsapp_api->logger;
            $this->message_manager = $wp_whatsapp_api->message_manager;
        }
        
        // Initialize WooCommerce hooks
        $this->init_hooks();
        
        // Only log if logger is available
        if ($this->logger) {
            $this->logger->debug('WooCommerce integration initialized');
        } else {
            // Fallback to WordPress error log if logger is not available
            error_log('WPWA: WooCommerce integration initialized without logger');
        }
    }
    
    /**
     * Initialize WooCommerce specific hooks
     */
    private function init_hooks() {
        // Order status change hooks
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // New order hooks
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_new_order'), 10, 3);
        
        // Product hooks
        add_action('woocommerce_product_quick_edit_save', array($this, 'handle_product_update'), 10, 1);
        add_action('woocommerce_new_product', array($this, 'handle_product_create'), 10, 2);
        add_action('woocommerce_update_product', array($this, 'handle_product_update'), 10, 2);
        
        // Admin menu for WhatsApp settings in WooCommerce
        add_action('admin_menu', array($this, 'add_wc_submenu'), 20);
        
        // Meta boxes for orders
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'), 10);
        
        // Settings in WooCommerce
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_wpwa', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_wpwa', array($this, 'update_settings'));
        
        // Customer phone normalization
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));
    }
    
    /**
     * Handle order status change
     *
     * @param int $order_id Order ID
     * @param string $from_status From status
     * @param string $to_status To status
     * @param WC_Order $order Order object
     */
    public function handle_order_status_change($order_id, $from_status, $to_status, $order) {
        if ($this->logger) {
            $this->logger->debug('Order status changed', array(
                'order_id' => $order_id,
                'from' => $from_status,
                'to' => $to_status
            ));
        }
        
        // Send notifications based on status change
        $this->maybe_send_order_notification($order, $to_status);
    }
    
    /**
     * Handle new order creation
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public function handle_new_order($order_id, $posted_data, $order) {
        if ($this->logger) {
            $this->logger->debug('New order created', array(
                'order_id' => $order_id
            ));
        }
        
        // Check if customer wants WhatsApp notifications
        $send_whatsapp = isset($_POST['wpwa_send_notifications']) ? 
            wc_clean($_POST['wpwa_send_notifications']) : 'no';
            
        // Save preference to order meta
        update_post_meta($order_id, '_wpwa_send_notifications', $send_whatsapp);
        
        // If customer opted in, send order confirmation
        if ($send_whatsapp === 'yes') {
            $this->send_order_confirmation($order);
        }
    }
    
    /**
     * Handle product creation
     *
     * @param int $product_id Product ID
     * @param WC_Product $product Product object
     */
    public function handle_product_create($product_id, $product) {
        if ($this->logger) {
            $this->logger->debug('Product created', array(
                'product_id' => $product_id
            ));
        }
        
        // Schedule product sync with WhatsApp catalog if enabled
        if (get_option('wpwa_sync_products', 'no') === 'yes') {
            $this->schedule_product_sync($product_id);
        }
    }
    
    /**
     * Handle product update
     *
     * @param int $product_id Product ID
     * @param WC_Product $product Product object (optional)
     */
    public function handle_product_update($product_id, $product = null) {
        if ($this->logger) {
            $this->logger->debug('Product updated', array(
                'product_id' => $product_id
            ));
        }
        
        // Schedule product sync with WhatsApp catalog if enabled
        if (get_option('wpwa_sync_products', 'no') === 'yes') {
            $this->schedule_product_sync($product_id);
        }
    }
    
    /**
     * Schedule product sync with WhatsApp catalog
     *
     * @param int $product_id Product ID
     */
    private function schedule_product_sync($product_id) {
        // Prevent duplicate scheduling
        if (wp_next_scheduled('wpwa_sync_product', array($product_id))) {
            return;
        }
        
        // Schedule sync for 5 minutes later (to batch updates)
        wp_schedule_single_event(
            time() + 300, 
            'wpwa_sync_product', 
            array($product_id)
        );
    }
    
    /**
     * Maybe send order notification based on status
     *
     * @param WC_Order $order Order object
     * @param string $status New status
     */
    private function maybe_send_order_notification($order, $status) {
        // Check if customer wants WhatsApp notifications
        $send_whatsapp = get_post_meta($order->get_id(), '_wpwa_send_notifications', true);
        
        if ($send_whatsapp !== 'yes') {
            return;
        }
        
        $session_id = get_option('wpwa_default_session_id', '');
        if (empty($session_id)) {
            if ($this->logger) {
                $this->logger->error('Cannot send order notification: No default session ID set');
            }
            return;
        }
        
        // Map status to template
        $templates = array(
            'processing' => 'order_confirmation',
            'completed' => 'order_shipped',
            'cancelled' => 'order_cancelled',
            'refunded' => 'order_refunded'
        );
        
        // If we have a template for this status
        if (isset($templates[$status])) {
            $template_id = $templates[$status];
            $phone = $order->get_billing_phone();
            
            if (!empty($phone) && $this->message_manager) {
                // Let the Message Manager handle sending the notification
                do_action('wpwa_send_order_notification', $order->get_id(), $template_id, $session_id);
            }
        }
    }
    
    /**
     * Send order confirmation WhatsApp message
     *
     * @param WC_Order $order Order object
     */
    private function send_order_confirmation($order) {
        $session_id = get_option('wpwa_default_session_id', '');
        if (empty($session_id)) {
            if ($this->logger) {
                $this->logger->error('Cannot send order confirmation: No default session ID set');
            }
            return false;
        }
        
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            return false;
        }
        
        // Use message manager to send order confirmation
        if ($this->message_manager) {
            $this->message_manager->send_order_confirmation($order->get_id(), $session_id);
        }
    }
    
    /**
     * Add submenu under WooCommerce menu
     */
    public function add_wc_submenu() {
        add_submenu_page(
            'woocommerce',
            __('WhatsApp Integration', 'wp-whatsapp-api'),
            __('WhatsApp', 'wp-whatsapp-api'),
            'manage_woocommerce',
            'wpwa-wc-settings',
            array($this, 'render_wc_settings_page')
        );
    }
    
    /**
     * Render WooCommerce settings page
     */
    public function render_wc_settings_page() {
        include WPWA_PATH . 'admin/woocommerce-settings.php';
    }
    
    /**
     * Add meta boxes to order edit screen
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'wpwa-order-messages',
            __('WhatsApp Messages', 'wp-whatsapp-api'),
            array($this, 'render_order_messages_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render order messages meta box
     *
     * @param WP_Post $post Post object
     */
    public function render_order_messages_meta_box($post) {
        $order_id = $post->ID;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            echo '<p>' . __('Order not found', 'wp-whatsapp-api') . '</p>';
            return;
        }
        
        $send_whatsapp = get_post_meta($order_id, '_wpwa_send_notifications', true);
        $phone = $order->get_billing_phone();
        $customer_name = $order->get_formatted_billing_full_name();
        
        ?>
        <p>
            <strong><?php _e('Customer:', 'wp-whatsapp-api'); ?></strong> 
            <?php echo esc_html($customer_name); ?>
        </p>
        
        <p>
            <strong><?php _e('Phone:', 'wp-whatsapp-api'); ?></strong> 
            <?php echo esc_html($phone); ?>
        </p>
        
        <p>
            <strong><?php _e('WhatsApp Notifications:', 'wp-whatsapp-api'); ?></strong> 
            <?php echo ($send_whatsapp === 'yes') ? __('Enabled', 'wp-whatsapp-api') : __('Disabled', 'wp-whatsapp-api'); ?>
        </p>
        
        <hr>
        
        <?php if (!empty($phone)) : ?>
        <p>
            <button type="button" class="button wpwa-send-message" data-order-id="<?php echo esc_attr($order_id); ?>">
                <?php _e('Send WhatsApp Message', 'wp-whatsapp-api'); ?>
            </button>
        </p>
        
        <div id="wpwa-message-form" style="display:none; margin-top: 10px;">
            <textarea id="wpwa-message-text" rows="3" style="width:100%" placeholder="<?php esc_attr_e('Enter your message', 'wp-whatsapp-api'); ?>"></textarea>
            <button type="button" class="button button-primary wpwa-send-message-submit" style="margin-top: 5px;">
                <?php _e('Send', 'wp-whatsapp-api'); ?>
            </button>
            <div class="wpwa-message-status" style="margin-top: 5px;"></div>
        </div>
        <?php else : ?>
        <p><?php _e('No phone number available', 'wp-whatsapp-api'); ?></p>
        <?php endif; ?>
        
        <script>
            jQuery(document).ready(function($) {
                $('.wpwa-send-message').on('click', function() {
                    $('#wpwa-message-form').toggle();
                });
                
                $('.wpwa-send-message-submit').on('click', function() {
                    var message = $('#wpwa-message-text').val();
                    var orderId = $('.wpwa-send-message').data('order-id');
                    
                    if (!message) {
                        $('.wpwa-message-status').html('<span style="color:red">Please enter a message</span>');
                        return;
                    }
                    
                    $(this).prop('disabled', true);
                    $('.wpwa-message-status').html('<span>Sending...</span>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpwa_send_order_message',
                            order_id: orderId,
                            message: message,
                            nonce: '<?php echo wp_create_nonce('wpwa_order_message'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.wpwa-message-status').html('<span style="color:green">' + response.data.message + '</span>');
                                $('#wpwa-message-text').val('');
                            } else {
                                $('.wpwa-message-status').html('<span style="color:red">' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            $('.wpwa-message-status').html('<span style="color:red">Error sending message</span>');
                        },
                        complete: function() {
                            $('.wpwa-send-message-submit').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Add WhatsApp settings tab to WooCommerce settings
     *
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_settings_tab($tabs) {
        $tabs['wpwa'] = __('WhatsApp', 'wp-whatsapp-api');
        return $tabs;
    }
    
    /**
     * Output settings fields for WhatsApp tab
     */
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * Update settings when saved
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings());
    }
    
    /**
     * Get settings array for WooCommerce settings API
     *
     * @return array Settings fields
     */
    private function get_settings() {
        return array(
            'section_title' => array(
                'name'     => __('WhatsApp Integration Settings', 'wp-whatsapp-api'),
                'type'     => 'title',
                'desc'     => __('Configure how WhatsApp integrates with your WooCommerce store', 'wp-whatsapp-api'),
                'id'       => 'wpwa_wc_section_title'
            ),
            'enable_notifications' => array(
                'name'     => __('Enable WhatsApp Notifications', 'wp-whatsapp-api'),
                'type'     => 'checkbox',
                'desc'     => __('Allow customers to opt-in to WhatsApp notifications during checkout', 'wp-whatsapp-api'),
                'default'  => 'yes',
                'id'       => 'wpwa_enable_notifications'
            ),
            'opt_in_text' => array(
                'name'     => __('Opt-in Text', 'wp-whatsapp-api'),
                'type'     => 'text',
                'desc'     => __('Text displayed for the WhatsApp opt-in checkbox', 'wp-whatsapp-api'),
                'default'  => __('Send me order updates via WhatsApp', 'wp-whatsapp-api'),
                'id'       => 'wpwa_opt_in_text'
            ),
            'default_session' => array(
                'name'     => __('Default Session ID', 'wp-whatsapp-api'),
                'type'     => 'text',
                'desc'     => __('Default WhatsApp session ID to use for sending messages', 'wp-whatsapp-api'),
                'default'  => '',
                'id'       => 'wpwa_default_session_id'
            ),
            'sync_products' => array(
                'name'     => __('Sync Products', 'wp-whatsapp-api'),
                'type'     => 'checkbox',
                'desc'     => __('Automatically sync products to WhatsApp catalog when updated', 'wp-whatsapp-api'),
                'default'  => 'no',
                'id'       => 'wpwa_sync_products'
            ),
            'section_end' => array(
                'type'     => 'sectionend',
                'id'       => 'wpwa_wc_section_end'
            ),
        );
    }
    
    /**
     * Modify checkout fields to add WhatsApp opt-in
     *
     * @param array $fields Checkout fields
     * @return array Modified fields
     */
    public function modify_checkout_fields($fields) {
        // Only add if enabled
        if (get_option('wpwa_enable_notifications', 'yes') === 'yes') {
            $opt_in_text = get_option('wpwa_opt_in_text', __('Send me order updates via WhatsApp', 'wp-whatsapp-api'));
            
            $fields['order']['wpwa_send_notifications'] = array(
                'type'      => 'checkbox',
                'label'     => $opt_in_text,
                'default'   => 1,
                'class'     => array('form-row-wide'),
                'priority'  => 120,
            );
        }
        
        return $fields;
    }
}

// Do not auto-initialize this class
// The main plugin will initialize it when needed after logger is available
// global $wp_whatsapp_api;
// $wp_whatsapp_api->woocommerce_integration = new WPWA_WooCommerce();