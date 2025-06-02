<?php
/**
 * Auth Manager Class
 *
 * Handles JWT token generation and validation for secure communication
 * between WordPress and WhatsApp API.
 *
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auth Manager Class
 */
class WPWA_Auth_Manager {
    /**
     * JWT secret key
     *
     * @var string
     */
    private $jwt_secret;
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get JWT secret from constant if defined, otherwise from option
        $this->jwt_secret = defined('WPWA_JWT_SECRET_KEY') ? WPWA_JWT_SECRET_KEY : get_option('wpwa_jwt_secret');
        $this->api_base_url = get_option('wpwa_api_url');
        
        // Include JWT library if not already available
        if (!class_exists('\\Firebase\\JWT\\JWT')) {
            require_once WPWA_PATH . 'vendor/firebase/php-jwt/src/JWT.php';
            require_once WPWA_PATH . 'vendor/firebase/php-jwt/src/Key.php';
        }
    }
    
    /**
     * Generate a new secure JWT secret key
     * 
     * @return string The generated secret key
     */
    public function generate_jwt_secret() {
        // Generate a secure random string for JWT secret (64 chars for better security)
        $new_secret = wp_generate_password(64, true, true);
        
        // Store in options table
        update_option('wpwa_jwt_secret', $new_secret);
        
        // Update the instance variable
        $this->jwt_secret = $new_secret;
        
        return $new_secret;
    }
    
    /**
     * Defines the allowed roles that can generate API tokens
     *
     * @return array List of allowed roles
     */
    public function get_allowed_roles() {
        // Define allowed roles with a filter to allow customization
        return apply_filters('wpwa_allowed_roles', [
            'administrator',
            'vendor',
            'shop_manager',
            'vendor_admin',
            'vendor_staff',
            'wcfm_vendor'
        ]);
    }
    
    /**
     * Checks if a user has any of the allowed roles
     *
     * @param array $user_roles Current user roles
     * @return boolean True if user has any allowed role
     */
    public function has_allowed_role($user_roles) {
        $allowed_roles = $this->get_allowed_roles();
        return !empty(array_intersect($allowed_roles, $user_roles));
    }
    
    /**
     * Generates a token JWT for authenticating with the WhatsApp API
     *
     * @return string|boolean JWT token or false on failure
     */
    public function get_api_token() {
        $current_user = wp_get_current_user();
        
        if (!$current_user || $current_user->ID === 0) {
            return false;
        }
        
        // Check if user has any of the allowed roles
        if (!$this->has_allowed_role($current_user->roles)) {
            // Log the unauthorized attempt
            error_log(sprintf('Unauthorized JWT token request: User %s (%d) with roles %s', 
                $current_user->user_login, 
                $current_user->ID, 
                implode(', ', $current_user->roles)
            ));
            return false;
        }
        
        // Verify if user is a vendor in the marketplace
        $is_vendor = $this->is_user_vendor($current_user->ID);
        $vendor_id = $is_vendor ? $this->get_vendor_id($current_user->ID) : null;
        $store_name = $is_vendor ? $this->get_vendor_store_name($current_user->ID) : null;
        
        // Create payload of the token with vendor information
        $payload = [
            'user_id' => $current_user->ID,
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'roles' => $current_user->roles,
            'is_vendor' => $is_vendor,
            'vendor_id' => $vendor_id,
            'store_name' => $store_name,
            'exp' => time() + (60 * 60), // 1 hour
            'iss' => get_site_url()
        ];
        
        try {
            // Generate JWT token
            return \Firebase\JWT\JWT::encode($payload, $this->jwt_secret, 'HS256');
        } catch (Exception $e) {
            error_log('Error generating JWT token: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validates a JWT token received from the API
     *
     * @param string $token JWT token
     * @return object|boolean Decoded token payload or false on failure
     */
    public function validate_api_token($token) {
        if (empty($token)) {
            return false;
        }
        
        try {
            $decoded = \Firebase\JWT\JWT::decode(
                $token, 
                new \Firebase\JWT\Key($this->jwt_secret, 'HS256')
            );
            
            // Verify expiration
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            error_log('Error validating JWT token: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate API request to WordPress REST endpoints
     *
     * @param WP_REST_Request $request The request object
     * @return boolean Whether the request is valid
     */
    public function validate_api_request($request) {
        $auth_header = $request->get_header('Authorization');
        
        if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
            return false;
        }
        
        $token = trim(substr($auth_header, 7));
        return $this->validate_api_token($token);
    }
    
    /**
     * Check if a user is a vendor
     *
     * @param int $user_id User ID
     * @return boolean True if user is a vendor
     */
    private function is_user_vendor($user_id) {
        // Check based on marketplace plugin
        if (function_exists('wcfm_is_vendor') && wcfm_is_vendor($user_id)) {
            return true;
        }
        
        if (function_exists('dokan_is_user_seller') && dokan_is_user_seller($user_id)) {
            return true;
        }
        
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($user_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get vendor ID from user ID
     *
     * @param int $user_id User ID
     * @return int|null Vendor ID or null if not found
     */
    private function get_vendor_id($user_id) {
        if (function_exists('wcfm_get_vendor_id_from_user')) {
            return wcfm_get_vendor_id_from_user($user_id);
        }
        
        if (function_exists('dokan_get_vendor_by_user')) {
            $vendor = dokan_get_vendor_by_user($user_id);
            return $vendor ? $vendor->get_id() : null;
        }
        
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($user_id)) {
            // For WC Vendors, user ID is typically the same as vendor ID
            return $user_id;
        }
        
        return null;
    }
    
    /**
     * Get vendor store name
     *
     * @param int $user_id User ID
     * @return string|null Store name or null if not found
     */
    private function get_vendor_store_name($user_id) {
        // WCFM Marketplace
        if (function_exists('wcfm_get_vendor_store_name')) {
            return wcfm_get_vendor_store_name($user_id);
        }
        
        // Dokan
        if (function_exists('dokan_get_vendor_by_user')) {
            $vendor = dokan_get_vendor_by_user($user_id);
            return $vendor ? $vendor->get_shop_name() : null;
        }
        
        // WC Vendors
        if (class_exists('WCV_Vendors') && WCV_Vendors::is_vendor($user_id)) {
            return get_user_meta($user_id, 'pv_shop_name', true);
        }
        
        return null;
    }
    
    /**
     * Generate API URL with endpoint
     *
     * @param string $endpoint API endpoint
     * @return string Full API URL
     */
    public function get_api_url($endpoint) {
        return rtrim($this->api_base_url, '/') . '/' . ltrim($endpoint, '/');
    }
}