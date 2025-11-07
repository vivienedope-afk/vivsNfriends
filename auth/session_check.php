<?php
// Session check for protected pages

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login - handle both root and subfolder paths
    $login_path = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../login.php' : 'login.php';
    header('Location: ' . $login_path);
    exit();
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to check if user is resident
function isResident() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'resident';
}

// Function to require admin access
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../home.php?error=unauthorized');
        exit();
    }
}

// Function to get current user info
function getCurrentUser() {
    // Get fresh data from database if needed
    require_once(__DIR__ . '/../config/database.php');
    $conn = getDBConnection();
    
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        $stmt = $conn->prepare("SELECT contact_number FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
    }
    
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'account_number' => $_SESSION['account_number'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'full_name' => ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''),
        'user_role' => $_SESSION['user_role'] ?? 'resident',
        'household_id' => $_SESSION['household_id'] ?? null,
        'unit_number' => $_SESSION['unit_number'] ?? '',
        'contact_number' => $user_data['contact_number'] ?? ''
    ];
}
?>
