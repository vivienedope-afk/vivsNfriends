# SMS & Email Notification System - Integration Guide

A comprehensive notification system for Maia Alta HOA that sends Email and SMS notifications with user preference management.

## 📋 Features

- ✅ **Email Notifications** - Using PHP mail() function
- ✅ **SMS Notifications** - With Twilio, AWS SNS, or Mock gateway support
- ✅ **Notification Preferences** - Users can toggle email/SMS on/off
- ✅ **Unified Notification Service** - Single API for all notification types
- ✅ **Automation Helpers** - Easy-to-use functions for common notifications
- ✅ **Notification Logging** - Track all sent notifications for auditing
- ✅ **Test Dashboard** - Comprehensive testing interface for administrators

## 🚀 Quick Start

### 1. Database Setup

The system requires several new tables. Execute the migration:

```sql
-- Run this in your MySQL database console
source config/databaseHndlr/sql/migration_add_notification_log.sql;
```

Or use the test dashboard to create tables automatically:
- Navigate to `test_notifications.php`
- Click "Create Missing Tables"

### 2. Files Added/Modified

**New Files:**
- `config/NotificationService.php` - Core notification service class
- `config/NotificationHelper.php` - Helper functions for easy notification sending
- `config/sms_config.php` - SMS gateway configuration
- `test_notifications.php` - Admin test dashboard
- `config/databaseHndlr/sql/migration_add_notification_log.sql` - Database migration

**Modified Files:**
- `save_notification_settings.php` - Updated to use NotificationService
- `script.js` - Added test notification and history functions
- `home.php` - Added test buttons and history viewer
- `database.sql` - Added notification_log and sms_gateway_config tables

### 3. Configuration

**Email Configuration:**
- Edit `config/sms_config.php` to configure email sender details
- By default uses PHP's `mail()` function
- Optional: Configure SMTP for better delivery (see sms_config.php comments)

**SMS Configuration (Default: Mock):**
The system comes with a Mock SMS gateway that logs messages to a file instead of actually sending them. This is perfect for testing.

To enable Twilio SMS:
1. Create a Twilio account at https://www.twilio.com/
2. Get your Account SID, Auth Token, and Phone Number
3. Edit `config/sms_config.php`:
   ```php
   define('SMS_GATEWAY', 'twilio');
   define('TWILIO_ACCOUNT_SID', 'your_sid_here');
   define('TWILIO_AUTH_TOKEN', 'your_auth_token');
   define('TWILIO_PHONE_NUMBER', '+1234567890');
   ```
4. Install Twilio SDK: `composer require twilio/sdk`

## 📖 Usage

### For Administrators - Testing

1. Navigate to: `admin/test_notifications.php` or `test_notifications.php`
2. Use the test dashboard to verify system functionality

### For Developers - Sending Notifications

**Basic Usage:**

```php
require_once('config/NotificationHelper.php');

// Send a simple notification
notifyAnnouncement($user_id, "Title", "Message content");

// Send payment reminder
notifyPaymentDue($household_id, 150.00, "2025-02-10");

// Send maintenance notice
notifyMaintenance($user_id, "Pipe Repair", "Water main repair", "2025-01-15");

// Broadcast to all residents
broadcastAnnouncement("Important Notice", "System maintenance scheduled");
```

**Advanced Usage:**

```php
require_once('config/NotificationService.php');

$notifier = new NotificationService();

// Send both email and SMS
$result = $notifier->sendNotification(
    $user_id,
    "Subject Line",
    "Message body HTML",
    'announcement'
);

// Get notification history
$history = $notifier->getNotificationLog($user_id, $limit = 20);

// Broadcast to multiple users
$results = $notifier->broadcastNotification(
    [1, 2, 3, 4], // User IDs
    "Title",
    "Message",
    'event'
);
```

### For Users - Managing Preferences

1. Click Settings icon (⚙️) in their dashboard
2. Toggle Email and SMS notification preferences
3. Click "Save Settings" to apply
4. Click "Send Test Notification" to verify setup
5. View notification history with "View History"

## 🔧 Notification Types

System supports the following notification types:

| Type | Use Case |
|------|----------|
| `announcement` | General announcements to residents |
| `payment` | Payment reminders and confirmations |
| `maintenance` | Maintenance work notifications |
| `event` | Event invitations and updates |
| `account` | Account-related notifications |
| `emergency` | Urgent/emergency notifications |
| `test` | Testing notifications |

## 📊 Database Tables

### notification_preferences
Stores individual user notification preferences

| Column | Type | Description |
|--------|------|-------------|
| preference_id | INT | Primary key |
| user_id | INT | Foreign key to users |
| email_notifications | BOOLEAN | Enable email (default: TRUE) |
| sms_notifications | BOOLEAN | Enable SMS (default: FALSE) |
| updated_at | TIMESTAMP | Last update time |

### notification_log
Tracks all sent notifications

| Column | Type | Description |
|--------|------|-------------|
| log_id | INT | Primary key |
| user_id | INT | Foreign key to users |
| notification_type | ENUM | email, sms, push |
| subject | VARCHAR | Notification subject |
| status | ENUM | sent, failed, skipped |
| error_message | TEXT | Error details if failed |
| created_at | TIMESTAMP | When notification was processed |

### sms_gateway_config
Stores SMS gateway configuration

| Column | Type | Description |
|--------|------|-------------|
| config_id | INT | Primary key |
| gateway_type | ENUM | twilio, aws_sns, mock |
| is_active | BOOLEAN | Is this gateway active |
| config_data | JSON | Configuration details |
| last_tested | TIMESTAMP | Last test time |
| updated_by | INT | Admin who changed it |

## 📁 File Structure

```
vivsNfriends/
├── config/
│   ├── NotificationService.php          ← Core service
│   ├── NotificationHelper.php           ← Helper functions
│   ├── sms_config.php                   ← SMS configuration
│   ├── database.php                     ← DB connection
│   └── databaseHndlr/sql/
│       └── migration_add_notification_log.sql
├── logs/
│   ├── sms_log.txt                      ← Mock SMS log
│   └── notification_errors.log          ← Error log
├── test_notifications.php               ← Admin test page
├── save_notification_settings.php       ← Preferences API
├── script.js                            ← Updated with new functions
├── home.php                             ← Updated UI
└── database.sql                         ← Updated schema
```

## 🧪 Testing

### Automated Testing

1. Go to `test_notifications.php` (admin only)
2. Run "Check Database" to verify tables
3. Run "Check Configuration" to verify files
4. Run "Send Test Email" to test email system
5. Run "Send Test SMS" to test SMS system
6. Run "Send Both Tests" to test complete system
7. Run "Broadcast to All Residents" to test broadcast

### Manual Testing

1. Log in as a resident
2. Go to Settings (⚙️ icon)
3. Enable Email and SMS notifications
4. Click "Send Test Notification"
5. Check email inbox and SMS messages
6. Check "View History" to see notification log

### Checking SMS Logs (Mock)

Mock SMS messages are logged to: `logs/sms_log.txt`

Each entry shows:
- Timestamp
- Phone number
- Message text

## 🐛 Troubleshooting

### Issue: "Unknown column in notification_log"
**Solution:** Run the database migration or click "Create Missing Tables" in test dashboard

### Issue: SMS not being sent
**Solution:** Check that SMS gateway is enabled in `config/sms_config.php`

### Issue: Emails not received
**Solution:** 
- Check PHP mail configuration in `php.ini`
- Verify email addresses in contacts_number field are valid
- Check spam/junk folder
- Enable SMTP in `config/sms_config.php` for better delivery

### Issue: Can't access test page
**Solution:** Must be logged in as admin. Regular users cannot access test_notifications.php

### Issue: SMS to mock gateway not appearing in log
**Solution:** Check file permissions on `logs/` directory. Must be writable.

## 🔐 Security Considerations

1. **Authentication** - All notification APIs require logged-in user
2. **User Preferences** - Respects individual user notification preferences
3. **Data Logging** - All notifications logged for audit trail
4. **Email Escaping** - Uses htmlspecialchars() to prevent injection
5. **Database** - Uses prepared statements to prevent SQL injection
6. **API Throttling** - Implement rate limiting to prevent spam (optional)

## 📝 Implementation Checklist

- [ ] Run database migration
- [ ] Verify all files are in place
- [ ] Test email sending with test dashboard
- [ ] Test SMS gateway (default: Mock)
- [ ] Optional: Configure Twilio for real SMS
- [ ] Test notification preferences toggle
- [ ] Test broadcast functionality
- [ ] Verify notification logs are being recorded
- [ ] Update admin user guides
- [ ] Train support staff on system
- [ ] Monitor logs for errors

## 📞 Support

For issues or questions about the notification system:

1. Check the Troubleshooting section
2. Review detailed logs in `logs/` directory
3. Access test dashboard: `test_notifications.php`
4. Check database tables with: `SHOW TABLES LIKE 'notification%'`

## 🎯 Future Enhancements

- [ ] Push notifications (in-app alerts)
- [ ] WhatsApp integration
- [ ] SMS rate limiting
- [ ] Scheduled notifications
- [ ] Custom notification templates
- [ ] Notification digest (daily/weekly summary)
- [ ] Read receipts for in-app notifications
- [ ] Multi-language support

---

**System Created:** April 2026  
**Version:** 1.0  
**Status:** Production Ready
