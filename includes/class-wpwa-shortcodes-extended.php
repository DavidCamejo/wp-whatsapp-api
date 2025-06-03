<?php
/**
 * WPWA Shortcodes Extended Class
 *
 * Extends the core shortcodes with additional vendor dashboard functionality
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Shortcodes Extended
 */
class WPWA_Shortcodes_Extended {
    /**
     * Instance of this class
     *
     * @var WPWA_Shortcodes_Extended
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return WPWA_Shortcodes_Extended
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
        // Initialize vendor dashboard shortcode
        require_once WPWA_PATH . 'includes/class-wpwa-vendor-dashboard-shortcode.php';
        require_once WPWA_PATH . 'includes/class-wpwa-vendor-dashboard-ajax.php';
        
        new WPWA_Vendor_Dashboard_Shortcode();
        new WPWA_Vendor_Dashboard_AJAX();
        
        // Hook to modify the main plugin instance to make vendor session manager globally available
        add_action('init', array($this, 'extend_plugin_instance'), 20);
    }
    
    /**
     * Extend the main plugin instance with additional references
     */
    public function extend_plugin_instance() {
        global $wp_whatsapp_api;
        
        // Ensure the plugin instance is available
        if ($wp_whatsapp_api) {
            // Make vendor session manager directly accessible
            if (!isset($wp_whatsapp_api->vendor_session_manager) && isset($wp_whatsapp_api->api_client)) {
                $wp_whatsapp_api->vendor_session_manager = new WPWA_Vendor_Session_Manager(
                    $wp_whatsapp_api->api_client,
                    $wp_whatsapp_api->logger
                );
            }
        }
    }
}

// Initialize the extended shortcodes
WPWA_Shortcodes_Extended::get_instance();