<?php

/**
 * Usage Tracker
 *
 * Handles tracking plugin usage statistics with user opt-in functionality.
 *
 * @package WP_WhatsApp_API
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class responsible for tracking plugin usage statistics
 */
class WPWA_Usage_Tracker {

    /**
     * Tracking endpoint URL
     * 
     * @var string
     */
    private $tracking_url = 'https://tracking.wpwhatsappapi.com/v1/collect';

    /**
     * Tracking data parameters
     * 
     * @var array
     */
    private $tracking_data = array();

    /**
     * Class constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'maybe_schedule_tracking'));
        add_action('wpwa_usage_tracking_event', array($this, 'send_tracking_data'));
        add_action('wpwa_api_call_made', array($this, 'track_api_call'), 10, 2);
        add_action('wpwa_session_created', array($this, 'track_session_created'));
        add_action('wpwa_order_processed', array($this, 'track_order_processed'));
    }

    /**
     * Schedule tracking if user has opted in
     */
    public function maybe_schedule_tracking() {
        // Only schedule if user has opted in
        if ($this->is_tracking_allowed()) {
            // Schedule weekly tracking event if not already scheduled
            if (!wp_next_scheduled('wpwa_usage_tracking_event')) {
                wp_schedule_event(time(), 'weekly', 'wpwa_usage_tracking_event');
            }
        } else {
            // Clear scheduled event if user has opted out
            wp_clear_scheduled_hook('wpwa_usage_tracking_event');
        }
    }

    /**
     * Check if tracking is allowed
     * 
     * @return bool
     */
    public function is_tracking_allowed() {
        return get_option('wpwa_allow_tracking', '0') === '1';
    }

    /**
     * Track API call
     * 
     * @param string $endpoint The API endpoint called
     * @param bool $success Whether the API call was successful
     */
    public function track_api_call($endpoint, $success) {
        if (!$this->is_tracking_allowed()) {
            return;
        }

        $api_calls = get_option('wpwa_api_call_stats', array());
        
        if (!isset($api_calls[$endpoint])) {
            $api_calls[$endpoint] = array(
                'total' => 0,
                'success' => 0,
                'failed' => 0
            );
        }
        
        $api_calls[$endpoint]['total']++;
        if ($success) {
            $api_calls[$endpoint]['success']++;
        } else {
            $api_calls[$endpoint]['failed']++;
        }
        
        update_option('wpwa_api_call_stats', $api_calls);
    }

    /**
     * Track session creation
     */
    public function track_session_created() {
        if (!$this->is_tracking_allowed()) {
            return;
        }
        
        $session_count = get_option('wpwa_session_count', 0);
        update_option('wpwa_session_count', $session_count + 1);
    }

    /**
     * Track order processed
     */
    public function track_order_processed() {
        if (!$this->is_tracking_allowed()) {
            return;
        }
        
        $order_count = get_option('wpwa_order_count', 0);
        update_option('wpwa_order_count', $order_count + 1);
    }

    /**
     * Send tracking data to the server
     */
    public function send_tracking_data() {
        if (!$this->is_tracking_allowed()) {
            return;
        }

        // Collect tracking data
        $this->prepare_tracking_data();

        // Send data
        $response = wp_remote_post(
            $this->tracking_url,
            array(
                'body' => json_encode($this->tracking_data),
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                )
            )
        );

        if (is_wp_error($response)) {
            // Log the error but don't disrupt the user experience
            if (class_exists('WPWA_Logger')) {
                WPWA_Logger::log('Usage tracking failed: ' . $response->get_error_message(), 'error');
            }
        } else {
            // Reset counters for next tracking period
            $this->reset_periodic_counters();
        }
    }

    /**
     * Prepare tracking data to send
     */
    private function prepare_tracking_data() {
        global $wpdb;
        
        $this->tracking_data = array(
            'site_url' => get_bloginfo('url'),
            'site_name' => get_bloginfo('name'),
            'site_language' => get_bloginfo('language'),
            'plugin_version' => WPWA_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
            'multisite' => is_multisite(),
            'woocommerce_active' => class_exists('WooCommerce'),
            'woocommerce_version' => class_exists('WooCommerce') ? WC()->version : '',
            'dokan_active' => class_exists('WeDevs_Dokan'),
            'wcfm_active' => class_exists('WCFM'),
            'active_theme' => get_template(),
            'debug_mode' => get_option('wpwa_debug_mode', '0'),
            'api_calls' => get_option('wpwa_api_call_stats', array()),
            'session_count' => get_option('wpwa_session_count', 0),
            'order_count' => get_option('wpwa_order_count', 0),
        );
        
        // Add information about active plugins
        $active_plugins = get_option('active_plugins', array());
        $plugins = array();
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            
            $plugins[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
            );
        }
        
        $this->tracking_data['active_plugins'] = $plugins;
        
        // Add unique site identifier (anonymized)
        $site_hash = md5(get_bloginfo('url') . get_bloginfo('admin_email'));
        $this->tracking_data['site_id'] = $site_hash;
        
        // Apply filter to allow extending/modifying tracking data
        $this->tracking_data = apply_filters('wpwa_tracking_data', $this->tracking_data);
    }
    
    /**
     * Reset periodic counters after sending data
     */
    private function reset_periodic_counters() {
        update_option('wpwa_api_call_stats', array());
        update_option('wpwa_session_count', 0);
        update_option('wpwa_order_count', 0);
    }
    
    /**
     * Opt in to tracking
     */
    public function opt_in() {
        update_option('wpwa_allow_tracking', '1');
        $this->maybe_schedule_tracking();
    }
    
    /**
     * Opt out of tracking
     */
    public function opt_out() {
        update_option('wpwa_allow_tracking', '0');
        wp_clear_scheduled_hook('wpwa_usage_tracking_event');
    }
}