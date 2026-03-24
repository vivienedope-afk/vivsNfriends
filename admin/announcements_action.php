<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function redirect_with($status) {
    header('Location: announcements.php?' . $status);
    exit();
}

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['announcement_type'] ?? 'general';
        $expiry = trim($_POST['expiry_date'] ?? '');
        if ($title === '' || $content === '') {
            redirect_with('error=validation');
        }

        $valid_types = ['general', 'maintenance', 'event', 'urgent'];
        if (!in_array($type, $valid_types, true)) {
            $type = 'general';
        }

        $sql = "INSERT INTO announcements (title, content, announcement_type, posted_by, expiry_date, status)
                VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $expiry_value = ($expiry !== '') ? $expiry : null;
        $stmt->bind_param('sssis', $title, $content, $type, $current_user['user_id'], $expiry_value);
        if ($stmt->execute()) {
            redirect_with('success=create');
        }
        redirect_with('error=create_failed');
        break;

    case 'update':
        $id = (int)($_POST['announcement_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['announcement_type'] ?? 'general';
        $expiry = trim($_POST['expiry_date'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $valid_types = ['general', 'maintenance', 'event', 'urgent'];
        $valid_status = ['active', 'archived'];
        if (!in_array($type, $valid_types, true) || !in_array($status, $valid_status, true) || $id <= 0 || $title === '' || $content === '') {
            redirect_with('error=validation');
        }

        $sql = "UPDATE announcements
                SET title = ?, content = ?, announcement_type = ?, expiry_date = ?, status = ?
                WHERE announcement_id = ?";
        $stmt = $conn->prepare($sql);
        $expiry_value = ($expiry !== '') ? $expiry : null;
        $stmt->bind_param('sssssi', $title, $content, $type, $expiry_value, $status, $id);
        if ($stmt->execute()) {
            redirect_with('success=update');
        }
        redirect_with('error=update_failed');
        break;

    case 'archive':
    case 'activate':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            redirect_with('error=invalid_id');
        }
        $next_status = ($action === 'archive') ? 'archived' : 'active';
        $sql = "UPDATE announcements SET status = ? WHERE announcement_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $next_status, $id);
        if ($stmt->execute()) {
            redirect_with('success=status');
        }
        redirect_with('error=status_failed');
        break;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            redirect_with('error=invalid_id');
        }
        $sql = "DELETE FROM announcements WHERE announcement_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            redirect_with('success=delete');
        }
        redirect_with('error=delete_failed');
        break;

    default:
        header('Location: announcements.php');
        exit();
}
