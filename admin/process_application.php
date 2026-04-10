<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
requireAdmin();

$conn         = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$app_id = isset($_GET['id'])     ? (int)$_GET['id'] : 0;

if (!$action || !$app_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action or id']);
    exit();
}

// Load application
$stmt = $conn->prepare("SELECT * FROM account_applications WHERE application_id = ?");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($app['status'] !== 'pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Application already processed']);
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
        header("Location: applications.php?error=exists");
        exit();
    }

    // Generate account number and temp password
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

        // 4. Create default notification preferences
        $ins_np = $conn->prepare("INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications) VALUES (?, 1, 0)");
        $ins_np->bind_param("i", $new_user_id);
        $ins_np->execute();

        // 5. Mark application approved
        $upd = $conn->prepare("UPDATE account_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
        $upd->bind_param("ii", $current_user['user_id'], $app_id);
        $upd->execute();

        $conn->commit();

        // 6. Send approval email with credentials
        $notif = notifyApplicationStatus(
            $conn,
            $app['email'],
            $app['first_name'] . ' ' . $app['last_name'],
            'approved',
            $app['unit_number'],
            $account_number,
            $temp_password
        );

        if (!$notif['success']) {
            error_log("Approval email failed for application_id={$app_id} email={$app['email']}");
        }

        header("Location: applications.php?success=approved");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("process_application approve error: " . $e->getMessage());
        header("Location: applications.php?error=failed");
        exit();
    }
}

/* ============================================================
   REJECT
   ============================================================ */
if ($action === 'reject') {

    $reason = isset($_GET['reason']) ? trim(urldecode($_GET['reason'])) : '';

    $upd = $conn->prepare("UPDATE account_applications SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
    $upd->bind_param("sii", $reason, $current_user['user_id'], $app_id);

    if ($upd->execute()) {
        // Send rejection email
        $notif = notifyApplicationStatus(
            $conn,
            $app['email'],
            $app['first_name'] . ' ' . $app['last_name'],
            'rejected',
            $app['unit_number'],
            null, null,
            $reason
        );

        if (!$notif['success']) {
            error_log("Rejection email failed for application_id={$app_id} email={$app['email']}");
        }

        header("Location: applications.php?success=rejected");
    } else {
        header("Location: applications.php?error=failed");
    }
    exit();
}

// Invalid action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
$conn->close();

/* ============================================================
   HELPER: Generate readable temp password
   ============================================================ */
function generateTempPassword($length = 10) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    $pw    = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pw;
}
?>