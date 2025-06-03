# WhatsApp Vendor Dashboard User Guide

## Introduction

Welcome to the WhatsApp Integration Vendor Dashboard! This guide will help you understand how to use the dashboard to connect your WhatsApp account, synchronize your products, and manage your WhatsApp integration.

## Accessing the Dashboard

Your marketplace administrator will provide you with a link to access your vendor dashboard. After logging into your vendor account, navigate to this page to access your personal WhatsApp integration dashboard.

## Dashboard Overview

The vendor dashboard consists of several sections:

1. **WhatsApp Connection Status** - Shows if your WhatsApp account is currently connected
2. **WhatsApp Session** - Allows you to connect/disconnect your WhatsApp account
3. **Product Synchronization** - Manage product syncing between your store and WhatsApp
4. **WhatsApp Settings** - Configure your WhatsApp integration preferences
5. **Activity Logs** - View recent activities and operations

## Connecting Your WhatsApp Account

### Initial Connection

1. Navigate to the "WhatsApp Session" section of the dashboard
2. Click the "Connect WhatsApp" button
3. A QR code will appear on your screen
4. Open WhatsApp on your phone
5. Go to Settings > WhatsApp Web/Desktop
6. Tap "Link a Device"
7. Scan the QR code displayed on your dashboard
8. Wait for the connection to be confirmed

### Connection Status

After scanning the QR code, your connection status will change to one of the following:

- **Initializing** - Connection is being established
- **Waiting for scan** - QR code is ready to be scanned
- **Connected** - Successfully connected to WhatsApp
- **Disconnected** - Not connected to WhatsApp

### Disconnecting

To disconnect your WhatsApp account:

1. Click the "Disconnect" button in the WhatsApp Session section
2. Confirm the disconnection when prompted
3. Your session will be closed both on the dashboard and on your phone

## Synchronizing Products

### How Synchronization Works

When you synchronize products, the system will:

1. Gather all your active products from your store
2. Format them according to WhatsApp catalog requirements
3. Submit them to WhatsApp's product catalog API
4. Update your dashboard with the synchronization status

### Starting Product Sync

1. Navigate to the "Product Synchronization" section
2. Click the "Sync Products" button
3. The system will queue your products for synchronization
4. You'll see a confirmation message with the number of products queued

### Checking Sync Status

The dashboard automatically displays your synchronization statistics:

- Total products in your store
- Number of products successfully synced
- Products pending synchronization
- Failed synchronizations

You'll also see a list of recently synchronized products with their current status.

### Troubleshooting Failed Syncs

If products fail to synchronize:

1. Check the product details in your store (missing images or descriptions can cause failures)
2. View the error message in the Activity Logs section
3. Fix any issues with the product in your store
4. Try synchronizing again

## Managing WhatsApp Settings

### Enabling/Disabling WhatsApp

You can temporarily disable WhatsApp integration without disconnecting:

1. Go to the "WhatsApp Settings" section
2. Toggle the "Enable WhatsApp for Sales" switch on or off
3. When disabled, your WhatsApp account remains connected, but customers won't be able to interact with you via WhatsApp

## Viewing Activity Logs

Activity logs help you track operations performed through your WhatsApp integration:

1. Click the "View Logs" button in the Activity Logs section
2. Browse through recent activities including:
   - Connection/disconnection events
   - Product synchronization attempts
   - Setting changes
   - Error messages

## Best Practices

1. **Keep your WhatsApp connected** - Disconnections may cause missed customer inquiries
2. **Regularly sync products** - Ensure your WhatsApp catalog stays updated with your store
3. **Check logs periodically** - Address any issues that appear in your activity logs
4. **Update product information** - Well-described products with clear images synchronize better
5. **Logout from other devices** - If you experience connection issues, try logging out from WhatsApp Web on other devices

## Troubleshooting

### QR Code Expired

If your QR code expires before scanning:

1. Click "Connect WhatsApp" again to generate a new code
2. Scan the new code promptly

### Connection Issues

If you're having trouble connecting:

1. Ensure your phone has an active internet connection
2. Verify WhatsApp is updated to the latest version
3. Try disconnecting WhatsApp Web from all other devices
4. Restart your phone and try again

### Product Sync Problems

If products aren't appearing in your WhatsApp catalog:

1. Check if the products were successfully synchronized in your dashboard
2. Verify products have all required attributes (image, price, description)
3. Ensure your WhatsApp Business account is properly set up
4. Allow up to 24 hours for WhatsApp to process new products

## Getting Help

If you continue experiencing issues with the WhatsApp integration, please contact your marketplace administrator for assistance.
