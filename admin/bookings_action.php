<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : (isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0);

switch ($action) {
    case 'approve':
        if (!$booking_id) { redirect('error=invalid_booking'); }
        
        $stmt = $conn->prepare("UPDATE facility_bookings SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE booking_id = ?");
        $stmt->bind_param("ii", $current_user['user_id'], $booking_id);
        
        if ($stmt->execute()) {
            notifyAdminNewBooking($conn, $booking_id);
            notifyResidentBookingStatus($conn, $booking_id);
            redirect('success=approved');
        } else {
            redirect('error=approve_failed');
        }
        break;
        
    case 'reject':
        if (!$booking_id) { redirect('error=invalid_booking'); }
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : (isset($_GET['remarks']) ? trim($_GET['remarks']) : '');
        
        $stmt = $conn->prepare("UPDATE facility_bookings SET status = 'rejected', remarks = ?, approved_by = ?, approved_at = NOW() WHERE booking_id = ?");
        $stmt->bind_param("sii", $remarks, $current_user['user_id'], $booking_id);
        
        if ($stmt->execute()) {
            notifyResidentBookingStatus($conn, $booking_id);
            redirect('success=rejected');
        } else {
            redirect('error=reject_failed');
        }
        break;
        
    case 'update_fee':
        if (!$booking_id) { redirect('error=invalid_booking'); }
        $fee = isset($_POST['booking_fee']) ? (float)$_POST['booking_fee'] : 0;
        
        $stmt = $conn->prepare("UPDATE facility_bookings SET booking_fee = ? WHERE booking_id = ?");
        $stmt->bind_param("di", $fee, $booking_id);
        
        if ($stmt->execute()) {
            redirect('success=fee_updated');
        } else {
            redirect('error=fee_update_failed');
        }
        break;
        
    default:
        redirect('error=invalid_action');
}

function redirect($param) {
    header("Location: bookings.php?{$param}");
    exit();
}
?>