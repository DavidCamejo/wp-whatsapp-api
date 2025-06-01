<?php
/**
 * Vendor Dashboard
 * 
 * Handles the vendor dashboard interface for WhatsApp API Integration plugin.
 * 
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Dashboard Class
 */
class WPWA_Vendor_Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        // Add dashboard to vendor panels in different marketplace plugins
        
        // WCFM
        add_filter('wcfm_menus', array($this, 'add_wcfm_menu'));
        add_action('wcfm_load_views', array($this, 'load_wcfm_view'), 50);
        add_action('wcfm_load_scripts', array($this, 'load_scripts'));
        add_action('wcfm_load_styles', array($this, 'load_styles'));
        
        // Dokan
        add_filter('dokan_get_dashboard_nav', array($this, 'add_dokan_menu'));
        add_action('dokan_load_custom_template', array($this, 'load_dokan_template'));
        
        // WC Vendors
        add_filter('wcv_vendor_dashboard_pages', array($this, 'add_wcv_menu'));
        add_action('init', array($this, 'wcv_dashboard_endpoint'));
        
        // Ajax handlers
        add_action('wp_ajax_wpwa_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_wpwa_check_session', array($this, 'ajax_check_session'));
        add_action('wp_ajax_wpwa_disconnect_session', array($this, 'ajax_disconnect_session'));
        add_action('wp_ajax_wpwa_send_test_message', array($this, 'ajax_send_test_message'));
        add_action('wp_ajax_wpwa_get_recent_orders', array($this, 'ajax_get_recent_orders'));
        add_action('wp_ajax_wpwa_toggle_whatsapp', array($this, 'ajax_toggle_whatsapp'));
    }

    /**
     * Add menu to WCFM dashboard
     *
     * @param array $menus Menu items
     * @return array Modified menu items
     */
    public function add_wcfm_menu($menus) {
        $menus['wpwa-dashboard'] = array(
            'label' => __('WhatsApp', 'wp-whatsapp-api'),
            'url'   => wcfm_get_endpoint_url('wpwa-dashboard', '', get_wcfm_page()),
            'icon'  => 'fab fa-whatsapp',
            'priority' => 80
        );
        
        return $menus;
    }
    
    /**
     * Load WCFM view
     *
     * @param array $views Views
     * @return array Modified views
     */
    public function load_wcfm_view($views) {
        $views['wpwa-dashboard'] = array(
            'label' => __('WhatsApp', 'wp-whatsapp-api'),
            'callback' => array($this, 'render_dashboard')
        );
        
        return $views;
    }

    /**
     * Add menu to Dokan dashboard
     *
     * @param array $urls Menu items
     * @return array Modified menu items
     */
    public function add_dokan_menu($urls) {
        $urls['wpwa-dashboard'] = array(
            'title' => __('WhatsApp', 'wp-whatsapp-api'),
            'icon'  => '<i class="fa fa-whatsapp"></i>',
            'url'   => dokan_get_navigation_url('wpwa-dashboard'),
            'pos'   => 70
        );
        
        return $urls;
    }

    /**
     * Load Dokan template
     *
     * @param array $query_vars Query variables
     */
    public function load_dokan_template($query_vars) {
        if (isset($query_vars['wpwa-dashboard'])) {
            $this->load_scripts();
            $this->load_styles();
            $this->render_dashboard();
        }
    }

    /**
     * Add menu to WC Vendors dashboard
     *
     * @param array $pages Pages
     * @return array Modified pages
     */
    public function add_wcv_menu($pages) {
        $pages['wpwa_dashboard'] = array(
            'title' => __('WhatsApp', 'wp-whatsapp-api'),
            'slug'  => 'wpwa-dashboard',
            'func'  => array($this, 'render_dashboard'),
            'priority' => 70
        );
        
        return $pages;
    }

    /**
     * Register WC Vendors endpoint
     */
    public function wcv_dashboard_endpoint() {
        add_rewrite_endpoint('wpwa-dashboard', EP_ROOT);
    }

    /**
     * Get available message templates
     * 
     * @return array Message templates data
     */
    private function get_message_templates() {
        $template_manager = new WPWA_Template_Manager();
        $templates = $template_manager->get_templates_for_vendor(get_current_user_id());
        $formatted_templates = array();
        
        if (!empty($templates) && is_array($templates)) {
            foreach ($templates as $template) {
                if (isset($template->id)) {
                    $formatted_templates[$template->id] = array(
                        'id' => $template->id,
                        'name' => $template->name,
                        'content' => $template->content
                    );
                }
            }
        }
        
        return $formatted_templates;
    }

    /**
     * Load scripts for the vendor dashboard
     */
    public function load_scripts() {
        wp_enqueue_script('wpwa-vendor-js', WPWA_ASSETS_URL . 'js/vendor-dashboard.js', array('jquery'), WPWA_VERSION, true);
        
        // Add localized variables for JavaScript
        wp_localize_script('wpwa-vendor-js', 'wpwa', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_nonce'),
            'templates' => $this->get_message_templates(),
            'i18n' => array(
                'confirm_disconnect' => __('Are you sure you want to disconnect your WhatsApp session?', 'wp-whatsapp-api'),
                'connecting' => __('Connecting...', 'wp-whatsapp-api'),
                'scan_qr' => __('Please scan the QR code with WhatsApp on your phone', 'wp-whatsapp-api'),
                'session_created' => __('Session created successfully', 'wp-whatsapp-api'),
                'session_failed' => __('Failed to create session', 'wp-whatsapp-api'),
                'sending_message' => __('Sending...', 'wp-whatsapp-api'),
                'message_sent' => __('Test message sent successfully', 'wp-whatsapp-api'),
                'message_failed' => __('Failed to send test message', 'wp-whatsapp-api'),
                'refresh_status' => __('Refreshing status...', 'wp-whatsapp-api'),
                'loading_orders' => __('Loading orders...', 'wp-whatsapp-api'),
            )
        ));
    }
    
    /**
     * Load styles
     */
    public function load_styles() {
        wp_enqueue_style('wpwa-vendor-css', WPWA_ASSETS_URL . 'css/vendor-dashboard.css', array(), WPWA_VERSION);
    }
    /**
     * Render vendor dashboard
     */
    public function render_dashboard() {
        $user_id = get_current_user_id();
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        $session_status = get_user_meta($user_id, 'wpwa_session_status', true);
        $session_name = get_user_meta($user_id, 'wpwa_session_name', true);
        $session_created = get_user_meta($user_id, 'wpwa_session_created', true);
        $whatsapp_enabled = get_user_meta($user_id, 'wpwa_enable_whatsapp', true) === '1';
        
        // Status label
        $status_labels = array(
            'initializing' => __('Initializing', 'wp-whatsapp-api'),
            'qr_ready' => __('QR Code Ready', 'wp-whatsapp-api'),
            'authenticated' => __('Authenticated', 'wp-whatsapp-api'),
            'disconnected' => __('Disconnected', 'wp-whatsapp-api'),
            'failed' => __('Failed', 'wp-whatsapp-api'),
        );
        
        $status_label = isset($status_labels[$session_status]) ? $status_labels[$session_status] : __('Not Connected', 'wp-whatsapp-api');
        $status_class = 'wpwa-status-' . sanitize_html_class($session_status ?: 'none');
        
        // Active session?
        $has_session = !empty($client_id) && !empty($session_name);
        ?>
        
        <div class="wpwa-vendor-dashboard">
            
            <div class="wpwa-enable-toggle">
                <label for="wpwa_enable_whatsapp">
                    <input type="checkbox" id="wpwa_enable_whatsapp" <?php checked($whatsapp_enabled); ?> />
                    <?php _e('Enable WhatsApp integration', 'wp-whatsapp-api'); ?>
                </label>
            </div>
            
            <div class="wpwa-cards-container">
                <div class="wpwa-card">
                    
                        <!-- Connected Session -->
                        <div class="wpwa-session-info">
                            <div class="wpwa-session-status">
                                <?php if ($has_session): ?>
                                <h3><?php echo esc_html($session_name); ?></h3>
                                <span class="<?php echo $status_class; ?>"><?php echo esc_html($status_label); ?></span>
                                <?php else: ?>
                                <p><?php _e('No active WhatsApp session', 'wp-whatsapp-api'); ?></p>
                                <?php endif; ?>
                            </div>
                            
                                <div class="wpwa-session-created">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session_created)); ?>
                                </div>
                            
                            <div class="wpwa-session-actions">
                                <?php if ($has_session): ?>
                                <button type="button" id="wpwa_check_session" class="button">
                                    <?php _e('Check Status', 'wp-whatsapp-api'); ?>
                                </button>
                                
                                <button type="button" id="wpwa_disconnect_session" class="button">
                                    <?php _e('Disconnect', 'wp-whatsapp-api'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Test Message Form -->
                        <div class="wpwa-test-message">
                            
                            <div class="wpwa-form-group">
                                <input type="text" id="wpwa_test_phone" placeholder="e.g. +1234567890" />
                                <label><?php _e('Phone Number', 'wp-whatsapp-api'); ?></label>
                            </div>
                            
                            <div class="wpwa-form-group">
                                <textarea id="wpwa_test_message" rows="3" placeholder="<?php _e('Enter your test message here...', 'wp-whatsapp-api'); ?>"></textarea>
                                <label><?php _e('Message', 'wp-whatsapp-api'); ?></label>
                            </div>
                            
                            <button type="button" id="wpwa_send_test" class="button button-primary">
                                <?php _e('Send Test Message', 'wp-whatsapp-api'); ?>
                            </button>
                            
                            <div id="wpwa_test_result"></div>
                        </div>
                    
                        <!-- Create New Session -->
                        <div class="wpwa-create-session">
                            
                            <div class="wpwa-form-group">
                                <input type="text" id="wpwa_session_name" placeholder="<?php _e('Enter session name', 'wp-whatsapp-api'); ?>" />
                                <label><?php _e('Session Name', 'wp-whatsapp-api'); ?></label>
                            </div>
                            
                            <button type="button" id="wpwa_create_session" class="button button-primary">
                                <?php _e('Connect WhatsApp Account', 'wp-whatsapp-api'); ?>
                            </button>
                            
                            <div class="wpwa-qr-container" id="wpwa_qr_container" style="display: none;">
                                <div id="wpwa_qr_status"></div>
                            </div>
                        </div>
                </div>
                
                    <div class="wpwa-card">
                        
                        <div class="wpwa-recent-orders" id="wpwa_recent_orders">
                        </div>
                        
                    </div>
            </div>
        </div>
        <?php
    }

    /**
     * Ajax handler for creating a new session
     */
    public function ajax_create_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $session_name = sanitize_text_field($_POST['session_name']);
        
        if (empty($session_name)) {
            wp_send_json_error(array('message' => __('Session name is required', 'wp-whatsapp-api')));
        }
        
        // Call API to create session
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        $response = $wp_whatsapp_api->api_client->post('/sessions', array(
            'name' => $session_name,
            'vendor_id' => $user_id
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        if (!isset($response['client_id']) || !isset($response['qr_code'])) {
            wp_send_json_error(array('message' => __('Invalid API response', 'wp-whatsapp-api')));
        }
        
        // Save session info to user meta
        update_user_meta($user_id, 'wpwa_session_client_id', $response['client_id']);
        update_user_meta($user_id, 'wpwa_session_name', $session_name);
        update_user_meta($user_id, 'wpwa_session_status', 'qr_ready');
        update_user_meta($user_id, 'wpwa_session_created', current_time('mysql'));
        
        // Enable WhatsApp integration by default
        update_user_meta($user_id, 'wpwa_enable_whatsapp', '1');
        
        wp_send_json_success(array(
            'qr_code' => $response['qr_code'],
            'client_id' => $response['client_id']
        ));
    }

    /**
     * Ajax handler for checking session status
     */
    public function ajax_check_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        // Call API to check session
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        $response = $wp_whatsapp_api->api_client->get('/sessions/' . $client_id);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        if (!isset($response['status'])) {
            wp_send_json_error(array('message' => __('Invalid API response', 'wp-whatsapp-api')));
        }
        
        // Update session status in user meta
        update_user_meta($user_id, 'wpwa_session_status', $response['status']);
        
        wp_send_json_success(array(
            'status' => $response['status'],
            'status_label' => $this->get_status_label($response['status'])
        ));
    }

    /**
     * Ajax handler for disconnecting a session
     */
    public function ajax_disconnect_session() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        // Call API to disconnect session
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/logout', array());
        
        // Even if the API call fails, clear the local session
        delete_user_meta($user_id, 'wpwa_session_client_id');
        delete_user_meta($user_id, 'wpwa_session_name');
        delete_user_meta($user_id, 'wpwa_session_status');
        
        wp_send_json_success(array(
            'message' => __('Session disconnected successfully', 'wp-whatsapp-api')
        ));
    }

    /**
     * Ajax handler for sending a test message
     */
    public function ajax_send_test_message() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('No active session found', 'wp-whatsapp-api')));
        }
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Phone number is required', 'wp-whatsapp-api')));
        }
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message is required', 'wp-whatsapp-api')));
        }
        
        // Format phone number (remove spaces, +, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Call API to send message
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->api_client)) {
            wp_send_json_error(array('message' => __('API client not available', 'wp-whatsapp-api')));
        }
        
        $response = $wp_whatsapp_api->api_client->post('/sessions/' . $client_id . '/messages/send', array(
            'recipient' => $phone,
            'message' => $message
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Test message sent successfully', 'wp-whatsapp-api')
        ));
    }

    /**
     * Ajax handler for getting recent orders
     */
    public function ajax_get_recent_orders() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        // Get recent orders from WhatsApp
        $args = array(
            'meta_key' => '_wpwa_vendor_id',
            'meta_value' => $vendor_id,
            'meta_compare' => '=',
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $orders_query = new WP_Query($args);
        $orders = $orders_query->posts;
        
        if (empty($orders)) {
            wp_send_json_success(array(
                'html' => '<p>' . __('No WhatsApp orders found.', 'wp-whatsapp-api') . '</p>'
            ));
            return;
        }
        
        $html = '<table class="wpwa-orders-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Order ID', 'wp-whatsapp-api') . '</th>';
        $html .= '<th>' . __('Date', 'wp-whatsapp-api') . '</th>';
        $html .= '<th>' . __('Customer', 'wp-whatsapp-api') . '</th>';
        $html .= '<th>' . __('Status', 'wp-whatsapp-api') . '</th>';
        $html .= '<th>' . __('Total', 'wp-whatsapp-api') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) continue;
            
            $html .= '<tr>';
            $html .= '<td><a href="' . $this->get_order_url($order->get_id()) . '">#' . $order->get_order_number() . '</a></td>';
            $html .= '<td>' . $order->get_date_created()->date_i18n(get_option('date_format')) . '</td>';
            $html .= '<td>' . $order->get_formatted_billing_full_name() . '</td>';
            $html .= '<td><span class="order-status status-' . sanitize_html_class($order->get_status()) . '">' . wc_get_order_status_name($order->get_status()) . '</span></td>';
            $html .= '<td>' . $order->get_formatted_order_total() . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Ajax handler for toggling WhatsApp integration
     */
    public function ajax_toggle_whatsapp() {
        check_ajax_referer('wpwa_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        
        update_user_meta($user_id, 'wpwa_enable_whatsapp', $enabled ? '1' : '0');
        
        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled 
                ? __('WhatsApp integration has been enabled', 'wp-whatsapp-api')
                : __('WhatsApp integration has been disabled', 'wp-whatsapp-api')
        ));
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
        
        return false;
    }

    /**
     * Get order URL based on marketplace plugin
     *
     * @param int $order_id Order ID
     * @return string Order URL
     */
    private function get_order_url($order_id) {
        // WCFM
        if (function_exists('wcfm_get_endpoint_url')) {
            return wcfm_get_endpoint_url('orders-details', $order_id, get_wcfm_page());
        }
        
        // Dokan
        if (function_exists('dokan_get_navigation_url')) {
            return add_query_arg(array('order_id' => $order_id), dokan_get_navigation_url('orders'));
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && function_exists('WCVendors_Pro_Dashboard::get_dashboard_page_url')) {
            return WCVendors_Pro_Dashboard::get_dashboard_page_url('order', array('wcv-order' => $order_id));
        }
        
        // Default to admin URL
        return admin_url('post.php?post=' . absint($order_id) . '&action=edit');
    }

    /**
     * Get status label from status code
     *
     * @param string $status Status code
     * @return string Status label
     */
    private function get_status_label($status) {
        $status_labels = array(
            'initializing' => __('Initializing', 'wp-whatsapp-api'),
            'qr_ready' => __('QR Code Ready', 'wp-whatsapp-api'),
            'authenticated' => __('Authenticated', 'wp-whatsapp-api'),
            'disconnected' => __('Disconnected', 'wp-whatsapp-api'),
            'failed' => __('Failed', 'wp-whatsapp-api'),
        );
        
        return isset($status_labels[$status]) ? $status_labels[$status] : __('Unknown', 'wp-whatsapp-api');
    }
}
?>
