<?php
/**
 * WPWA Vendor Session Manager
 *
 * Manages WhatsApp sessions for vendors in a multi-vendor environment
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA Vendor Session Manager
 */
class WPWA_Vendor_Session_Manager {
    /**
     * API Client
     *
     * @var WPWA_API_Client
     */
    private $api_client;
    
    /**
     * Logger
     *
     * @var WPWA_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param WPWA_API_Client $api_client API client for WhatsApp API communication
     * @param WPWA_Logger $logger Logger for activity tracking
     */
    public function __construct($api_client, $logger) {
        $this->api_client = $api_client;
        $this->logger = $logger;
    }
    
    /**
     * Create a new WhatsApp session for a vendor
     *
     * @param int $vendor_id Vendor ID
     * @param string $session_name Name for the session
     * @return array|false Session data or false on failure
     */
    public function create_vendor_session($vendor_id, $session_name) {
        // Get vendor data
        $vendor_data = $this->get_vendor_data($vendor_id);
        if (!$vendor_data) {
            $this->logger->error('Failed to create session - invalid vendor data', array(
                'vendor_id' => $vendor_id
            ));
            return false;
        }
        
        // Get user ID associated with vendor
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if (!$user_id) {
            $this->logger->error('Failed to create session - could not map vendor to user', array(
                'vendor_id' => $vendor_id
            ));
            return false;
        }
        
        // Configuration for new session
        $session_config = array(
            'name' => sanitize_text_field($session_name),
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor_data['store_name'] ?: 'Vendor ' . $vendor_id,
            'vendor_url' => $vendor_data['store_url'] ?: '',
            'webhook_url' => add_query_arg(array(
                'wpwa_webhook' => 'vendor',
                'vendor_id' => $vendor_id,
                'token' => wp_create_nonce('wpwa_webhook_' . $vendor_id)
            ), get_site_url())
        );
        
        // Create session via API
        $response = $this->api_client->post('/sessions', $session_config);
        
        if (is_wp_error($response)) {
            $this->logger->error('API error creating session', array(
                'vendor_id' => $vendor_id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        if (!isset($response['client_id']) || !isset($response['qr_code'])) {
            $this->logger->error('Invalid API response creating session', array(
                'vendor_id' => $vendor_id,
                'response' => $response
            ));
            return false;
        }
        
        // Store session data in user meta
        $client_id = sanitize_text_field($response['client_id']);
        update_user_meta($user_id, 'wpwa_session_client_id', $client_id);
        update_user_meta($user_id, 'wpwa_session_name', $session_name);
        update_user_meta($user_id, 'wpwa_session_status', 'initializing');
        update_user_meta($user_id, 'wpwa_session_created', current_time('mysql'));
        
        // Associate session with vendor in API system
        $this->associate_session_to_vendor($client_id, $vendor_id);
        
        $this->logger->info('Created new WhatsApp session', array(
            'vendor_id' => $vendor_id,
            'session_name' => $session_name,
            'client_id' => $client_id
        ));
        
        return array(
            'client_id' => $client_id,
            'qr_code' => $response['qr_code'],
            'status' => 'initializing'
        );
    }
    
    /**
     * Get active sessions for a vendor
     *
     * @param int $vendor_id Vendor ID
     * @return array Sessions data
     */
    public function get_vendor_sessions($vendor_id) {
        // Get user ID
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if (!$user_id) {
            return array();
        }
        
        // Check if user has an active session
        $client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        if (!$client_id) {
            return array();
        }
        
        // Get session data
        $session_name = get_user_meta($user_id, 'wpwa_session_name', true);
        $session_status = get_user_meta($user_id, 'wpwa_session_status', true);
        $session_created = get_user_meta($user_id, 'wpwa_session_created', true);
        
        return array(
            array(
                'client_id' => $client_id,
                'session_name' => $session_name,
                'status' => $session_status,
                'created_at' => $session_created
            )
        );
    }
    
    /**
     * Disconnect a vendor's WhatsApp session
     *
     * @param int $vendor_id Vendor ID
     * @param string $client_id Client ID of the session to disconnect
     * @return bool Success status
     */
    public function disconnect_vendor_session($vendor_id, $client_id) {
        // Validate vendor ownership of session
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if (!$user_id) {
            $this->logger->error('Failed to disconnect session - invalid vendor', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id
            ));
            return false;
        }
        
        // Check if this session belongs to the vendor
        $stored_client_id = get_user_meta($user_id, 'wpwa_session_client_id', true);
        if ($stored_client_id !== $client_id) {
            $this->logger->error('Failed to disconnect session - ownership mismatch', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id,
                'stored_client_id' => $stored_client_id
            ));
            return false;
        }
        
        // Send disconnect request to API
        $response = $this->api_client->delete('/sessions/' . $client_id);
        
        if (is_wp_error($response)) {
            $this->logger->error('API error disconnecting session', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            // Even if API call fails, we'll clean up local data
        }
        
        // Clean up user meta
        delete_user_meta($user_id, 'wpwa_session_client_id');
        delete_user_meta($user_id, 'wpwa_session_status');
        
        $this->logger->info('Disconnected WhatsApp session', array(
            'vendor_id' => $vendor_id,
            'client_id' => $client_id
        ));
        
        return true;
    }
    
    /**
     * Get QR code for a session
     *
     * @param string $client_id Client ID
     * @return array|WP_Error QR data or error
     */
    public function get_session_qr_code($client_id) {
        return $this->api_client->get('/sessions/' . $client_id . '/qr');
    }
    
    /**
     * Check status of a WhatsApp session
     *
     * @param string $client_id Client ID
     * @return array|WP_Error Status data or error
     */
    public function check_session_status($client_id) {
        return $this->api_client->get('/sessions/' . $client_id . '/status');
    }
    
    /**
     * Update metadata for a session
     *
     * @param string $client_id Client ID
     * @param array $metadata Metadata to update
     * @return array|WP_Error Response or error
     */
    public function update_session_metadata($client_id, $metadata) {
        return $this->api_client->put('/sessions/' . $client_id . '/metadata', $metadata);
    }
    
    /**
     * Get vendor data
     *
     * @param int $vendor_id Vendor ID
     * @return array|false Vendor data or false
     */
    private function get_vendor_data($vendor_id) {
        if (!$vendor_id) {
            return false;
        }
        
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            return false;
        }
        
        $store_name = '';
        $store_url = '';
        
        // WCFM
        if (function_exists('wcfm_get_vendor_store_name')) {
            $store_name = wcfm_get_vendor_store_name($vendor_id);
            $store_url = wcfm_get_vendor_store_url($vendor_id);
        } 
        // Dokan
        elseif (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($user_id);
            $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : '';
            $store_url = function_exists('dokan_get_store_url') ? dokan_get_store_url($user_id) : '';
        } 
        // WC Vendors
        elseif (function_exists('get_user_meta')) {
            $store_name = get_user_meta($user_id, 'pv_shop_name', true);
            $store_url = class_exists('WCV_Vendors') ? WCV_Vendors::get_vendor_shop_page($user_id) : '';
        }
        
        // Default to admin
        if (!$store_name && user_can($user_id, 'manage_woocommerce')) {
            $store_name = get_option('blogname');
            $store_url = get_site_url();
        }
        
        return array(
            'vendor_id' => $vendor_id,
            'user_id' => $user_id,
            'store_name' => $store_name,
            'store_url' => $store_url,
        );
    }
    
    /**
     * Get user ID from vendor ID
     *
     * @param int $vendor_id Vendor ID
     * @return int|false User ID or false
     */
    private function get_user_id_from_vendor($vendor_id) {
        // For most marketplace plugins, the vendor ID is the user ID
        if (is_numeric($vendor_id)) {
            $user_id = (int) $vendor_id;
            
            // Check if user exists
            if (get_user_by('id', $user_id)) {
                return $user_id;
            }
        }
        
        // WCFM might have a different mapping
        if (function_exists('wcfm_get_vendor_id_by_user')) {
            // Try to find a user that maps to this vendor ID
            global $wpdb;
            
            $users = get_users(array('role__in' => array(
                'administrator', 'shop_manager', 'wcfm_vendor', 'seller', 
                'vendor', 'dc_vendor', 'wc_product_vendors_admin_vendor'
            )));
            
            foreach ($users as $user) {
                $user_vendor_id = wcfm_get_vendor_id_by_user($user->ID);
                if ($user_vendor_id == $vendor_id) {
                    return $user->ID;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Associate a session with a vendor
     *
     * @param string $client_id Client ID
     * @param int $vendor_id Vendor ID
     * @return bool Success status
     */
    private function associate_session_to_vendor($client_id, $vendor_id) {
        $metadata = array(
            'vendor_id' => $vendor_id,
            'site_url' => get_site_url()
        );
        
        $response = $this->update_session_metadata($client_id, $metadata);
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to associate session with vendor', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        return true;
    }
}