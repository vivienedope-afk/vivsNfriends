<?php
require_once('config/database.php');
$conn = getDBConnection();

echo "Recent Notification Logs:\n";
echo "========================\n";

$result = $conn->query('SELECT * FROM notification_log ORDER BY created_at DESC LIMIT 10');
while($row = $result->fetch_assoc()) {
    echo date('Y-m-d H:i:s', strtotime($row['created_at'])) . ' - ' . $row['notification_type'] . ' - ' . $row['subject'] . ' - ' . $row['status'] . "\n";
}

echo "\nRecent Applications:\n";
echo "===================\n";

$result2 = $conn->query('SELECT application_id, first_name, last_name, email, status, reviewed_at FROM account_applications ORDER BY created_at DESC LIMIT 5');
while($row = $result2->fetch_assoc()) {
    echo "ID: {$row['application_id']} - {$row['first_name']} {$row['last_name']} - {$row['email']} - Status: {$row['status']}";
    if ($row['reviewed_at']) {
        echo " - Reviewed: " . date('Y-m-d H:i:s', strtotime($row['reviewed_at']));
    }
    echo "\n";
}
?>