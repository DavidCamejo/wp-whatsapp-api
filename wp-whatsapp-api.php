<?php
/**
 * Plugin Name: WhatsApp API for WooCommerce
 * Plugin URI: https://example.com/wp-whatsapp-api
 * Description: Connect your WooCommerce store to WhatsApp API for automated messaging and customer interactions
 * Version: 1.1.19
 * Author: WhatsApp API Team
 * Author URI: https://example.com
 * Text Domain: wp-whatsapp-api
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 7.9
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPWA_VERSION', '1.1.19'); // Updated with role-based JWT token generation and message manager
define('WPWA_FILE', __FILE__);
define('WPWA_PATH', plugin_dir_path(__FILE__));
define('WPWA_URL', plugin_dir_url(__FILE__));
define('WPWA_ASSETS_URL', WPWA_URL . 'assets/');

/**
 * Main plugin class
 */
class WP_WhatsApp_API {
    /**
     * API client instance
     * @var WPWA_API_Client
     */
    public $api_client;

    /**
     * Auth manager instance
     * @var WPWA_Auth_Manager
     */
    public $auth_manager;

    /**
     * Vendor session manager instance
     * @var WPWA_Vendor_Session_Manager
     */
    public $vendor_session_manager;

    /**
     * Product sync manager instance
     * @var WPWA_Product_Sync_Manager
     */
    public $product_sync_manager;

    /**
     * Order manager instance
     * @var WPWA_Order_Manager
     */
    public $order_manager;

    /**
     * Cart manager instance
     * @var WPWA_Cart_Manager
     */
    public $cart_manager;

    /**
     * Customer manager instance
     * @var WPWA_Customer_Manager
     */
    public $customer_manager;

    /**
     * Template manager instance
     * @var WPWA_Template_Manager
     */
    public $template_manager;

    /**
     * Message manager instance
     * @var WPWA_Message_Manager
     */
    public $message_manager;

    /**
     * Order processor instance
     * @var WPWA_Order_Processor
     */
    public $order_processor;

    /**
     * Logger instance
     * @var WPWA_Logger
     */
    public $logger;

    /**
     * AJAX handler instance
     * @var WPWA_AJAX_Handler
     */
    public $ajax_handler;

    /**
     * Usage tracker instance
     * @var WPWA_Usage_Tracker
     */
    public $usage_tracker;

    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->check_woocommerce_dependency()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Store installation date if not already set
        if (!get_option('wpwa_install_date')) {
            update_option('wpwa_install_date', current_time('mysql'));
        }

        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));

        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function check_woocommerce_dependency() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('woocommerce/woocommerce.php');
    }

    /**
     * Show WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WhatsApp API for WooCommerce requires WooCommerce to be installed and active.', 'wp-whatsapp-api'); ?></p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-whatsapp-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Include required files
        $this->includes();

        // Initialize components
        $this->logger = new WPWA_Logger();
        
        $this->auth_manager = new WPWA_Auth_Manager();
        $this->api_client = new WPWA_API_Client($this->auth_manager);
        $this->vendor_session_manager = new WPWA_Vendor_Session_Manager($this->api_client, $this->logger);
        $this->product_sync_manager = new WPWA_Product_Sync_Manager($this->api_client, $this->logger);
        $this->cart_manager = new WPWA_Cart_Manager($this->logger);
        $this->customer_manager = new WPWA_Customer_Manager($this->logger);
        $this->template_manager = new WPWA_Template_Manager();
        $this->message_manager = new WPWA_Message_Manager($this->api_client, $this->template_manager, $this->logger);
        $this->order_processor = new WPWA_Order_Processor($this->logger);
        $this->order_manager = new WPWA_Order_Manager($this->customer_manager, $this->api_client, $this->logger);
        $this->usage_tracker = new WPWA_Usage_Tracker($this->logger);
        $this->ajax_handler = new WPWA_AJAX_Handler();
        
        // Make logger globally available
        $GLOBALS['wpwa_logger'] = $this->logger;

        // Admin includes
        if (is_admin()) {
            $this->admin_includes();
        }

        // Init shortcodes
        if (class_exists('WPWA_Shortcodes')) {
            WPWA_Shortcodes::get_instance();
        }

        // Log plugin initialization
        $this->logger->info('WhatsApp API plugin initialized (v' . WPWA_VERSION . ')');
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once WPWA_PATH . 'includes/class-wpwa-logger.php';
        require_once WPWA_PATH . 'includes/class-wpwa-api-client.php';
        require_once WPWA_PATH . 'includes/class-wpwa-auth-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-vendor-session-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-product-sync-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-cart-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-customer-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-template-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-message-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-order-processor.php';
        require_once WPWA_PATH . 'includes/class-wpwa-order-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-usage-tracker.php';
        require_once WPWA_PATH . 'includes/class-wpwa-ajax-handler.php';

        // Shortcodes
        require_once WPWA_PATH . 'includes/class-wpwa-shortcodes.php';
        require_once WPWA_PATH . 'includes/class-wpwa-ajax-handler-frontend.php';
    }

    /**
     * Include admin files
     */
    private function admin_includes() {
        require_once WPWA_PATH . 'admin/admin-settings.php';
        require_once WPWA_PATH . 'admin/vendor-dashboard.php';
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Store installation date
        update_option('wpwa_install_date', current_time('mysql'));

        // Create necessary directories
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/wpwa-logs');

        // Ensure default options are set
        if (!get_option('wpwa_debug_mode')) {
            update_option('wpwa_debug_mode', '0');
        }

        if (!get_option('wpwa_connection_timeout')) {
            update_option('wpwa_connection_timeout', '30');
        }

        if (!get_option('wpwa_max_retries')) {
            update_option('wpwa_max_retries', '3');
        }

        // Generate default JWT secret if not exists
        if (!get_option('wpwa_jwt_secret') && class_exists('WPWA_Auth_Manager')) {
            $auth_manager = new WPWA_Auth_Manager();
            $auth_manager->generate_jwt_secret();
        }

        // Clear any transients
        delete_transient('wpwa_api_status');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wpwa_daily_sync');
    }
}

// Initialize the plugin
$GLOBALS['wp_whatsapp_api'] = new WP_WhatsApp_API();

// Make the global object accessible
function wpwa() {
    global $wp_whatsapp_api;
    return $wp_whatsapp_api;
}