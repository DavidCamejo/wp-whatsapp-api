<?php
/**
 * Admin Settings Page
 * 
 * Handles the admin settings interface for WhatsApp API Integration plugin.
 * 
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings Class
 */
class WPWA_Admin_Settings {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        // Main menu item
        add_menu_page(
            __('WhatsApp API', 'wp-whatsapp-api'),
            __('WhatsApp API', 'wp-whatsapp-api'),
            'manage_options',
            'wpwa-settings',
            array($this, 'render_settings_page'),
            'dashicons-whatsapp',
            58
        );

        // Settings submenu
        add_submenu_page(
            'wpwa-settings',
            __('Settings', 'wp-whatsapp-api'),
            __('Settings', 'wp-whatsapp-api'),
            'manage_options',
            'wpwa-settings',
            array($this, 'render_settings_page')
        );

        // Session manager submenu
        add_submenu_page(
            'wpwa-settings',
            __('Session Manager', 'wp-whatsapp-api'),
            __('Session Manager', 'wp-whatsapp-api'),
            'manage_options',
            'wpwa-sessions',
            array($this, 'render_sessions_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'wpwa-settings',
            __('Logs', 'wp-whatsapp-api'),
            __('Logs', 'wp-whatsapp-api'),
            'manage_options',
            'wpwa-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wpwa_settings', 'wpwa_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting('wpwa_settings', 'wpwa_jwt_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => wp_generate_password(32, false, false),
        ));

        register_setting('wpwa_settings', 'wpwa_debug_mode', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => '0',
        ));

        register_setting('wpwa_settings', 'wpwa_notification_template', array(
            'type' => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default' => "Hello {{customer_name}},\n\nYour order #{{order_id}} status has been updated to: {{order_status}}.\n\nThank you for shopping with us!",
        ));
        
        register_setting('wpwa_settings', 'wpwa_allow_tracking', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => '0',
        ));

        // Add settings sections
        add_settings_section(
            'wpwa_api_settings',
            __('API Settings', 'wp-whatsapp-api'),
            array($this, 'render_api_settings_section'),
            'wpwa_settings'
        );

        add_settings_section(
            'wpwa_message_settings',
            __('Message Templates', 'wp-whatsapp-api'),
            array($this, 'render_message_settings_section'),
            'wpwa_settings'
        );
        
        add_settings_section(
            'wpwa_advanced_settings',
            __('Advanced Settings', 'wp-whatsapp-api'),
            array($this, 'render_advanced_settings_section'),
            'wpwa_settings'
        );

        // Add settings fields
        add_settings_field(
            'wpwa_api_url',
            __('API URL', 'wp-whatsapp-api'),
            array($this, 'render_api_url_field'),
            'wpwa_settings',
            'wpwa_api_settings'
        );

        add_settings_field(
            'wpwa_jwt_secret',
            __('JWT Secret', 'wp-whatsapp-api'),
            array($this, 'render_jwt_secret_field'),
            'wpwa_settings',
            'wpwa_api_settings'
        );

        add_settings_field(
            'wpwa_debug_mode',
            __('Debug Mode', 'wp-whatsapp-api'),
            array($this, 'render_debug_mode_field'),
            'wpwa_settings',
            'wpwa_api_settings'
        );

        add_settings_field(
            'wpwa_notification_template',
            __('Order Status Notification', 'wp-whatsapp-api'),
            array($this, 'render_notification_template_field'),
            'wpwa_settings',
            'wpwa_message_settings'
        );
        
        add_settings_field(
            'wpwa_connection_timeout',
            __('Connection Timeout', 'wp-whatsapp-api'),
            array($this, 'render_timeout_field'),
            'wpwa_settings',
            'wpwa_advanced_settings'
        );
        
        add_settings_field(
            'wpwa_max_retries',
            __('Max Retries', 'wp-whatsapp-api'),
            array($this, 'render_retries_field'),
            'wpwa_settings',
            'wpwa_advanced_settings'
        );
        
        add_settings_field(
            'wpwa_allow_tracking',
            __('Usage Tracking', 'wp-whatsapp-api'),
            array($this, 'render_tracking_field'),
            'wpwa_settings',
            'wpwa_advanced_settings'
        );
    }

    /**
     * Render API settings section
     */
    public function render_api_settings_section($args) {
        echo '<p>' . __('Configure your WhatsApp API connection settings.', 'wp-whatsapp-api') . '</p>';
    }

    /**
     * Render message settings section
     */
    public function render_message_settings_section($args) {
        echo '<p>' . __('Configure message templates used for WhatsApp notifications.', 'wp-whatsapp-api') . '</p>';
        echo '<p>' . __('Available placeholders: {{customer_name}}, {{order_id}}, {{order_status}}, {{store_name}}, {{order_total}}', 'wp-whatsapp-api') . '</p>';
    }
    
    /**
     * Render the advanced settings section
     */
    public function render_advanced_settings_section($args) {
        echo '<p>' . __('Advanced configuration options for the WhatsApp API integration', 'wp-whatsapp-api') . '</p>';
    }
    
    /**
     * Render connection timeout field
     */
    public function render_timeout_field($args) {
        $value = get_option('wpwa_connection_timeout', 30);
        ?>
        <input type="number" id="wpwa_connection_timeout" name="wpwa_connection_timeout" value="<?php echo esc_attr($value); ?>" min="5" max="120" step="1" />
        <p class="description"><?php _e('Connection timeout in seconds for API requests.', 'wp-whatsapp-api'); ?></p>
        <?php
    }
    
    /**
     * Render max retries field
     */
    public function render_retries_field($args) {
        $value = get_option('wpwa_max_retries', 3);
        ?>
        <input type="number" id="wpwa_max_retries" name="wpwa_max_retries" value="<?php echo esc_attr($value); ?>" min="0" max="10" step="1" />
        <p class="description"><?php _e('Maximum number of retry attempts for failed API requests.', 'wp-whatsapp-api'); ?></p>
        <?php
    }
    
    public function render_tracking_field($args) {
        $value = get_option('wpwa_allow_tracking', '0');
        echo '<input type="checkbox" id="wpwa_allow_tracking" name="wpwa_allow_tracking" value="1" ' . checked('1', $value, false) . ' />';
        echo '<label for="wpwa_allow_tracking">' . __('Help improve this plugin by sharing non-sensitive usage data', 'wp-whatsapp-api') . '</label>';
        echo '<p class="description">' . __('We collect data about how you use the plugin, which features you use the most, and basic information about your WordPress setup. No personal data is tracked or stored.', 'wp-whatsapp-api') . '</p>';
    }

    /**
     * Render API URL field
     */
    public function render_api_url_field($args) {
        $api_url = get_option('wpwa_api_url', '');
        ?>
        <input type="url" id="wpwa_api_url" name="wpwa_api_url" class="regular-text" value="<?php echo esc_attr($api_url); ?>" />
        <p class="description"><?php _e('The URL of your WhatsApp API server.', 'wp-whatsapp-api'); ?></p>
        <?php
    }

    /**
     * Render JWT Secret field
     */
    public function render_jwt_secret_field($args) {
        $jwt_secret = get_option('wpwa_jwt_secret', '');
        ?>
        <input type="text" id="wpwa_jwt_secret" name="wpwa_jwt_secret" class="regular-text" value="<?php echo esc_attr($jwt_secret); ?>" />
        <button type="button" id="wpwa_generate_jwt_secret" class="button"><?php _e('Generate New Secret', 'wp-whatsapp-api'); ?></button>
        <p class="description"><?php _e('Secret key used for JWT token generation. Keep this secure.', 'wp-whatsapp-api'); ?></p>
        <?php
    }

    /**
     * Render Debug Mode field
     */
    public function render_debug_mode_field($args) {
        $debug_mode = get_option('wpwa_debug_mode', '0');
        ?>
        <label for="wpwa_debug_mode">
            <input type="checkbox" id="wpwa_debug_mode" name="wpwa_debug_mode" value="1" <?php checked($debug_mode, '1'); ?> />
            <?php _e('Enable debug mode (logs additional information)', 'wp-whatsapp-api'); ?>
        </label>
        <?php
    }

    /**
     * Render notification template field
     */
    public function render_notification_template_field($args) {
        $template = get_option('wpwa_notification_template', '');
        ?>
        <textarea id="wpwa_notification_template" name="wpwa_notification_template" rows="6" class="large-text"><?php echo esc_textarea($template); ?></textarea>
        <p class="description"><?php _e('Template used for order status update notifications.', 'wp-whatsapp-api'); ?></p>
        <?php
    }

    /**
     * Sanitize checkbox values
     */
    public function sanitize_checkbox($input) {
        return isset($input) ? '1' : '0';
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add success message if settings updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error('wpwa_messages', 'wpwa_message', __('Settings saved.', 'wp-whatsapp-api'), 'updated');
        }
        ?>
        <div class="wrap">
            <div class="wpwa-admin-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <span class="wpwa-version">
                    <?php 
                    echo sprintf(__('Version %s', 'wp-whatsapp-api'), WPWA_VERSION);
                    $install_date = get_option('wpwa_install_date');
                    if ($install_date) {
                        echo ' | ' . sprintf(__('Installed: %s', 'wp-whatsapp-api'), date_i18n(get_option('date_format'), strtotime($install_date)));
                    }
                    ?>
                </span>
            </div>
            
            <?php settings_errors('wpwa_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('wpwa_settings');
                do_settings_sections('wpwa_settings');
                submit_button(__('Save Settings', 'wp-whatsapp-api'));
                ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Connection Test', 'wp-whatsapp-api'); ?></h2>
            <p><?php _e('Test your WhatsApp API connection to ensure it\'s working properly.', 'wp-whatsapp-api'); ?></p>
            <button type="button" id="wpwa_test_connection" class="button button-primary"><?php _e('Test Connection', 'wp-whatsapp-api'); ?></button>
            <span id="wpwa_connection_result" style="margin-left: 10px;"></span>
        </div>
        <?php
    }

    /**
     * Render the sessions management page
     */
    public function render_sessions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get all vendor users
        $vendors = $this->get_all_vendors();
        global $wp_whatsapp_api;
        ?>
        <div class="wrap">
            <div class="wpwa-admin-header">
                <h1><?php _e('WhatsApp Session Manager', 'wp-whatsapp-api'); ?></h1>
                <span class="wpwa-version"><?php echo sprintf(__('Version %s', 'wp-whatsapp-api'), WPWA_VERSION); ?></span>
            </div>
            
            <div class="wpwa-session-manager">
                <div class="wpwa-card">
                    <h2><?php _e('Create New Session', 'wp-whatsapp-api'); ?></h2>
                    <div class="wpwa-form-group">
                        <label for="wpwa_vendor"><?php _e('Select Vendor', 'wp-whatsapp-api'); ?></label>
                        <select id="wpwa_vendor" name="wpwa_vendor">
                            <option value=""><?php _e('-- Select Vendor --', 'wp-whatsapp-api'); ?></option>
                            <?php foreach ($vendors as $vendor) : ?>
                                <option value="<?php echo esc_attr($vendor['id']); ?>"><?php echo esc_html($vendor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="wpwa-form-group">
                        <label for="wpwa_session_name"><?php _e('Session Name', 'wp-whatsapp-api'); ?></label>
                        <input type="text" id="wpwa_session_name" name="wpwa_session_name" placeholder="<?php esc_attr_e('e.g. Store WhatsApp', 'wp-whatsapp-api'); ?>" />
                    </div>
                    
                    <div class="wpwa-form-actions">
                        <button type="button" id="wpwa_create_session" class="button button-primary"><?php _e('Create Session', 'wp-whatsapp-api'); ?></button>
                    </div>
                </div>
                
                <div class="wpwa-card" id="wpwa_qr_container" style="display: none;">
                    <h2><?php _e('Scan QR Code', 'wp-whatsapp-api'); ?></h2>
                    <div class="wpwa-qr-code">
                        <img id="wpwa_qr_code" src="" alt="<?php esc_attr_e('QR Code', 'wp-whatsapp-api'); ?>" />
                    </div>
                    <p><?php _e('Scan this QR code with your WhatsApp mobile app to link this session.', 'wp-whatsapp-api'); ?></p>
                    <div id="wpwa_qr_status"></div>
                </div>
                
                <div class="wpwa-card">
                    <h2><?php _e('Active Sessions', 'wp-whatsapp-api'); ?></h2>
                    <div id="wpwa_sessions_table">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Vendor', 'wp-whatsapp-api'); ?></th>
                                    <th><?php _e('Session Name', 'wp-whatsapp-api'); ?></th>
                                    <th><?php _e('Status', 'wp-whatsapp-api'); ?></th>
                                    <th><?php _e('Created', 'wp-whatsapp-api'); ?></th>
                                    <th><?php _e('Actions', 'wp-whatsapp-api'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Load active sessions
                                foreach ($vendors as $vendor) {
                                    $user_id = $vendor['user_id'];
                                    $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
                                    $session_status = get_user_meta($user_id, 'wpwa_session_status', true);
                                    $session_name = get_user_meta($user_id, 'wpwa_session_name', true);
                                    $session_created = get_user_meta($user_id, 'wpwa_session_created', true);
                                    
                                    if (!$client_id || !$session_name) {
                                        continue;
                                    }
                                    
                                    $status_class = 'wpwa-status-' . sanitize_html_class($session_status);
                                    $status_label = $this->get_status_label($session_status);
                                    ?>
                                    <tr data-vendor-id="<?php echo esc_attr($vendor['id']); ?>" data-client-id="<?php echo esc_attr($client_id); ?>">
                                        <td><?php echo esc_html($vendor['name']); ?></td>
                                        <td><?php echo esc_html($session_name); ?></td>
                                        <td><span class="wpwa-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                                        <td><?php echo $session_created ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session_created))) : '—'; ?></td>
                                        <td>
                                            <button type="button" class="button wpwa-check-status"><?php _e('Check Status', 'wp-whatsapp-api'); ?></button>
                                            <button type="button" class="button wpwa-disconnect"><?php _e('Disconnect', 'wp-whatsapp-api'); ?></button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <?php if (empty($client_id)) : ?>
                                <tr class="wpwa-no-sessions">
                                    <td colspan="5"><?php _e('No active sessions found.', 'wp-whatsapp-api'); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wp_whatsapp_api;
        $logs = $wp_whatsapp_api->logger->get_logs_for_admin(100);
        ?>
        <div class="wrap">
            <div class="wpwa-admin-header">
                <h1><?php _e('WhatsApp API Logs', 'wp-whatsapp-api'); ?></h1>
                <span class="wpwa-version"><?php echo sprintf(__('Version %s', 'wp-whatsapp-api'), WPWA_VERSION); ?></span>
            </div>
            
            <div class="wpwa-log-filters">
                <select id="wpwa_log_level">
                    <option value=""><?php _e('All Levels', 'wp-whatsapp-api'); ?></option>
                    <option value="INFO"><?php _e('Info', 'wp-whatsapp-api'); ?></option>
                    <option value="WARNING"><?php _e('Warning', 'wp-whatsapp-api'); ?></option>
                    <option value="ERROR"><?php _e('Error', 'wp-whatsapp-api'); ?></option>
                    <option value="DEBUG"><?php _e('Debug', 'wp-whatsapp-api'); ?></option>
                </select>
                
                <input type="text" id="wpwa_log_search" placeholder="<?php esc_attr_e('Search logs...', 'wp-whatsapp-api'); ?>" />
                
                <button type="button" id="wpwa_clear_logs" class="button"><?php _e('Clear Logs', 'wp-whatsapp-api'); ?></button>
                <button type="button" id="wpwa_refresh_logs" class="button"><?php _e('Refresh', 'wp-whatsapp-api'); ?></button>
            </div>
            
            <div id="wpwa_logs_table">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'wp-whatsapp-api'); ?></th>
                            <th><?php _e('Level', 'wp-whatsapp-api'); ?></th>
                            <th><?php _e('Message', 'wp-whatsapp-api'); ?></th>
                            <th><?php _e('Details', 'wp-whatsapp-api'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                        <tr class="wpwa-no-logs">
                            <td colspan="4"><?php _e('No logs found.', 'wp-whatsapp-api'); ?></td>
                        </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : 
                                $level_class = 'wpwa-log-' . strtolower($log['level']);
                            ?>
                            <tr class="<?php echo $level_class; ?>" data-level="<?php echo esc_attr($log['level']); ?>">
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><span class="wpwa-log-level"><?php echo esc_html($log['level']); ?></span></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td>
                                    <?php if (!empty($log['context'])) : ?>
                                    <button type="button" class="wpwa-view-context button button-small"><?php _e('View Details', 'wp-whatsapp-api'); ?></button>
                                    <pre class="wpwa-context-data" style="display: none;"><?php echo esc_html(wp_json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                                    <?php else : ?>
                                    —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
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

    /**
     * Get all vendors from supported marketplace plugins
     *
     * @return array List of vendor data
     */
    private function get_all_vendors() {
        $vendors = array();
        
        // WCFM
        if (function_exists('wcfm_get_vendor_list')) {
            $wcfm_vendors = wcfm_get_vendor_list();
            
            foreach ($wcfm_vendors as $vendor_id => $vendor_data) {
                $user_id = $vendor_data['id'];
                $vendors[] = array(
                    'id' => $vendor_id,
                    'user_id' => $user_id,
                    'name' => $vendor_data['name'],
                    'email' => $vendor_data['email'],
                    'source' => 'wcfm'
                );
            }
        }
        
        // Dokan
        if (function_exists('dokan_get_sellers')) {
            $args = array(
                'number' => -1,
                'status' => 'approved',
            );
            
            $dokan_vendors = dokan_get_sellers($args);
            
            if (!empty($dokan_vendors['users'])) {
                foreach ($dokan_vendors['users'] as $vendor) {
                    $store_info = dokan_get_store_info($vendor->ID);
                    $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : $vendor->display_name;
                    
                    $vendors[] = array(
                        'id' => $vendor->ID,
                        'user_id' => $vendor->ID,
                        'name' => $store_name,
                        'email' => $vendor->user_email,
                        'source' => 'dokan'
                    );
                }
            }
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && method_exists('WCV_Vendors', 'get_vendors')) {
            $wc_vendors = WCV_Vendors::get_vendors();
            
            foreach ($wc_vendors as $vendor_id) {
                $user_data = get_userdata($vendor_id);
                $store_name = get_user_meta($vendor_id, 'pv_shop_name', true);
                
                if ($user_data) {
                    $vendors[] = array(
                        'id' => $vendor_id,
                        'user_id' => $vendor_id,
                        'name' => $store_name ?: $user_data->display_name,
                        'email' => $user_data->user_email,
                        'source' => 'wcvendors'
                    );
                }
            }
        }
        
        return $vendors;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'wpwa-') === false) {
            return;
        }
        
        wp_enqueue_style('wpwa-admin-css', WPWA_ASSETS_URL . 'css/admin.css', array(), WPWA_VERSION);
        wp_enqueue_script('wpwa-admin-js', WPWA_ASSETS_URL . 'js/admin.js', array('jquery'), WPWA_VERSION, true);
        
        // Add localized variables for JavaScript
        wp_localize_script('wpwa-admin-js', 'wpwa_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_admin_nonce'),
            'i18n' => array(
                'confirm_disconnect' => __('Are you sure you want to disconnect this WhatsApp session? This cannot be undone.', 'wp-whatsapp-api'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs? This cannot be undone.', 'wp-whatsapp-api'),
                'confirm_generate_jwt' => __('Are you sure you want to generate a new JWT secret? This will invalidate all existing tokens.', 'wp-whatsapp-api'),
                'connecting' => __('Connecting...', 'wp-whatsapp-api'),
                'scan_qr' => __('Please scan the QR code with WhatsApp on your phone', 'wp-whatsapp-api'),
                'session_created' => __('Session created successfully', 'wp-whatsapp-api'),
                'session_failed' => __('Failed to create session', 'wp-whatsapp-api'),
                'testing_connection' => __('Testing connection...', 'wp-whatsapp-api'),
                'connection_success' => __('Connection successful!', 'wp-whatsapp-api'),
                'connection_failed' => __('Connection failed: ', 'wp-whatsapp-api'),
                'refresh_status' => __('Refreshing status...', 'wp-whatsapp-api'),
                'no_sessions' => __('No active sessions found.', 'wp-whatsapp-api'),
                'no_logs' => __('No logs found.', 'wp-whatsapp-api'),
            )
        ));
    }
}

// Initialize admin settings
new WPWA_Admin_Settings();