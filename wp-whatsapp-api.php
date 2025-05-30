<?php
/**
 * Plugin Name: WhatsApp API for WooCommerce
 * Plugin URI: https://example.com/wp-whatsapp-api
 * Description: Unofficial WhatsApp API integration for WooCommerce multivendor marketplaces. Allows vendors to connect WhatsApp, sync product catalogs, and manage orders.
 * Version: 1.1.0
 * Author: Alex
 * Author URI: https://example.com
 * Text Domain: wp-whatsapp-api
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 7.5
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WPWA_VERSION', '1.1.0');
define('WPWA_FILE', __FILE__);
define('WPWA_PATH', plugin_dir_path(__FILE__));
define('WPWA_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class WP_WhatsApp_API {
    /**
     * Latest version available (for update checking)
     * 
     * @var string
     */
    private $latest_version = null;
    
    /**
     * API client instance
     *
     * @var WPWA_API_Client
     */
    public $api_client = null;
    
    /**
     * Auth manager instance
     *
     * @var WPWA_Auth_Manager
     */
    public $auth_manager = null;
    
    /**
     * Vendor session manager instance
     *
     * @var WPWA_Vendor_Session_Manager
     */
    public $vendor_session_manager = null;
    
    /**
     * Logger instance
     *
     * @var WPWA_Logger
     */
    public $logger = null;
    
    /**
     * Class constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->check_woocommerce()) {
            return;
        }
        
        // Load required files
        $this->includes();
        
        // Setup global instances
        $this->setup_globals();
        
        // Register activation hooks
        register_activation_hook(WPWA_FILE, array($this, 'activate'));
        register_deactivation_hook(WPWA_FILE, array($this, 'deactivate'));
        
        // Init actions
        add_action('plugins_loaded', array($this, 'init'), 0);
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        
        // Check for plugin updates
        add_action('admin_init', array($this, 'maybe_display_update_notice'));
        
        // Add menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Settings link
        add_filter('plugin_action_links_' . plugin_basename(WPWA_FILE), array($this, 'add_plugin_links'));
    }
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool True if WooCommerce is active, false otherwise
     */
    private function check_woocommerce() {
        // Don't check during activation since it would prevent our plugin from activating
        if (isset($_GET['action']) && $_GET['action'] == 'activate') {
            return true;
        }
        
        // Always use 'plugins_loaded' with priority higher than 10 to check WooCommerce presence
        if (!did_action('plugins_loaded') || doing_action('plugins_loaded')) {
            // We're too early, defer the check
            add_action('plugins_loaded', array($this, 'verify_woocommerce_dependency'), 20);
            return true;
        }
        
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        if (!is_plugin_active('woocommerce/woocommerce.php') && !class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo esc_html__('WhatsApp API for WooCommerce requires WooCommerce to be installed and activated.', 'wp-whatsapp-api');
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once WPWA_PATH . 'includes/class-wpwa-auth-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-api-client.php';
        require_once WPWA_PATH . 'includes/class-wpwa-vendor-session-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-product-sync-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-order-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-cart-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-logger.php';
        require_once WPWA_PATH . 'includes/class-wpwa-template-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-customer-manager.php';
        require_once WPWA_PATH . 'includes/class-wpwa-order-processor.php';
        require_once WPWA_PATH . 'includes/class-wpwa-ajax-handler.php';
        require_once WPWA_PATH . 'includes/class-wpwa-usage-tracker.php';
        
        // Admin pages
        if (is_admin()) {
            require_once WPWA_PATH . 'admin/admin-settings.php';
            require_once WPWA_PATH . 'admin/vendor-dashboard.php';
        }
    }
    
    /**
     * Setup global instances
     */
    private function setup_globals() {
        global $wpwa_logger, $wpwa_usage_tracker;
        
        // Create global logger instance
        $wpwa_logger = new WPWA_Logger();
        $this->logger = $wpwa_logger;
        
        // Initialize usage tracker
        $wpwa_usage_tracker = new WPWA_Usage_Tracker();
        
        // Create API client
        $api_url = get_option('wpwa_api_url', '');
        $api_key = get_option('wpwa_api_key', '');
        
        if ($api_url && $api_key) {
            $this->api_client = new WPWA_API_Client($api_url, $api_key);
        }
        
        // Create auth manager
        $this->auth_manager = new WPWA_Auth_Manager();
        
        // Create vendor session manager
        $this->vendor_session_manager = new WPWA_Vendor_Session_Manager();
        
        // Make this instance globally available
        global $wp_whatsapp_api;
        $wp_whatsapp_api = $this;
    }
    
    /**
     * Verify WooCommerce dependency at the appropriate time
     * This method will be called after all plugins are loaded
     */
    public function verify_woocommerce_dependency() {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        if (!is_plugin_active('woocommerce/woocommerce.php') && !class_exists('WooCommerce')) {
            // Deactivate our plugin
            deactivate_plugins(plugin_basename(WPWA_FILE));
            
            // Display notice
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo esc_html__('WhatsApp API for WooCommerce has been deactivated because it requires WooCommerce to be installed and activated.', 'wp-whatsapp-api');
                echo '</p></div>';
                
                // If we're on the plugins page, make sure the deactivation is visible
                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            });
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wp-whatsapp-api', false, dirname(plugin_basename(WPWA_FILE)) . '/languages');
        
        // We need to verify WooCommerce is active before initializing components
        // But delay the actual initialization until after 'init' to ensure all WC data is loaded
        if ($this->check_woocommerce()) {
            add_action('init', array($this, 'initialize_components'), 20);
        }
    }
    
    /**
     * Initialize plugin components after WooCommerce is fully loaded
     */
    public function initialize_components() {
        // Make sure WooCommerce is fully loaded before initializing components
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // API URL and key
        $api_url = get_option('wpwa_api_url');
        $api_key = get_option('wpwa_api_key');
        
        // Initialize API client
        if ($api_url && $api_key) {
            $this->api_client = new WPWA_API_Client($api_url, $api_key);
        }
        
        // Create auth manager
        $this->auth_manager = new WPWA_Auth_Manager();
        
        // Create vendor session manager
        $this->vendor_session_manager = new WPWA_Vendor_Session_Manager();
        
        // Initialize components
        new WPWA_Product_Sync_Manager();
        new WPWA_Order_Manager();
        new WPWA_Cart_Manager();
        new WPWA_Template_Manager();
        new WPWA_Customer_Manager();
        new WPWA_Order_Processor();
        new WPWA_AJAX_Handler();
        
        // Make this instance globally available
        global $wp_whatsapp_api;
        $wp_whatsapp_api = $this;
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Set version
        update_option('wpwa_version', WPWA_VERSION);
        
        // Set installation date if not exists
        if (!get_option('wpwa_install_date')) {
            update_option('wpwa_install_date', date('Y-m-d H:i:s'));
        }
        
        // Generate API key if not exists
        if (!get_option('wpwa_api_key')) {
            update_option('wpwa_api_key', $this->generate_api_key());
        }
        
        // Schedule table creation for after WooCommerce is loaded
        add_action('plugins_loaded', array($this, 'delayed_activation_tasks'), 30);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Delayed activation tasks that run after plugins_loaded
     * This ensures WooCommerce is fully loaded before we try to access its functions
     */
    public function delayed_activation_tasks() {
        // Only run if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Create necessary tables
        if (class_exists('WPWA_Cart_Manager')) {
            $cart_manager = new WPWA_Cart_Manager();
            $cart_manager->maybe_create_tables();
        }
        
        if (class_exists('WPWA_Customer_Manager')) {
            $customer_manager = new WPWA_Customer_Manager();
            $customer_manager->maybe_create_tables();
        }
        
        // Add default templates
        if (class_exists('WPWA_Template_Manager')) {
            $template_manager = new WPWA_Template_Manager();
            update_option('wpwa_global_templates', $template_manager->get_default_templates());
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WhatsApp API', 'wp-whatsapp-api'),
            __('WhatsApp API', 'wp-whatsapp-api'),
            'manage_woocommerce',
            'wp-whatsapp-api',
            'wpwa_admin_settings_page',
            'dashicons-format-chat',
            56
        );
        
        add_submenu_page(
            'wp-whatsapp-api',
            __('Settings', 'wp-whatsapp-api'),
            __('Settings', 'wp-whatsapp-api'),
            'manage_woocommerce',
            'wp-whatsapp-api',
            'wpwa_admin_settings_page'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function admin_assets() {
        $screen = get_current_screen();
        
        // Always ensure dashicons are available
        wp_enqueue_style('dashicons');
        
        // Only on plugin admin pages
        if ($screen && strpos($screen->id, 'wp-whatsapp-api') !== false) {
            wp_enqueue_style(
                'wpwa-admin-style',
                WPWA_URL . 'assets/css/admin-style.css',
                array('dashicons'),
                WPWA_VERSION
            );
            
            wp_enqueue_script(
                'wpwa-admin-script',
                WPWA_URL . 'assets/js/admin-script.js',
                array('jquery'),
                WPWA_VERSION,
                true
            );
            
            wp_localize_script('wpwa-admin-script', 'wpwa', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpwa_admin_nonce')
            ));
        }
    }
    
    /**
     * Add plugin action links
     *
     * @param array $links Plugin action links
     * @return array Modified links
     */
    public function add_plugin_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wp-whatsapp-api') . '">' . __('Settings', 'wp-whatsapp-api') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Generate API key
     *
     * @return string Generated API key
     */
    private function generate_api_key() {
        return 'wpwa_' . substr(md5(uniqid(mt_rand(), true)), 0, 32);
    }
    
    /**
     * Check for plugin updates
     * 
     * @return array|bool Update information or false if no update available
     */
    public function check_for_updates() {
        // In a real implementation, this would check an actual remote endpoint
        // For demo purposes, we're simply hardcoding a potential newer version
        $latest_version = '1.2.0'; // Simulate a newer version available
        
        if (version_compare(WPWA_VERSION, $latest_version, '<')) {
            return [
                'version' => $latest_version,
                'url' => 'https://example.com/wp-whatsapp-api/update',
                'package' => 'https://example.com/wp-whatsapp-api/download/1.2.0.zip',
                'requires_wp' => '5.6',
                'tested_wp' => '6.3',
                'last_checked' => time()
            ];
        }
        
        return false;
    }
    
    /**
     * Display update notification in admin
     */
    public function maybe_display_update_notice() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check for updates
        $update_info = $this->check_for_updates();
        
        // If update available, display notice
        if ($update_info) {
            add_action('admin_notices', function() use ($update_info) {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php _e('WhatsApp API Update Available:', 'wp-whatsapp-api'); ?></strong>
                        <?php printf(
                            __('Version %s is available. <a href="%s" target="_blank">View details</a> or <a href="%s">update now</a>.', 'wp-whatsapp-api'),
                            esc_html($update_info['version']),
                            esc_url($update_info['url']),
                            esc_url(admin_url('plugins.php'))
                        ); ?>
                    </p>
                </div>
                <?php
            });
        }
    }
}

// Initialize the plugin
$wp_whatsapp_api = new WP_WhatsApp_API();
