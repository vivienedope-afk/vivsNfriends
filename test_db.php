<?php
require_once('config/database.php');

$conn = getDBConnection();
$result = $conn->query('SELECT * FROM facility_bookings');

if ($result->num_rows > 0) {
    echo "Current bookings:\n";
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['booking_id'] . " - Facility: " . $row['facility_name'] . " - Date: " . $row['booking_date'] . " - Status: " . $row['status'] . "\n";
    }
} else {
    echo "No bookings found.\n";
}

$conn->close();
?>
