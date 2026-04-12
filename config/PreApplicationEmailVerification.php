<?php

function ensurePreApplicationVerificationSchema($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS pre_application_email_verifications (
        verification_id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        verification_code VARCHAR(10) NOT NULL,
        verified_at DATETIME NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_created (email, created_at),
        INDEX idx_email_verified (email, verified_at)
    )";

    $conn->query($sql);
}

function generateSixDigitCode() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function canSendNewCode($conn, $email, $cooldownSeconds = 60) {
    $sql = "SELECT created_at FROM pre_application_email_verifications
            WHERE email = ?
            ORDER BY verification_id DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || empty($row['created_at'])) {
        return true;
    }

    $lastTs = strtotime($row['created_at']);
    return $lastTs === false || (time() - $lastTs) >= $cooldownSeconds;
}

function createVerificationCode($conn, $email, $ttlMinutes = 10) {
    $code = generateSixDigitCode();
    $sql = "INSERT INTO pre_application_email_verifications (email, verification_code, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ssi', $email, $code, $ttlMinutes);
    return $stmt->execute() ? $code : null;
}

function verifyCodeForEmail($conn, $email, $code) {
    $sql = "SELECT verification_id FROM pre_application_email_verifications
            WHERE email = ?
              AND verification_code = ?
              AND verified_at IS NULL
              AND expires_at >= NOW()
            ORDER BY verification_id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $email, $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        return false;
    }

    $verificationId = (int)$row['verification_id'];
    $update = $conn->prepare("UPDATE pre_application_email_verifications SET verified_at = NOW() WHERE verification_id = ?");
    if (!$update) {
        return false;
    }

    $update->bind_param('i', $verificationId);
    return $update->execute();
}

function hasRecentVerifiedEmail($conn, $email, $validMinutes = 30) {
    $sql = "SELECT verification_id FROM pre_application_email_verifications
            WHERE email = ?
              AND verified_at IS NOT NULL
              AND verified_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY verification_id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $email, $validMinutes);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res && $res->num_rows > 0;
}

function consumeEmailVerification($conn, $email) {
    $stmt = $conn->prepare("DELETE FROM pre_application_email_verifications WHERE email = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
}
