# Migration Guide: Upgrading to WhatsApp Integration v1.2.0

## Overview

This guide provides instructions for marketplace administrators to safely upgrade from earlier versions of the WhatsApp Integration plugin to version 1.2.0, which introduces the new vendor dashboard feature.

## Table of Contents

1. [Before You Begin](#before-you-begin)
2. [Backup Process](#backup-process)
3. [Upgrade Procedure](#upgrade-procedure)
4. [Database Changes](#database-changes)
5. [Testing After Upgrade](#testing-after-upgrade)
6. [Rollback Procedure](#rollback-procedure)
7. [Common Issues](#common-issues)

## Before You Begin

### Version Requirements

- WordPress 5.6 or higher
- WooCommerce 4.0.0 or higher
- PHP 7.4 or higher

### Compatibility Check

Verify compatibility with your current marketplace extensions and theme:

1. **Multivendor Plugin Compatibility**:
   - WCFM Marketplace: v6.5.0 or higher
   - Dokan: v3.3.0 or higher
   - WC Vendors: v2.2.0 or higher
   - WooCommerce Product Vendors: v2.1.0 or higher

2. **Custom Integrations**:
   - Review any custom code interacting with WhatsApp Integration
   - Check for direct references to classes or functions that may have changed

3. **System Requirements**:
   - Memory limit: 128MB minimum, 256MB recommended
   - Max execution time: 60 seconds minimum

## Backup Process

### 1. Complete Site Backup

```bash
# Using WP-CLI to export database
wp db export wpwa_backup_$(date +"%Y%m%d").sql

# Backup plugin directory
zip -r wp-whatsapp-integration_backup_$(date +"%Y%m%d").zip wp-content/plugins/wp-whatsapp-integration/
```

### 2. Selective Data Backup

Backup critical WhatsApp integration data:

```sql
-- Export WhatsApp integration options
SELECT * FROM wp_options WHERE option_name LIKE 'wpwa_%';

-- Export vendor WhatsApp session data
SELECT * FROM wp_usermeta WHERE meta_key LIKE 'wpwa_%';

-- Export any custom tables
SELECT * FROM wp_wpwa_logs;
```

## Upgrade Procedure

### Method 1: Standard WordPress Update

1. Deactivate any third-party extensions that integrate with WhatsApp Integration
2. Navigate to Plugins → Installed Plugins
3. Deactivate WhatsApp Integration
4. Delete the plugin (your settings will be preserved)
5. Upload and install the new version (1.2.0)
6. Activate the plugin
7. Navigate to WhatsApp API → Settings to verify configuration

### Method 2: Manual Update

1. Deactivate the WhatsApp Integration plugin
2. Download the v1.2.0 release package
3. Extract package contents
4. Via FTP/SFTP, delete the existing `wp-whatsapp-integration` folder
5. Upload the new version to `wp-content/plugins/`
6. Activate the plugin

### Method 3: WP-CLI Update

```bash
# Update using WP-CLI
wp plugin deactivate wp-whatsapp-integration
wp plugin delete wp-whatsapp-integration
wp plugin install wp-whatsapp-integration.zip
wp plugin activate wp-whatsapp-integration
```

## Database Changes

Version 1.2.0 introduces several new database elements:

### 1. New Options

Added options:
- `wpwa_vendor_dashboard_options`: General vendor dashboard settings
- `wpwa_allowed_vendor_roles`: Roles that can access the vendor dashboard
- `wpwa_version`: Plugin version tracking for future migrations

### 2. User Meta Fields

New vendor-specific user meta fields:
- `wpwa_vendor_session`: WhatsApp session information
- `wpwa_vendor_status`: Connection status
- `wpwa_vendor_whatsapp_enabled`: Enable/disable toggle state
- `wpwa_vendor_last_sync`: Last product sync timestamp
- `wpwa_vendor_sync_stats`: Product synchronization statistics

### 3. Tables

New database table for activity logging:

```sql
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpwa_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    log_time datetime NOT NULL,
    log_type varchar(50) NOT NULL,
    log_message text NOT NULL,
    log_data longtext,
    log_status varchar(20) NOT NULL DEFAULT 'info',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY log_time (log_time),
    KEY log_type (log_type)
) {$charset_collate};
```

### Migration Process

The plugin automatically performs these migrations during activation:

1. Creates new tables if they don't exist
2. Adds default options if missing
3. Updates version number

No manual database changes are required.

## Testing After Upgrade

### 1. Administrator Dashboard Test

Verify the WhatsApp API admin panel functions correctly:

- Navigate to WhatsApp API → Settings
- Verify all settings are preserved
- Check connection status
- Test API connectivity

### 2. Vendor Dashboard Test

Create a test page to verify the vendor dashboard shortcode:

1. Create a new page titled "WhatsApp Vendor Test"
2. Add the shortcode: `[wpwa_vendor_dashboard]`
3. Preview the page as admin
4. Log in as a vendor to test access controls

### 3. Feature Verification Checklist

- [ ] WhatsApp connection via QR code works
- [ ] Product synchronization functions correctly
- [ ] Enable/disable toggle works
- [ ] Activity logs display properly
- [ ] Vendors can only access their own data
- [ ] AJAX requests complete successfully
- [ ] Mobile responsiveness checks out

## Rollback Procedure

If issues arise during upgrade, follow these steps to rollback:

### Method 1: Plugin Replacement

1. Deactivate the v1.2.0 plugin
2. Delete it from the Plugins page
3. Upload and install your backup version
4. Activate the previous version

### Method 2: Database Restoration

If database issues occur:

```sql
-- Restore options if needed
DELETE FROM wp_options WHERE option_name LIKE 'wpwa_vendor_%';
DELETE FROM wp_options WHERE option_name = 'wpwa_version';

-- Restore previous version number
UPDATE wp_options SET option_value = '1.1.0' WHERE option_name = 'wpwa_version';

-- Drop new tables if necessary
DROP TABLE IF EXISTS wp_wpwa_logs;
```

## Common Issues

### 1. WhatsApp Connection Problems

**Issue**: Vendors can't connect their WhatsApp accounts

**Solution**: 
- Check API credentials in WhatsApp API → Settings
- Verify server connectivity to WhatsApp servers
- Ensure firewall isn't blocking WebSocket connections
- Check browser console for JavaScript errors

### 2. Session Persistence Issues

**Issue**: WhatsApp sessions disconnect frequently

**Solution**:
- Increase PHP memory limit (256MB recommended)
- Adjust session timeout settings in wp-config.php:
  ```php
  define('WP_MEMORY_LIMIT', '256M');
  define('WPWA_SESSION_DURATION', 86400); // 24 hours
  ```

### 3. Product Sync Errors

**Issue**: Product synchronization fails

**Solution**:
- Check for product data requirements (missing images, etc.)
- Increase max execution time in php.ini
- Implement batch processing for large catalogs:
  ```php
  add_filter('wpwa_vendor_sync_batch_size', function() { return 25; });
  ```

### 4. Permission Issues

**Issue**: Some vendors can't access the dashboard

**Solution**:
- Check user roles against `wpwa_allowed_vendor_roles` setting
- Add custom roles as needed:
  ```php
  add_filter('wpwa_allowed_vendor_roles', function($roles) {
      $roles[] = 'custom_vendor_role';
      return $roles;
  });
  ```

### 5. Shortcode Display Issues

**Issue**: Dashboard doesn't render correctly

**Solution**:
- Check for theme or plugin conflicts
- Verify shortcode placement is correct (not in sidebar/widget)
- Add proper CSS containers around the shortcode:
  ```html
  <div class="wpwa-container"> 
      [wpwa_vendor_dashboard]
  </div>
  ```

## Additional Resources

- [Complete Documentation](https://example.com/wpwa-docs)
- [Support Portal](https://example.com/wpwa-support)
- [WhatsApp API Status](https://example.com/whatsapp-api-status)

---

If you encounter any issues not covered by this migration guide, please contact our support team for assistance.