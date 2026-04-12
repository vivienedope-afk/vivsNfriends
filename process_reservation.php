<?php
require_once('auth/session_check.php');
require_once('config/database.php');
require_once('config/NotificationHelper.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDBConnection();
$current_user = getCurrentUser();

function resolveEffectiveResidentUserId($conn, $current_user) {
    $sessionUserId = (int)($current_user['user_id'] ?? 0);
    if ($sessionUserId > 0) {
        $sql = "SELECT user_id FROM users WHERE user_id = ? AND user_role = 'resident' AND status = 'active' LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sessionUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            return $sessionUserId;
        }
    }

    $accountNumber = trim((string)($current_user['account_number'] ?? ''));
    $email = trim((string)($current_user['email'] ?? ''));

    if ($accountNumber === '' && $email === '') {
        return 0;
    }

    $sql = "SELECT user_id, account_number, email, first_name, last_name
            FROM users
            WHERE user_role = 'resident' AND status = 'active' AND (account_number = ? OR email = ?)
            ORDER BY (account_number = ?) DESC, user_id DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $accountNumber, $email, $accountNumber);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        return 0;
    }

    // Refresh stale session identity so next requests use the correct resident row.
    $_SESSION['user_id'] = (int)$row['user_id'];
    $_SESSION['account_number'] = (string)$row['account_number'];
    $_SESSION['email'] = (string)$row['email'];
    $_SESSION['first_name'] = (string)$row['first_name'];
    $_SESSION['last_name'] = (string)$row['last_name'];

    return (int)$row['user_id'];
}

function resolveValidHouseholdId($conn, $user_id, $session_household_id = null) {
    $candidates = [];

    $sessionId = (int)($session_household_id ?? 0);
    if ($sessionId > 0) {
        $candidates[] = $sessionId;
    }

    $sql = "SELECT household_id
            FROM household_members
            WHERE user_id = ? AND is_primary = 1
            ORDER BY member_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $candidates[] = (int)$row['household_id'];
    }

    $sql = "SELECT household_id
            FROM household_members
            WHERE user_id = ?
            ORDER BY is_primary DESC, member_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $candidates[] = (int)$row['household_id'];
    }

    $sql = "SELECT household_id
            FROM households
            WHERE owner_id = ?
            ORDER BY household_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $candidates[] = (int)$row['household_id'];
    }

    $seen = [];
    $existsSql = "SELECT household_id FROM households WHERE household_id = ? LIMIT 1";
    $existsStmt = $conn->prepare($existsSql);
    foreach ($candidates as $candidate) {
        if ($candidate <= 0 || isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;

        $existsStmt->bind_param("i", $candidate);
        $existsStmt->execute();
        $existsRes = $existsStmt->get_result();
        if ($existsRes && $existsRes->num_rows > 0) {
            return $candidate;
        }
    }

    return 0;
}

// Resolve and repair stale session user identity first.
$effective_user_id = resolveEffectiveResidentUserId($conn, $current_user);
if ($effective_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Resident session is invalid. Please log out and log in again.']);
    exit;
}

// Resolve household_id with fallbacks (session -> primary member -> any member -> owner),
// and ensure the resolved ID still exists in households.
$household_id = resolveValidHouseholdId(
    $conn,
    $effective_user_id,
    $current_user['household_id'] ?? null
);

if ($household_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Household not found. Please contact admin to relink your unit.']);
    exit;
}

// Keep household info in session current after successful resolution.
$_SESSION['household_id'] = $household_id;
$unitStmt = $conn->prepare("SELECT unit_number FROM households WHERE household_id = ? LIMIT 1");
$unitStmt->bind_param("i", $household_id);
$unitStmt->execute();
$unitRes = $unitStmt->get_result();
$unitRow = $unitRes ? $unitRes->fetch_assoc() : null;
if ($unitRow && !empty($unitRow['unit_number'])) {
    $_SESSION['unit_number'] = $unitRow['unit_number'];
}

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
try {
    $insert_query = "INSERT INTO facility_bookings (household_id, facility_name, booking_date, start_time, end_time, purpose, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssss", $household_id, $facility, $booking_date, $start_time, $end_time, $purpose);

    if ($stmt->execute()) {
        $booking_id = (int)$conn->insert_id;
        // Non-blocking: reservation success should not fail even if email delivery fails.
        $notif = notifyAdminNewBooking($conn, $booking_id);
        if ($notif === false) {
            error_log('notifyAdminNewBooking failed for booking_id=' . $booking_id);
        }
        echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit reservation']);
    }
} catch (Throwable $e) {
    error_log('Reservation insert error: ' . $e->getMessage() . ' | user_id=' . (int)$effective_user_id . ' | household_id=' . (int)$household_id);
    echo json_encode(['success' => false, 'message' => 'Failed to submit reservation. Please contact admin to check household linkage.']);
    exit;
}

$stmt->close();
$conn->close();
?>
