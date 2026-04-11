<?php
/**
 * SMS Gateway Configuration
 * 
 * This file configures which SMS gateway to use for sending SMS notifications.
 * Current supported gateways: 'mock', 'twilio', 'aws_sns'
 * 
 * Default: 'mock' - Logs SMS to file instead of actually sending
 * 
 * To enable Twilio:
 * 1. Create a Twilio account at https://www.twilio.com/
 * 2. Get your Account SID, Auth Token, and Phone Number from the dashboard
 * 3. Uncomment the Twilio section below and fill in your credentials
 * 4. Install Twilio SDK: composer require twilio/sdk
 */

// =====================================================
// MOCK GATEWAY (DEFAULT - For Testing)
// =====================================================
define('SMS_GATEWAY', 'mock');
// SMS messages are logged to logs/sms_log.txt

// =====================================================
// TWILIO GATEWAY
// =====================================================
// Uncomment and configure these to enable Twilio
/*
define('SMS_GATEWAY', 'twilio');
define('TWILIO_ACCOUNT_SID', 'your_account_sid_here');
define('TWILIO_AUTH_TOKEN', 'your_auth_token_here');
define('TWILIO_PHONE_NUMBER', '+1234567890'); // Your Twilio phone number
*/

// =====================================================
// AWS SNS GATEWAY
// =====================================================
// Uncomment and configure these to enable AWS SNS
/*
define('SMS_GATEWAY', 'aws_sns');
define('AWS_SNS_REGION', 'ap-southeast-1');
define('AWS_SNS_ACCESS_KEY', 'your_access_key_here');
define('AWS_SNS_SECRET_KEY', 'your_secret_key_here');
*/

// =====================================================
// EMAIL CONFIGURATION
// =====================================================

// Email sender name and address
define('EMAIL_FROM_NAME', 'Maia Alta HOA');
define('EMAIL_FROM_ADDRESS', 'noreply@maiaalthoa.com');
define('EMAIL_REPLY_TO', 'support@maiaalthoa.com');

// Optional: Configure SMTP for better email delivery
// Uncomment to enable SMTP configuration via PHPMailer
/*
define('USE_SMTP', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
*/

// =====================================================
// NOTIFICATION SETTINGS
// =====================================================

// Enable/disable notification types
define('ENABLE_EMAIL_NOTIFICATIONS', true);
define('ENABLE_SMS_NOTIFICATIONS', true);
define('ENABLE_PUSH_NOTIFICATIONS', false);

// Notification retry settings
define('NOTIFICATION_MAX_RETRIES', 3);
define('NOTIFICATION_RETRY_DELAY', 300); // seconds

// Notification rate limiting (prevent spam)
define('NOTIFICATION_RATE_LIMIT', true);
define('NOTIFICATION_RATE_LIMIT_PER_HOUR', 50); // Max notifications per user per hour

// =====================================================
// ERROR LOGGING
// =====================================================
define('NOTIFICATION_LOG_ERRORS', true);
define('NOTIFICATION_ERROR_LOG_FILE', __DIR__ . '/../../logs/notification_errors.log');

?>
