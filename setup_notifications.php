<?php
/**
 * Database Migration Setup Script
 * This script creates all necessary notification tables
 */

require_once('config/database.php');

$conn = getDBConnection();

echo "Starting database migration...\n";

// SQL statements to create tables
$migrations = [
    // Create notification_log table
    "CREATE TABLE IF NOT EXISTS notification_log (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        notification_type ENUM('email', 'sms', 'push') DEFAULT 'email',
        subject VARCHAR(255) NOT NULL,
        status ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status)
    );",
    
    // Create sms_gateway_config table
    "CREATE TABLE IF NOT EXISTS sms_gateway_config (
        config_id INT PRIMARY KEY AUTO_INCREMENT,
        gateway_type ENUM('twilio', 'aws_sns', 'mock') DEFAULT 'mock',
        is_active BOOLEAN DEFAULT FALSE,
        config_data JSON,
        last_tested TIMESTAMP NULL,
        updated_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
    );",
    
    // Insert default SMS gateway config
    "INSERT INTO sms_gateway_config (gateway_type, is_active, config_data) 
     VALUES ('mock', TRUE, '{}')
     ON DUPLICATE KEY UPDATE is_active = TRUE;"
];

$success_count = 0;
$error_count = 0;

foreach ($migrations as $index => $sql) {
    echo "\nExecuting migration " . ($index + 1) . "...\n";
    
    if ($conn->query($sql)) {
        echo "✓ Migration " . ($index + 1) . " completed successfully\n";
        $success_count++;
    } else {
        echo "✗ Migration " . ($index + 1) . " failed: " . $conn->error . "\n";
        $error_count++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Migration Summary:\n";
echo "✓ Successful: " . $success_count . "\n";
echo "✗ Failed: " . $error_count . "\n";
echo str_repeat("=", 50) . "\n";

if ($error_count === 0) {
    echo "\n✓ All migrations completed successfully!\n";
    echo "The database is now ready for notifications.\n";
} else {
    echo "\n⚠ Some migrations failed. Please check the errors above.\n";
}

$conn->close();
?>
