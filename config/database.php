<?php
// Database Configuration for Maia Alta HOA Management System

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'maia_alta_hoa');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("Database connection error. Please try again later.");
    }
}

// Check if database exists, if not guide user
function checkDatabaseSetup() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            return false;
        }
        
        $result = $conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $conn->close();
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}
?>
