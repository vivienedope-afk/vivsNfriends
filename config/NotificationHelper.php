<?php
/**
 * NotificationHelper.php
 * Central notification dispatcher for Maia Alta HOA
 */

require_once __DIR__ . '/../helpers/EmailService.php';
require_once __DIR__ . '/../helpers/SmsService.php';

/* ============================================================
   HELPER: Get admin email
   ============================================================ */
function getAdminEmail($conn) {
    $sql = "SELECT email FROM users WHERE user_role = 'admin' AND status = 'active' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['email'];
    }
    return null;
}

/* ============================================================
   HELPER: Get all active residents
   ============================================================ */
function getActiveResidents($conn) {
    $sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.contact_number,
                   COALESCE(np.email_notifications, 1) as email_on,
                   COALESCE(np.sms_notifications, 0) as sms_on
            FROM users u
            LEFT JOIN notification_preferences np ON u.user_id = np.user_id
            WHERE u.user_role = 'resident' AND u.status = 'active'";
    $result = $conn->query($sql);
    $residents = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $residents[] = $row;
        }
    }
    return $residents;
}

/* ============================================================
   HELPER: Get resident by household_id
   ============================================================ */
function getResidentByHousehold($conn, $household_id) {
    $sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.contact_number,
                   COALESCE(np.email_notifications, 1) as email_on,
                   COALESCE(np.sms_notifications, 0) as sms_on,
                   h.unit_number
            FROM households h
            INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
            INNER JOIN users u ON hm.user_id = u.user_id
            LEFT JOIN notification_preferences np ON u.user_id = np.user_id
            WHERE h.household_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/* ============================================================
   HELPER: Log to notification_log
   ============================================================ */
function logNotification($conn, $user_id, $type, $subject, $status, $error = null) {
    $sql = "INSERT INTO notification_log (user_id, notification_type, subject, status, error_message)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $type, $subject, $status, $error);
        $stmt->execute();
    }
}

/* ============================================================
   1. NOTIFY ADMIN OF NEW BOOKING
   ============================================================ */
function notifyAdminNewBooking($conn, $booking_id) {
    $admin_email = getAdminEmail($conn);
    if (!$admin_email) return false;
    
    $sql = "SELECT fb.*, h.unit_number
            FROM facility_bookings fb
            INNER JOIN households h ON fb.household_id = h.household_id
            WHERE fb.booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return false;
    
    $email_svc = getEmailService($conn);
    
    $subject = "MAIA ALTA HOA - New Facility Booking Request";
    $date = date('F d, Y', strtotime($booking['booking_date']));
    $start = date('g:i A', strtotime($booking['start_time']));
    $end = date('g:i A', strtotime($booking['end_time']));
    
    $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
          <div style='background:#c17f59;padding:20px;text-align:center'>
            <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
          </div>
          <div style='padding:24px;background:#fff'>
            <p>A new facility booking requires your review.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Unit Number</td><td style='padding:8px'>{$booking['unit_number']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Facility</td><td style='padding:8px'>{$booking['facility_name']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Date</td><td style='padding:8px'>{$date}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Time</td><td style='padding:8px'>{$start} - {$end}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Purpose</td><td style='padding:8px'>{$booking['purpose']}</td></tr>
             </table>
            <p><a href='http://localhost/vivsNfriends-emailsms/admin/bookings.php' style='background:#c17f59;color:white;padding:10px 20px;text-decoration:none;border-radius:5px'>Review Booking</a></p>
          </div>
        </div>";
    
    return $email_svc->sendEmail($admin_email, 'Admin', $subject, $body, 'booking', null, $booking_id);
}

/* ============================================================
   2. NOTIFY RESIDENT OF BOOKING STATUS
   ============================================================ */
function notifyResidentBookingStatus($conn, $booking_id) {
    $sql = "SELECT fb.*, h.unit_number
            FROM facility_bookings fb
            INNER JOIN households h ON fb.household_id = h.household_id
            WHERE fb.booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return false;

    $resident = getResidentByHousehold($conn, $booking['household_id']);
    if (!$resident) return false;

    $email_svc = getEmailService($conn);
    $sms_svc = getSmsService($conn);

    $status = strtoupper($booking['status']);
    $facility = $booking['facility_name'];
    $date = date('F d, Y', strtotime($booking['booking_date']));
    $start = date('g:i A', strtotime($booking['start_time']));
    $end = date('g:i A', strtotime($booking['end_time']));
    $color = $booking['status'] === 'approved' ? '#27ae60' : '#e74c3c';
    $icon = $booking['status'] === 'approved' ? '✅' : '❌';

    $subject = "MAIA ALTA HOA - Booking {$status}: {$facility}";

    $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
          <div style='background:#c17f59;padding:20px;text-align:center'>
            <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
          </div>
          <div style='padding:24px;background:#fff'>
            <p>Hello <strong>{$resident['first_name']} {$resident['last_name']}</strong> (Unit {$resident['unit_number']}),</p>
            <p>Your facility booking has been <strong style='color:{$color}'>{$icon} {$status}</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Facility</td><td style='padding:8px'>{$facility}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Date</td><td style='padding:8px'>{$date}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Time</td><td style='padding:8px'>{$start} - {$end}</td></tr>
            </table>
            <p style='color:#888;font-size:12px'>— Maia Alta HOA Administration</p>
          </div>
        </div>";

    $sms_msg = "MAIA ALTA HOA: Your booking for {$facility} on {$date} has been {$status}.";
    $sms_msg = substr($sms_msg, 0, 160);

    $email_ok = false;
    $sms_ok = false;

    if ($resident['email_on'] && !empty($resident['email'])) {
        $email_result = $email_svc->sendEmail($resident['email'], $resident['first_name'], $subject, $body, 'booking', $resident['user_id'], $booking_id);
        $email_ok = $email_result['success'];
        logNotification($conn, $resident['user_id'], 'email', $subject, $email_ok ? 'sent' : 'failed', $email_ok ? null : 'Email failed');
    }

    if ($resident['sms_on'] && !empty($resident['contact_number'])) {
        $sms_result = $sms_svc->sendSms($resident['contact_number'], $sms_msg);
        $sms_ok = $sms_result['success'];
        logNotification($conn, $resident['user_id'], 'sms', $subject, $sms_ok ? 'sent' : 'failed', $sms_ok ? null : ($sms_result['error'] ?? 'SMS failed'));
    }

    return ['email' => $email_ok, 'sms' => $sms_ok];
}

/* ============================================================
   3. NOTIFY ADMIN OF NEW APPLICATION
   ============================================================ */
function notifyAdminNewApplication($conn, $application_id) {
    $admin_email = getAdminEmail($conn);
    if (!$admin_email) return false;
    
    $sql = "SELECT * FROM account_applications WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    if (!$app) return false;
    
    $email_svc = getEmailService($conn);
    
    $subject = "MAIA ALTA HOA - New Account Application";
    $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
          <div style='background:#c17f59;padding:20px;text-align:center'>
            <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
          </div>
          <div style='padding:24px;background:#fff'>
            <p>A new account application requires your review.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Name</td><td style='padding:8px'>{$app['first_name']} {$app['last_name']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Email</td><td style='padding:8px'>{$app['email']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Contact</td><td style='padding:8px'>{$app['contact_number']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Unit</td><td style='padding:8px'>{$app['unit_number']}</td></tr>
              <tr><td style='padding:8px;background:#f9f9f9;font-weight:bold'>Resident Type</td><td style='padding:8px'>{$app['resident_type']}</td></tr>
             </table>
            <p><a href='http://localhost/vivsNfriends-emailsms/admin/view_application.php?id={$application_id}' style='background:#c17f59;color:white;padding:10px 20px;text-decoration:none;border-radius:5px'>Review Application</a></p>
          </div>
        </div>";
    
    return $email_svc->sendEmail($admin_email, 'Admin', $subject, $body, 'application', null, $application_id);
}

/* ============================================================
   4. NOTIFY APPLICANT OF APPLICATION STATUS
   ============================================================ */
function notifyApplicantApplicationStatus($conn, $application_id, $status, $rejection_reason = null) {
    $sql = "SELECT * FROM account_applications WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    if (!$app) return false;
    
    $email_svc = getEmailService($conn);
    $sms_svc = getSmsService($conn);
    
    $name = $app['first_name'] . ' ' . $app['last_name'];
    
    if ($status === 'approved') {
        $subject = "MAIA ALTA HOA - Your Application Has Been Approved!";
        $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
                  <div style='background:#27ae60;padding:20px;text-align:center'><h2 style='color:white;margin:0'>Maia Alta HOA</h2></div>
                  <div style='padding:24px;background:#fff'>
                    <p>Dear <strong>{$name}</strong>,</p>
                    <p>Congratulations! ✅ Your application has been <strong style='color:#27ae60'>APPROVED</strong>.</p>
                    <div style='background:#f0faf4;padding:16px;border-left:4px solid #27ae60;margin:16px 0'>
                      <p><strong>Unit Number:</strong> {$app['unit_number']}</p>
                    </div>
                    <p>Please wait for your account credentials to be sent to your email.</p>
                    <p style='color:#888;font-size:12px'>— Maia Alta HOA Administration</p>
                  </div></div>";
        $sms_msg = "MAIA ALTA HOA: Your application has been APPROVED! Unit: {$app['unit_number']}. Wait for your login credentials via email.";
    } else {
        $reason = $rejection_reason ?: 'Your application did not meet the current requirements.';
        $subject = "MAIA ALTA HOA - Application Status Update";
        $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
                  <div style='background:#e74c3c;padding:20px;text-align:center'><h2 style='color:white;margin:0'>Maia Alta HOA</h2></div>
                  <div style='padding:24px;background:#fff'>
                    <p>Dear <strong>{$name}</strong>,</p>
                    <p>We regret to inform you that your application has been <strong style='color:#e74c3c'>REJECTED</strong>.</p>
                    <div style='background:#fdf3f3;padding:16px;border-left:4px solid #e74c3c;margin:16px 0'>
                      <p><strong>Reason:</strong> {$reason}</p>
                    </div>
                    <p>If you have questions, please contact the HOA administration directly.</p>
                    <p style='color:#888;font-size:12px'>— Maia Alta HOA Administration</p>
                  </div></div>";
        $sms_msg = "MAIA ALTA HOA: Your application has been REJECTED. Reason: " . substr($reason, 0, 80);
    }
    
    $sms_msg = substr($sms_msg, 0, 160);
    
    $email_result = $email_svc->sendEmail($app['email'], $name, $subject, $body, 'application', null, $application_id);
    $email_ok = $email_result['success'];
    logNotification($conn, null, 'email', $subject, $email_ok ? 'sent' : 'failed', $email_ok ? null : 'Email failed');
    
    $sms_ok = false;
    if (!empty($app['contact_number'])) {
        $sms_result = $sms_svc->sendSms($app['contact_number'], $sms_msg);
        $sms_ok = $sms_result['success'];
        logNotification($conn, null, 'sms', $subject, $sms_ok ? 'sent' : 'failed', $sms_ok ? null : ($sms_result['error'] ?? 'SMS failed'));
    }
    
    return ['email' => $email_ok, 'sms' => $sms_ok];
}

/* ============================================================
   5. SEND DUE REMINDER
   ============================================================ */
function sendDueReminder($conn, $dues_id, $reminder_type = 'due_date') {
    $sql = "SELECT md.*, h.unit_number FROM monthly_dues md INNER JOIN households h ON md.household_id = h.household_id WHERE md.dues_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dues_id);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_assoc();
    if (!$due) return false;
    
    $resident = getResidentByHousehold($conn, $due['household_id']);
    if (!$resident) return false;
    
    $email_svc = getEmailService($conn);
    $sms_svc = getSmsService($conn);
    
    $amount = number_format($due['amount'], 2);
    $due_date = date('F d, Y', strtotime($due['due_date']));
    $label = $reminder_type === '3days' ? '3-Day Reminder' : ($reminder_type === 'overdue' ? 'OVERDUE NOTICE' : 'Due Date Reminder');
    $color = $reminder_type === 'overdue' ? '#e74c3c' : '#c17f59';
    
    $subject = "MAIA ALTA HOA - {$label}: Monthly Due {$due['due_month']} {$due['due_year']}";
    
    $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:{$color};padding:20px;text-align:center'><h2 style='color:white;margin:0'>Maia Alta HOA</h2></div>
             <div style='padding:24px;background:#fff'><p>Hello <strong>{$resident['first_name']} {$resident['last_name']}</strong> (Unit {$resident['unit_number']}),</p>
             " . ($reminder_type === 'overdue' ? "<p>⚠️ Your monthly HOA due is now <strong style='color:#e74c3c'>OVERDUE</strong>. Please settle immediately.</p>" : ($reminder_type === '3days' ? "<p>This is a friendly reminder that your monthly HOA due is due in <strong>3 days</strong>.</p>" : "<p>This is a reminder that your monthly HOA due is due <strong>today</strong>.</p>")) . "
             <div style='background:#f9f9f9;padding:16px;border-left:4px solid {$color};margin:16px 0'><p><strong>Period:</strong> {$due['due_month']} {$due['due_year']}</p>
             <p><strong>Amount Due:</strong> ₱{$amount}</p><p><strong>Due Date:</strong> {$due_date}</p></div>
             <p>Please settle your balance on or before the due date.</p>
             <p style='color:#888;font-size:12px'>— Maia Alta HOA Administration</p></div></div>";
    
    $sms_msg = "MAIA ALTA HOA [{$label}]: Monthly due for {$due['due_month']} {$due['due_year']} = ₱{$amount}. Due: {$due_date}.";
    $sms_msg = substr($sms_msg, 0, 160);
    
    $email_ok = false;
    if ($resident['email_on'] && !empty($resident['email'])) {
        $email_result = $email_svc->sendEmail($resident['email'], $resident['first_name'], $subject, $body, 'monthly_due', $resident['user_id'], $dues_id);
        $email_ok = $email_result['success'];
        logNotification($conn, $resident['user_id'], 'email', $subject, $email_ok ? 'sent' : 'failed', $email_ok ? null : 'Email failed');
    }
    
    $sms_ok = false;
    if ($resident['sms_on'] && !empty($resident['contact_number'])) {
        $sms_result = $sms_svc->sendSms($resident['contact_number'], $sms_msg);
        $sms_ok = $sms_result['success'];
        logNotification($conn, $resident['user_id'], 'sms', $subject, $sms_ok ? 'sent' : 'failed', $sms_ok ? null : ($sms_result['error'] ?? 'SMS failed'));
    }
    
    return ['email' => $email_ok, 'sms' => $sms_ok];
}

/* ============================================================
   6. BROADCAST ANNOUNCEMENT (EMAIL ONLY)
   ============================================================ */
function broadcastAnnouncement($conn, $title, $content, $type = 'general') {
    $residents = getActiveResidents($conn);
    if (empty($residents)) return [];
    
    $email_svc = getEmailService($conn);
    $results = [];
    $type_label = strtoupper($type);
    $subject = "MAIA ALTA HOA [{$type_label}] - {$title}";
    
    $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:#c17f59;padding:20px;text-align:center'><h2 style='color:white;margin:0'>Maia Alta HOA</h2></div>
             <div style='padding:24px;background:#fff'><p>Hello Resident,</p><h3 style='color:#c17f59'>{$title}</h3>
             <div style='background:#f9f9f9;padding:16px;border-left:4px solid #c17f59;margin:16px 0'>" . nl2br(htmlspecialchars($content)) . "</div>
             <p style='color:#888;font-size:12px'>— Maia Alta HOA Administration</p></div></div>";
    
    foreach ($residents as $r) {
        if ($r['email_on'] && !empty($r['email'])) {
            $email_result = $email_svc->sendEmail($r['email'], $r['first_name'], $subject, $body, 'announcement', $r['user_id']);
            $email_ok = $email_result['success'];
            logNotification($conn, $r['user_id'], 'email', $subject, $email_ok ? 'sent' : 'failed', $email_ok ? null : 'Email failed');
            $results[$r['user_id']] = ['email' => $email_ok];
        }
    }
    
    

    return $results;
}
?>