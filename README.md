# WhatsApp API for WooCommerce Multivendor Marketplaces

## Description
This WordPress plugin provides unofficial WhatsApp API integration for WooCommerce multivendor marketplaces. It allows vendors to connect their WhatsApp accounts, synchronize product catalogs, manage customer interactions, and process orders directly through WhatsApp.

## Features

### For Vendors
- **WhatsApp Session Management**: Connect your WhatsApp account via QR code and manage your session
- **Product Catalog Synchronization**: Automatically sync your WooCommerce products to WhatsApp Business catalog
- **Customer Management**: Track and manage customer interactions through WhatsApp
- **Order Processing**: Receive and process orders placed through WhatsApp
- **Message Templates**: Use pre-defined templates for common communications
- **Order Status Updates**: Automatically notify customers about order status changes

### For Administrators
- **Central Configuration**: Manage API settings and global configurations
- **Template Management**: Create global message templates for all vendors
- **Activity Logs**: Monitor WhatsApp integration activities
- **Multi-vendor Support**: Compatible with WCFM, Dokan, and WC Vendors

### For Customers
- **Shop via WhatsApp**: Browse products and place orders directly in WhatsApp
- **Order Updates**: Receive automatic notifications about order status
- **Direct Communication**: Chat with vendors for product inquiries and support

## Installation

1. Upload the `wp-whatsapp-integration` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'WhatsApp API' in the admin menu to configure the API settings
4. Vendors can access the WhatsApp dashboard from their vendor panel

## Configuration

### API Configuration
1. Obtain WhatsApp API credentials from your API provider
2. Go to WhatsApp API > Settings in your WordPress admin panel
3. Enter the API URL and API Key
4. Save the settings

### Vendor Setup
1. Navigate to your vendor dashboard
2. Enable WhatsApp integration
3. Click 'Connect WhatsApp' and enter a session name
4. Scan the QR code with your WhatsApp app
5. Once connected, you can start using WhatsApp for your store

## Message Templates

The plugin comes with several pre-defined message templates that you can customize:

- Order Confirmation
- Order Shipped
- Order Status Update
- Welcome Message
- Order Follow-up

You can create additional templates from the WhatsApp API settings page or from your vendor dashboard.

## Marketplace Compatibility

This plugin is compatible with the following multivendor marketplace plugins:

- WCFM Marketplace
- Dokan Multivendor Marketplace
- WC Vendors Marketplace

## Requirements

- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.2 or higher
- Any supported multivendor marketplace plugin
- Active WhatsApp API subscription

## Frequently Asked Questions

### How does the WhatsApp connection work?
Vendors connect their WhatsApp account by scanning a QR code, similar to WhatsApp Web. The connection remains active until explicitly disconnected or if the session expires.

### Can multiple vendors use the same WhatsApp account?
No, each vendor needs to connect their own WhatsApp account.

### What happens when a WhatsApp order is received?
The plugin converts the WhatsApp order into a standard WooCommerce order, making it appear in your regular order management system with special tags indicating it came from WhatsApp.

### Does this plugin include the WhatsApp API?
No, this plugin provides integration with a WhatsApp API, but you need to separately subscribe to a WhatsApp API service provider.

## Support

For support inquiries, please contact us through [our support page](https://example.com/support).

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.0.0
* Initial release
