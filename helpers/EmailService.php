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

class EmailService {

    private $from_email = 'maiaaltahoa@gmail.com'; 
    private $from_name  = 'SIAA';
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /* =========================
       MAIN EMAIL SENDER
    ========================== */
    public function sendEmail($recipient_email, $recipient_name, $subject, $body, $email_type = 'general', $user_id = null, $related_id = null) {

        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email'];
        }

        $sent = $this->sendViaSMTP($recipient_email, $recipient_name, $subject, $body);

        $log_id = $this->logEmail(
            $recipient_email,
            $recipient_name,
            $subject,
            $body,
            $sent ? 'sent' : 'failed',
            $email_type,
            $user_id,
            $related_id,
            $sent ? null : 'SMTP failed'
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
    private function sendViaSMTP($recipient_email, $recipient_name, $subject, $body) {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'maiaaltahoa@gmail.com'; 
            $mail->Password   = 'wxjn nllj pmka gpns'; // Siguraduhin na App Password ito
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($recipient_email, $recipient_name);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            return $mail->send();

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
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