<?php
session_start();
require_once('../config/database.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        header('Location: ../login.php?error=empty');
        exit();
    }
    
    // Get database connection
    $conn = getDBConnection();
    
    // Prepare statement to prevent SQL injection - using account_number instead of username
    $stmt = $conn->prepare("SELECT user_id, account_number, password, email, first_name, last_name, user_role, status FROM users WHERE account_number = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            header('Location: ../login.php?error=inactive');
            exit();
        }
        
        // Verify password - plain text for now (will add hashing later)
        if ($password === $user['password']) {
            // Password is correct, start session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['account_number'] = $user['account_number'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['logged_in'] = true;
            
            // Get household info if resident
            if ($user['user_role'] === 'resident') {
                $stmt2 = $conn->prepare("SELECT h.household_id, h.unit_number FROM households h 
                                         INNER JOIN household_members hm ON h.household_id = hm.household_id 
                                         WHERE hm.user_id = ? AND hm.is_primary = 1");
                $stmt2->bind_param("i", $user['user_id']);
                $stmt2->execute();
                $household_result = $stmt2->get_result();
                
                if ($household_result->num_rows === 1) {
                    $household = $household_result->fetch_assoc();
                    $_SESSION['household_id'] = $household['household_id'];
                    $_SESSION['unit_number'] = $household['unit_number'];
                }
                $stmt2->close();
            }
            
            // Redirect based on role
            if ($user['user_role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../home.php');
            }
            exit();
            
        } else {
            // Invalid password
            header('Location: ../login.php?error=invalid');
            exit();
        }
    } else {
        // User not found
        header('Location: ../login.php?error=invalid');
        exit();
    }
    
    $stmt->close();
    $conn->close();
    
} else {
    // Not a POST request
    header('Location: ../login.php');
    exit();
}
?>
