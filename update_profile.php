<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('config/database.php');

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$unit_number = trim($_POST['unitNumber'] ?? '');
$resident_type = trim($_POST['residentType'] ?? 'owner');
$first_name = trim($_POST['firstName'] ?? '');
$last_name = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($unit_number === '' || $first_name === '' || $last_name === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Please complete required fields']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

if (!in_array($resident_type, ['owner', 'tenant'], true)) {
    $resident_type = 'owner';
}

$conn = getDBConnection();

// Check email uniqueness excluding current user
$email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
$email_check->bind_param('si', $email, $user_id);
$email_check->execute();
$email_exists = $email_check->get_result();
if ($email_exists && $email_exists->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already in use']);
    $conn->close();
    exit();
}

$conn->begin_transaction();

try {
    $update_user = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE user_id = ?");
    $update_user->bind_param('ssssi', $first_name, $last_name, $email, $phone, $user_id);
    $update_user->execute();

    // Update household info for primary resident
    $get_household = $conn->prepare("SELECT household_id FROM household_members WHERE user_id = ? AND is_primary = 1 LIMIT 1");
    $get_household->bind_param('i', $user_id);
    $get_household->execute();
    $household_result = $get_household->get_result();

    if ($household_result && $household_result->num_rows > 0) {
        $household_id = (int)$household_result->fetch_assoc()['household_id'];

        // Prevent duplicate unit_number conflicts with another household
        $unit_check = $conn->prepare("SELECT household_id FROM households WHERE unit_number = ? AND household_id != ? LIMIT 1");
        $unit_check->bind_param('si', $unit_number, $household_id);
        $unit_check->execute();
        $unit_exists = $unit_check->get_result();
        if ($unit_exists && $unit_exists->num_rows > 0) {
            throw new Exception('Unit number already assigned to another household');
        }

        $update_household = $conn->prepare("UPDATE households SET unit_number = ?, resident_type = ? WHERE household_id = ?");
        $update_household->bind_param('ssi', $unit_number, $resident_type, $household_id);
        $update_household->execute();

        $_SESSION['unit_number'] = $unit_number;
    }

    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
