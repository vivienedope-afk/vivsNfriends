<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = $_GET['action'] ?? '';
$booking_id = (int)($_GET['id'] ?? 0);
$remarks = trim($_GET['remarks'] ?? '');

if ($booking_id <= 0 || $action === '') {
    header('Location: bookings.php?error=invalid');
    exit();
}

$status_map = [
  'approve' => 'approved',
  'reject' => 'rejected',
    'cancel' => 'cancelled',
    'archive' => 'cancelled'
];

if (isset($status_map[$action])) {
    $new_status = $status_map[$action];
    $sql = "UPDATE facility_bookings
            SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ?
            WHERE booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sisi', $new_status, $current_user['user_id'], $remarks, $booking_id);
    if ($stmt->execute()) {
        // Non-blocking notification so status update remains successful even if email fails.
        $notif = notifyResidentBookingStatus($conn, $booking_id);
        if ($notif === false || (is_array($notif) && empty($notif['email']) && empty($notif['sms']))) {
            error_log('notifyResidentBookingStatus failed or skipped for booking_id=' . $booking_id);
        }
        header('Location: bookings.php?success=status');
        exit();
    }
    header('Location: bookings.php?error=status_failed');
    exit();
}

if ($action === 'mark_paid' || $action === 'mark_unpaid') {
    $payment_status = ($action === 'mark_paid') ? 'paid' : 'unpaid';
    $sql = "UPDATE facility_bookings SET payment_status = ? WHERE booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $payment_status, $booking_id);
    if ($stmt->execute()) {
        header('Location: bookings.php?success=payment');
        exit();
    }
    header('Location: bookings.php?error=payment_failed');
    exit();
}

if ($action === 'delete') {
    $check_sql = "SELECT booking_id FROM facility_bookings WHERE booking_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $booking_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $booking = $check_result ? $check_result->fetch_assoc() : null;

    if (!$booking) {
        header('Location: bookings.php?error=invalid');
        exit();
    }

    $delete_sql = "DELETE FROM facility_bookings WHERE booking_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $booking_id);
    if ($delete_stmt->execute()) {
        header('Location: bookings.php?success=deleted');
        exit();
    }
    header('Location: bookings.php?error=delete_failed');
    exit();
}

header('Location: bookings.php?error=unsupported');
exit();
?>