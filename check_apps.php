<?php
require_once('config/database.php');
$conn = getDBConnection();

echo "Recent Applications with Contact:\n";
echo "=================================\n";

$result2 = $conn->query('SELECT application_id, first_name, last_name, email, contact_number, status, reviewed_at FROM account_applications ORDER BY created_at DESC LIMIT 5');
while($row = $result2->fetch_assoc()) {
    echo "ID: {$row['application_id']} - {$row['first_name']} {$row['last_name']} - Email: {$row['email']} - Phone: {$row['contact_number']} - Status: {$row['status']}";
    if ($row['reviewed_at']) {
        echo " - Reviewed: " . date('Y-m-d H:i:s', strtotime($row['reviewed_at']));
    }
    echo "\n";
}
?>