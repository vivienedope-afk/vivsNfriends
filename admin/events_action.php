<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function go_back($query = '') {
    $suffix = $query ? ('?' . $query) : '';
    header('Location: events.php' . $suffix);
    exit();
}

if ($action === 'create') {
    $event_name = trim($_POST['event_name'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = trim($_POST['event_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $max_participants = (int)($_POST['max_participants'] ?? 0);
    $status = $_POST['status'] ?? 'upcoming';

    if ($event_name === '' || $event_date === '') {
        go_back('error=validation');
    }

    if (!in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'], true)) {
        $status = 'upcoming';
    }

    $event_time_value = ($event_time !== '') ? $event_time : null;
    $location_value = ($location !== '') ? $location : null;
    $max_value = $max_participants > 0 ? $max_participants : null;

    $sql = "INSERT INTO events (event_name, event_description, event_date, event_time, location, organizer_id, max_participants, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssiss', $event_name, $event_description, $event_date, $event_time_value, $location_value, $current_user['user_id'], $max_value, $status);
    if ($stmt->execute()) {
        go_back('success=create');
    }
    go_back('error=create');
}

if ($action === 'update') {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $event_name = trim($_POST['event_name'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = trim($_POST['event_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $max_participants = (int)($_POST['max_participants'] ?? 0);
    $status = $_POST['status'] ?? 'upcoming';

    if ($event_id <= 0 || $event_name === '' || $event_date === '') {
        go_back('error=validation');
    }

    if (!in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'], true)) {
        $status = 'upcoming';
    }

    $event_time_value = ($event_time !== '') ? $event_time : null;
    $location_value = ($location !== '') ? $location : null;
    $max_value = $max_participants > 0 ? $max_participants : null;

    $sql = "UPDATE events
            SET event_name = ?, event_description = ?, event_date = ?, event_time = ?, location = ?, max_participants = ?, status = ?
            WHERE event_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssisi', $event_name, $event_description, $event_date, $event_time_value, $location_value, $max_value, $status, $event_id);
    if ($stmt->execute()) {
        go_back('success=update');
    }
    go_back('error=update');
}

if ($action === 'delete') {
    $event_id = (int)($_GET['id'] ?? 0);
    if ($event_id <= 0) {
        go_back('error=invalid');
    }

    $sql = "DELETE FROM events WHERE event_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $event_id);
    if ($stmt->execute()) {
        go_back('success=delete');
    }
    go_back('error=delete');
}

go_back();
