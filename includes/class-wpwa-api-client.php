<?php
/**
 * API Client Class
 *
 * Handles HTTP communication with the WhatsApp API server.
 *
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Client Class
 */
class WPWA_API_Client {
    /**
     * API base URL
     *
     * @var string
     */
    private $base_url;
    
    /**
     * Auth manager instance
     *
     * @var WPWA_Auth_Manager
     */
    private $auth_manager;
    
    /**
     * Constructor
     *
     * @param WPWA_Auth_Manager $auth_manager Auth manager instance
     */
    public function __construct($auth_manager) {
        $this->auth_manager = $auth_manager;
        $this->base_url = get_option('wpwa_api_url');
    }
    
    /**
     * Make a GET request to the API
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return array|WP_Error Response data or error
     */
    public function get($endpoint, $params = array()) {
        return $this->request('GET', $endpoint, $params);
    }
    
    /**
     * Make a POST request to the API
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @return array|WP_Error Response data or error
     */
    public function post($endpoint, $data = array()) {
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * Make a PUT request to the API
     *
     * @param string $endpoint API endpoint
     * @param array  $data     Request body data
     * @return array|WP_Error Response data or error
     */
    public function put($endpoint, $data = array()) {
        return $this->request('PUT', $endpoint, $data);
    }
    
    /**
     * Make a DELETE request to the API
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Query parameters
     * @return array|WP_Error Response data or error
     */
    public function delete($endpoint, $params = array()) {
        return $this->request('DELETE', $endpoint, $params);
    }
    
    /**
     * Make a request to the API
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Request data (query params for GET/DELETE or body for POST/PUT)
     * @return array|WP_Error Response data or error
     */
    private function request($method, $endpoint, $data = array()) {
        // Get JWT token for authentication
        $token = $this->auth_manager->get_api_token();
        
        if (!$token) {
            return new WP_Error('auth_error', __('Failed to generate authentication token', 'wp-whatsapp-api'));
        }
        
        // Build full endpoint URL
        $url = $this->auth_manager->get_api_url($endpoint);
        
        // Prepare request arguments
        // Get timeout from settings or use default
        $timeout = intval(get_option('wpwa_connection_timeout', 30));
        
        $args = array(
            'method'    => $method,
            'timeout'   => $timeout,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token
            )
        );
        
        // Add request data
        if ($method === 'GET' || $method === 'DELETE') {
            // Add query parameters to URL
            if (!empty($data)) {
                $url = add_query_arg($data, $url);
            }
        } else {
            // Add body data for POST/PUT
            $args['body'] = wp_json_encode($data);
        }
        
        // Enable debug logging if needed
        $debug_mode = get_option('wpwa_debug_mode', '0');
        
        if ($debug_mode === '1') {
            error_log('WhatsApp API Request: ' . $method . ' ' . $url);
            error_log('Request data: ' . wp_json_encode($data));
        }
        
        // Get max retries from settings
        $max_retries = intval(get_option('wpwa_max_retries', 3));
        $retry_count = 0;
        $response = null;
        
        // Implement retry logic
        while ($retry_count <= $max_retries) {
            // Make the request
            $response = wp_remote_request($url, $args);
            
            // Success or non-connection error, break the loop
            if (!is_wp_error($response) || 
                ($retry_count >= $max_retries) || 
                !in_array($response->get_error_code(), array('http_request_failed', 'request_timeout'))) {
                break;
            }
            
            // Log retry attempt
            if ($debug_mode === '1') {
                error_log('WhatsApp API Request Error: ' . $response->get_error_message() . ' - Retrying (' . ($retry_count + 1) . '/' . $max_retries . ')');
            }
            
            $retry_count++;
            // Short delay before retry
            sleep(1);
        }
        
        // Check for request error
        if (is_wp_error($response)) {
            if ($debug_mode === '1') {
                error_log('WhatsApp API Request Error: ' . $response->get_error_message());
            }
            return $response;
        }
        
        // Track API usage if enabled
        if (get_option('wpwa_allow_tracking', '0') === '1') {
            $this->track_api_usage($endpoint, $method, $response);
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($debug_mode === '1') {
            error_log('WhatsApp API Response Code: ' . $response_code);
            error_log('WhatsApp API Response: ' . $response_body);
        }
        
        // Check for error response codes
        if ($response_code >= 400) {
            $error_message = __('API request failed', 'wp-whatsapp-api');
            
            // Try to get error message from response
            $response_data = json_decode($response_body, true);
            if (isset($response_data['error'])) {
                $error_message = $response_data['error'];
            } elseif (isset($response_data['message'])) {
                $error_message = $response_data['message'];
            }
            
            return new WP_Error(
                'api_error_' . $response_code,
                $error_message,
                array('status' => $response_code)
            );
        }
        
        // Parse response body
        $data = json_decode($response_body, true);
        
        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error(
                'api_parse_error',
                __('Error parsing API response', 'wp-whatsapp-api')
            );
        }
        
        return $data;
    }
    
    /**
     * Track API usage for analytics
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $response WP_HTTP response array
     */
    private function track_api_usage($endpoint, $method, $response) {
        global $wp_whatsapp_api;
        
        // Get the usage tracker instance
        $tracker = $wp_whatsapp_api->usage_tracker;
        if (!$tracker) {
            return;
        }
        
        // Extract base endpoint without parameters
        $base_endpoint = preg_replace('/\?.*$/','', $endpoint);
        
        // Basic tracking data
        $track_data = array(
            'endpoint' => $base_endpoint,
            'method' => $method,
            'response_code' => wp_remote_retrieve_response_code($response),
            'timestamp' => current_time('mysql'),
        );
        
        // Track this API call
        $tracker->track_event('api_call', $track_data);
    }
    
    /**
     * Upload a file to the API
     *
     * @param string $endpoint API endpoint
     * @param string $file_path Path to the file
     * @param string $file_name Optional file name
     * @return array|WP_Error Response data or error
     */
    public function upload_file($endpoint, $file_path, $file_name = null) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found', 'wp-whatsapp-api'));
        }
        
        // Get JWT token for authentication
        $token = $this->auth_manager->get_api_token();
        
        if (!$token) {
            return new WP_Error('auth_error', __('Failed to generate authentication token', 'wp-whatsapp-api'));
        }
        
        // Build full endpoint URL
        $url = $this->auth_manager->get_api_url($endpoint);
        
        // Get file info
        $file_type = wp_check_filetype(basename($file_path));
        $file_name = $file_name ?: basename($file_path);
        
        // Create multipart body
        $boundary = wp_generate_password(24, false);
        
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: ' . $file_type['type'] . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . '--';
        
        // Prepare request arguments
        // Get timeout from settings (use a longer timeout for uploads)
        $timeout = intval(get_option('wpwa_connection_timeout', 30)) * 2;
        
        $args = array(
            'method'    => 'POST',
            'timeout'   => $timeout,
            'headers'   => array(
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
                'Authorization' => 'Bearer ' . $token
            ),
            'body'      => $body
        );
        
        // Get max retries from settings
        $max_retries = intval(get_option('wpwa_max_retries', 3));
        $retry_count = 0;
        $response = null;
        
        // Enable debug logging if needed
        $debug_mode = get_option('wpwa_debug_mode', '0');
        
        // Implement retry logic
        while ($retry_count <= $max_retries) {
            // Make the request
            $response = wp_remote_request($url, $args);
            
            // Success or non-connection error, break the loop
            if (!is_wp_error($response) || 
                ($retry_count >= $max_retries) || 
                !in_array($response->get_error_code(), array('http_request_failed', 'request_timeout'))) {
                break;
            }
            
            // Log retry attempt
            if ($debug_mode === '1') {
                error_log('WhatsApp API File Upload Request Error: ' . $response->get_error_message() . ' - Retrying (' . ($retry_count + 1) . '/' . $max_retries . ')');
            }
            
            $retry_count++;
            // Short delay before retry
            sleep(1);
        }
        
        // Check for request error
        if (is_wp_error($response)) {
            if ($debug_mode === '1') {
                error_log('WhatsApp API File Upload Error: ' . $response->get_error_message());
            }
            return $response;
        }
        
        // Track API usage if enabled
        if (get_option('wpwa_allow_tracking', '0') === '1') {
            $this->track_api_usage($endpoint, 'POST', $response);
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check for error response codes
        if ($response_code >= 400) {
            $error_message = __('API upload request failed', 'wp-whatsapp-api');
            
            // Try to get error message from response
            $response_data = json_decode($response_body, true);
            if (isset($response_data['error'])) {
                $error_message = $response_data['error'];
            } elseif (isset($response_data['message'])) {
                $error_message = $response_data['message'];
            }
            
            return new WP_Error(
                'api_error_' . $response_code,
                $error_message,
                array('status' => $response_code)
            );
        }
        
        // Parse response body
        $data = json_decode($response_body, true);
        
        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error(
                'api_parse_error',
                __('Error parsing API response', 'wp-whatsapp-api')
            );
        }
        
        return $data;
    }
}