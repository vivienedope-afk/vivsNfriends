<?php
/**
 * Gmail SMTP Configuration
 * Fill these values with your real Gmail + App Password credentials.
 */

define('GMAIL_ENABLED', true);
define('GMAIL_HOST', 'smtp.gmail.com');
define('GMAIL_PORT', 587);
define('GMAIL_SECURE', 'tls'); // tls or ssl

// TODO: Replace with your sender Gmail address
define('GMAIL_ADDRESS', 'yourgmail@gmail.com');

// TODO: Replace with your 16-character Gmail App Password
define('GMAIL_PASSWORD', 'YOUR_16_CHAR_APP_PASSWORD');

// Display name for outgoing email
define('GMAIL_FROM_NAME', 'Maia Alta HOA');

// Reply-to for recipients
define('GMAIL_REPLY_TO', 'yourgmail@gmail.com');
?>
