# WhatsApp API WordPress Integration

## Description

This plugin integrates WhatsApp API functionality with WordPress and WooCommerce for a multivendor marketplace setup. It allows vendors to connect their WhatsApp accounts, manage product catalogs, and process orders through WhatsApp.

## Features

- **WhatsApp Session Management**: Connect and manage WhatsApp sessions for vendors
- **Product Sync**: Synchronize WooCommerce products with WhatsApp catalogs
- **Order Processing**: Process orders received through WhatsApp
- **Message Templates**: Configure and manage message templates
- **Safe JWT Tokens**: URL-safe token generation for secure transmission in URLs and headers
- **Admin Dashboard**: Comprehensive admin interface for configuration and monitoring
- **Frontend Admin Panel**: Access plugin settings from the frontend using a shortcode
- **Vendor Dashboard**: Dedicated interface for vendors to manage their WhatsApp integration
- **Logging System**: Detailed logging of all API operations
- **Usage Tracking**: Optional anonymous usage tracking

## Installation

1. Upload the `wp-whatsapp-integration` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WhatsApp API > Settings to configure the plugin

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- WooCommerce 4.0 or higher
- A WhatsApp API server endpoint

## Configuration

1. Set up your WhatsApp API server URL in the plugin settings
2. Configure JWT secret for secure communication
3. Set up message templates for order notifications
4. Connect vendor WhatsApp accounts through the session manager

### Frontend Admin Panel

You can access the admin settings from the frontend using a shortcode:

1. Create a new page in WordPress
2. Add the shortcode `[wpwa_frontend_admin]` to the page content
3. Publish the page
4. Navigate to the published page to access the frontend admin panel

**Note:** Only administrators and shop managers can access this panel. For security reasons, you may want to protect this page with a password or restrict access using a membership plugin.

The frontend admin panel includes all the same functionality as the admin dashboard, including:

- API configuration settings
- JWT Secret generation
- Log viewing and management
- System information

### Vendor Dashboard

A dedicated dashboard for vendors to manage their WhatsApp integration is available through a shortcode:

1. Create a new page in WordPress
2. Add the shortcode `[wpwa_vendor_dashboard]` to the page content
3. Publish the page
4. Share the page with your vendors

**Note:** Only users with vendor roles (configurable through the `wpwa_allowed_vendor_roles` filter) can access their specific vendor dashboard. The plugin automatically detects and supports various marketplace plugins including WCFM, Dokan, and WC Vendors.

The vendor dashboard includes the following functionality:

- WhatsApp connection status tracking
- Session management with QR code scanning
- Product synchronization with WhatsApp catalogs
- Recently synced products list
- WhatsApp integration toggle
- Activity logs viewing

You can customize the dashboard appearance with the following attributes:

```
[wpwa_vendor_dashboard title="Your Custom Title" show_logs="yes|no"]
```

### Vendor Dashboard

A dedicated dashboard for vendors to manage their WhatsApp integration is available through a shortcode:

1. Create a new page in WordPress
2. Add the shortcode `[wpwa_vendor_dashboard]` to the page content
3. Publish the page
4. Share the page with your vendors

**Note:** Only users with vendor roles (configurable through the `wpwa_allowed_vendor_roles` filter) can access their specific vendor dashboard. The plugin automatically detects and supports various marketplace plugins including WCFM, Dokan, and WC Vendors.

The vendor dashboard includes the following functionality:

- WhatsApp connection status tracking
- Session management with QR code scanning
- Product synchronization with WhatsApp catalogs
- Recently synced products list
- WhatsApp integration toggle
- Activity logs viewing

You can customize the dashboard appearance with the following attributes:

```
[wpwa_vendor_dashboard title="Your Custom Title" show_logs="yes|no"]
```

## Development

### Version Control

This project uses Git for version control. To contribute or make changes:

1. Clone the repository
2. Create a feature branch: `git checkout -b feature/my-new-feature`
3. Make your changes
4. Commit your changes: `git commit -m "Add some feature"`
5. Push to the branch: `git push origin feature/my-new-feature`
6. Submit a pull request

### Release Process

To create a new release:

1. Update version number in `wp-whatsapp-api.php`
2. Update `CHANGELOG.md` with details of changes
3. Commit changes: `git commit -m "Prepare release vX.Y.Z"`
4. Tag the new version: `git tag -a vX.Y.Z -m "Version X.Y.Z"`
5. Push changes and tags: `git push origin main && git push origin --tags`

The GitHub Actions workflow will automatically create a release with a downloadable ZIP file.

## Support

For support, please open an issue in the GitHub repository or contact the plugin author.

## License

This plugin is licensed under the GPL v2 or later.
