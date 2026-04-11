<?php
/**
 * Notification System - Integration Examples
 * 
 * This file shows common patterns for using the notification system
 * throughout the Maia Alta HOA application.
 * 
 * Example use cases:
 * 1. New announcement posted
 * 2. Payment reminder
 * 3. Payment received and verified
 * 4. Maintenance scheduled
 * 5. Account application status update
 */

// =====================================================
// EXAMPLE 1: Send Announcement to All Residents
// =====================================================

/**
 * From: admin/announcements.php (after posting new announcement)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

// After inserting announcement into database
$announcement_title = "Important: Water Main Maintenance";
$announcement_content = "
    <p>Dear Residents,</p>
    <p>We will perform water main maintenance on January 15, 2025.</p>
    <p>Expect water supply interruption from 9:00 AM to 2:00 PM.</p>
    <p>Thank you for your patience.</p>
";

// Send to all active residents
$results = broadcastAnnouncement($conn, $announcement_title, $announcement_content);

// Log results
foreach ($results as $user_id => $result) {
    error_log("Announcement sent to user {$user_id}: " . json_encode($result));
}
*/


// =====================================================
// EXAMPLE 2: Send Payment Reminder
// =====================================================

/**
 * From: admin/payments.php or automated cron job
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

// After querying overdue payments
$household_id = 5;
$monthly_dues = 150.00;
$due_date = '2025-02-10';

// Send payment reminder
$results = notifyPaymentDue($household_id, $monthly_dues, $due_date);

// The function automatically gets all household members and notifies them
*/


// =====================================================
// EXAMPLE 3: Send Payment Confirmation
// =====================================================

/**
 * From: admin/payments_action.php (after verifying payment)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

$user_id = 3; // Resident who made the payment
$amount_paid = 150.00;
$reference_number = 'BT987654321'; // Bank transfer reference

notifyPaymentSuccess($user_id, $amount_paid, $reference_number);

// User receives a confirmation notification
*/


// =====================================================
// EXAMPLE 4: Maintenance Notification
// =====================================================

/**
 * From: admin/maintenance.php (after scheduling maintenance)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

$user_id = 5; // Resident whose unit needs maintenance
$maintenance_type = "Plumbing Repair";
$description = "Repair leaking pipe in bathroom";
$scheduled_date = "2025-01-20 10:00 AM";

notifyMaintenance($user_id, $maintenance_type, $description, $scheduled_date);

// User gets a heads-up about upcoming maintenance
*/


// =====================================================
// EXAMPLE 5: Account Application Status Update
// =====================================================

/**
 * From: admin/view_application.php (after reviewing application)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

$applicant_user_id = 12; // ID of the applicant (if already created as user)
$status = 'approved'; // or 'rejected'
$notes = 'All documents verified. Welcome to Maia Alta!';

notifyApplicationStatus($applicant_user_id, $status, $notes);

// Applicant knows the status of their account application
*/


// =====================================================
// EXAMPLE 6: Event Invitation
// =====================================================

/**
 * From: admin/events.php (after creating event)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

$invited_user_ids = [1, 2, 3, 4, 5]; // All residents
$event_title = "Annual General Meeting";
$description = "Join us for our annual general meeting to discuss community matters";
$event_date = "2025-02-28";
$event_time = "6:00 PM - 8:00 PM";
$event_location = "Clubhouse";

notifyEvent($invited_user_ids, $event_title, $description, $event_date, $event_time, $event_location);

// All residents receive event invitation
*/


// =====================================================
// EXAMPLE 7: Emergency Broadcast
// =====================================================

/**
 * From: admin/dashboard.php (in case of emergency)
 * 
 * Usage:
 */

/*
require_once('config/NotificationHelper.php');

$emergency_title = "ALERT: Gas Leak Detected";
$emergency_message = "
    <p><strong>URGENT SAFETY ALERT</strong></p>
    <p>A gas leak has been detected in Block C.</p>
    <p>IMMEDIATE ACTIONS:</p>
    <ul>
        <li>Open all windows immediately</li>
        <li>Do NOT use any electrical devices or flames</li>
        <li>Evacuate the building immediately</li>
        <li>Call emergency services at 911</li>
    </ul>
";

broadcastEmergency($emergency_title, $emergency_message);

// All residents receive urgent alert via email and SMS
*/


// =====================================================
// EXAMPLE 8: Advanced - Custom Notification with Service
// =====================================================

/**
 * From: Any file needing granular control
 * 
 * Usage:
 */

/*
require_once('config/NotificationService.php');

$notifier = new NotificationService();

// Get specific user's notification preferences
$user_id = 5;
$preferences = $notifier->getUserPreferences($user_id);

if ($preferences['email_notifications']) {
    // Send email only
    $notifier->sendEmailNotification(
        $user_id,
        "Custom Email Subject",
        "Custom email body with HTML",
        'general'
    );
}

if ($preferences['sms_notifications']) {
    // Send SMS only
    $notifier->sendSMSNotification(
        $user_id,
        "SMS message text (max 160 chars)",
        'general'
    );
}

// Or send both respecting preferences
$result = $notifier->sendNotification(
    $user_id,
    "Email Subject",
    "Email body HTML",
    'custom_type'
);

// Get notification history
$history = $notifier->getNotificationLog($user_id, 50);
foreach ($history as $log) {
    echo "ID: {$log['log_id']}, Type: {$log['notification_type']}, Status: {$log['status']}\n";
}
*/


// =====================================================
// EXAMPLE 9: Broadcast to Specific User Group
// =====================================================

/**
 * From: Any admin function needing targeted broadcast
 * 
 * Usage:
 */

/*
require_once('config/database.php');
require_once('config/NotificationService.php');

$conn = getDBConnection();
$notifier = new NotificationService($conn);

// Get all users in a specific block
$query = "SELECT DISTINCT u.user_id 
          FROM users u
          INNER JOIN household_members hm ON u.user_id = hm.user_id
          INNER JOIN households h ON hm.household_id = h.household_id
          WHERE h.block_number = 'A'";

$result = $conn->query($query);
$block_a_users = [];
while ($row = $result->fetch_assoc()) {
    $block_a_users[] = $row['user_id'];
}

// Send notification to Block A only
$results = $notifier->broadcastNotification(
    $block_a_users,
    "Block A: Announcement",
    "This announcement is for Block A residents only.",
    'general'
);
*/


// =====================================================
// EXAMPLE 10: Scheduled/Cron Job - Payment Reminders
// =====================================================

/**
 * From: A cron job (run daily by server scheduler)
 * Path: admin/cron/send_payment_reminders.php
 * 
 * Usage: Add to crontab to run daily at 8:00 AM
 * 0 8 * * * php /path/to/admin/cron/send_payment_reminders.php
 */

/*
require_once('config/database.php');
require_once('config/NotificationHelper.php');

$conn = getDBConnection();

// Get all due payments
$query = "SELECT md.household_id, md.amount, md.due_date 
          FROM monthly_dues md
          WHERE md.status = 'unpaid'
          AND md.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND md.due_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY)";

$result = $conn->query($query);

$count = 0;
while ($row = $result->fetch_assoc()) {
    notifyPaymentDue(
        $row['household_id'],
        $row['amount'],
        $row['due_date']
    );
    $count++;
}

error_log("Sent {$count} payment reminder notifications");

$conn->close();
*/


// =====================================================
// INTEGRATION CHECKLIST
// =====================================================

/*
When integrating notifications in your code:

1. [ ] Include the required files:
   - require_once('config/NotificationHelper.php'); // For simple functions
   - require_once('config/NotificationService.php'); // For advanced control

2. [ ] After CREATE operations, send notifications:
   - After posting announcement → use broadcastAnnouncement($conn, ...)
   - After creating event → use notifyEvent()
   - After registering new account → use notifyApplicationStatus()

3. [ ] After UPDATE operations related to payments:
   - After verifying payment → use notifyPaymentSuccess()
   - Before payment due date → use notifyPaymentDue()

4. [ ] After UPDATE operations related to maintenance:
   - After scheduling maintenance → use notifyMaintenance()

5. [ ] For system events:
   - For emergencies → use broadcastEmergency()
   - For critical alerts → use broadcastEmergency()

6. [ ] Always handle the response:
   - Log results for debugging
   - Don't let failures block main operation
   - Display user-friendly messages

7. [ ] Test thoroughly:
   - Use verify_notifications.php to check setup
   - Use test_notifications.php to test sending
   - Check logs/sms_log.txt for mock SMS messages

Key Points:
- Notification system respects user preferences automatically
- All notifications are logged for audit trail
- Errors don't break the main application
- System is fault-tolerant and graceful

*/

?>
