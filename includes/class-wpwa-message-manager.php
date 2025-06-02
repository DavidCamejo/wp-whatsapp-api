<?php
/**
 * WPWA_Message_Manager Class
 *
 * Handles sending various types of WhatsApp messages through the API
 *
 * @package WP WhatsApp API
 */

defined('ABSPATH') || exit;

/**
 * WPWA_Message_Manager Class
 */
class WPWA_Message_Manager {
    /**
     * API client instance
     * @var WPWA_API_Client
     */
    private $api_client;

    /**
     * Template manager instance
     * @var WPWA_Template_Manager
     */
    private $template_manager;

    /**
     * Logger instance
     * @var WPWA_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param WPWA_API_Client $api_client API client instance
     * @param WPWA_Template_Manager $template_manager Template manager instance
     * @param WPWA_Logger $logger Logger instance
     */
    public function __construct($api_client, $template_manager, $logger) {
        $this->api_client = $api_client;
        $this->template_manager = $template_manager;
        $this->logger = $logger;

        // Hook into actions where messages should be sent
        add_action('wpwa_after_order_created', array($this, 'send_order_confirmation'), 10, 2);
    }

    /**
     * Send a text message
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $message Message text
     * @param string $session_id WhatsApp session ID
     * @param array $options Additional options
     * @return array|WP_Error Response or error
     */
    public function send_text_message($recipient_phone, $message, $session_id, $options = array()) {
        $this->logger->debug('Sending WhatsApp text message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'message_length' => strlen($message),
        ));

        // Format phone number (remove spaces, dashes, etc.)
        $recipient_phone = $this->format_phone_number($recipient_phone);

        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => 'text',
            'content' => array(
                'text' => $message
            )
        );

        // Add optional parameters
        if (!empty($options['preview_url'])) {
            $payload['content']['preview_url'] = (bool) $options['preview_url'];
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp text message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone
            ));
            return $response;
        }

        $this->logger->info('WhatsApp text message sent successfully', array(
            'recipient' => $recipient_phone,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send a media message (image, video, audio, document)
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $media_type Media type (image, video, audio, document)
     * @param string $media_url URL to media
     * @param string $session_id WhatsApp session ID
     * @param array $options Additional options (caption, filename, etc)
     * @return array|WP_Error Response or error
     */
    public function send_media_message($recipient_phone, $media_type, $media_url, $session_id, $options = array()) {
        $this->logger->debug('Sending WhatsApp media message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'media_type' => $media_type,
            'media_url' => $media_url
        ));

        // Format phone number
        $recipient_phone = $this->format_phone_number($recipient_phone);

        // Validate media type
        $valid_media_types = array('image', 'video', 'audio', 'document');
        if (!in_array($media_type, $valid_media_types)) {
            return new WP_Error(
                'invalid_media_type',
                sprintf(
                    __('Invalid media type: %s. Valid types are: %s', 'wp-whatsapp-api'),
                    $media_type,
                    implode(', ', $valid_media_types)
                )
            );
        }

        // Build payload
        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => $media_type,
            'content' => array(
                $media_type => array(
                    'url' => $media_url
                )
            )
        );

        // Add caption for image, video, document
        if (in_array($media_type, array('image', 'video', 'document')) && !empty($options['caption'])) {
            $payload['content'][$media_type]['caption'] = $options['caption'];
        }

        // Add filename for document
        if ($media_type === 'document' && !empty($options['filename'])) {
            $payload['content']['document']['filename'] = $options['filename'];
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp media message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone,
                'media_type' => $media_type
            ));
            return $response;
        }

        $this->logger->info('WhatsApp media message sent successfully', array(
            'recipient' => $recipient_phone,
            'media_type' => $media_type,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send an interactive button message
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $header_text Header text (optional)
     * @param string $body_text Body text
     * @param string $footer_text Footer text (optional)
     * @param array $buttons Array of buttons (max 3)
     * @param string $session_id WhatsApp session ID
     * @return array|WP_Error Response or error
     */
    public function send_interactive_buttons($recipient_phone, $body_text, $buttons, $session_id, $header_text = '', $footer_text = '') {
        $this->logger->debug('Sending WhatsApp interactive buttons message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'num_buttons' => count($buttons)
        ));

        // Format phone number
        $recipient_phone = $this->format_phone_number($recipient_phone);

        // Validate buttons (max 3)
        if (count($buttons) > 3) {
            return new WP_Error(
                'too_many_buttons',
                __('WhatsApp supports a maximum of 3 buttons per message', 'wp-whatsapp-api')
            );
        }

        if (count($buttons) === 0) {
            return new WP_Error(
                'no_buttons',
                __('At least one button must be provided', 'wp-whatsapp-api')
            );
        }

        // Format buttons
        $formatted_buttons = array();
        foreach ($buttons as $index => $button) {
            $formatted_buttons[] = array(
                'id' => isset($button['id']) ? $button['id'] : 'btn_' . ($index + 1),
                'title' => $button['title']
            );
        }

        // Build payload
        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => 'interactive',
            'content' => array(
                'interactive' => array(
                    'type' => 'button',
                    'body' => array(
                        'text' => $body_text
                    ),
                    'action' => array(
                        'buttons' => $formatted_buttons
                    )
                )
            )
        );

        // Add header if provided
        if (!empty($header_text)) {
            $payload['content']['interactive']['header'] = array(
                'type' => 'text',
                'text' => $header_text
            );
        }

        // Add footer if provided
        if (!empty($footer_text)) {
            $payload['content']['interactive']['footer'] = array(
                'text' => $footer_text
            );
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp interactive buttons message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone
            ));
            return $response;
        }

        $this->logger->info('WhatsApp interactive buttons message sent successfully', array(
            'recipient' => $recipient_phone,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send an interactive list message
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $header_text Header text (optional)
     * @param string $body_text Body text
     * @param string $footer_text Footer text (optional)
     * @param string $button_text Button text
     * @param array $sections List sections
     * @param string $session_id WhatsApp session ID
     * @return array|WP_Error Response or error
     */
    public function send_interactive_list($recipient_phone, $body_text, $button_text, $sections, $session_id, $header_text = '', $footer_text = '') {
        $this->logger->debug('Sending WhatsApp interactive list message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'num_sections' => count($sections)
        ));

        // Format phone number
        $recipient_phone = $this->format_phone_number($recipient_phone);

        // Build payload
        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => 'interactive',
            'content' => array(
                'interactive' => array(
                    'type' => 'list',
                    'body' => array(
                        'text' => $body_text
                    ),
                    'action' => array(
                        'button' => $button_text,
                        'sections' => $sections
                    )
                )
            )
        );

        // Add header if provided
        if (!empty($header_text)) {
            $payload['content']['interactive']['header'] = array(
                'type' => 'text',
                'text' => $header_text
            );
        }

        // Add footer if provided
        if (!empty($footer_text)) {
            $payload['content']['interactive']['footer'] = array(
                'text' => $footer_text
            );
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp interactive list message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone
            ));
            return $response;
        }

        $this->logger->info('WhatsApp interactive list message sent successfully', array(
            'recipient' => $recipient_phone,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send a template message
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $template_name Template name
     * @param string $language Template language
     * @param array $components Template components (header, body, etc.)
     * @param string $session_id WhatsApp session ID
     * @return array|WP_Error Response or error
     */
    public function send_template_message($recipient_phone, $template_name, $language, $components, $session_id) {
        $this->logger->debug('Sending WhatsApp template message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'template_name' => $template_name,
            'language' => $language
        ));

        // Format phone number
        $recipient_phone = $this->format_phone_number($recipient_phone);

        // Build payload
        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => 'template',
            'content' => array(
                'template' => array(
                    'name' => $template_name,
                    'language' => array(
                        'code' => $language
                    )
                )
            )
        );

        // Add components if provided
        if (!empty($components)) {
            $payload['content']['template']['components'] = $components;
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp template message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone,
                'template_name' => $template_name
            ));
            return $response;
        }

        $this->logger->info('WhatsApp template message sent successfully', array(
            'recipient' => $recipient_phone,
            'template_name' => $template_name,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send a catalog message
     *
     * @param string $recipient_phone Recipient phone number
     * @param string $catalog_id Catalog ID
     * @param string $section_id Section ID (optional)
     * @param string $session_id WhatsApp session ID
     * @return array|WP_Error Response or error
     */
    public function send_catalog_message($recipient_phone, $catalog_id, $session_id, $section_id = null) {
        $this->logger->debug('Sending WhatsApp catalog message', array(
            'recipient' => $recipient_phone,
            'session_id' => $session_id,
            'catalog_id' => $catalog_id
        ));

        // Format phone number
        $recipient_phone = $this->format_phone_number($recipient_phone);

        // Build payload
        $payload = array(
            'session_id' => $session_id,
            'recipient' => $recipient_phone,
            'type' => 'interactive',
            'content' => array(
                'interactive' => array(
                    'type' => 'catalog_message',
                    'action' => array(
                        'catalog_id' => $catalog_id
                    )
                )
            )
        );

        // Add section ID if provided
        if (!empty($section_id)) {
            $payload['content']['interactive']['action']['sections'] = array($section_id);
        }

        $response = $this->api_client->post('messages/send', $payload);

        if (is_wp_error($response)) {
            $this->logger->error('Failed to send WhatsApp catalog message', array(
                'error' => $response->get_error_message(),
                'recipient' => $recipient_phone,
                'catalog_id' => $catalog_id
            ));
            return $response;
        }

        $this->logger->info('WhatsApp catalog message sent successfully', array(
            'recipient' => $recipient_phone,
            'catalog_id' => $catalog_id,
            'message_id' => isset($response['message_id']) ? $response['message_id'] : 'unknown'
        ));

        return $response;
    }

    /**
     * Send order confirmation message
     *
     * @param int $order_id WooCommerce order ID
     * @param string $session_id WhatsApp session ID
     * @return array|WP_Error|bool Response or false if message not sent
     */
    public function send_order_confirmation($order_id, $session_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logger->error('Failed to send order confirmation: order not found', array(
                'order_id' => $order_id
            ));
            return false;
        }

        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            $this->logger->error('Failed to send order confirmation: phone number not found', array(
                'order_id' => $order_id
            ));
            return false;
        }

        // Format phone number
        $phone = $this->format_phone_number($phone);

        // Get template from global templates
        $global_templates = get_option('wpwa_global_templates', array());
        $template = isset($global_templates['order_confirmation']) 
            ? $global_templates['order_confirmation'] 
            : null;

        if (!$template) {
            $this->logger->info('Order confirmation template not found, using default message', array(
                'order_id' => $order_id
            ));
            
            // Default message if template not found
            $message = sprintf(
                __("🎉 *Thank you for your order!*\n\n*Order #:* %s\n*Total:* %s\n\nWe will process your order soon. Thank you for shopping with %s!", 'wp-whatsapp-api'),
                $order->get_order_number(),
                strip_tags($order->get_formatted_order_total()),
                get_bloginfo('name')
            );
        } else {
            // Use template parser from template manager
            $message = $this->template_manager->parse_template($template['content'], array('order' => $order));
        }

        // Send the message
        return $this->send_text_message($phone, $message, $session_id);
    }

    /**
     * Format a phone number for WhatsApp API
     *
     * @param string $phone_number Input phone number
     * @return string Formatted phone number
     */
    private function format_phone_number($phone_number) {
        // Remove all non-digit characters except the + at the beginning
        $formatted = preg_replace('/[^\d+]/', '', $phone_number);
        
        // Ensure the number starts with +
        if (substr($formatted, 0, 1) !== '+') {
            // Try to add country code if missing
            $default_country_code = apply_filters('wpwa_default_country_code', '+1');
            $formatted = $default_country_code . $formatted;
        }
        
        return $formatted;
    }
}