<?php
require "config/database.php";
require "config/NotificationHelper.php";
$conn = getDBConnection();
$app_id = 1;
$res = $conn->query("SELECT * FROM account_applications WHERE application_id = $app_id");
if ($res && $row = $res->fetch_assoc()) {
    echo "Testing notification for application ID $app_id:" . PHP_EOL;
    echo "Name: {$row['first_name']} {$row['last_name']}" . PHP_EOL;
    echo "Email: {$row['email']}" . PHP_EOL;
    echo "Phone: {$row['contact_number']}" . PHP_EOL;
    echo PHP_EOL;
    $result = notifyApplicantApplicationStatus($conn, $app_id, 'approved');
    echo "Results: " . json_encode($result) . PHP_EOL;
} else {
    echo "No application found with ID $app_id" . PHP_EOL;
}
