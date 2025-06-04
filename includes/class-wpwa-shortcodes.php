<?php
/**
 * WPWA Shortcodes Class
 *
 * Handles shortcodes for the WhatsApp API integration
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Shortcodes
 */
class WPWA_Shortcodes {
    /**
     * Instance of this class
     *
     * @var WPWA_Shortcodes
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return WPWA_Shortcodes
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register all shortcodes
        add_shortcode('wpwa_frontend_admin', array($this, 'frontend_admin_shortcode'));
        
        // Register scripts and styles for frontend admin
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
    }

    /**
     * Register frontend scripts and styles
     */
    public function register_frontend_assets() {
        // Register jQuery UI - load multiple themes to ensure compatibility
        wp_register_style('jquery-ui-base', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
        wp_register_style('jquery-ui-smoothness', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        
        // Register frontend admin styles
        wp_register_style(
            'wpwa-frontend-admin-css',
            WPWA_ASSETS_URL . 'css/frontend-admin.css',
            array('jquery-ui-base', 'jquery-ui-smoothness'),
            WPWA_VERSION
        );

        // Register frontend admin scripts - ensure jQuery is loaded first
        wp_register_script(
            'wpwa-frontend-admin-js',
            WPWA_ASSETS_URL . 'js/frontend-admin.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-dialog'),
            WPWA_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('wpwa-frontend-admin-js', 'wpwaFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_nonce'),
            'frontendNonce' => wp_create_nonce('wpwa_frontend_nonce'),
            // Debug info for nonce generation
            'debug' => true,
            'texts' => array(
                'validating' => __('Validating API credentials...', 'wp-whatsapp-api'),
                'generateJwtSecret' => __('Generate New JWT Secret', 'wp-whatsapp-api'),
                'generating' => __('Generating...', 'wp-whatsapp-api'),
                'generateApiKey' => __('Generate New API Key', 'wp-whatsapp-api'),
                'saving' => __('Saving settings...', 'wp-whatsapp-api'),
                'saved' => __('Settings saved successfully', 'wp-whatsapp-api'),
                'error' => __('An error occurred', 'wp-whatsapp-api'),
                'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'wp-whatsapp-api')
            )
        ));
    }

    /**
     * Frontend admin panel shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function frontend_admin_shortcode($atts) {
        // Debug output
        error_log('WPWA Frontend Admin shortcode called');
        error_log('WPWA Frontend Admin - JQuery UI version: ' . wp_scripts()->registered['jquery-ui-core']->ver);
        error_log('WPWA Frontend Admin - WordPress version: ' . get_bloginfo('version'));
        error_log('WPWA Frontend Admin - Plugin version: ' . WPWA_VERSION);
        error_log('WPWA Frontend Admin - Tabs available: ' . (wp_script_is('jquery-ui-tabs', 'registered') ? 'yes' : 'no'));
        error_log('WPWA Frontend Admin - jQuery UI core loaded: ' . (wp_script_is('jquery-ui-core', 'enqueued') ? 'yes' : 'no'));
        
        // Load all jQuery UI dependencies in the correct order
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget'); // Required for tabs and other UI components
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-dialog');
        
        // Enqueue both jQuery UI themes to ensure compatibilty
        wp_enqueue_style('jquery-ui-base');
        wp_enqueue_style('jquery-ui-smoothness');
        
        // Also load directly from CDN as a fallback
        wp_enqueue_style('jquery-ui-cdn', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', array(), '1.13.2');
        echo '<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>';
        // Parse attributes
        $atts = shortcode_atts(array(
            'title' => __('WhatsApp API Settings', 'wp-whatsapp-api'),
            'show_tabs' => 'all',  // all, settings, sessions, logs
        ), $atts);
        
        // Check if user has permission to view admin panel
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            return '<div class="wpwa-frontend-error">' . 
                __('You do not have permission to access these settings.', 'wp-whatsapp-api') . 
                '</div>';
        }
        
        // Enqueue required assets
        wp_enqueue_style('wpwa-frontend-admin-css');
        wp_enqueue_script('wpwa-frontend-admin-js');
        
        // Get current settings
        $api_url = get_option('wpwa_api_url', '');
        $api_key = get_option('wpwa_api_key', '');
        $jwt_secret = get_option('wpwa_jwt_secret', '');
        $connection_timeout = get_option('wpwa_connection_timeout', 30);
        $max_retries = get_option('wpwa_max_retries', 3);
        $debug_mode = get_option('wpwa_debug_mode', 0);
        $usage_tracking = get_option('wpwa_usage_tracking', 0);
        $install_date = get_option('wpwa_install_date', '');
        
        // Determine which tabs to show
        $show_tabs = explode(',', $atts['show_tabs']);
        $show_tabs = array_map('trim', $show_tabs);
        $show_all = in_array('all', $show_tabs) || $atts['show_tabs'] === 'all';
        
        // Start output buffer
        ob_start();
        
        // Admin panel container
        echo '<div class="wpwa-frontend-admin-panel">';
        
        // Header
        echo '<div class="wpwa-frontend-admin-header">';
        echo '<h2>' . esc_html($atts['title']) . '</h2>';
        echo '<div class="wpwa-version-info">';
        echo '<span class="wpwa-version">' . __('Version', 'wp-whatsapp-api') . ': ' . esc_html(WPWA_VERSION) . '</span>';
        if ($install_date) {
            echo '<span class="wpwa-install-date">' . __('Installed on', 'wp-whatsapp-api') . ': ' . 
                esc_html(date_i18n(get_option('date_format'), strtotime($install_date))) . '</span>';
        }
        echo '</div>';
        echo '</div>';
        
        // Tabs
        echo '<div id="wpwa-admin-tabs" class="wpwa-tabs">';
        
        // Tab navigation
        echo '<ul class="wpwa-tab-nav">';
        if ($show_all || in_array('settings', $show_tabs)) {
            echo '<li><a href="#wpwa-tab-settings">' . __('Settings', 'wp-whatsapp-api') . '</a></li>';
        }
        if ($show_all || in_array('sessions', $show_tabs)) {
            echo '<li><a href="#wpwa-tab-sessions">' . __('Sessions', 'wp-whatsapp-api') . '</a></li>';
        }
        if ($show_all || in_array('logs', $show_tabs)) {
            echo '<li><a href="#wpwa-tab-logs">' . __('Logs', 'wp-whatsapp-api') . '</a></li>';
        }
        echo '</ul>';
        
        // Settings tab
        if ($show_all || in_array('settings', $show_tabs)) {
            echo '<div id="wpwa-tab-settings">';
            echo '<form id="wpwa-settings-form" class="wpwa-frontend-form">';
            
            // API Settings section
            echo '<div class="wpwa-settings-section">';
            echo '<h3>' . __('API Connection Settings', 'wp-whatsapp-api') . '</h3>';
            
            echo '<div class="wpwa-form-group">';
            echo '<label for="wpwa_api_url">' . __('API Server URL', 'wp-whatsapp-api') . ' <span class="required">*</span></label>';
            echo '<input type="url" id="wpwa_api_url" name="wpwa_api_url" value="' . esc_attr($api_url) . '" required>';
            echo '<p class="wpwa-field-desc">' . __('The URL of your WhatsApp API server', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group">';
            echo '<label for="wpwa_api_key">' . __('API Key', 'wp-whatsapp-api') . ' <span class="required">*</span></label>';
            echo '<div class="wpwa-input-group">';
            echo '<input type="text" id="wpwa_api_key" name="wpwa_api_key" value="' . esc_attr($api_key) . '" required>';
            echo '<button type="button" id="wpwa-generate-api-key" class="wpwa-button wpwa-button-secondary">' . 
                __('Generate', 'wp-whatsapp-api') . '</button>';
            echo '</div>';
            echo '<p class="wpwa-field-desc">' . __('Your authentication key for the API', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group">';
            echo '<label for="wpwa_jwt_secret">' . __('JWT Secret', 'wp-whatsapp-api') . '</label>';
            echo '<div class="wpwa-input-group">';
            echo '<input type="text" id="wpwa_jwt_secret" name="wpwa_jwt_secret" value="' . esc_attr($jwt_secret) . '" readonly>';
            echo '<button type="button" id="wpwa-generate-jwt-secret" class="wpwa-button wpwa-button-secondary">' . 
                __('Generate', 'wp-whatsapp-api') . '</button>';
            echo '</div>';
            echo '<p class="wpwa-field-desc">' . 
                __('The secret key used for JWT token generation. Keep this secure!', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group">';
            echo '<button type="button" id="wpwa-validate-api" class="wpwa-button wpwa-button-secondary">' . 
                __('Test Connection', 'wp-whatsapp-api') . '</button>';
            echo '<span id="wpwa-api-status"></span>';
            echo '</div>';
            
            echo '</div>'; // End API Settings section
            
            // Advanced Settings section
            echo '<div class="wpwa-settings-section">';
            echo '<h3>' . __('Advanced Settings', 'wp-whatsapp-api') . '</h3>';
            
            echo '<div class="wpwa-form-group">';
            echo '<label for="wpwa_connection_timeout">' . __('Connection Timeout', 'wp-whatsapp-api') . '</label>';
            echo '<div class="wpwa-input-with-suffix">';
            echo '<input type="number" id="wpwa_connection_timeout" name="wpwa_connection_timeout" ' . 
                'value="' . esc_attr($connection_timeout) . '" min="5" max="120" required>';
            echo '<span class="wpwa-input-suffix">' . __('seconds', 'wp-whatsapp-api') . '</span>';
            echo '</div>';
            echo '<p class="wpwa-field-desc">' . 
                __('Timeout for API requests in seconds (5-120)', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group">';
            echo '<label for="wpwa_max_retries">' . __('Max Retries', 'wp-whatsapp-api') . '</label>';
            echo '<input type="number" id="wpwa_max_retries" name="wpwa_max_retries" ' . 
                'value="' . esc_attr($max_retries) . '" min="0" max="10" required>';
            echo '<p class="wpwa-field-desc">' . 
                __('Maximum number of retry attempts for failed API requests (0-10)', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group wpwa-checkbox-group">';
            echo '<input type="checkbox" id="wpwa_debug_mode" name="wpwa_debug_mode" value="1" ' . 
                checked($debug_mode, 1, false) . '>';
            echo '<label for="wpwa_debug_mode">' . __('Enable Debug Mode', 'wp-whatsapp-api') . '</label>';
            echo '<p class="wpwa-field-desc">' . 
                __('Log detailed debugging information', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '<div class="wpwa-form-group wpwa-checkbox-group">';
            echo '<input type="checkbox" id="wpwa_usage_tracking" name="wpwa_usage_tracking" value="1" ' . 
                checked($usage_tracking, 1, false) . '>';
            echo '<label for="wpwa_usage_tracking">' . __('Enable Usage Tracking', 'wp-whatsapp-api') . '</label>';
            echo '<p class="wpwa-field-desc">' . 
                __('Allow anonymous usage data collection to help improve the plugin', 'wp-whatsapp-api') . '</p>';
            echo '</div>';
            
            echo '</div>'; // End Advanced Settings section
            
            // Save button
            echo '<div class="wpwa-form-actions">';
            echo '<button type="submit" id="wpwa-save-settings" class="wpwa-button wpwa-button-primary">' . 
                __('Save Settings', 'wp-whatsapp-api') . '</button>';
            echo '<div id="wpwa-settings-messages" class="wpwa-messages"></div>';
            echo '</div>';
            
            echo '</form>';
            echo '</div>'; // End Settings tab
        }
        
        // Sessions tab
        if ($show_all || in_array('sessions', $show_tabs)) {
            echo '<div id="wpwa-tab-sessions">';
            echo '<div id="wpwa-sessions-content">';
            echo '<div class="wpwa-loading">' . __('Loading sessions...', 'wp-whatsapp-api') . '</div>';
            echo '</div>';
            echo '</div>'; // End Sessions tab
        }
        
        // Logs tab
        if ($show_all || in_array('logs', $show_tabs)) {
            echo '<div id="wpwa-tab-logs">';
            echo '<div class="wpwa-logs-actions">';
            echo '<button type="button" id="wpwa-refresh-logs" class="wpwa-button wpwa-button-secondary">' . 
                __('Refresh Logs', 'wp-whatsapp-api') . '</button>';
            echo '<button type="button" id="wpwa-clear-logs" class="wpwa-button wpwa-button-danger">' . 
                __('Clear All Logs', 'wp-whatsapp-api') . '</button>';
            echo '</div>';
            echo '<div id="wpwa-logs-container" class="wpwa-logs-table"></div>';
            echo '</div>'; // End Logs tab
        }
        
        echo '</div>'; // End tabs
        
        // Footer
        echo '<div class="wpwa-frontend-admin-footer">';
        echo '<p>' . sprintf(
            __('WhatsApp API for WooCommerce - %1$sDocumentation%2$s', 'wp-whatsapp-api'),
            '<a href="https://example.com/docs" target="_blank">',
            '</a>'
        ) . '</p>';
        echo '</div>';
        
        echo '</div>'; // End admin panel container
        
        // Get the contents of the buffer and clean it
        $content = ob_get_clean();
        
        return $content;
    }

    /**
     * Log activity for debugging
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $level = 'info') {
        global $wpwa_logger;
        
        if ($wpwa_logger) {
            if (method_exists($wpwa_logger, $level)) {
                $wpwa_logger->$level($message);
            } else {
                $wpwa_logger->log($message, $level);
            }
        }
    }
}