<?php
/**
 * Plugin Name: WhatsApp Integration for WooCommerce
 * Plugin URI: https://example.com/wp-whatsapp-integration
 * Description: Integrate WhatsApp API with WooCommerce for vendors and store management
 * Version: 1.2.7
 * Author: Your Company
 * Author URI: https://example.com
 * Text Domain: wp-whatsapp-api
 * Domain Path: /languages
 * WC requires at least: 4.0.0
 * WC tested up to: 7.9.0
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WPWA_VERSION', '1.2.7');
define('WPWA_PATH', plugin_dir_path(__FILE__));
define('WPWA_URL', plugin_dir_url(__FILE__));
define('WPWA_ASSETS_URL', WPWA_URL . 'assets/');

// Main plugin class
class WP_WhatsApp_API {
    /**
     * Singleton instance
     *
     * @var WP_WhatsApp_API
     */
    private static $instance = null;
    
    /**
     * API Client instance
     *
     * @var WPWA_API_Client
     */
    public $api_client = null;
    
    /**
     * Auth Manager instance
     *
     * @var WPWA_Auth_Manager
     */
    public $auth_manager = null;
    
    /**
     * Message Manager instance
     *
     * @var WPWA_Message_Manager
     */
    public $message_manager = null;
    
    /**
     * Logger instance
     *
     * @var WPWA_Logger
     */
    public $logger = null;
    
    /**
     * Vendor Session Manager instance
     *
     * @var WPWA_Vendor_Session_Manager
     */
    public $vendor_session_manager = null;
    
    /**
     * Product Sync Manager instance
     *
     * @var WPWA_Product_Sync_Manager
     */
    public $product_sync_manager = null;
    
    /**
     * WooCommerce Integration instance
     *
     * @var WPWA_WooCommerce
     */
    public $woocommerce_integration = null;

    /**
     * Get singleton instance
     *
     * @return WP_WhatsApp_API
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
        // Load autoloader
        $this->load_autoloader();
        
        // Load core classes and dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init();
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load autoloader
     */
    private function load_autoloader() {
        // Autoloader has been replaced with direct includes
        // No action needed here
        return;
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load required files
        require_once WPWA_PATH . 'includes/class-wpwa-auth-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-api-client.php';
        require_once WPWA_PATH . 'includes/class-wpwa-message-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-logger.php';
        require_once WPWA_PATH . 'includes/class-wpwa-vendor-session-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-product-sync-manager.php';
        
        // Initialize core components
        $this->logger = new WPWA_Logger();
        $this->auth_manager = new WPWA_Auth_Manager();
        $this->api_client = new WPWA_API_Client($this->auth_manager);
        
        // Load template manager before message manager
        require_once WPWA_PATH . 'includes/class-wpwa-template-manager.php';
        $template_manager = new WPWA_Template_Manager();
        
        $this->message_manager = new WPWA_Message_Manager($this->api_client, $template_manager, $this->logger);
        $this->vendor_session_manager = new WPWA_Vendor_Session_Manager($this->api_client, $this->logger);
        $this->product_sync_manager = new WPWA_Product_Sync_Manager($this->api_client, $this->logger);
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_text_domain'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
        
        // Load shortcodes
        require_once WPWA_PATH . 'includes/class-wpwa-shortcodes.php';
        
        // Load extended shortcodes (vendor dashboard)
        require_once WPWA_PATH . 'includes/class-wpwa-shortcodes-extended.php';
        
        // Check if WooCommerce is active
        if ($this->is_woocommerce_active()) {
            $this->init_woocommerce_integration();
        }
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'register_cron_schedules'));
        
        // Make plugin available globally
        global $wp_whatsapp_api;
        $wp_whatsapp_api = $this;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Maybe create tables
        if (method_exists($this->logger, 'create_tables')) {
            $this->logger->create_tables();
        }
        
        // Generate JWT secret if not exists
        if (method_exists($this->auth_manager, 'generate_jwt_secret')) {
            $this->auth_manager->generate_jwt_secret();
        }
        
        // Clear any scheduled hooks
        wp_clear_scheduled_hook('wpwa_check_sessions');
        
        // Schedule session checker
        if (!wp_next_scheduled('wpwa_check_sessions')) {
            wp_schedule_event(time(), 'hourly', 'wpwa_check_sessions');
        }
        
        // Trigger version-specific updates
        $this->maybe_update();
        
        // Set version
        update_option('wpwa_version', WPWA_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('wpwa_check_sessions');
        wp_clear_scheduled_hook('wpwa_sync_product');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain
     */
    public function load_text_domain() {
        load_plugin_textdomain('wp-whatsapp-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_menu_page(
            __('WhatsApp API', 'wp-whatsapp-api'),
            __('WhatsApp API', 'wp-whatsapp-api'),
            'manage_options',
            'wpwa-settings',
            array($this, 'render_settings_page'),
            'dashicons-whatsapp',
            85
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Include settings page template
        include WPWA_PATH . 'admin/settings-page.php';
    }

    /**
     * Register admin scripts and styles
     */
    public function register_admin_scripts() {
        // Admin styles
        wp_register_style(
            'wpwa-admin',
            WPWA_ASSETS_URL . 'css/admin-style.css',
            array(),
            WPWA_VERSION
        );
        
        // Register jQuery UI
        wp_register_style(
            'jquery-ui',
            'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
            array(),
            '1.13.2'
        );
        
        // Admin scripts
        wp_register_script(
            'wpwa-admin',
            WPWA_ASSETS_URL . 'js/admin-script.js',
            array('jquery', 'jquery-ui-tabs', 'jquery-ui-dialog'),
            WPWA_VERSION,
            true
        );
        
        // Localize admin script
        wp_localize_script('wpwa-admin', 'wpwaAdminVars', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwa_nonce')
        ));
        
        // Only enqueue on plugin pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'wpwa') !== false) {
            wp_enqueue_style('jquery-ui');
            wp_enqueue_style('wpwa-admin');
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('wpwa-admin');
        }
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Initialize WooCommerce integration
     */
    public function init_woocommerce_integration() {
        // Add WooCommerce specific features
        $woocommerce_file = WPWA_PATH . 'includes/class-wpwa-woocommerce.php';
        
        if (file_exists($woocommerce_file)) {
            require_once $woocommerce_file;
        } else {
            // Log the missing file and continue without WooCommerce integration
            if ($this->logger) {
                $this->logger->error('WooCommerce integration file not found', array(
                    'file' => $woocommerce_file
                ));
            }
            
            // Add admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo sprintf(
                    __('WhatsApp API: WooCommerce integration is disabled. Please contact support if you need this feature.', 'wp-whatsapp-api')
                );
                echo '</p></div>';
            });
        }
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Load AJAX handlers
        require_once WPWA_PATH . 'includes/class-wpwa-ajax-handler-admin.php';
        require_once WPWA_PATH . 'includes/class-wpwa-ajax-handler-frontend.php';
        
        // Initialize AJAX handlers
        new WPWA_AJAX_Handler_Admin();
        new WPWA_AJAX_Handler_Frontend();
    }

    /**
     * Register custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_cron_schedules($schedules) {
        // Add a 5-minute schedule
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 minutes', 'wp-whatsapp-api')
        );
        
        return $schedules;
    }

    /**
     * Maybe update plugin
     */
    private function maybe_update() {
        $current_version = get_option('wpwa_version', '0.0.0');
        
        // Skip if this is a new installation
        if ($current_version === '0.0.0') {
            return;
        }
        
        // Run version-specific updates
        if (version_compare($current_version, '1.1.0', '<')) {
            // Update from pre-1.1.0 to 1.1.0+
            $this->update_to_1_1_0();
        }
        
        if (version_compare($current_version, '1.2.0', '<')) {
            // Update from pre-1.2.0 to 1.2.0+
            $this->update_to_1_2_0();
        }
    }

    /**
     * Update to version 1.1.0
     */
    private function update_to_1_1_0() {
        // Example version-specific updates
        // Re-generate JWT secret
        if (method_exists($this->auth_manager, 'generate_jwt_secret')) {
            $this->auth_manager->generate_jwt_secret();
        }
    }
    
    /**
     * Update to version 1.2.0
     */
    private function update_to_1_2_0() {
        // Migrate vendor data if needed
        // Create new database tables for logs if needed
        if (method_exists($this->logger, 'create_tables')) {
            $this->logger->create_tables();
        }
    }
}

// Initialize plugin
function wp_whatsapp_api() {
    return WP_WhatsApp_API::get_instance();
}

// Start the plugin
wp_whatsapp_api();
