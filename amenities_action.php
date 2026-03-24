<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: amenities.php?error=invalid_request');
    exit();
}

$action = $_POST['action'] ?? '';

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

if ($action !== 'report_damage') {
    header('Location: amenities.php?error=invalid_action');
    exit();
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$incident_date = trim($_POST['incident_date'] ?? '');
$incident_type = trim($_POST['incident_type'] ?? 'damage');
$description = trim($_POST['description'] ?? '');
$estimated_cost = isset($_POST['estimated_cost']) && $_POST['estimated_cost'] !== '' ? (float)$_POST['estimated_cost'] : null;

if ($booking_id <= 0 || $incident_date === '' || $description === '') {
    header('Location: amenities.php?error=validation');
    exit();
}

$booking_query = "SELECT booking_id, household_id, status, booking_date
                  FROM facility_bookings
                  WHERE booking_id = ? AND household_id = ?
                  LIMIT 1";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param('ii', $booking_id, $current_user['household_id']);
$stmt->execute();
$booking_result = $stmt->get_result();
$booking = $booking_result ? $booking_result->fetch_assoc() : null;

if (!$booking) {
    header('Location: amenities.php?error=not_found');
    exit();
}

if ($booking['status'] !== 'approved') {
    header('Location: amenities.php?error=invalid_status');
    exit();
}

$report_exists_query = "SELECT report_id FROM amenity_damage_reports WHERE booking_id = ? LIMIT 1";
$stmt = $conn->prepare($report_exists_query);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$report_exists = $stmt->get_result();
if ($report_exists && $report_exists->num_rows > 0) {
    header('Location: amenities.php?error=already_reported');
    exit();
}

$insert_query = "INSERT INTO amenity_damage_reports
                (booking_id, household_id, reported_by, incident_date, incident_type, description, estimated_cost, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'reported')";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param(
    'iiisssd',
    $booking_id,
    $current_user['household_id'],
    $current_user['user_id'],
    $incident_date,
    $incident_type,
    $description,
    $estimated_cost
);

if ($stmt->execute()) {
    header('Location: amenities.php?success=damage_reported');
    exit();
}

header('Location: amenities.php?error=failed');
exit();
