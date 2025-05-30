# Changelog

All notable changes to the WhatsApp API for WooCommerce plugin will be documented in this file.

## [1.1.0] - 2025-05-31

### Added
- Advanced settings for connection timeout and request retries
- API usage tracking functionality with user opt-in
- Improved error handling and logging for API requests

### Enhanced
- Retry logic for API requests to handle temporary connection issues
- Extended timeout settings for file uploads
- Debug mode to track API request/response details

## [1.0.1] - 2025-05-30

### Fixed
- Resolved fatal errors during plugin activation with improved WooCommerce dependency handling
- Fixed dashicons loading issue by explicitly enqueueing dashicons
- Improved table creation timing in Cart and Customer Manager classes
- Added proper dependency verification system for WooCommerce

### Added
- Implemented versioning system with semantic versioning (MAJOR.MINOR.PATCH)
- Created CHANGELOG.md to track version changes

## [1.0.0] - Initial Release

### Added
- Initial release of WhatsApp API for WooCommerce
- Integration with WooCommerce for multivendor marketplaces
- Vendor session management for WhatsApp connections
- Product catalog synchronization
- Order management through WhatsApp
- Cart management for WhatsApp customers
- Admin dashboard for configuration and settings
- Vendor dashboard for session and order management
