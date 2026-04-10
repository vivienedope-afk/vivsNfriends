<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
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
        
        // Send welcome email with credentials and login instructions
        $service = getNotificationService();
        $subject = "Welcome to Maia Alta HOA - Account Created";
        $email_message = "
            <p>Dear {$application['first_name']},</p>
            <p>Congratulations! Your account application has been <strong>APPROVED</strong>.</p>
            <p>Your account has been successfully created. Below are your login credentials:</p>
            <ul style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; font-weight: 500;'>
                <li><strong>Account Number:</strong> {$account_number}</li>
                <li><strong>Temporary Password:</strong> {$password}</li>
            </ul>
            <p><strong>How to Login:</strong></p>
            <ol>
                <li>Visit the resident portal at <a href='http://maiaalthoa.com/login.php'>http://maiaalthoa.com/login.php</a></li>
                <li>Use your Account Number as the username</li>
                <li>Enter the temporary password provided above</li>
                <li>You will be prompted to change your password on first login</li>
            </ol>
            <p><strong>Important:</strong> Please keep your credentials confidential and change your password after your first login.</p>
            <p>If you experience any issues accessing your account, please contact the management office.</p>
            <p>Welcome to Maia Alta Homes!</p>
        ";
        
        // Email notification
        $email_sent = $service->sendEmailNotification($user_id, $subject, $email_message, 'account');
        
        // SMS notification (shorter message)
        $sms_message = "Welcome to Maia Alta HOA! Your application has been approved. Account Number: {$account_number}. Check your email for login password and instructions.";
        $sms_sent = $service->sendSMSNotification($user_id, $sms_message, 'account');
        
        // Log the action
        $credentials_info = "Account: $account_number | Password: $password | Email Sent: " . ($email_sent ? 'Yes' : 'No') . " | SMS Sent: " . ($sms_sent ? 'Yes' : 'No');
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
        // Send rejection notification email and SMS
        $service = getNotificationService();
        $subject = "Account Application Status - Decision Made";
        $email_message = "
            <p>Dear {$application['first_name']},</p>
            <p>Thank you for submitting your account application to Maia Alta HOA.</p>
            <p>After careful review, we regret to inform you that your application has been <strong>REJECTED</strong>.</p>
            <p><strong>Reason for Rejection:</strong></p>
            <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0;'>
                {$rejection_reason}
            </div>
            <p>If you believe this decision was made in error, or if you have additional information to provide, please contact the management office to discuss your application further.</p>
            <p>Thank you for your understanding.</p>
            <p>Maia Alta HOA Management Team</p>
        ";
        
        // Email notification
        $email_sent = $service->sendEmailNotification(0, $subject, $email_message, 'account');
        // Note: We use direct email since user doesn't exist yet
        $to = $application['email'];
        $name = $application['first_name'] . ' ' . $application['last_name'];
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: Maia Alta HOA <noreply@maiaalthoa.com>" . "\r\n";
        $headers .= "Reply-To: support@maiaalthoa.com" . "\r\n";
        $email_sent = mail($to, $subject, $email_message, $headers);
        
        // SMS notification
        $sms_message = "Dear {$application['first_name']}, your account application to Maia Alta HOA has been reviewed. Please check your email for the decision details.";
        
        // For SMS, we need to format the phone number and send directly
        $phone = preg_replace('/\D/', '', $application['contact_number']);
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
            $phone = '63' . substr($phone, 1);
        } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            $phone = '63' . substr($phone, 2);
        }
        
        $log_dir = __DIR__ . '/../logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/sms_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] Phone: {$phone} | Message: {$sms_message}\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        error_log("Application #{$app_id} rejected. Email Sent: " . ($email_sent ? 'Yes' : 'No') . " | Reason: $rejection_reason");
        
        header('Location: applications.php?success=rejected');
        exit();
    } else {
        header('Location: applications.php?error=failed');
        exit();
    }
}

$conn->close();
?>
