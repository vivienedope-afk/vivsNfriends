<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add_dues':
        $household_id = (int)$_POST['household_id'];
        $due_month = $_POST['due_month'];
        $due_year = (int)$_POST['due_year'];
        $amount = (float)$_POST['amount'];
        $due_date = $_POST['due_date'];
        
        // Check if dues already exist for this household and period
        $check_query = "SELECT dues_id FROM monthly_dues WHERE household_id = ? AND due_month = ? AND due_year = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("isi", $household_id, $due_month, $due_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            header("Location: payments.php?error=duplicate");
            exit();
        }
        
        $insert_query = "INSERT INTO monthly_dues (household_id, due_month, due_year, amount, due_date, status) 
                        VALUES (?, ?, ?, ?, ?, 'unpaid')";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isids", $household_id, $due_month, $due_year, $amount, $due_date);
        
        if ($stmt->execute()) {
            header("Location: payments.php?success=dues_added");
        } else {
            header("Location: payments.php?error=add_failed");
        }
        break;
        
    case 'record_payment':
        $dues_id = (int)$_POST['dues_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = (float)$_POST['amount_paid'];
        $payment_method = $_POST['payment_method'];
        $reference_number = isset($_POST['reference_number']) ? $_POST['reference_number'] : null;
        $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : null;
        
        // Get household_id from dues
        $get_household = "SELECT household_id, amount FROM monthly_dues WHERE dues_id = ?";
        $stmt = $conn->prepare($get_household);
        $stmt->bind_param("i", $dues_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $dues = $result->fetch_assoc();
        
        if (!$dues) {
            header("Location: payments.php?error=dues_not_found");
            exit();
        }
        
        $household_id = $dues['household_id'];
        
        $conn->begin_transaction();
        
        try {
            // Insert payment record
            $insert_payment = "INSERT INTO payments (dues_id, household_id, payment_date, amount_paid, payment_method, reference_number, remarks, verified_by, verified_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_payment);
            $stmt->bind_param("iidssssi", $dues_id, $household_id, $payment_date, $amount_paid, $payment_method, $reference_number, $remarks, $current_user['user_id']);
            $stmt->execute();
            
            // Update dues status to paid
            $update_dues = "UPDATE monthly_dues SET status = 'paid' WHERE dues_id = ?";
            $stmt = $conn->prepare($update_dues);
            $stmt->bind_param("i", $dues_id);
            $stmt->execute();
            
            $conn->commit();
            header("Location: payments.php?success=payment_recorded");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: payments.php?error=payment_failed");
        }
        break;
        
    case 'verify_payment':
        $payment_id = (int)$_GET['payment_id'];
        
        $verify_query = "UPDATE payments SET verified_by = ?, verified_at = NOW() WHERE payment_id = ?";
        $stmt = $conn->prepare($verify_query);
        $stmt->bind_param("ii", $current_user['user_id'], $payment_id);
        
        if ($stmt->execute()) {
            header("Location: payments.php?success=payment_verified");
        } else {
            header("Location: payments.php?error=verify_failed");
        }
        break;
        
    default:
        header("Location: payments.php");
        break;
}

$conn->close();
?>
