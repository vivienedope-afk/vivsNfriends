<?php
require_once('config/database.php');
$conn = getDBConnection();

echo "Notification Log Table Structure:\n";
echo "=================================\n";

$result = $conn->query('DESCRIBE notification_log');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>