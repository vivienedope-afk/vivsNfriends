<?php
/**
 * Email Service for Maia Alta HOA System
 * Uses PHPMailer (Gmail SMTP)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../test_mail/PHPMailer-master/src/Exception.php';
require __DIR__ . '/../test_mail/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../test_mail/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../config/env.php';

class EmailService {

    private $db;
    private $lastError = null;

    public function __construct($db) {
        $this->db = $db;
    }

    /* =========================
       MAIN EMAIL SENDER
    ========================== */
    public function sendEmail($recipient_email, $recipient_name, $subject, $body, $email_type = 'general', $user_id = null, $related_id = null) {
        $this->lastError = null;

        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email'];
        }

        $sent = $this->sendViaSMTP($recipient_email, $recipient_name, $subject, $body, $email_type);

        $log_id = $this->logEmail(
            $recipient_email,
            $recipient_name,
            $subject,
            $body,
            $sent ? 'sent' : 'failed',
            $email_type,
            $user_id,
            $related_id,
            $sent ? null : ($this->lastError ?: 'SMTP failed')
        );

        return [
            'success' => $sent,
            'log_id'  => $log_id
        ];
    }

    /* =========================
       MONTHLY BILLING EMAIL 
    ========================== */
    public function sendMonthlyDueEmail($user_id, $email, $name, $amount, $due_date, $month, $year) {

        $subject = "MAIA ALTA HOA Monthly Due – $month $year";

        $body = "
            <p>Hello <strong>$name</strong>,</p>
            <p>This is a reminder for your monthly HOA dues.</p>
            <p><strong>Amount Due:</strong> ₱" . number_format($amount, 2) . "</p>
            <p><strong>Due Date:</strong> $due_date</p>
            <br>
            <p>Please settle your balance on or before the due date.</p>
            <br>
            <p>— Maia Alta HOA</p>
        ";

        return $this->sendEmail(
            $email,
            $name,
            $subject,
            $body,
            'monthly_due',
            $user_id
        );
    }

    /* =========================
       PHPMailer SMTP
    ========================== */
    private function sendViaSMTP($recipient_email, $recipient_name, $subject, $body, $email_type = 'general') {
        try {
            $cfg = $this->getEmailConfig($email_type);
            if (empty($cfg['user']) || empty($cfg['pass'])) {
                $this->lastError = "Email SMTP config missing for type={$email_type}";
                error_log($this->lastError);
                return false;
            }

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['user'];
            $mail->Password   = $cfg['pass'];
            $mail->SMTPSecure = $cfg['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$cfg['port'];
            // Local PHP/OpenSSL on XAMPP may fail CA validation for SMTP TLS.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            if (!empty($cfg['reply_to'])) {
                $mail->addReplyTo($cfg['reply_to'], $cfg['from_name']);
            }
            $mail->addAddress($recipient_email, $recipient_name);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            return $mail->send();

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Email error: " . $this->lastError);
            return false;
        }
    }

    private function getEmailConfig($email_type = 'general') {
        $type = strtoupper(trim((string)$email_type));
        if ($type === '') {
            $type = 'DEFAULT';
        }

        $prefixMap = [
            'BOOKING' => 'EMAIL_BOOKING_',
            'APPLICATION' => 'EMAIL_APPLICATION_',
            'ANNOUNCEMENT' => 'EMAIL_ANNOUNCEMENT_',
            'VERIFICATION' => 'EMAIL_VERIFICATION_',
            'MONTHLY_DUE' => 'EMAIL_MONTHLY_DUE_',
            'GENERAL' => 'EMAIL_DEFAULT_'
        ];

        $prefix = isset($prefixMap[$type]) ? $prefixMap[$type] : 'EMAIL_DEFAULT_';

        $defaultHost = env('EMAIL_DEFAULT_HOST', 'smtp.gmail.com');
        $defaultPort = env('EMAIL_DEFAULT_PORT', 587);
        $defaultSecure = strtolower((string)env('EMAIL_DEFAULT_SECURE', 'tls'));
        $defaultUser = env('EMAIL_DEFAULT_USER', '');
        $defaultPass = env('EMAIL_DEFAULT_PASS', '');
        $defaultFromEmail = env('EMAIL_DEFAULT_FROM_EMAIL', $defaultUser);
        $defaultFromName = env('EMAIL_DEFAULT_FROM_NAME', 'Maia Alta HOA');
        $defaultReplyTo = env('EMAIL_DEFAULT_REPLY_TO', $defaultFromEmail);

        $cfg = [
            'host' => env($prefix . 'HOST', $defaultHost),
            'port' => env($prefix . 'PORT', $defaultPort),
            'secure' => strtolower((string)env($prefix . 'SECURE', $defaultSecure)),
            'user' => env($prefix . 'USER', $defaultUser),
            'pass' => env($prefix . 'PASS', $defaultPass),
            'from_email' => env($prefix . 'FROM_EMAIL', $defaultFromEmail),
            'from_name' => env($prefix . 'FROM_NAME', $defaultFromName),
            'reply_to' => env($prefix . 'REPLY_TO', $defaultReplyTo)
        ];

        if (empty($cfg['from_email'])) {
            $cfg['from_email'] = $cfg['user'];
        }

        if (empty($cfg['reply_to'])) {
            $cfg['reply_to'] = $cfg['from_email'];
        }

        return $cfg;
    }

    /* =========================
       EMAIL LOGGING
    ========================== */
    private function logEmail($email, $name, $subject, $body, $status, $type, $user_id, $related_id, $error) {
        // Siguraduhin na may table ka na 'email_logs' sa database mo
        $sql = "INSERT INTO email_logs
                (recipient_email, recipient_name, subject, body, delivery_status, email_type, user_id, related_id, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param(
            "ssssssiss",
            $email,
            $name,
            $subject,
            $body,
            $status,
            $type,
            $user_id,
            $related_id,
            $error
        );

        return $stmt->execute() ? $this->db->insert_id : null;
    }
}

/* Helper */
function getEmailService($conn) {
    return new EmailService($conn);
}
?>