<?php
/**
 * Admin Settings Page Template
 * 
 * This serves as the primary settings page for the WhatsApp API integration plugin.
 * It includes the necessary admin structure while delegating the actual settings
 * rendering to the WPWA_Admin_Settings class.
 * 
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load admin settings if not already loaded
if (!class_exists('WPWA_Admin_Settings')) {
    require_once WPWA_PATH . 'admin/admin-settings.php';
}

// Get the instance of settings class 
global $wpwa_admin_settings;
if (!isset($wpwa_admin_settings)) {
    $wpwa_admin_settings = new WPWA_Admin_Settings();
}

// Check if user has permissions to access this page
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'wp-whatsapp-api'));
}

?>
<div class="wrap wpwa-settings-wrap">
    <?php
    // Display admin notices
    settings_errors('wpwa_messages');
    
    // Let the admin settings class render the page
    $wpwa_admin_settings->render_settings_page();
    ?>
</div>