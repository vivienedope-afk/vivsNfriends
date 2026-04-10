<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../config/NotificationHelper.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$announcement_id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : (isset($_GET['announcement_id']) ? (int)$_GET['announcement_id'] : 0);

switch ($action) {
    case 'create':
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $announcement_type = isset($_POST['announcement_type']) ? $_POST['announcement_type'] : '';
        $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        // Validation
        if (empty($title) || empty($content) || empty($announcement_type)) {
            header("Location: announcements.php?error=required_fields");
            exit();
        }
        
        $insert_query = "INSERT INTO announcements (title, content, announcement_type, posted_by, post_date, expiry_date, status) 
                        VALUES (?, ?, ?, ?, NOW(), ?, 'active')";
        $stmt = $conn->prepare($insert_query);
        
        if (!$stmt) {
            header("Location: announcements.php?error=db_error");
            exit();
        }
        
        $stmt->bind_param("sssss", $title, $content, $announcement_type, $current_user['user_id'], $expiry_date);
        
        if ($stmt->execute()) {
            // FIXED: Added $conn as first parameter
            $notification_results = broadcastAnnouncement($conn, $title, $content, $announcement_type);

            // Additional direct email send to owner's Gmail inbox for guaranteed copy
            $owner_gmail = 'vivienedope@gmail.com';
            $owner_message = "
                <p>A new announcement has been posted in the admin panel.</p>
                <p><strong>Title:</strong> " . htmlspecialchars($title) . "</p>
                <p><strong>Type:</strong> " . htmlspecialchars($announcement_type) . "</p>
                <p><strong>Content:</strong></p>
                <div>" . nl2br(htmlspecialchars($content)) . "</div>
            ";

            $owner_headers = "MIME-Version: 1.0" . "\r\n";
            $owner_headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
            $owner_headers .= "From: Maia Alta HOA <noreply@maiaalthoa.com>" . "\r\n";
            $owner_headers .= "Reply-To: support@maiaalthoa.com" . "\r\n";

            $owner_email_sent = mail($owner_gmail, "[Announcement] " . $title, $owner_message, $owner_headers);
            if (!$owner_email_sent) {
                error_log("Owner Gmail copy failed for announcement: {$title} -> {$owner_gmail}");
            }

            // Keep announcement creation successful even if some notifications fail
            if (is_array($notification_results)) {
                foreach ($notification_results as $resident_user_id => $result) {
                    $email_ok = isset($result['email']) ? (bool)$result['email'] : false;

                    if (!$email_ok) {
                        error_log(
                            "Announcement notification failed for user_id={$resident_user_id}. " .
                            "email=" . ($email_ok ? 'sent' : 'failed/skipped')
                        );
                    }
                }
            } else {
                error_log("Announcement notification broadcast returned unexpected response.");
            }

            header("Location: announcements.php?success=created");
        } else {
            header("Location: announcements.php?error=create_failed");
        }
        break;
        
    case 'update':
        if (!$announcement_id) {
            header("Location: announcements.php?error=invalid_announcement");
            exit();
        }
        
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $announcement_type = isset($_POST['announcement_type']) ? $_POST['announcement_type'] : '';
        $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        if (empty($title) || empty($content) || empty($announcement_type)) {
            header("Location: announcements.php?error=required_fields");
            exit();
        }
        
        $check_query = "SELECT announcement_id FROM announcements WHERE announcement_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            header("Location: announcements.php?error=announcement_not_found");
            exit();
        }
        
        $update_query = "UPDATE announcements 
                        SET title = ?, content = ?, announcement_type = ?, expiry_date = ?
                        WHERE announcement_id = ?";
        $stmt = $conn->prepare($update_query);
        
        if (!$stmt) {
            header("Location: announcements.php?error=db_error");
            exit();
        }
        
        $stmt->bind_param("ssssi", $title, $content, $announcement_type, $expiry_date, $announcement_id);
        
        if ($stmt->execute()) {
            header("Location: announcements.php?success=updated");
        } else {
            header("Location: announcements.php?error=update_failed");
        }
        break;
        
    case 'delete':
        if (!$announcement_id) {
            header("Location: announcements.php?error=invalid_announcement");
            exit();
        }
        
        $check_query = "SELECT announcement_id FROM announcements WHERE announcement_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            header("Location: announcements.php?error=announcement_not_found");
            exit();
        }
        
        $delete_query = "DELETE FROM announcements WHERE announcement_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $announcement_id);
        
        if ($stmt->execute()) {
            header("Location: announcements.php?success=deleted");
        } else {
            header("Location: announcements.php?error=delete_failed");
        }
        break;
        
    case 'archive':
        if (!$announcement_id) {
            header("Location: announcements.php?error=invalid_announcement");
            exit();
        }
        
        $check_query = "SELECT announcement_id FROM announcements WHERE announcement_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            header("Location: announcements.php?error=announcement_not_found");
            exit();
        }
        
        $archive_query = "UPDATE announcements SET status = 'archived' WHERE announcement_id = ?";
        $stmt = $conn->prepare($archive_query);
        $stmt->bind_param("i", $announcement_id);
        
        if ($stmt->execute()) {
            header("Location: announcements.php?success=archived");
        } else {
            header("Location: announcements.php?error=archive_failed");
        }
        break;
        
    default:
        header("Location: announcements.php?error=invalid_action");
        exit();
}

$conn->close();
?>