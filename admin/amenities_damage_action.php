<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php?error=invalid_request');
    exit();
}

$action = $_POST['action'] ?? '';
if ($action !== 'update_damage_status') {
    header('Location: bookings.php?error=invalid_action');
    exit();
}

$report_id = (int)($_POST['report_id'] ?? 0);
$status = trim($_POST['status'] ?? 'reported');
$admin_notes = trim($_POST['admin_notes'] ?? '');

if ($report_id <= 0 || !in_array($status, ['reported', 'under_review', 'resolved'], true)) {
    header('Location: bookings.php?error=invalid_damage_update');
    exit();
}

$create_damage_table_sql = "CREATE TABLE IF NOT EXISTS amenity_damage_reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  booking_id INT NOT NULL,
  household_id INT NOT NULL,
  reported_by INT NOT NULL,
  incident_date DATE NOT NULL,
  incident_type VARCHAR(100) DEFAULT 'damage',
  description TEXT NOT NULL,
  estimated_cost DECIMAL(10,2) DEFAULT NULL,
  status ENUM('reported','under_review','resolved') DEFAULT 'reported',
  admin_notes TEXT,
  resolved_by INT DEFAULT NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES facility_bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
)";
$conn->query($create_damage_table_sql);

if ($status === 'resolved') {
    $sql = "UPDATE amenity_damage_reports
            SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW()
            WHERE report_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $status, $admin_notes, $current_user['user_id'], $report_id);
} else {
    $sql = "UPDATE amenity_damage_reports
            SET status = ?, admin_notes = ?
            WHERE report_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $status, $admin_notes, $report_id);
}

if ($stmt->execute()) {
    header('Location: bookings.php?success=damage_updated');
    exit();
}

header('Location: bookings.php?error=damage_update_failed');
exit();
