<?php
require_once('auth/session_check.php');
require_once('config/database.php');

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid maintenance request ID']);
    exit;
}

$maintenance_id = (int)$_GET['id'];
$current_user = getCurrentUser();

$conn = getDBConnection();

// Query maintenance request details, ensuring it belongs to the current user
$query = "SELECT mr.*, h.unit_number
          FROM maintenance_requests mr
          INNER JOIN households h ON mr.household_id = h.household_id
          WHERE mr.request_id = ? AND mr.household_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $maintenance_id, $current_user['household_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Maintenance request not found or access denied']);
    exit;
}

$maintenance = $result->fetch_assoc();

// Format the response
$response = [
    'success' => true,
    'maintenance' => [
        'request_id' => $maintenance['request_id'],
        'subject' => $maintenance['subject'],
        'category' => $maintenance['category'],
        'description' => $maintenance['description'],
        'status' => $maintenance['status'],
        'created_at' => $maintenance['created_at'],
        'updated_at' => $maintenance['updated_at'],
        'unit_number' => $maintenance['unit_number']
    ]
];

echo json_encode($response);

$conn->close();
?>
