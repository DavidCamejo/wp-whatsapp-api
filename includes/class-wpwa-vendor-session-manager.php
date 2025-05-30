<?php
/**
 * Vendor Session Manager Class
 *
 * Handles creation, management, and disconnection of WhatsApp sessions for vendors.
 *
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Session Manager Class
 */
class WPWA_Vendor_Session_Manager {
    /**
     * API client instance
     *
     * @var WPWA_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     *
     * @var WPWA_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param WPWA_API_Client $api_client API client instance
     * @param WPWA_Logger     $logger     Logger instance
     */
    public function __construct($api_client, $logger) {
        $this->api_client = $api_client;
        $this->logger = $logger;
    }
    
    /**
     * Create a new WhatsApp session for a vendor
     *
     * @param int    $vendor_id    Vendor ID
     * @param string $session_name Name for the session
     * @return array|false Session data or false on failure
     */
    public function create_vendor_session($vendor_id, $session_name) {
        if (!$vendor_id) {
            $this->logger->error('Failed to create session: No vendor ID provided');
            return false;
        }
        
        // Get vendor data
        $vendor_data = $this->get_vendor_data($vendor_id);
        if (!$vendor_data) {
            $this->logger->error('Failed to create session: Invalid vendor', array('vendor_id' => $vendor_id));
            return false;
        }
        
        // Prepare session configuration
        $session_config = array(
            'name' => $session_name,
            'vendor_id' => $vendor_id,
            'vendor_data' => array(
                'store_name' => $vendor_data['store_name'],
                'email' => $vendor_data['email']
            )
        );
        
        // Request session creation from API
        $response = $this->api_client->post('/sessions', $session_config);
        
        if (is_wp_error($response)) {
            $this->logger->error('API error when creating vendor session', array(
                'vendor_id' => $vendor_id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        // Verify the response contains necessary data
        if (!isset($response['client_id']) || !isset($response['status'])) {
            $this->logger->error('Invalid response from API when creating session', array(
                'vendor_id' => $vendor_id,
                'response' => $response
            ));
            return false;
        }
        
        // Associate the session with this vendor
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if ($user_id) {
            update_user_meta($user_id, 'wpwa_session_client_id', $response['client_id']);
            update_user_meta($user_id, 'wpwa_session_status', $response['status']);
            update_user_meta($user_id, 'wpwa_session_name', $session_name);
            update_user_meta($user_id, 'wpwa_session_created', current_time('mysql'));
        }
        
        // Association on API side
        $this->associate_session_to_vendor($response['client_id'], $vendor_id);
        
        $this->logger->info('Created new vendor session', array(
            'vendor_id' => $vendor_id,
            'client_id' => $response['client_id'],
            'session_name' => $session_name
        ));
        
        return $response;
    }
    
    /**
     * Get all sessions for a vendor
     *
     * @param int $vendor_id Vendor ID
     * @return array List of sessions
     */
    public function get_vendor_sessions($vendor_id) {
        if (!$vendor_id) {
            return array();
        }
        
        // Query API for sessions associated with this vendor
        $response = $this->api_client->get('/vendor/' . $vendor_id . '/sessions');
        
        if (is_wp_error($response)) {
            $this->logger->error('API error when fetching vendor sessions', array(
                'vendor_id' => $vendor_id,
                'error' => $response->get_error_message()
            ));
            return array();
        }
        
        if (!isset($response['sessions']) || !is_array($response['sessions'])) {
            return array();
        }
        
        return $response['sessions'];
    }
    
    /**
     * Disconnect a session for a vendor
     *
     * @param int    $vendor_id Vendor ID
     * @param string $client_id Session client ID
     * @return boolean Success status
     */
    public function disconnect_vendor_session($vendor_id, $client_id) {
        if (!$vendor_id || !$client_id) {
            return false;
        }
        
        // Request session disconnection from API
        $response = $this->api_client->delete('/sessions/' . $client_id);
        
        if (is_wp_error($response)) {
            $this->logger->error('API error when disconnecting vendor session', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        // Clear user meta for this session
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        if ($user_id && get_user_meta($user_id, 'wpwa_session_client_id', true) === $client_id) {
            delete_user_meta($user_id, 'wpwa_session_client_id');
            delete_user_meta($user_id, 'wpwa_session_status');
            delete_user_meta($user_id, 'wpwa_session_name');
            
            // Keep history of disconnected sessions
            $disconnected_sessions = get_user_meta($user_id, 'wpwa_disconnected_sessions', true);
            if (!is_array($disconnected_sessions)) {
                $disconnected_sessions = array();
            }
            
            $disconnected_sessions[] = array(
                'client_id' => $client_id,
                'disconnected_at' => current_time('mysql')
            );
            
            // Keep only last 10 disconnected sessions
            if (count($disconnected_sessions) > 10) {
                array_shift($disconnected_sessions);
            }
            
            update_user_meta($user_id, 'wpwa_disconnected_sessions', $disconnected_sessions);
        }
        
        $this->logger->info('Disconnected vendor session', array(
            'vendor_id' => $vendor_id,
            'client_id' => $client_id
        ));
        
        return true;
    }
    
    /**
     * Associate session to vendor in the API
     *
     * @param string $client_id Session client ID
     * @param int    $vendor_id Vendor ID
     * @return boolean Success status
     */
    private function associate_session_to_vendor($client_id, $vendor_id) {
        if (!$client_id || !$vendor_id) {
            return false;
        }
        
        // Get vendor data
        $vendor_data = $this->get_vendor_data($vendor_id);
        if (!$vendor_data) {
            return false;
        }
        
        // Associate session with vendor in API
        $response = $this->api_client->put('/sessions/' . $client_id . '/vendor', array(
            'vendor_id' => $vendor_id,
            'vendor_data' => $vendor_data
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('API error when associating session to vendor', array(
                'vendor_id' => $vendor_id,
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Get vendor data from vendor ID
     *
     * @param int $vendor_id Vendor ID
     * @return array|false Vendor data or false if not found
     */
    private function get_vendor_data($vendor_id) {
        $user_id = $this->get_user_id_from_vendor($vendor_id);
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $store_name = '';
        $store_url = '';
        
        // Get vendor store data based on marketplace plugin
        if (function_exists('wcfm_get_vendor_store_name')) {
            $store_name = wcfm_get_vendor_store_name($user_id);
            $store_url = wcfm_get_vendor_store_url($user_id);
        } elseif (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($user_id);
            $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : '';
            $store_url = dokan_get_store_url($user_id);
        } elseif (class_exists('WCV_Vendors') && function_exists('WCV_Vendors::get_vendor_shop_page')) {
            $store_name = get_user_meta($user_id, 'pv_shop_name', true);
            $store_url = WCV_Vendors::get_vendor_shop_page($user_id);
        }
        
        return array(
            'id' => $vendor_id,
            'user_id' => $user_id,
            'store_name' => $store_name,
            'store_url' => $store_url,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'billing_phone', true)
        );
    }
    
    /**
     * Get user ID from vendor ID
     *
     * @param int $vendor_id Vendor ID
     * @return int|false User ID or false if not found
     */
    private function get_user_id_from_vendor($vendor_id) {
        // WCFM
        if (function_exists('wcfm_get_vendor_id_by_vendor')) {
            return wcfm_get_vendor_id_by_vendor($vendor_id);
        }
        
        // Dokan (vendor ID is typically user ID)
        if (function_exists('dokan_is_user_seller')) {
            if (dokan_is_user_seller($vendor_id)) {
                return $vendor_id;
            }
        }
        
        // WC Vendors (vendor ID is typically user ID)
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($vendor_id)) {
            return $vendor_id;
        }
        
        return false;
    }
    
    /**
     * Get QR code for a session
     *
     * @param string $client_id Client ID
     * @return array|WP_Error QR code data or error
     */
    public function get_session_qr_code($client_id) {
        if (!$client_id) {
            return new WP_Error('invalid_client_id', __('Invalid client ID', 'wp-whatsapp-api'));
        }
        
        $response = $this->api_client->get('/sessions/' . $client_id . '/qr');
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to get QR code for session', array(
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Check session status
     *
     * @param string $client_id Client ID
     * @return array|WP_Error Session status or error
     */
    public function check_session_status($client_id) {
        if (!$client_id) {
            return new WP_Error('invalid_client_id', __('Invalid client ID', 'wp-whatsapp-api'));
        }
        
        $response = $this->api_client->get('/sessions/' . $client_id . '/status');
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to check session status', array(
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Update session metadata
     *
     * @param string $client_id Client ID
     * @param array  $metadata  Metadata to update
     * @return array|WP_Error Response or error
     */
    public function update_session_metadata($client_id, $metadata) {
        if (!$client_id || !is_array($metadata)) {
            return new WP_Error('invalid_parameters', __('Invalid parameters', 'wp-whatsapp-api'));
        }
        
        $response = $this->api_client->put('/sessions/' . $client_id . '/metadata', $metadata);
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to update session metadata', array(
                'client_id' => $client_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        return $response;
    }
}