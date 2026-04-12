<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/EmailVerificationHelper.php';

$conn = getDBConnection();
ensureEmailVerificationSchema($conn);

$token = trim($_GET['token'] ?? '');
$status = 'invalid';
$message = 'Invalid verification link.';

if ($token !== '') {
    $resident = getResidentByVerificationToken($conn, $token);

    if ($resident) {
        if (!empty($resident['email_verified_at'])) {
            $status = 'already_verified';
            $message = 'Email is already verified.';
        } else {
            $ok = markResidentEmailVerifiedByToken($conn, $token);
            if ($ok) {
                $status = 'verified';
                $message = 'Email verified successfully. Your resident account is now verified.';
            } else {
                $status = 'invalid';
                $message = 'Verification link is invalid or expired.';
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Email Verification - Maia Alta HOA</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; }
    .wrap { max-width: 640px; margin: 60px auto; background: #fff; border-radius: 10px; padding: 28px; box-shadow: 0 10px 24px rgba(0,0,0,0.08); }
    .title { margin: 0 0 12px; color: #2c3e50; }
    .msg { font-size: 16px; line-height: 1.5; }
    .ok { color: #1e8449; }
    .warn { color: #b9770e; }
    .err { color: #c0392b; }
    .link { display: inline-block; margin-top: 18px; color: #2c3e50; text-decoration: none; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="title">Maia Alta HOA Resident Verification</h1>

    <?php if ($status === 'verified'): ?>
      <p class="msg ok"><?php echo htmlspecialchars($message); ?></p>
    <?php elseif ($status === 'already_verified'): ?>
      <p class="msg warn"><?php echo htmlspecialchars($message); ?></p>
    <?php else: ?>
      <p class="msg err"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <a class="link" href="login.php">Go to Login</a>
  </div>
</body>
</html>