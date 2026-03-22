<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get action and application ID
$action = $_GET['action'] ?? '';
$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($app_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header('Location: applications.php?error=invalid');
    exit();
}

// Fetch application details
$app_query = "SELECT * FROM account_applications WHERE application_id = ? AND status = 'pending'";
$app_stmt = $conn->prepare($app_query);
$app_stmt->bind_param("i", $app_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

if ($app_result->num_rows === 0) {
    header('Location: applications.php?error=not_found');
    exit();
}

$application = $app_result->fetch_assoc();

// Handle APPROVAL
if ($action === 'approve') {
    // Generate account number
    function generateAccountNumber($conn) {
        $year = date('Y');
        $query = "SELECT account_number FROM users WHERE account_number LIKE 'MAIA-$year-%' ORDER BY account_number DESC LIMIT 1";
        $result = $conn->query($query);
        
        if ($result->num_rows > 0) {
            $last_acc = $result->fetch_assoc()['account_number'];
            $last_num = intval(substr($last_acc, -3));
            $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_num = '001';
        }
        
        return "MAIA-$year-$next_num";
    }
    
    // Generate random password
    function generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    $account_number = generateAccountNumber($conn);
    $password = generatePassword(10);
    
    // Check if email already exists in users table
    $check_query = "SELECT user_id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $application['email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: applications.php?error=exists');
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert user
        $user_query = "INSERT INTO users 
                      (account_number, password, email, first_name, last_name, contact_number, user_role, status, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, 'resident', 'active', ?)";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param(
            "ssssssi", 
            $account_number, 
            $password, 
            $application['email'], 
            $application['first_name'], 
            $application['last_name'], 
            $application['contact_number'], 
            $current_user['user_id']
        );
        $user_stmt->execute();
        $user_id = $conn->insert_id;
        
        // 2. Insert household
        $household_query = "INSERT INTO households 
                           (unit_number, lot_number, block_number, owner_id, resident_type, move_in_date, status) 
                           VALUES (?, ?, ?, ?, ?, CURDATE(), 'occupied')";
        $household_stmt = $conn->prepare($household_query);
        $household_stmt->bind_param(
            "sssss", 
            $application['unit_number'], 
            $application['lot_number'], 
            $application['block_number'], 
            $user_id, 
            $application['resident_type']
        );
        $household_stmt->execute();
        $household_id = $conn->insert_id;
        
        // 3. Link user to household
        $member_query = "INSERT INTO household_members (household_id, user_id, relationship, is_primary) 
                        VALUES (?, ?, 'owner', 1)";
        $member_stmt = $conn->prepare($member_query);
        $member_stmt->bind_param("ii", $household_id, $user_id);
        $member_stmt->execute();
        
        // 4. Create notification preferences for new user
        $notif_query = "INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications) 
                       VALUES (?, 1, 0)";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("i", $user_id);
        $notif_stmt->execute();
        
        // 5. Update application status
        $update_app_query = "UPDATE account_applications 
                            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() 
                            WHERE application_id = ?";
        $update_app_stmt = $conn->prepare($update_app_query);
        $update_app_stmt->bind_param("ii", $current_user['user_id'], $app_id);
        $update_app_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // TODO: Send welcome email with credentials
        // TODO: Send SMS notification
        
        // For now, we'll just redirect with success and credentials
        // In production, this should be sent via email/SMS
        $credentials_info = "Account: $account_number | Password: $password";
        error_log("NEW ACCOUNT CREATED - Email: {$application['email']} | $credentials_info");
        
        header('Location: applications.php?success=approved');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Application approval failed: " . $e->getMessage());
        header('Location: applications.php?error=failed');
        exit();
    }
}

// Handle REJECTION
if ($action === 'reject') {
    $rejection_reason = trim($_GET['reason'] ?? '');
    
    if (empty($rejection_reason)) {
        header('Location: applications.php?error=reason_required');
        exit();
    }
    
    // Update application status to rejected
    $reject_query = "UPDATE account_applications 
                    SET status = 'rejected', 
                        rejection_reason = ?, 
                        reviewed_by = ?, 
                        reviewed_at = NOW() 
                    WHERE application_id = ?";
    $reject_stmt = $conn->prepare($reject_query);
    $reject_stmt->bind_param("sii", $rejection_reason, $current_user['user_id'], $app_id);
    
    if ($reject_stmt->execute()) {
        // TODO: Send rejection email/SMS with reason
        error_log("Application #{$app_id} rejected. Reason: $rejection_reason");
        
        header('Location: applications.php?success=rejected');
        exit();
    } else {
        header('Location: applications.php?error=failed');
        exit();
    }
}

$conn->close();
?>
