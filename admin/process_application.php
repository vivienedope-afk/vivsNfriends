<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
requireAdmin();

// Always return JSON — called via fetch() from applications.php
header('Content-Type: application/json');

$conn         = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$app_id = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if (!$action || !$app_id) {
    echo json_encode(['success' => false, 'message' => 'Missing action or id']);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM account_applications WHERE application_id = ?");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($app['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Application has already been processed']);
    exit();
}

/* ============================================================
   APPROVE
   ============================================================ */
if ($action === 'approve') {

    // Check duplicate email
    $dup = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $dup->bind_param("s", $app['email']);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
        exit();
    }

    $account_number = 'HOA-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $temp_password  = generateTempPassword();
    $hashed_pw      = password_hash($temp_password, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
        // 1. Create user
        $ins_user = $conn->prepare("INSERT INTO users (account_number, password, email, first_name, last_name, contact_number, user_role, status, created_by)
                                    VALUES (?, ?, ?, ?, ?, ?, 'resident', 'active', ?)");
        $ins_user->bind_param("ssssssi",
            $account_number, $hashed_pw,
            $app['email'], $app['first_name'], $app['last_name'],
            $app['contact_number'], $current_user['user_id']
        );
        $ins_user->execute();
        $new_user_id = $conn->insert_id;

        // 2. Create household
        $ins_hh = $conn->prepare("INSERT INTO households (unit_number, lot_number, block_number, owner_id, resident_type, status)
                                  VALUES (?, ?, ?, ?, ?, 'occupied')");
        $ins_hh->bind_param("sssss",
            $app['unit_number'], $app['lot_number'],
            $app['block_number'], $new_user_id, $app['resident_type']
        );
        $ins_hh->execute();
        $new_hh_id = $conn->insert_id;

        // 3. Create household member (primary)
        $ins_hm = $conn->prepare("INSERT INTO household_members (household_id, user_id, relationship, is_primary) VALUES (?, ?, ?, 1)");
        $relationship = $app['resident_type'] === 'owner' ? 'owner' : 'tenant';
        $ins_hm->bind_param("iis", $new_hh_id, $new_user_id, $relationship);
        $ins_hm->execute();

        // 4. Default notification preferences
        $ins_np = $conn->prepare("INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications) VALUES (?, 1, 0)");
        $ins_np->bind_param("i", $new_user_id);
        $ins_np->execute();

        // 5. Mark application approved
        $upd = $conn->prepare("UPDATE account_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
        $upd->bind_param("ii", $current_user['user_id'], $app_id);
        $upd->execute();

        $conn->commit();

        // 6a. Send approval notification (from NotificationHelper.php)
        notifyApplicantApplicationStatus($conn, $app_id, 'approved');

        // 6b. Send credentials in a separate email
        sendCredentialsEmail($conn, $app, $account_number, $temp_password);

        echo json_encode([
            'success' => true,
            'message' => 'Application approved! Account created and credentials sent to ' . $app['email']
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Approve error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    $conn->close();
    exit();
}

/* ============================================================
   REJECT
   ============================================================ */
if ($action === 'reject') {

    $reason = isset($_GET['reason']) ? trim(urldecode($_GET['reason'])) : '';

    $upd = $conn->prepare("UPDATE account_applications SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
    $upd->bind_param("sii", $reason, $current_user['user_id'], $app_id);

    if ($upd->execute()) {
        // Correct function name from your actual NotificationHelper.php
        notifyApplicantApplicationStatus($conn, $app_id, 'rejected', $reason);
        echo json_encode(['success' => true, 'message' => 'Application rejected.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update application. Please try again.']);
    }

    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
$conn->close();

/* ============================================================
   HELPER: Send credentials email after approval
   (separate from the approval notification — includes account number + temp password)
   ============================================================ */
function sendCredentialsEmail($conn, $app, $account_number, $temp_password) {
    $email_svc = getEmailService($conn);
    $name      = $app['first_name'] . ' ' . $app['last_name'];
    $subject   = 'MAIA ALTA HOA – Your Account Credentials';

    $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
          <div style='background:#c17f59;padding:20px;text-align:center'>
            <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
          </div>
          <div style='padding:24px;background:#fff'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your resident account has been set up. Here are your login credentials:</p>
            <div style='background:#f0faf4;padding:20px;border-left:4px solid #27ae60;margin:16px 0;border-radius:4px'>
              <p style='margin:6px 0'><strong>Account Number:</strong> {$account_number}</p>
              <p style='margin:6px 0'><strong>Email:</strong> {$app['email']}</p>
              <p style='margin:6px 0'><strong>Temporary Password:</strong>
                <code style='background:#e8f5e9;padding:3px 8px;border-radius:4px;font-size:15px;letter-spacing:1px'>{$temp_password}</code>
              </p>
              <p style='margin:6px 0'><strong>Unit Number:</strong> {$app['unit_number']}</p>
            </div>
            <p style='color:#e74c3c;font-size:13px'>⚠️ Please log in and change your password immediately.</p>
            <p style='color:#888;font-size:12px;margin-top:24px'>— Maia Alta HOA Administration</p>
          </div>
        </div>";

    $result = $email_svc->sendEmail($app['email'], $name, $subject, $body, 'application');
    if (!$result['success']) {
        error_log("Credentials email failed for {$app['email']}");
    }
    return $result;
}

/* ============================================================
   HELPER: Generate temp password
   ============================================================ */
function generateTempPassword($length = 10) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    $pw    = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pw;

    /* ============================================================
   HELPER: Get EmailService instance
   ============================================================ */
function getEmailService($conn) {
    static $email_svc = null;
    if ($email_svc === null) {
        $email_svc = new EmailService($conn);
    }
    return $email_svc;
}

/* ============================================================
   HELPER: Get SmsService instance
   ============================================================ */
function getSmsService($conn) {
    static $sms_svc = null;
    if ($sms_svc === null) {
        $sms_svc = new SmsService($conn);
    }
    return $sms_svc;
}
}