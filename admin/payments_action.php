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
            $insert_payment = "INSERT INTO payments (dues_id, household_id, payment_date, amount_paid, payment_method, reference_number, remarks) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_payment);
            $stmt->bind_param("iidssss", $dues_id, $household_id, $payment_date, $amount_paid, $payment_method, $reference_number, $remarks);
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

    case 'edit_dues':
        $dues_id = (int)($_POST['dues_id'] ?? 0);
        $due_month = $_POST['due_month'] ?? '';
        $due_year = (int)($_POST['due_year'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $due_date = $_POST['due_date'] ?? '';
        $status = $_POST['status'] ?? 'unpaid';

        if ($dues_id <= 0 || $due_month === '' || $due_year <= 0 || $amount < 0 || $due_date === '' || !in_array($status, ['unpaid', 'paid', 'overdue'], true)) {
            header("Location: payments.php?error=invalid_edit");
            exit();
        }

        $edit_query = "UPDATE monthly_dues
                       SET due_month = ?, due_year = ?, amount = ?, due_date = ?, status = ?
                       WHERE dues_id = ?";
        $stmt = $conn->prepare($edit_query);
        $stmt->bind_param("sidssi", $due_month, $due_year, $amount, $due_date, $status, $dues_id);

        if ($stmt->execute()) {
            header("Location: payments.php?success=dues_updated");
        } else {
            header("Location: payments.php?error=edit_failed");
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

    case 'archive_payment':
        $payment_id = (int)($_GET['payment_id'] ?? 0);
        if ($payment_id <= 0) {
            header("Location: payments.php?error=invalid_payment");
            exit();
        }

        $archive_query = "UPDATE payments
                          SET remarks = CONCAT('[ARCHIVED by admin on ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'), '] ', COALESCE(remarks, ''))
                          WHERE payment_id = ?";
        $stmt = $conn->prepare($archive_query);
        $stmt->bind_param("i", $payment_id);

        if ($stmt->execute()) {
            header("Location: payments.php?success=payment_archived");
        } else {
            header("Location: payments.php?error=archive_failed");
        }
        break;

    case 'delete_payment':
        $payment_id = (int)($_GET['payment_id'] ?? 0);
        if ($payment_id <= 0) {
            header("Location: payments.php?error=invalid_payment");
            exit();
        }

        $fetch_query = "SELECT dues_id FROM payments WHERE payment_id = ? LIMIT 1";
        $stmt = $conn->prepare($fetch_query);
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result ? $result->fetch_assoc() : null;

        if (!$payment) {
            header("Location: payments.php?error=payment_not_found");
            exit();
        }

        $dues_id = (int)$payment['dues_id'];
        $conn->begin_transaction();

        try {
            $delete_query = "DELETE FROM payments WHERE payment_id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            $reset_dues_query = "UPDATE monthly_dues SET status = 'unpaid' WHERE dues_id = ?";
            $stmt = $conn->prepare($reset_dues_query);
            $stmt->bind_param("i", $dues_id);
            $stmt->execute();

            $conn->commit();
            header("Location: payments.php?success=payment_deleted");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: payments.php?error=delete_failed");
        }
        break;

    case 'delete_dues':
        $dues_id = (int)($_GET['dues_id'] ?? 0);
        if ($dues_id <= 0) {
            header("Location: payments.php?error=invalid_dues");
            exit();
        }

        $payment_check_query = "SELECT COUNT(*) AS total FROM payments WHERE dues_id = ?";
        $stmt = $conn->prepare($payment_check_query);
        $stmt->bind_param("i", $dues_id);
        $stmt->execute();
        $payment_count = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

        if ($payment_count > 0) {
            header("Location: payments.php?error=dues_has_payment");
            exit();
        }

        $delete_dues_query = "DELETE FROM monthly_dues WHERE dues_id = ?";
        $stmt = $conn->prepare($delete_dues_query);
        $stmt->bind_param("i", $dues_id);

        if ($stmt->execute()) {
            header("Location: payments.php?success=dues_deleted");
        } else {
            header("Location: payments.php?error=delete_failed");
        }
        break;
        
    default:
        header("Location: payments.php");
        break;
}

$conn->close();
?>
