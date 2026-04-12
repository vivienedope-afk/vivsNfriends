<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/PreApplicationEmailVerification.php';
require_once __DIR__ . '/helpers/EmailService.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$conn = getDBConnection();
ensurePreApplicationVerificationSchema($conn);

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

if (!canSendNewCode($conn, $email, 60)) {
    echo json_encode(['success' => false, 'message' => 'Please wait 60 seconds before requesting a new code']);
    exit();
}

$code = createVerificationCode($conn, $email, 10);
if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate verification code']);
    exit();
}

$emailSvc = getEmailService($conn);
$subject = 'Maia Alta HOA - Verify Your Email';
$body = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
  <div style='background:#2c3e50;padding:20px;text-align:center'>
    <h2 style='color:white;margin:0'>Maia Alta HOA</h2>
  </div>
  <div style='padding:24px;background:#fff'>
    <p>Use this verification code to continue your account application:</p>
    <p style='font-size:30px;letter-spacing:4px;font-weight:700;color:#2c3e50'>{$code}</p>
    <p>This code expires in 10 minutes.</p>
  </div>
</div>";

$result = $emailSvc->sendEmail($email, 'Applicant', $subject, $body, 'verification');
if (!$result['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Verification code sent']);
