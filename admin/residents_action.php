<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Add new resident
    $account_number = trim($_POST['account_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $unit_number = trim($_POST['unit_number']);
    $lot_number = trim($_POST['lot_number']);
    $block_number = trim($_POST['block_number']);
    $resident_type = $_POST['resident_type'];
    $default_password = $_POST['default_password'];
    
    // Plain text password for now (will add hashing later)
    
    // Check if account number or email already exists
    $check_query = "SELECT user_id FROM users WHERE account_number = ? OR email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $account_number, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: residents.php?error=exists');
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert user
        $user_query = "INSERT INTO users (account_number, password, email, first_name, last_name, contact_number, user_role, status, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, 'resident', 'active', ?)";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param("ssssssi", $account_number, $default_password, $email, $first_name, $last_name, $contact_number, $current_user['user_id']);
        $user_stmt->execute();
        $user_id = $conn->insert_id;
        
        // Insert household
        $household_query = "INSERT INTO households (unit_number, lot_number, block_number, owner_id, resident_type, move_in_date, status) 
                            VALUES (?, ?, ?, ?, ?, CURDATE(), 'occupied')";
        $household_stmt = $conn->prepare($household_query);
        $household_stmt->bind_param("sssis", $unit_number, $lot_number, $block_number, $user_id, $resident_type);
        $household_stmt->execute();
        $household_id = $conn->insert_id;
        
        // Link user to household
        $member_query = "INSERT INTO household_members (household_id, user_id, relationship, is_primary) 
                         VALUES (?, ?, 'owner', 1)";
        $member_stmt = $conn->prepare($member_query);
        $member_stmt->bind_param("ii", $household_id, $user_id);
        $member_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        header('Location: residents.php?success=added');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: residents.php?error=failed');
        exit();
    }
}

// Toggle status (activate/deactivate)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status') {
    $user_id = intval($_GET['user_id']);
    $current_status = $_GET['status'];
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    $update_query = "UPDATE users SET status = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_status, $user_id);
    
    if ($update_stmt->execute()) {
        header('Location: residents.php?success=updated');
    } else {
        header('Location: residents.php?error=failed');
    }
    exit();
}

$conn->close();
header('Location: residents.php');
exit();
?>
