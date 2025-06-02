# Changelog

All notable changes to the WhatsApp API for WooCommerce plugin will be documented in this file.

## [1.1.19] - 2025-06-02

### Fixed
- Added missing class-wpwa-message-manager.php causing fatal errors
- Created WPWA_Message_Manager implementation with support for all message types
- Enhanced message error handling and logging capabilities

## [1.1.18] - 2025-06-01

### Added
- Enhanced security with role-based JWT token generation
- Added configurable list of allowed roles for API access via 'wpwa_allowed_roles' filter
- Improved error messages for unauthorized API access attempts
- Added detailed logging for unauthorized token requests

## [1.1.17] - 2025-05-31

### Fixed
- Fixed Debug Mode toggle functionality in the frontend admin panel
- Fixed session data display in frontend admin Sessions tab
- Fixed logs display and retrieval in frontend admin panel
- Fixed Clear Logs button functionality in frontend admin panel
- Made logger globally accessible to ensure proper functionality across the system
- Improved log display format compatibility in the frontend JavaScript

### Added
- New Message Manager class for centralized handling of WhatsApp messaging operations
- Support for sending various message types: text, media, interactive buttons, lists, templates, and catalogs

## [1.1.16] - 2025-05-31

### Fixed
- Fixed AJAX nonce verification in the frontend admin panel to support both admin and frontend requests
- Updated AJAX handlers to accept both 'wpwa_nonce' and 'wpwa_frontend_nonce' parameters
- Improved security and reliability of the frontend admin panel AJAX operations
- Ensured consistent version numbering across all plugin files

## [1.1.15] - 2025-05-31

### Fixed
- Fixed nonce parameter name inconsistency between frontend JavaScript and backend PHP code
- Updated all nonce parameter names in AJAX handler for frontend admin panel to 'wpwa_frontend_nonce'
- Fixed 'Clear Logs' functionality in frontend admin panel
- Ensured consistent nonce verification across all frontend admin AJAX requests

## [1.1.14] - 2025-05-31

### Fixed
- Fixed frontend admin panel JavaScript variables (replaced wpwa_admin with wpwaFrontend)
- Added notification area creation function to improve user experience
- Updated CSS selectors to match the HTML structure from the shortcode
- Fixed nonce verification for frontend settings AJAX requests
- Corrected nonce parameter names in all frontend admin AJAX calls to use frontendNonce consistently
- Implemented comprehensive AJAX handler for frontend admin panel with proper nonce verification

## [1.1.13] - 2025-05-31

### Fixed
- Fixed critical error in API client initialization, ensuring proper dependency order
- Corrected constructor parameter in WPWA_API_Client instantiation

## [1.1.12] - 2025-05-31

### Added
- Frontend admin panel shortcode `[wpwa_frontend_admin]` to manage plugin settings from the frontend
- Support for JWT Secret generation directly from frontend for improved accessibility
- Responsive design for the frontend admin panel
- Permission controls to ensure only admins and shop managers can access the frontend panel
- Added frontend CSS and JavaScript files for the admin panel shortcode

## [1.1.11] - 2025-05-31

### Fixed
- Enhanced JWT Secret generation with improved security by using 64-character keys
- Added automatic JWT Secret verification during generation to ensure it works correctly
- Improved Auth Manager to support JWT Secret defined via PHP constant
- Fixed potential issues with missing JWT library dependencies

## [1.1.10] - 2025-05-31

### Fixed
- Fixed JWT Secret generation functionality by correcting nonce name in AJAX handler
- Updated nonce verification in admin settings page for consistent security checks
- Improved error handling for JWT Secret generation

## [1.1.9] - 2025-05-31

### Fixed
- Changed `log()` method from private to public in WPWA_Logger class to fix fatal error
- Fixed AJAX handler log method usage to correctly use logger methods

## [1.1.8] - 2025-05-31

### Fixed
- Fixed update notification system to use dynamic version checking instead of hardcoded values
- Implemented daily update checking with dismissible notifications
- Added proper update URL linking directly to WordPress update mechanism
- Fixed JWT Secret generation functionality in settings page

## [1.1.7] - 2025-05-31

### Fixed
- Fixed missing closing brace in vendor-dashboard.php causing PHP parse error
- Fixed plugin update process to handle directory renaming during updates
- Added method to properly fix plugin directory names during WordPress updates

## [1.1.6] - 2025-05-31

### Fixed
- Fixed plugin update process to prevent duplicate installations with '-1' suffix
- Added improved handling for plugin directory naming consistency
- Enhanced plugin header identification to ensure proper updates

## [1.1.5] - 2025-05-31

### Fixed
- Resolved PHP syntax error in vendor-dashboard.php due to unclosed curly brace

## [1.1.4] - 2025-05-31

### Fixed
- Added safeguards against duplicate constant definitions to prevent conflicts when multiple plugin instances are installed
- Added class_exists check to prevent 'cannot redeclare class' errors 
- Ensured WPWA_ASSETS_URL constant is properly defined before being used
- Fixed initialization to prevent duplicated instances of the main plugin class

## [1.1.3] - 2025-05-31

### Fixed
- Resolved critical error in admin menu registration (duplicate menu items) 
- Fixed incorrect asset paths causing CSS and JS files not to load
- Updated plugin structure to better handle menu registration

## [1.1.2] - 2025-05-31

### Fixed
- Fixed missing closing PHP tag in vendor-dashboard.php
- Added compatibility with WooCommerce High Performance Order Storage feature
- Updated plugin header to declare HPOS compatibility

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
