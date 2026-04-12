<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/PreApplicationEmailVerification.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$conn = getDBConnection();
ensurePreApplicationVerificationSchema($conn);

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

if (!preg_match('/^[0-9]{6}$/', $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    exit();
}

$verified = verifyCodeForEmail($conn, $email, $code);
if (!$verified) {
    echo json_encode(['success' => false, 'message' => 'Incorrect or expired verification code']);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Email verified']);
