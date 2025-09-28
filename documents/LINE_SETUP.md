# LINE Login and Messaging Integration Setup

This document explains how to set up LINE Login and messaging functionality for the Eastern Air Logistics system.

## Overview

The system now supports:
1. **LINE Login Integration**: Users can connect their LINE accounts to their system accounts
2. **LINE Messaging**: Send notifications and updates directly to users' LINE accounts

## Features Implemented

### 1. LINE Account Connection
- Users can connect their LINE accounts from the Profile page
- Secure OAuth2 flow using Laravel Socialite
- Store LINE user information (ID, display name, profile picture)
- One-to-one mapping between system users and LINE accounts

### 2. LINE Messaging Service
- Send text messages to connected LINE users
- Pre-built message templates for common notifications
- Shipment status updates
- Vessel arrival notifications
- Document reminders
- Welcome messages for new connections

### 3. User Interface
- **Profile Page**: LINE connection status and management
- **Connect Button**: Initiates LINE OAuth flow
- **Test Message**: Send test message to verify connection
- **Disconnect**: Remove LINE account connection

## Setup Instructions

### Step 1: Create LINE Developer Account
1. Go to [LINE Developers Console](https://developers.line.biz/)
2. Create a new provider (company account)
3. Create a new channel

### Step 2: Create LINE Login Channel
1. In LINE Developers Console, create a "LINE Login" channel
2. Configure the following settings:
   - **App Type**: Web App
   - **Callback URL**: `http://your-domain.com/line/callback`
   - **Scopes**: `profile`, `openid`
3. Note down:
   - Channel ID (Client ID)
   - Channel Secret (Client Secret)

### Step 3: Create Messaging API Channel
1. Create a "Messaging API" channel for sending messages
2. Generate a Channel Access Token
3. Note down:
   - Channel Access Token
   - Channel Secret

### Step 4: Configure Environment Variables
Add the following to your `.env` file:

```env
# LINE Configuration
LINE_BOT_CHANNEL_TOKEN=your_messaging_api_channel_token
LINE_BOT_CHANNEL_SECRET=your_messaging_api_channel_secret
LINE_LOGIN_CLIENT_ID=your_line_login_channel_id
LINE_LOGIN_CLIENT_SECRET=your_line_login_channel_secret
LINE_LOGIN_REDIRECT=http://your-domain.com/line/callback
```

### Step 5: Update Callback URL
In the LINE Developers Console, set the callback URL to:
- Development: `http://localhost:8000/line/callback`
- Production: `https://your-domain.com/line/callback`

## Usage

### For Users
1. **Connect LINE Account**:
   - Go to Profile page
   - Click "Connect to LINE" button
   - Authorize the application on LINE
   - Receive welcome message on LINE

2. **Test Connection**:
   - Click "Send Test Message" on Profile page
   - Check LINE app for test message

3. **Disconnect**:
   - Click "Disconnect LINE" on Profile page
   - Confirm disconnection

### For Developers

#### Send Custom Messages
```php
use App\Services\LineMessagingService;

$lineMessaging = new LineMessagingService();

// Send text message
$lineMessaging->sendTextMessage($lineUserId, 'Your message here');

// Send shipment notification
$lineMessaging->sendShipmentNotification($user, 'arrival', [
    'invoice_number' => 'INV-001',
    'customer_name' => 'ABC Company',
    'vessel_name' => 'SHIP NAME',
    'terminal' => 'Terminal A'
]);

// Send vessel arrival notification
$lineMessaging->sendVesselArrivalNotification($user, [
    'vessel_name' => 'VESSEL NAME',
    'voyage_code' => 'V001',
    'terminal' => 'Terminal B',
    'eta' => '2024-01-01 10:00'
]);
```

#### Check LINE Connection
```php
// Check if user has LINE account connected
if ($user->hasLineAccount()) {
    // Send LINE notification
}
```

## Database Schema

### Users Table Additions
```sql
ALTER TABLE users ADD COLUMN line_user_id VARCHAR(255) NULL UNIQUE;
ALTER TABLE users ADD COLUMN line_display_name VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN line_picture_url VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN line_connected_at TIMESTAMP NULL;
```

## Security Considerations

1. **LINE User ID Uniqueness**: Each LINE account can only be connected to one system user
2. **OAuth Security**: Uses secure OAuth2 flow with state validation
3. **Token Security**: Channel tokens are stored as environment variables
4. **Error Handling**: Comprehensive error logging and user feedback

## Troubleshooting

### Common Issues

1. **"LINE Login redirect error"**
   - Check if LINE_LOGIN_CLIENT_ID and LINE_LOGIN_CLIENT_SECRET are set correctly
   - Verify callback URL matches exactly with LINE Developers Console settings

2. **"Failed to send LINE message"**
   - Check if LINE_BOT_CHANNEL_TOKEN is valid
   - Ensure the bot is friends with the user (for Messaging API)
   - Check Laravel logs for detailed error messages

3. **"This LINE account is already connected"**
   - Each LINE account can only be connected to one system user
   - User needs to disconnect from previous account first

### Debug Mode
Enable debug logging by setting `LOG_LEVEL=debug` in `.env` file.

## Message Templates

The system includes pre-built message templates for:
- Welcome messages
- Shipment arrivals
- Customs clearance updates
- Delivery notifications
- Document reminders
- Test messages

## Future Enhancements

Possible future features:
1. **Rich Messages**: FlexMessage templates with buttons and carousels
2. **LINE Bot Commands**: Interactive bot responding to user commands
3. **Group Notifications**: Send notifications to LINE groups
4. **Webhook Integration**: Receive messages from LINE users
5. **Push Notification Scheduling**: Schedule automated notifications

## Support

For technical support:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode for detailed error messages
3. Verify LINE Developers Console configuration
4. Test with simple text messages first before complex notifications