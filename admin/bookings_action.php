<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : (isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0);

if (!$booking_id) {
    header("Location: bookings.php?error=invalid_booking");
    exit();
}

switch ($action) {
    case 'approve':
        $booking_fee = isset($_POST['booking_fee']) ? (float)$_POST['booking_fee'] : 0;
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        
        // Check if booking exists and is pending
        $check_query = "SELECT status FROM facility_bookings WHERE booking_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            header("Location: bookings.php?error=booking_not_found");
            exit();
        }
        
        if ($booking['status'] != 'pending') {
            header("Location: bookings.php?error=booking_not_pending");
            exit();
        }
        
        // Update booking status
        $update_query = "UPDATE facility_bookings 
                        SET status = 'approved', 
                            approved_by = ?, 
                            approved_at = NOW(),
                            booking_fee = ?,
                            remarks = ?
                        WHERE booking_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("idsi", $current_user['user_id'], $booking_fee, $remarks, $booking_id);
        
        if ($stmt->execute()) {
            header("Location: bookings.php?success=approved");
        } else {
            header("Location: bookings.php?error=approve_failed");
        }
        break;
        
    case 'reject':
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        
        if (empty($remarks)) {
            header("Location: bookings.php?error=remarks_required");
            exit();
        }
        
        // Check if booking exists and is pending
        $check_query = "SELECT status FROM facility_bookings WHERE booking_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            header("Location: bookings.php?error=booking_not_found");
            exit();
        }
        
        if ($booking['status'] != 'pending') {
            header("Location: bookings.php?error=booking_not_pending");
            exit();
        }
        
        // Update booking status
        $update_query = "UPDATE facility_bookings 
                        SET status = 'rejected', 
                            approved_by = ?, 
                            approved_at = NOW(),
                            remarks = ?
                        WHERE booking_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isi", $current_user['user_id'], $remarks, $booking_id);
        
        if ($stmt->execute()) {
            header("Location: bookings.php?success=rejected");
        } else {
            header("Location: bookings.php?error=reject_failed");
        }
        break;
        
    default:
        header("Location: bookings.php?error=invalid_action");
        exit();
}

$conn->close();
?>
