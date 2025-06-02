<?php
/**
 * WPWA Template Manager Class
 *
 * Handles message templates for WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Template Manager
 */
class WPWA_Template_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_wpwa_vendor_get_templates', array($this, 'ajax_get_templates'));
        add_action('wp_ajax_wpwa_vendor_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_wpwa_vendor_delete_template', array($this, 'ajax_delete_template'));
        
        // Admin settings
        add_action('wpwa_admin_settings_after_api', array($this, 'render_global_templates_settings'));
        add_action('wpwa_admin_save_settings', array($this, 'save_global_templates'));
        
        // Hooks for automatic messaging
        add_action('woocommerce_order_status_changed', array($this, 'maybe_send_order_status_message'), 10, 4);
    }
    
    /**
     * Get available templates for a vendor
     *
     * @param int $vendor_id Vendor ID
     * @return array List of templates
     */
    public function get_vendor_templates($vendor_id) {
        // Get vendor-specific templates
        $vendor_templates = get_user_meta($vendor_id, 'wpwa_message_templates', true);
        $vendor_templates = is_array($vendor_templates) ? $vendor_templates : array();
        
        // Get global templates
        $global_templates = get_option('wpwa_global_templates', array());
        
        // Merge templates, with vendor templates overriding global ones with the same ID
        $all_templates = $global_templates;
        
        foreach ($vendor_templates as $template) {
            $template_id = isset($template['id']) ? $template['id'] : '';
            
            if ($template_id) {
                $all_templates[$template_id] = $template;
            }
        }
        
        return $all_templates;
    }
    
    /**
     * AJAX handler for getting message templates
     */
    public function ajax_get_templates() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        $templates = $this->get_vendor_templates($vendor_id);
        
        wp_send_json_success(array('templates' => $templates));
    }
    
    /**
     * AJAX handler for saving a message template
     */
    public function ajax_save_template() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
        $template_content = isset($_POST['template_content']) ? sanitize_textarea_field($_POST['template_content']) : '';
        
        if (empty($template_name) || empty($template_content)) {
            wp_send_json_error(array('message' => __('Template name and content are required', 'wp-whatsapp-api')));
        }
        
        // Get existing templates
        $vendor_templates = get_user_meta($vendor_id, 'wpwa_message_templates', true);
        $vendor_templates = is_array($vendor_templates) ? $vendor_templates : array();
        
        // Generate ID if new template
        if (empty($template_id)) {
            $template_id = 'vendor_' . $vendor_id . '_' . uniqid();
        }
        
        // Update or add template
        $vendor_templates[$template_id] = array(
            'id' => $template_id,
            'name' => $template_name,
            'content' => $template_content,
            'updated_at' => current_time('mysql')
        );
        
        // Save templates
        update_user_meta($vendor_id, 'wpwa_message_templates', $vendor_templates);
        
        wp_send_json_success(array(
            'message' => __('Template saved successfully', 'wp-whatsapp-api'),
            'template_id' => $template_id
        ));
    }
    
    /**
     * AJAX handler for deleting a message template
     */
    public function ajax_delete_template() {
        check_ajax_referer('wpwa_vendor_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $vendor_id = $this->get_vendor_id($user_id);
        
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor', 'wp-whatsapp-api')));
        }
        
        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';
        
        if (empty($template_id)) {
            wp_send_json_error(array('message' => __('Template ID is required', 'wp-whatsapp-api')));
        }
        
        // Check if this is a global template
        $global_templates = get_option('wpwa_global_templates', array());
        
        if (isset($global_templates[$template_id])) {
            // Can't delete global templates, but can hide them
            $vendor_templates = get_user_meta($vendor_id, 'wpwa_message_templates', true);
            $vendor_templates = is_array($vendor_templates) ? $vendor_templates : array();
            
            $vendor_templates[$template_id] = array(
                'id' => $template_id,
                'hidden' => true
            );
            
            update_user_meta($vendor_id, 'wpwa_message_templates', $vendor_templates);
            
            wp_send_json_success(array(
                'message' => __('Global template hidden', 'wp-whatsapp-api')
            ));
            return;
        }
        
        // Delete vendor template
        $vendor_templates = get_user_meta($vendor_id, 'wpwa_message_templates', true);
        
        if (is_array($vendor_templates) && isset($vendor_templates[$template_id])) {
            unset($vendor_templates[$template_id]);
            update_user_meta($vendor_id, 'wpwa_message_templates', $vendor_templates);
            
            wp_send_json_success(array(
                'message' => __('Template deleted successfully', 'wp-whatsapp-api')
            ));
        } else {
            wp_send_json_error(array('message' => __('Template not found', 'wp-whatsapp-api')));
        }
    }
    
    /**
     * Render global templates settings on admin page
     */
    public function render_global_templates_settings() {
        // Get global templates
        $global_templates = get_option('wpwa_global_templates', array());
        
        // Default templates if none exist
        if (empty($global_templates)) {
            $global_templates = $this->get_default_templates();
        }
        ?>
        <h2><?php _e('Global Message Templates', 'wp-whatsapp-api'); ?></h2>
        <p><?php _e('Create message templates that all vendors can use. You can use placeholders like {customer_name}, {order_number}, etc.', 'wp-whatsapp-api'); ?></p>
        
        <table class="form-table" id="wpwa-global-templates-table">
            <tbody>
                <tr>
                    <th><?php _e('Templates', 'wp-whatsapp-api'); ?></th>
                    <td>
                        <div id="wpwa-templates-container">
                            <?php foreach ($global_templates as $id => $template) : ?>
                                <div class="wpwa-template" data-id="<?php echo esc_attr($id); ?>">
                                    <div class="wpwa-template-header">
                                        <input type="text" name="wpwa_global_templates[<?php echo esc_attr($id); ?>][name]" 
                                            value="<?php echo esc_attr($template['name']); ?>" 
                                            placeholder="<?php esc_attr_e('Template Name', 'wp-whatsapp-api'); ?>" 
                                            class="wpwa-template-name" />
                                        
                                        <div class="wpwa-template-actions">
                                            <button type="button" class="button wpwa-delete-template">
                                                <?php _e('Delete', 'wp-whatsapp-api'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="wpwa-template-content">
                                        <textarea name="wpwa_global_templates[<?php echo esc_attr($id); ?>][content]" 
                                            rows="4" class="large-text"><?php echo esc_textarea($template['content']); ?></textarea>
                                    </div>
                                    <input type="hidden" name="wpwa_global_templates[<?php echo esc_attr($id); ?>][id]" value="<?php echo esc_attr($id); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button" id="wpwa-add-template">
                            <?php _e('Add Template', 'wp-whatsapp-api'); ?>
                        </button>
                        
                        <div class="wpwa-template-variables">
                            <h4><?php _e('Available Variables:', 'wp-whatsapp-api'); ?></h4>
                            <ul>
                                <li><code>{customer_name}</code> - <?php _e('Customer\'s full name', 'wp-whatsapp-api'); ?></li>
                                <li><code>{order_number}</code> - <?php _e('Order number', 'wp-whatsapp-api'); ?></li>
                                <li><code>{order_status}</code> - <?php _e('Current order status', 'wp-whatsapp-api'); ?></li>
                                <li><code>{order_total}</code> - <?php _e('Order total amount', 'wp-whatsapp-api'); ?></li>
                                <li><code>{order_items}</code> - <?php _e('List of ordered items', 'wp-whatsapp-api'); ?></li>
                                <li><code>{shop_name}</code> - <?php _e('Your shop name', 'wp-whatsapp-api'); ?></li>
                                <li><code>{store_url}</code> - <?php _e('Your store URL', 'wp-whatsapp-api'); ?></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Automatic Messages', 'wp-whatsapp-api'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Automatic Messages', 'wp-whatsapp-api'); ?></legend>
                            
                            <?php
                            $auto_messages = get_option('wpwa_auto_messages', array());
                            $order_statuses = wc_get_order_statuses();
                            
                            foreach ($order_statuses as $status => $label) :
                                $status = str_replace('wc-', '', $status);
                                $enabled = isset($auto_messages[$status]['enabled']) ? $auto_messages[$status]['enabled'] : false;
                                $template_id = isset($auto_messages[$status]['template_id']) ? $auto_messages[$status]['template_id'] : '';
                            ?>
                                <div class="wpwa-auto-message">
                                    <label>
                                        <input type="checkbox" name="wpwa_auto_messages[<?php echo esc_attr($status); ?>][enabled]" value="1" <?php checked($enabled); ?> />
                                        <?php printf(__('Send message when order status changes to %s', 'wp-whatsapp-api'), $label); ?>
                                    </label>
                                    
                                    <div class="wpwa-auto-message-template" style="margin-left: 24px;<?php echo $enabled ? '' : ' display:none;'; ?>">
                                        <select name="wpwa_auto_messages[<?php echo esc_attr($status); ?>][template_id]">
                                            <option value=""><?php _e('Select template', 'wp-whatsapp-api'); ?></option>
                                            <?php foreach ($global_templates as $id => $template) : ?>
                                                <option value="<?php echo esc_attr($id); ?>" <?php selected($template_id, $id); ?>>
                                                    <?php echo esc_html($template['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <script id="wpwa-template-row-template" type="text/template">
            <div class="wpwa-template" data-id="{{id}}">
                <div class="wpwa-template-header">
                    <input type="text" name="wpwa_global_templates[{{id}}][name]" 
                        value="" 
                        placeholder="<?php esc_attr_e('Template Name', 'wp-whatsapp-api'); ?>" 
                        class="wpwa-template-name" />
                    
                    <div class="wpwa-template-actions">
                        <button type="button" class="button wpwa-delete-template">
                            <?php _e('Delete', 'wp-whatsapp-api'); ?>
                        </button>
                    </div>
                </div>
                <div class="wpwa-template-content">
                    <textarea name="wpwa_global_templates[{{id}}][content]" 
                        rows="4" class="large-text"></textarea>
                </div>
                <input type="hidden" name="wpwa_global_templates[{{id}}][id]" value="{{id}}">
            </div>
        </script>
        
        <style>
            #wpwa-templates-container {
                margin-bottom: 20px;
            }
            .wpwa-template {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                background: #f9f9f9;
                border-radius: 3px;
            }
            .wpwa-template-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                background: #f0f0f0;
                border-bottom: 1px solid #ddd;
            }
            .wpwa-template-name {
                flex: 1;
                margin-right: 10px;
            }
            .wpwa-template-content {
                padding: 15px;
            }
            .wpwa-template-actions {
                display: flex;
                gap: 5px;
            }
            .wpwa-template-variables {
                margin-top: 20px;
                background: #f9f9f9;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .wpwa-template-variables h4 {
                margin-top: 0;
            }
            .wpwa-template-variables ul {
                margin-bottom: 0;
            }
            .wpwa-auto-message {
                margin-bottom: 10px;
            }
            .wpwa-auto-message select {
                margin-top: 5px;
                min-width: 300px;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                // Add new template
                $('#wpwa-add-template').on('click', function() {
                    var template = $('#wpwa-template-row-template').html();
                    var id = 'global_' + new Date().getTime();
                    
                    template = template.replace(/{{id}}/g, id);
                    $('#wpwa-templates-container').append(template);
                });
                
                // Delete template
                $(document).on('click', '.wpwa-delete-template', function() {
                    if (confirm('<?php esc_attr_e('Are you sure you want to delete this template?', 'wp-whatsapp-api'); ?>')) {
                        $(this).closest('.wpwa-template').remove();
                    }
                });
                
                // Toggle automatic message template select
                $('input[name^="wpwa_auto_messages"]').on('change', function() {
                    var isChecked = $(this).prop('checked');
                    $(this).closest('.wpwa-auto-message').find('.wpwa-auto-message-template').toggle(isChecked);
                });
            });
        </script>
        <?php
    }
    
    /**
     * Save global templates settings
     *
     * @param array $settings Settings to save
     */
    public function save_global_templates($settings) {
        if (isset($_POST['wpwa_global_templates']) && is_array($_POST['wpwa_global_templates'])) {
            $templates = array();
            
            foreach ($_POST['wpwa_global_templates'] as $id => $template) {
                if (empty($template['name']) || empty($template['content'])) {
                    continue;
                }
                
                $templates[$id] = array(
                    'id' => sanitize_text_field($template['id']),
                    'name' => sanitize_text_field($template['name']),
                    'content' => sanitize_textarea_field($template['content']),
                    'updated_at' => current_time('mysql')
                );
            }
            
            update_option('wpwa_global_templates', $templates);
        }
        
        if (isset($_POST['wpwa_auto_messages']) && is_array($_POST['wpwa_auto_messages'])) {
            $auto_messages = array();
            
            foreach ($_POST['wpwa_auto_messages'] as $status => $settings) {
                $auto_messages[sanitize_text_field($status)] = array(
                    'enabled' => isset($settings['enabled']) ? (bool)$settings['enabled'] : false,
                    'template_id' => isset($settings['template_id']) ? sanitize_text_field($settings['template_id']) : ''
                );
            }
            
            update_option('wpwa_auto_messages', $auto_messages);
        }
    }
    
    /**
     * Maybe send order status message
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public function maybe_send_order_status_message($order_id, $old_status, $new_status, $order) {
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
        
        // Check if this is a WhatsApp order
        $is_whatsapp_order = get_post_meta($order_id, '_wpwa_order', true) === 'yes';
        $session_id = get_post_meta($order_id, '_wpwa_session_id', true);
        
        if (!$is_whatsapp_order || empty($session_id)) {
            return;
        }
        
        // Get customer phone
        $phone = $order->get_billing_phone();
        
        if (empty($phone)) {
            return;
        }
        
        // Format phone number
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Get message content
        $template = $global_templates[$template_id];
        $message = $this->parse_template($template['content'], array('order' => $order));
        
        // Send message via Message Manager
        global $wp_whatsapp_api;
        
        if (!$wp_whatsapp_api || !isset($wp_whatsapp_api->message_manager)) {
            return;
        }
        
        // Use the dedicated Message Manager to send the message
        $wp_whatsapp_api->message_manager->send_text_message($phone, $message, $session_id);
    }
    
    /**
     * Parse template with variables
     *
     * @param string $template Template content
     * @param array $data Data for variables
     * @return string Parsed template
     */
    public function parse_template($template, $data = array()) {
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
        
        // Replace customer variables
        if (isset($data['customer'])) {
            $customer = $data['customer'];
            
            $customer_replacements = array(
                '{customer_name}' => isset($customer['full_name']) ? $customer['full_name'] : '',
                '{customer_first_name}' => isset($customer['first_name']) ? $customer['first_name'] : '',
                '{customer_last_name}' => isset($customer['last_name']) ? $customer['last_name'] : '',
                '{customer_phone}' => isset($customer['phone']) ? $customer['phone'] : '',
            );
            
            $template = str_replace(array_keys($customer_replacements), array_values($customer_replacements), $template);
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
     * Get default templates
     *
     * @return array Default templates
     */
    private function get_default_templates() {
        return array(
            'order_confirmation' => array(
                'id' => 'order_confirmation',
                'name' => __('Order Confirmation', 'wp-whatsapp-api'),
                'content' => __(
                    "🎉 *Thank you for your order!*\n\n" .
                    "*Order #:* {order_number}\n" .
                    "*Total:* {order_total}\n\n" .
                    "*Your order details:*\n{order_items}\n\n" .
                    "We will process your order soon. Thank you for shopping with {shop_name}!",
                    'wp-whatsapp-api'
                ),
                'updated_at' => current_time('mysql')
            ),
            'order_shipped' => array(
                'id' => 'order_shipped',
                'name' => __('Order Shipped', 'wp-whatsapp-api'),
                'content' => __(
                    "📦 *Your order has been shipped!*\n\n" .
                    "*Order #:* {order_number}\n\n" .
                    "Your order has been shipped and is on its way to you. Thank you for shopping with {shop_name}!",
                    'wp-whatsapp-api'
                ),
                'updated_at' => current_time('mysql')
            ),
            'order_status_update' => array(
                'id' => 'order_status_update',
                'name' => __('Order Status Update', 'wp-whatsapp-api'),
                'content' => __(
                    "📝 *Order Status Update*\n\n" .
                    "*Order #:* {order_number}\n" .
                    "*New Status:* {order_status}\n\n" .
                    "Thank you for shopping with {shop_name}!",
                    'wp-whatsapp-api'
                ),
                'updated_at' => current_time('mysql')
            ),
            'welcome_message' => array(
                'id' => 'welcome_message',
                'name' => __('Welcome Message', 'wp-whatsapp-api'),
                'content' => __(
                    "👋 *Welcome to {shop_name}!*\n\n" .
                    "Thank you for connecting with us on WhatsApp. We're here to help with any questions about our products or your orders.\n\n" .
                    "Visit our store: {store_url}",
                    'wp-whatsapp-api'
                ),
                'updated_at' => current_time('mysql')
            ),
            'follow_up' => array(
                'id' => 'follow_up',
                'name' => __('Order Follow-up', 'wp-whatsapp-api'),
                'content' => __(
                    "👋 *Hello {customer_name}!*\n\n" .
                    "How are you enjoying your recent purchase from {shop_name}?\n\n" .
                    "We'd love to hear your feedback. Feel free to let us know if you have any questions or comments about your order.\n\n" .
                    "Thank you for choosing {shop_name}!",
                    'wp-whatsapp-api'
                ),
                'updated_at' => current_time('mysql')
            )
        );
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