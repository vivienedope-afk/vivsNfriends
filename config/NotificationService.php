<?php
/**
 * Notification Service
 * Handles Email and SMS notifications with preference checking
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService {
    private $conn;
    private $db_host;
    private $db_user;
    private $db_pass;
    private $db_name;

    public function __construct($database_connection = null) {
        if ($database_connection) {
            $this->conn = $database_connection;
        } else {
            require_once(__DIR__ . '/database.php');
            $this->conn = getDBConnection();
        }

        // Load PHPMailer classes (manual install in lib/PHPMailer/src)
        $phpmailer_base = __DIR__ . '/../lib/PHPMailer/src/';
        if (file_exists($phpmailer_base . 'Exception.php')) {
            require_once($phpmailer_base . 'Exception.php');
        }
        if (file_exists($phpmailer_base . 'PHPMailer.php')) {
            require_once($phpmailer_base . 'PHPMailer.php');
        }
        if (file_exists($phpmailer_base . 'SMTP.php')) {
            require_once($phpmailer_base . 'SMTP.php');
        }
    }

    /**
     * Get user notification preferences
     * @param int $user_id
     * @return array Preferences array
     */
    public function getUserPreferences($user_id) {
        $query = "SELECT email_notifications, sms_notifications FROM notification_preferences WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return [
            'email_notifications' => true,
            'sms_notifications' => false
        ];
    }

    /**
     * Get user email and phone
     * @param int $user_id
     * @return array User contact info
     */
    public function getUserContact($user_id) {
        $query = "SELECT email, contact_number, first_name, last_name FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    /**
     * Send Email Notification
     * @param int $user_id
     * @param string $subject
     * @param string $message
     * @param string $type (announcement, payment, maintenance, etc)
     * @return bool Success status
     */
    public function sendEmailNotification($user_id, $subject, $message, $type = 'general') {
        // Check preferences
        $preferences = $this->getUserPreferences($user_id);
        if (!$preferences['email_notifications']) {
            $this->logNotification($user_id, 'email', $subject, 'skipped', 'User has disabled email notifications');
            return true; // Consider as success since user doesn't want it
        }

        // Get user info
        $user = $this->getUserContact($user_id);
        if (!$user || empty($user['email'])) {
            $this->logNotification($user_id, 'email', $subject, 'failed', 'No email address found');
            return false;
        }

        // Prepare email
        $to = $user['email'];
        $name = $user['first_name'] . ' ' . $user['last_name'];
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: Maia Alta HOA <noreply@maiaalthoa.com>" . "\r\n";
        $headers .= "Reply-To: support@maiaalthoa.com" . "\r\n";

        // Build HTML email
        $html_message = $this->buildEmailTemplate($name, $message, $type);

        // Send email using PHPMailer Gmail SMTP if configured, otherwise use local mail()
        $email_sent = false;
        $gmail_config_file = __DIR__ . '/gmail_config.php';

        if (file_exists($gmail_config_file)) {
            require_once $gmail_config_file;

            if (
                defined('GMAIL_ENABLED') && GMAIL_ENABLED &&
                defined('GMAIL_HOST') &&
                defined('GMAIL_PORT') &&
                defined('GMAIL_ADDRESS') &&
                defined('GMAIL_PASSWORD') &&
                class_exists('PHPMailer\\PHPMailer\\PHPMailer')
            ) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = GMAIL_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = GMAIL_ADDRESS;
                    $mail->Password = GMAIL_PASSWORD;
                    $mail->Port = (int) GMAIL_PORT;

                    if (defined('GMAIL_SECURE') && GMAIL_SECURE === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    }

                    $from_name = defined('GMAIL_FROM_NAME') ? GMAIL_FROM_NAME : 'Maia Alta HOA';
                    $reply_to = defined('GMAIL_REPLY_TO') ? GMAIL_REPLY_TO : GMAIL_ADDRESS;

                    $mail->setFrom(GMAIL_ADDRESS, $from_name);
                    $mail->addAddress($to, $name);
                    $mail->addReplyTo($reply_to, $from_name);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $html_message;
                    $mail->CharSet = 'UTF-8';

                    $email_sent = $mail->send();

                    if ($email_sent) {
                        error_log("Email sent via PHPMailer SMTP: To={$to}, Subject={$subject}");
                    }
                } catch (Exception $e) {
                    $email_sent = false;
                    error_log("PHPMailer SMTP failed: To={$to}, Error=" . $e->getMessage());
                }
            }
        }

        // Optional fallback for environments without SMTP configuration
        if (!$email_sent) {
            $email_sent = mail($to, $subject, $html_message, $headers);
        }

        if ($email_sent) {
            $this->logNotification($user_id, 'email', $subject, 'sent', null);
            return true;
        } else {
            $this->logNotification($user_id, 'email', $subject, 'failed', 'Email sending failed');
            return false;
        }
    }

    /**
     * Send SMS Notification
     * @param int $user_id
     * @param string $message
     * @param string $type
     * @return bool Success status
     */
    public function sendSMSNotification($user_id, $message, $type = 'general') {
        // Check preferences
        $preferences = $this->getUserPreferences($user_id);
        if (!$preferences['sms_notifications']) {
            $this->logNotification($user_id, 'sms', 'SMS', 'skipped', 'User has disabled SMS notifications');
            return true;
        }

        // Get user info
        $user = $this->getUserContact($user_id);
        if (!$user || empty($user['contact_number'])) {
            $this->logNotification($user_id, 'sms', 'SMS', 'failed', 'No phone number found');
            return false;
        }

        // Prepare SMS
        $phone = $this->formatPhoneNumber($user['contact_number']);
        
        // If SMS gateway is configured, use it. For now, we'll support Twilio and a mock gateway
        $sms_sent = $this->sendViaSMSGateway($phone, $message);

        if ($sms_sent) {
            $this->logNotification($user_id, 'sms', 'SMS', 'sent', null);
            return true;
        } else {
            $this->logNotification($user_id, 'sms', 'SMS', 'failed', 'SMS gateway failed');
            return false;
        }
    }

    /**
     * Send both Email and SMS notifications
     * @param int $user_id
     * @param string $subject Email subject
     * @param string $message Email body and SMS message
     * @param string $type
     * @return array Results array
     */
    public function sendNotification($user_id, $subject, $message, $type = 'general') {
        $results = [
            'email' => $this->sendEmailNotification($user_id, $subject, $message, $type),
            'sms' => $this->sendSMSNotification($user_id, $message, $type),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $results;
    }

    /**
     * Build HTML email template
     * @param string $name
     * @param string $message
     * @param string $type
     * @return string HTML email
     */
    private function buildEmailTemplate($name, $message, $type) {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #8B7355; color: #fff; padding: 15px; border-radius: 5px; text-align: center; }
        .content { padding: 20px 0; }
        .footer { background-color: #f9f9f9; padding: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Maia Alta HOA</h2>
        </div>
        <div class="content">
            <p>Hello {$name},</p>
            <div>{$message}</div>
            <p>Best regards,<br/>Maia Alta HOA Management Team</p>
        </div>
        <div class="footer">
            <p>This is an automated notification. Please do not reply to this email.</p>
            <p>&copy; 2025 Maia Alta Homes. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
        return $html;
    }

    /**
     * Format phone number for SMS gateway
     * @param string $phone
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Add country code if not present (assuming Philippines)
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
            $phone = '63' . substr($phone, 1);
        } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            $phone = '63' . substr($phone, 2);
        }
        
        return $phone;
    }

    /**
     * Send SMS via configured gateway
     * Supports: Mock (file logging), Twilio, etc.
     * @param string $phone
     * @param string $message
     * @return bool Success status
     */
    private function sendViaSMSGateway($phone, $message) {
        $gateway = $this->getSMSGateway();
        
        switch ($gateway) {
            case 'twilio':
                return $this->sendViaTwilio($phone, $message);
            case 'mock':
            default:
                return $this->sendViaLocalLog($phone, $message);
        }
    }

    /**
     * Get configured SMS gateway type
     * @return string Gateway type
     */
    private function getSMSGateway() {
        // Check if config file exists
        $config_file = __DIR__ . '/sms_config.php';
        if (file_exists($config_file)) {
            require_once $config_file;
            return defined('SMS_GATEWAY') ? SMS_GATEWAY : 'mock';
        }
        return 'mock';
    }

    /**
     * Send SMS via Twilio
     * @param string $phone
     * @param string $message
     * @return bool Success status
     */
    private function sendViaTwilio($phone, $message) {
        // This is a placeholder for Twilio integration
        // In production, you would use the Twilio SDK
        // For now, we'll return true if Twilio is configured
        
        $config_file = __DIR__ . '/sms_config.php';
        if (!file_exists($config_file)) {
            return false;
        }

        require_once $config_file;
        
        if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_PHONE_NUMBER')) {
            return false;
        }

        // To use Twilio, install the SDK: composer require twilio/sdk
        // For now, this is a placeholder
        error_log("SMS via Twilio to {$phone}: {$message}");
        return true;
    }

    /**
     * Send SMS via local log (mock/testing)
     * @param string $phone
     * @param string $message
     * @return bool Always returns true
     */
    private function sendViaLocalLog($phone, $message) {
        $log_dir = __DIR__ . '/../logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/sms_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] Phone: {$phone} | Message: {$message}\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        error_log("SMS (mock) sent to {$phone}");
        
        return true;
    }

    /**
     * Log notification to database
     * @param int $user_id
     * @param string $type (email, sms)
     * @param string $subject
     * @param string $status (sent, failed, skipped)
     * @param string $error_message
     * @return bool Success status
     */
    private function logNotification($user_id, $type, $subject, $status, $error_message = null) {
        try {
            // First, ensure notification_log table exists
            $create_table = "CREATE TABLE IF NOT EXISTS notification_log (
                log_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                notification_type ENUM('email', 'sms', 'push') DEFAULT 'email',
                subject VARCHAR(255) NOT NULL,
                status ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_status (status)
            )";
            $this->conn->query($create_table);
            
            $query = "INSERT INTO notification_log (user_id, notification_type, subject, status, error_message) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                // If prepare fails, just log to error_log and return
                error_log("Failed to prepare notification_log insert: " . $this->conn->error);
                return false;
            }

            $stmt->bind_param("issss", $user_id, $type, $subject, $status, $error_message);
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error logging notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notification log for a user
     * @param int $user_id
     * @param int $limit
     * @return array Notification logs
     */
    public function getNotificationLog($user_id, $limit = 20) {
        $query = "SELECT * FROM notification_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }

        return $logs;
    }

    /**
     * Broadcast notification to multiple users
     * @param array $user_ids
     * @param string $subject
     * @param string $message
     * @param string $type
     * @return array Results for each user
     */
    public function broadcastNotification($user_ids, $subject, $message, $type = 'announcement') {
        $results = [];

        foreach ($user_ids as $user_id) {
            $results[$user_id] = $this->sendNotification($user_id, $subject, $message, $type);
        }

        return $results;
    }

    /**
     * Send payment reminder notifications
     * @param int $household_id
     * @param float $amount
     * @param string $due_date
     * @return array Results
     */
    public function sendPaymentReminder($household_id, $amount, $due_date) {
        // Get all household members
        $query = "SELECT u.user_id, u.first_name, u.last_name FROM users u
                  INNER JOIN household_members hm ON u.user_id = hm.user_id
                  WHERE hm.household_id = ? AND u.status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $household_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $results = [];
        $subject = "Payment Reminder - Monthly Dues";
        $message = "Your monthly dues of PHP " . number_format($amount, 2) . " is due on {$due_date}. 
                   Please make your payment at your earliest convenience. Thank you!";

        while ($row = $result->fetch_assoc()) {
            $results[$row['user_id']] = $this->sendNotification(
                $row['user_id'],
                $subject,
                $message,
                'payment'
            );
        }

        return $results;
    }

    /**
     * Send announcement notification to all residents
     * @param string $title
     * @param string $content
     * @return array Results
     */
    public function sendAnnouncementToAll($title, $content) {
        $query = "SELECT user_id FROM users WHERE user_role = 'resident' AND status = 'active'";
        $result = $this->conn->query($query);

        $user_ids = [];
        while ($row = $result->fetch_assoc()) {
            $user_ids[] = $row['user_id'];
        }

        return $this->broadcastNotification($user_ids, $title, $content, 'announcement');
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
