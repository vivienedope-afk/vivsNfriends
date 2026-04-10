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
    $end = date('g:i A', strtot