<?php
/**
 * Notification Helper Functions
 * Easy-to-use functions for triggering notifications throughout the application
 */

require_once(__DIR__ . '/NotificationService.php');

/**
 * Initialize notification service (singleton pattern)
 */
$_notification_service = null;

function getNotificationService() {
    global $_notification_service;
    if ($_notification_service === null) {
        $_notification_service = new NotificationService();
    }
    return $_notification_service;
}

/**
 * Send payment reminder to household
 * @param int $household_id
 * @param float $amount
 * @param string $due_date
 */
function notifyPaymentDue($household_id, $amount, $due_date) {
    $service = getNotificationService();
    return $service->sendPaymentReminder($household_id, $amount, $due_date);
}

/**
 * Send announcement to specific resident
 * @param int $user_id
 * @param string $title
 * @param string $content
 */
function notifyAnnouncement($user_id, $title, $content) {
    $service = getNotificationService();
    return $service->sendNotification($user_id, $title, $content, 'announcement');
}

/**
 * Broadcast announcement to all residents
 * @param string $title
 * @param string $content
 */
function broadcastAnnouncement($title, $content) {
    $service = getNotificationService();
    return $service->sendAnnouncementToAll($title, $content);
}

/**
 * Send maintenance notification
 * @param int $user_id
 * @param string $maintenance_type
 * @param string $description
 * @param string $scheduled_date
 */
function notifyMaintenance($user_id, $maintenance_type, $description, $scheduled_date) {
    $service = getNotificationService();
    $subject = "Maintenance Notice - {$maintenance_type}";
    $message = "
        <p>Dear Resident,</p>
        <p>We have scheduled maintenance work at your unit:</p>
        <ul>
            <li><strong>Type:</strong> {$maintenance_type}</li>
            <li><strong>Description:</strong> {$description}</li>
            <li><strong>Scheduled Date:</strong> {$scheduled_date}</li>
        </ul>
        <p>Please make sure someone is available during this time.</p>
        <p>If you have any concerns, please contact the management office.</p>
    ";
    return $service->sendNotification($user_id, $subject, $message, 'maintenance');
}

/**
 * Send payment success notification
 * @param int $user_id
 * @param float $amount
 * @param string $reference_number
 */
function notifyPaymentSuccess($user_id, $amount, $reference_number) {
    $service = getNotificationService();
    $subject = "Payment Confirmation";
    $message = "
        <p>Dear Resident,</p>
        <p>Your payment has been received and verified.</p>
        <ul>
            <li><strong>Amount Paid:</strong> PHP " . number_format($amount, 2) . "</li>
            <li><strong>Reference #:</strong> {$reference_number}</li>
            <li><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</li>
        </ul>
        <p>Thank you for your prompt payment.</p>
    ";
    return $service->sendNotification($user_id, $subject, $message, 'payment');
}

/**
 * Send account application status update
 * @param int $user_id
 * @param string $status (approved, rejected)
 * @param string $notes
 */
function notifyApplicationStatus($user_id, $status, $notes = '') {
    $service = getNotificationService();
    
    if ($status === 'approved') {
        $subject = "Account Application Approved";
        $message = "
            <p>Congratulations! Your account application has been approved.</p>
            <p>You can now log in to your account using your account number and password.</p>
            " . (!empty($notes) ? "<p><strong>Notes:</strong> {$notes}</p>" : "") . "
            <p>Welcome to Maia Alta Homes!</p>
        ";
    } else {
        $subject = "Account Application Status Update";
        $message = "
            <p>Thank you for your application. Unfortunately, your account application has been reviewed.</p>
            " . (!empty($notes) ? "<p><strong>Reason:</strong> {$notes}</p>" : "") . "
            <p>Please contact the management office if you have questions.</p>
        ";
    }
    
    return $service->sendNotification($user_id, $subject, $message, 'account');
}

/**
 * Send event notification
 * @param array $user_ids
 * @param string $event_title
 * @param string $event_description
 * @param string $event_date
 * @param string $event_time
 * @param string $event_location
 */
function notifyEvent($user_ids, $event_title, $event_description, $event_date, $event_time, $event_location) {
    $service = getNotificationService();
    $subject = "Event Notice - {$event_title}";
    $message = "
        <p>You are invited to:</p>
        <h3>{$event_title}</h3>
        <p>{$event_description}</p>
        <ul>
            <li><strong>Date:</strong> {$event_date}</li>
            <li><strong>Time:</strong> {$event_time}</li>
            <li><strong>Location:</strong> {$event_location}</li>
        </ul>
        <p>We look forward to your attendance!</p>
    ";
    
    $results = [];
    if (is_array($user_ids)) {
        foreach ($user_ids as $user_id) {
            $results[$user_id] = $service->sendNotification($user_id, $subject, $message, 'event');
        }
    } else {
        $results = $service->sendNotification($user_ids, $subject, $message, 'event');
    }
    
    return $results;
}

/**
 * Send emergency notification
 * @param int $user_id
 * @param string $message
 */
function notifyEmergency($user_id, $message) {
    $service = getNotificationService();
    $subject = "URGENT: Emergency Notification";
    return $service->sendNotification($user_id, $subject, $message, 'emergency');
}

/**
 * Send bulk urgent notification to all residents
 * @param string $subject
 * @param string $message
 */
function broadcastEmergency($subject, $message) {
    $service = getNotificationService();
    return $service->sendAnnouncementToAll($subject, $message);
}

/**
 * Send password reset notification
 * @param int $user_id
 * @param string $reset_link
 */
function notifyPasswordReset($user_id, $reset_link) {
    $service = getNotificationService();
    $subject = "Password Reset Request";
    $message = "
        <p>We received a request to reset your password.</p>
        <p><a href='{$reset_link}' style='background-color: #8B7355; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
        <p>This link expires in 24 hours.</p>
    ";
    return $service->sendNotification($user_id, $subject, $message, 'account');
}

/**
 * Get notification history for a user
 * @param int $user_id
 * @param int $limit
 */
function getNotificationHistory($user_id, $limit = 20) {
    $service = getNotificationService();
    return $service->getNotificationLog($user_id, $limit);
}

/**
 * Send test notification to user
 * @param int $user_id
 * @return array Results
 */
function sendTestNotification($user_id) {
    $service = getNotificationService();
    $subject = "Test Notification - Maia Alta HOA";
    $message = "This is a test notification from Maia Alta HOA notification system. If you received this, your notifications are working properly!";
    return $service->sendNotification($user_id, $subject, $message, 'test');
}

?>
