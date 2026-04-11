<?php
/**
 * Notification System Setup & Verification
 * Access via: http://localhost/vivsNfriends/verify_notifications.php
 */

// Don't require session - anyone can run this for setup
$database_available = false;
$setup_complete = false;
$messages = [];

// Check if database connection is available
try {
    $conn = new mysqli('localhost', 'root', '', 'maia_alta_hoa');
    if (!$conn->connect_error) {
        $database_available = true;
        
        // Check if notification tables exist
        $tables_to_check = ['notification_log', 'notification_preferences', 'sms_gateway_config'];
        $missing_tables = [];
        
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '{$table}'");
            if ($result->num_rows === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            $setup_complete = true;
            $messages[] = ['type' => 'success', 'text' => 'All notification tables exist!'];
        } else {
            // Try to create the missing tables
            $created = 0;
            $failed = 0;
            
            if (in_array('notification_log', $missing_tables)) {
                $sql = "CREATE TABLE IF NOT EXISTS notification_log (
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
                )";
                if ($conn->query($sql)) {
                    $messages[] = ['type' => 'success', 'text' => 'Created notification_log table'];
                    $created++;
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to create notification_log: ' . $conn->error];
                    $failed++;
                }
            }
            
            if (in_array('sms_gateway_config', $missing_tables)) {
                $sql = "CREATE TABLE IF NOT EXISTS sms_gateway_config (
                    config_id INT PRIMARY KEY AUTO_INCREMENT,
                    gateway_type ENUM('twilio', 'aws_sns', 'mock') DEFAULT 'mock',
                    is_active BOOLEAN DEFAULT FALSE,
                    config_data JSON,
                    last_tested TIMESTAMP NULL,
                    updated_by INT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
                )";
                if ($conn->query($sql)) {
                    $messages[] = ['type' => 'success', 'text' => 'Created sms_gateway_config table'];
                    $created++;
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to create sms_gateway_config: ' . $conn->error];
                    $failed++;
                }
            }
            
            if ($created > 0 && $failed === 0) {
                $setup_complete = true;
                $messages[] = ['type' => 'success', 'text' => 'Database setup completed!'];
            }
        }
        
        // Check if NotificationService files exist
        $files_to_check = [
            'config/NotificationService.php',
            'config/NotificationHelper.php',
            'config/sms_config.php'
        ];
        
        $missing_files = [];
        foreach ($files_to_check as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (empty($missing_files)) {
            $messages[] = ['type' => 'success', 'text' => 'All notification service files are in place'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Missing files: ' . implode(', ', $missing_files)];
        }
        
        // Check if logs directory is writable
        if (!file_exists(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        if (is_writable(__DIR__ . '/logs')) {
            $messages[] = ['type' => 'success', 'text' => 'Logs directory is writable'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Logs directory exists but may not be writable'];
        }
        
        $conn->close();
    } else {
        $messages[] = ['type' => 'error', 'text' => 'Cannot connect to database: ' . $conn->connect_error];
    }
} catch (Exception $e) {
    $messages[] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification System Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .status {
            text-align: center;
            margin-bottom: 30px;
        }
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-badge.ready {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .messages {
            margin-top: 20px;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #ccc;
            display: flex;
            align-items: center;
        }
        .message.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .message.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .message-icon {
            margin-right: 10px;
            font-size: 20px;
        }
        .next-steps {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .next-steps h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .next-steps ol {
            margin-left: 20px;
            color: #333;
        }
        .next-steps li {
            margin: 8px 0;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Notification System Setup</h1>
        
        <div class="status">
            <?php if ($setup_complete): ?>
                <p style="color: #28a745; font-size: 18px;">✓ Setup Complete!</p>
                <div class="status-badge ready">System is Ready to Use</div>
            <?php else: ?>
                <p style="color: #ffc107; font-size: 18px;">⚠ Setup in Progress</p>
                <div class="status-badge warning">Please Complete Setup</div>
            <?php endif; ?>
        </div>

        <div class="messages">
            <?php foreach ($messages as $message): ?>
            <div class="message <?php echo $message['type']; ?>">
                <span class="message-icon">
                    <?php
                    switch ($message['type']) {
                        case 'success': echo '✓'; break;
                        case 'error': echo '✗'; break;
                        case 'warning': echo '⚠'; break;
                    }
                    ?>
                </span>
                <span><?php echo htmlspecialchars($message['text']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="next-steps">
            <h3>📋 Next Steps:</h3>
            <ol>
                <li>Verify all messages above show success ✓</li>
                <li>If any table creation failed, check database permissions</li>
                <li>Access the test dashboard: <a href="test_notifications.php">test_notifications.php</a></li>
                <li>Read the setup guide: <a href="NOTIFICATION_SYSTEM.md">NOTIFICATION_SYSTEM.md</a></li>
                <li>Optional: Configure Twilio SMS in <code>config/sms_config.php</code></li>
                <li>Start using notifications in your application</li>
            </ol>
        </div>

        <?php if ($setup_complete): ?>
        <div style="text-align: center; margin-top: 30px;">
            <p style="color: #666; margin-bottom: 15px;">System is ready! You can now:</p>
            <a href="test_notifications.php" style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; border-radius: 5px; text-decoration: none; margin: 0 10px;">Test Dashboard</a>
            <a href="home.php" style="display: inline-block; background: #28a745; color: white; padding: 12px 30px; border-radius: 5px; text-decoration: none; margin: 0 10px;">Home</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
