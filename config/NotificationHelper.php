<?php
/**
 * NotificationHelper.php
 * Central notification dispatcher for Maia Alta HOA
 */

require_once __DIR__ . '/../helpers/EmailService.php';
require_once __DIR__ . '/../helpers/SmsService.php';
require_once __DIR__ . '/EmailVerificationHelper.php';

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
     HELPER: Get resident by household with fallbacks
     ============================================================ */
  function getResidentByHouseholdWithFallback($conn, $household_id) {
    $resident = getResidentByHousehold($conn, $household_id);
    if ($resident) {
      return $resident;
    }

    $fallback_sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.contact_number,
                COALESCE(np.email_notifications, 1) AS email_on,
                COALESCE(np.sms_notifications, 0) AS sms_on,
                h.unit_number
             FROM households h
             INNER JOIN household_members hm ON h.household_id = hm.household_id
             INNER JOIN users u ON hm.user_id = u.user_id
             LEFT JOIN notification_preferences np ON u.user_id = np.user_id
             WHERE h.household_id = ? AND u.user_role = 'resident'
             ORDER BY hm.is_primary DESC, hm.member_id ASC
             LIMIT 1";
    $fallback_stmt = $conn->prepare($fallback_sql);
    if ($fallback_stmt) {
      $fallback_stmt->bind_param("i", $household_id);
      $fallback_stmt->execute();
      $resident = $fallback_stmt->get_result()->fetch_assoc();
      if ($resident) {
        return $resident;
      }
    }

    $owner_sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.contact_number,
               COALESCE(np.email_notifications, 1) AS email_on,
               COALESCE(np.sms_notifications, 0) AS sms_on,
               h.unit_number
            FROM households h
            INNER JOIN users u ON h.owner_id = u.user_id
            LEFT JOIN notification_preferences np ON u.user_id = np.user_id
            WHERE h.household_id = ? AND u.user_role = 'resident'
            LIMIT 1";
    $owner_stmt = $conn->prepare($owner_sql);
    if ($owner_stmt) {
      $owner_stmt->bind_param("i", $household_id);
      $owner_stmt->execute();
      $resident = $owner_stmt->get_result()->fetch_assoc();
      if ($resident) {
        return $resident;
      }
    }

    return null;
  }

/* ============================================================
   HELPER: Log to notification_log
   ============================================================ */
function logNotification($conn, $user_id, $type, $subject, $status, $error = null) {
    static $schemaChecked = false;
    if (!$schemaChecked) {
        $conn->query("ALTER TABLE notification_log MODIFY COLUMN status ENUM('queued', 'sent', 'failed', 'skipped') DEFAULT 'queued'");
        $schemaChecked = true;
    }

    $allowedStatuses = ['queued', 'sent', 'failed', 'skipped'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'failed';
    }

    $sql = "INSERT INTO notification_log (user_id, notification_type, subject, status, error_message)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $type, $subject, $status, $error);
        $stmt->execute();
    }
}

function buildSmsLogMeta($sms_result) {
    $meta = [
      'status' => 'failed',
      'detail' => 'SMS failed'
    ];

    if (!is_array($sms_result)) {
      return $meta;
    }

    $providerState = strtolower(trim((string)($sms_result['provider_state'] ?? '')));
    $providerMessage = trim((string)($sms_result['provider_message'] ?? ($sms_result['message'] ?? '')));
    $providerMessageId = trim((string)($sms_result['provider_message_id'] ?? ''));

    if (!empty($sms_result['success'])) {
      if (in_array($providerState, ['sent', 'delivered', 'success'], true)) {
        $meta['status'] = 'sent';
      } else {
        $meta['status'] = 'queued';
      }

      $parts = [];
      if ($providerState !== '') {
        $parts[] = 'provider_state=' . $providerState;
      }
      if ($providerMessageId !== '') {
        $parts[] = 'message_id=' . $providerMessageId;
      }
      if ($providerMessage !== '') {
        $parts[] = $providerMessage;
      }

      $meta['detail'] = empty($parts) ? null : implode(' | ', $parts);
      return $meta;
    }

    $errorMessage = trim((string)($sms_result['error'] ?? ''));
    if ($errorMessage === '') {
      $errorMessage = trim((string)($sms_result['message'] ?? 'SMS failed'));
    }

    $parts = ['provider_state=failed'];
    if ($providerMessageId !== '') {
      $parts[] = 'message_id=' . $providerMessageId;
    }
    if ($errorMessage !== '') {
      $parts[] = $errorMessage;
    }

    $meta['detail'] = implode(' | ', $parts);
    return $meta;
}

function logSmsNotification($conn, $user_id, $subject, $sms_result) {
    $meta = buildSmsLogMeta($sms_result);
    logNotification($conn, $user_id, 'sms', $subject, $meta['status'], $meta['detail']);
    return $meta;
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
            <p><a href='http://localhost/lokongTo/vivsNfriends/admin/bookings.php' style='background:#c17f59;color:white;padding:10px 20px;text-decoration:none;border-radius:5px'>Review Booking</a></p>
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

    $resident = getResidentByHouseholdWithFallback($conn, (int)$booking['household_id']);
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
      logSmsNotification($conn, $resident['user_id'], $subject, $sms_result);
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
   3B. NOTIFY APPLICANT THAT APPLICATION IS RECEIVED (VERIFICATION FLOW)
   ============================================================ */
function notifyApplicantVerificationReceived($conn, $application_id) {
  ensureEmailVerificationSchema($conn);

    $sql = "SELECT * FROM account_applications WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    if (!$app || empty($app['email'])) return false;

    $email_svc = getEmailService($conn);
    $name = trim($app['first_name'] . ' ' . $app['last_name']);

    $token = createApplicationVerificationToken($conn, $application_id);
    if (!$token) {
      return false;
    }
    $verification_url = getEmailVerificationUrl($token);

    $subject = "MAIA ALTA HOA - Application Received (Verification in Progress)";

    $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
          <div style='background:#2c3e50;padding:20px;text-align:center'>
            <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
          </div>
          <div style='padding:24px;background:#fff'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We received your account application and your details are now under verification.</p>
            <p>Please verify your email address by clicking the button below:</p>
            <p>
              <a href='{$verification_url}' style='display:inline-block;background:#2c3e50;color:#fff;padding:10px 18px;text-decoration:none;border-radius:6px'>
                Verify My Email
              </a>
            </p>
            <div style='background:#f6f8fa;padding:16px;border-left:4px solid #2c3e50;margin:16px 0'>
              <p><strong>Application ID:</strong> {$application_id}</p>
              <p><strong>Unit Number:</strong> {$app['unit_number']}</p>
              <p><strong>Status:</strong> Pending Verification</p>
            </div>
            <p style='font-size:12px;color:#666'>If button does not work, copy this link:<br>{$verification_url}</p>
            <p>You will receive another email once your application is approved or rejected.</p>
            <p style='color:#888;font-size:12px'>- Maia Alta HOA Administration</p>
          </div>
        </div>";

    return $email_svc->sendEmail($app['email'], $name, $subject, $body, 'verification', null, $application_id);
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
      logSmsNotification($conn, null, $subject, $sms_result);
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
    
    $resident = getResidentByHouseholdWithFallback($conn, (int)$due['household_id']);
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
      logSmsNotification($conn, $resident['user_id'], $subject, $sms_result);
    }
    
    return ['email' => $email_ok, 'sms' => $sms_ok];
}

  /* ============================================================
     5A. SEND NEW DUE POSTED NOTIFICATION (SMS)
     ============================================================ */
  function sendNewDuePostedSms($conn, $dues_id) {
    $sql = "SELECT md.*, h.unit_number
        FROM monthly_dues md
        INNER JOIN households h ON md.household_id = h.household_id
        WHERE md.dues_id = ?
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $dues_id);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_assoc();
    if (!$due) return false;

    $resident = getResidentByHouseholdWithFallback($conn, (int)$due['household_id']);
    if (!$resident) return false;
    if (empty($resident['contact_number'])) {
      return ['sms' => false, 'reason' => 'no_contact_number'];
    }

    $sms_svc = getSmsService($conn);

    $amount = number_format((float)$due['amount'], 2);
    $dueDate = date('M d, Y', strtotime($due['due_date']));
    $period = $due['due_month'] . ' ' . $due['due_year'];
    $dueTitle = trim((string)($due['title'] ?? ''));
    if ($dueTitle === '') {
      $dueTitle = 'Monthly HOA Dues';
    }
    $dueDescription = trim((string)($due['description'] ?? ''));

    $sms_msg = "MAIA ALTA HOA: {$dueTitle}";
    if ($dueDescription !== '') {
      $sms_msg .= " ({$dueDescription})";
    }
    $sms_msg .= " posted for {$period}. Amount: PHP {$amount}. Due date: {$dueDate}.";
    $sms_msg = substr($sms_msg, 0, 160);

    $sms_result = $sms_svc->sendSms($resident['contact_number'], $sms_msg, 'billing');
    $sms_ok = !empty($sms_result['success']);
    $sms_meta = buildSmsLogMeta($sms_result);

    $subject = "MAIA ALTA HOA - New Due Posted: {$period}";
    logNotification(
      $conn,
      (int)$resident['user_id'],
      'sms',
      $subject,
      $sms_meta['status'],
      $sms_meta['detail']
    );

    return ['sms' => $sms_ok, 'status' => $sms_meta['status'], 'detail' => $sms_meta['detail']];
  }

  /* ============================================================
     5B. SEND DUE PAYMENT SUCCESS CONFIRMATION (SMS)
     ============================================================ */
  function sendDuePaymentSuccessSms($conn, $dues_id, $amount_paid, $payment_date, $payment_method = null) {
    $sql = "SELECT md.*, h.unit_number
        FROM monthly_dues md
        INNER JOIN households h ON md.household_id = h.household_id
        WHERE md.dues_id = ?
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $dues_id);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_assoc();
    if (!$due) return false;

    $resident = getResidentByHouseholdWithFallback($conn, (int)$due['household_id']);

    if (!$resident) {
      return false;
    }

    if (empty($resident['contact_number'])) {
      return ['sms' => false, 'reason' => 'no_contact_number'];
    }

    $sms_svc = getSmsService($conn);

    $amount = number_format((float)$amount_paid, 2);
    $datePaid = date('M d, Y', strtotime($payment_date));
    $period = $due['due_month'] . ' ' . $due['due_year'];
    $dueTitle = trim((string)($due['title'] ?? ''));
    if ($dueTitle === '') {
      $dueTitle = 'Monthly HOA Dues';
    }
    $dueDescription = trim((string)($due['description'] ?? ''));
    $methodText = !empty($payment_method) ? (" via " . strtoupper($payment_method)) : '';

    $sms_msg = "MAIA ALTA HOA: Payment received for {$dueTitle}";
    if ($dueDescription !== '') {
      $sms_msg .= " ({$dueDescription})";
    }
    $sms_msg .= " {$period}. PHP {$amount} on {$datePaid}{$methodText}. Thank you.";
    $sms_msg = substr($sms_msg, 0, 160);

    $sms_result = $sms_svc->sendSms($resident['contact_number'], $sms_msg, 'billing');
    $sms_ok = !empty($sms_result['success']);
    $sms_meta = buildSmsLogMeta($sms_result);

    $subject = "MAIA ALTA HOA - Payment Received: {$period}";
    logNotification(
      $conn,
      (int)$resident['user_id'],
      'sms',
      $subject,
      $sms_meta['status'],
      $sms_meta['detail']
    );

    return ['sms' => $sms_ok, 'status' => $sms_meta['status'], 'detail' => $sms_meta['detail']];
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