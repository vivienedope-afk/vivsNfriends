<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('config/database.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit();
}

$conn = getDBConnection();
$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    // Get current notification preferences
    $query = "SELECT email_notifications, sms_notifications FROM notification_preferences WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $preferences = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'preferences' => [
                'email_notifications' => (bool)$preferences['email_notifications'],
                'sms_notifications' => (bool)$preferences['sms_notifications']
            ]
        ]);
    } else {
        // Return default preferences if none exist
        echo json_encode([
            'success' => true,
            'preferences' => [
                'email_notifications' => true,
                'sms_notifications' => false
            ]
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    // Save notification preferences
    $email_notifications = isset($_POST['email_notifications']) ? (int)$_POST['email_notifications'] : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? (int)$_POST['sms_notifications'] : 0;

    // Check if preferences already exist
    $check_query = "SELECT preference_id FROM notification_preferences WHERE user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing preferences
        $update_query = "UPDATE notification_preferences SET email_notifications = ?, sms_notifications = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iii", $email_notifications, $sms_notifications, $current_user_id);
    } else {
        // Insert new preferences
        $insert_query = "INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iii", $current_user_id, $email_notifications, $sms_notifications);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification preferences saved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save notification preferences: ' . $stmt->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

$conn->close();
?>
