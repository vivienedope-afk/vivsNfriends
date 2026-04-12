<?php
require_once __DIR__ . '/env.php';

function ensureEmailVerificationSchema($conn) {
    // account_applications.email_verification_token
    $check = $conn->query("SHOW COLUMNS FROM account_applications LIKE 'email_verification_token'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE account_applications ADD COLUMN email_verification_token VARCHAR(128) NULL AFTER id_proof_path");
        $conn->query("ALTER TABLE account_applications ADD INDEX idx_email_verification_token (email_verification_token)");
    }

    // account_applications.email_verification_sent_at
    $check = $conn->query("SHOW COLUMNS FROM account_applications LIKE 'email_verification_sent_at'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE account_applications ADD COLUMN email_verification_sent_at TIMESTAMP NULL AFTER email_verification_token");
    }

    // account_applications.email_verified_at
    $check = $conn->query("SHOW COLUMNS FROM account_applications LIKE 'email_verified_at'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE account_applications ADD COLUMN email_verified_at TIMESTAMP NULL AFTER email_verification_sent_at");
        $conn->query("ALTER TABLE account_applications ADD INDEX idx_email_verified_at (email_verified_at)");
    }

    // users.email_verified_at
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL AFTER email");
        $conn->query("ALTER TABLE users ADD INDEX idx_user_email_verified_at (email_verified_at)");
    }
}

function generateEmailVerificationToken() {
    return bin2hex(random_bytes(32));
}

function createApplicationVerificationToken($conn, $application_id) {
    ensureEmailVerificationSchema($conn);

    $token = generateEmailVerificationToken();
    $sql = "UPDATE account_applications
            SET email_verification_token = ?, email_verification_sent_at = NOW()
            WHERE application_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('si', $token, $application_id);
    return $stmt->execute() ? $token : null;
}

function getEmailVerificationUrl($token) {
    $base = rtrim((string) env('APP_BASE_URL', 'http://localhost/vivsNfriends'), '/');
    return $base . '/verify_email.php?token=' . urlencode($token);
}

function markApplicationEmailVerifiedByToken($conn, $token) {
    ensureEmailVerificationSchema($conn);

    $sql = "UPDATE account_applications
            SET email_verified_at = NOW(), email_verification_token = NULL
            WHERE email_verification_token = ? AND email_verified_at IS NULL";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function getApplicationByVerificationToken($conn, $token) {
    ensureEmailVerificationSchema($conn);

    $sql = "SELECT application_id, first_name, last_name, email, email_verified_at
            FROM account_applications
            WHERE email_verification_token = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
}

function syncUserEmailVerificationFromApplication($conn, $application_id, $user_id) {
    ensureEmailVerificationSchema($conn);

    $sql = "UPDATE users u
            JOIN account_applications a ON a.application_id = ?
            SET u.email_verified_at = a.email_verified_at
            WHERE u.user_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $application_id, $user_id);
    return $stmt->execute();
}
