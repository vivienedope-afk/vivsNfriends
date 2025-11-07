<?php
require_once('auth/session_check.php');
require_once('config/database.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get household_id from user
$household_query = "SELECT household_id FROM household_members WHERE user_id = ?";
$stmt = $conn->prepare($household_query);
$stmt->bind_param("i", $current_user['user_id']);
$stmt->execute();
$household_result = $stmt->get_result();

if ($household_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Household not found']);
    exit;
}

$household_id = $household_result->fetch_assoc()['household_id'];

// Validate and sanitize inputs
$facility = trim($_POST['facility'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$booking_date = $_POST['booking_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';

if (empty($facility) || empty($purpose) || empty($booking_date) || empty($start_time) || empty($end_time)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate facility
$allowed_facilities = ['Clubhouse', 'Basketball Court'];
if (!in_array($facility, $allowed_facilities)) {
    echo json_encode(['success' => false, 'message' => 'Invalid facility']);
    exit;
}

// Validate date (not in past)
if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Booking date cannot be in the past']);
    exit;
}

// Validate times
if (strtotime($start_time) >= strtotime($end_time)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

// Check for conflicting bookings
$conflict_query = "SELECT COUNT(*) as count FROM facility_bookings
                   WHERE facility_name = ? AND booking_date = ? AND status IN ('pending', 'approved')
                   AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
$stmt = $conn->prepare($conflict_query);
$stmt->bind_param("ssssss", $facility, $booking_date, $start_time, $start_time, $end_time, $end_time);
$stmt->execute();
$conflict_count = $stmt->get_result()->fetch_assoc()['count'];

if ($conflict_count > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
    exit;
}

// Insert booking
$insert_query = "INSERT INTO facility_bookings (household_id, facility_name, booking_date, start_time, end_time, purpose, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("isssss", $household_id, $facility, $booking_date, $start_time, $end_time, $purpose);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit reservation']);
}

$stmt->close();
$conn->close();
?>
