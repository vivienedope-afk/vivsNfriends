<?php
require_once('config/database.php');
require_once('config/NotificationHelper.php');

header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: apply_account.php');
    exit();
}

$conn = getDBConnection();

// Get and sanitize form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$unit_number = trim($_POST['unit_number'] ?? '');
$lot_number = trim($_POST['lot_number'] ?? '');
$block_number = trim($_POST['block_number'] ?? '');
$resident_type = $_POST['resident_type'] ?? '';
$terms = isset($_POST['terms']) ? true : false;

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email) || empty($contact_number) || empty($unit_number) || empty($resident_type) || !$terms) {
    header('Location: apply_account.php?error=required');
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: apply_account.php?error=invalid_email');
    exit();
}

// Validate contact number format (Philippine mobile)
if (!preg_match('/^09[0-9]{9}$/', $contact_number)) {
    header('Location: apply_account.php?error=invalid_contact');
    exit();
}

// Check for duplicate applications (same email or contact number)
$check_query = "SELECT application_id FROM account_applications 
                WHERE (email = ? OR contact_number = ?) 
                AND status = 'pending'";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ss", $email, $contact_number);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    header('Location: apply_account.php?error=exists');
    exit();
}

// Handle file upload
$id_proof_path = null;
if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['id_proof'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed file extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    // Validate file extension
    if (!in_array($file_ext, $allowed_extensions)) {
        header('Location: apply_account.php?error=invalid_file');
        exit();
    }
    
    // Validate file size (max 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        header('Location: apply_account.php?error=file_too_large');
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/resident_ids/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $new_filename = 'id_' . uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $id_proof_path = $upload_path;
    } else {
        header('Location: apply_account.php?error=file_error');
        exit();
    }
} else {
    header('Location: apply_account.php?error=file_required');
    exit();
}

// Insert application into database
$insert_query = "INSERT INTO account_applications 
                (first_name, last_name, email, contact_number, unit_number, lot_number, block_number, resident_type, id_proof_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$insert_stmt = $conn->prepare($insert_query);
$insert_stmt->bind_param(
    "sssssssss", 
    $first_name, 
    $last_name, 
    $email, 
    $contact_number, 
    $unit_number, 
    $lot_number, 
    $block_number, 
    $resident_type, 
    $id_proof_path
);

if ($insert_stmt->execute()) {
    // Notify admin of the new application
    $application_id = $conn->insert_id;
    if (!notifyAdminNewApplication($conn, $application_id)) {
        error_log("Admin notification failed for application_id: " . $application_id);
    }

    header('Location: apply_account.php?success=1');
    exit();
} else {
    // Failed - log error and redirect
    error_log("Application submission failed: " . $conn->error);
    
    // Delete uploaded file if database insert failed
    if ($id_proof_path && file_exists($id_proof_path)) {
        unlink($id_proof_path);
    }
    
    header('Location: apply_account.php?error=failed');
    exit();
}

$conn->close();
?>
