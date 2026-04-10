<?php
/**
 * Notification System Test Page
 * Test all notification functionality
 */

require_once('auth/session_check.php');
require_once('config/database.php');
require_once('config/NotificationService.php');
require_once('config/NotificationHelper.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

// Check if user is admin (for testing)
if ($current_user['user_role'] !== 'admin') {
    die("Access restricted to administrators only.");
}

$test_results = [];
$notification_service = new NotificationService($conn);

// Handle test actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_email':
            $test_results[] = [
                'type' => 'Email',
                'result' => $notification_service->sendEmailNotification(
                    $current_user['user_id'],
                    'Test Email - Maia Alta HOA',
                    'This is a test email from the notification system.',
                    'test'
                )
            ];
            break;
            
        case 'test_sms':
            $test_results[] = [
                'type' => 'SMS',
                'result' => $notification_service->sendSMSNotification(
                    $current_user['user_id'],
                    'Test SMS from Maia Alta HOA notification system.',
                    'test'
                )
            ];
            break;
            
        case 'test_both':
            $result = $notification_service->sendNotification(
                $current_user['user_id'],
                'Test Notification - Maia Alta HOA',
                'This is a complete test notification from the system.',
                'test'
            );
            $test_results[] = [
                'type' => 'Email & SMS',
                'result' => true,
                'details' => $result
            ];
            break;
            
        case 'test_broadcast':
            $test_results[] = [
                'type' => 'Broadcast to All',
                'result' => true,
                'details' => broadcastAnnouncement(
                    'System Test - Broadcast Announcement',
                    'This is a test broadcast notification. If you received this, please confirm the system is working.'
                )
            ];
            break;
            
        case 'check_database':
            // Check if notification tables exist
            $tables = ['notification_preferences', 'notification_log', 'email_logs', 'sms_logs'];
            $missing_tables = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '{$table}'");
                if ($result->num_rows === 0) {
                    $missing_tables[] = $table;
                }
            }
            
            if (empty($missing_tables)) {
                $test_results[] = [
                    'type' => 'Database Tables',
                    'result' => true,
                    'details' => 'All notification tables exist'
                ];
            } else {
                $test_results[] = [
                    'type' => 'Database Tables',
                    'result' => false,
                    'details' => 'Missing tables: ' . implode(', ', $missing_tables)
                ];
            }
            break;
            
        case 'check_config':
            // Check if SMS configuration is available
            $config_exists = file_exists('config/sms_config.php');
            $notification_service_exists = file_exists('config/NotificationService.php');
            $notification_helper_exists = file_exists('config/NotificationHelper.php');
            
            $test_results[] = [
                'type' => 'Configuration Files',
                'result' => $config_exists && $notification_service_exists && $notification_helper_exists,
                'details' => [
                    'SMS Config: ' . ($config_exists ? '✓' : '✗'),
                    'NotificationService: ' . ($notification_service_exists ? '✓' : '✗'),
                    'NotificationHelper: ' . ($notification_helper_exists ? '✓' : '✗')
                ]
            ];
            break;
            
        case 'get_preferences':
            $prefs = $notification_service->getUserPreferences($current_user['user_id']);
            $test_results[] = [
                'type' => 'Current Preferences',
                'result' => true,
                'details' => [
                    'Email Notifications: ' . ($prefs['email_notifications'] ? 'Enabled' : 'Disabled'),
                    'SMS Notifications: ' . ($prefs['sms_notifications'] ? 'Enabled' : 'Disabled')
                ]
            ];
            break;
            
        case 'create_tables':
            // Execute migration
            $migration_sql = file_get_contents('config/databaseHndlr/sql/migration_add_notification_log.sql');
            if ($conn->multi_query($migration_sql)) {
                while ($conn->more_results() && $conn->next_result());
                $test_results[] = [
                    'type' => 'Create Tables',
                    'result' => true,
                    'details' => 'Notification tables created successfully'
                ];
            } else {
                $test_results[] = [
                    'type' => 'Create Tables',
                    'result' => false,
                    'details' => 'Error: ' . $conn->error
                ];
            }
            break;
    }
}

$user_preferences = $notification_service->getUserPreferences($current_user['user_id']);
$user_contact = $notification_service->getUserContact($current_user['user_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification System Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .info-section {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .test-section {
            margin-bottom: 30px;
        }
        .test-section h2 {
            color: #667eea;
            font-size: 1.3em;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        button {
            flex: 1;
            min-width: 200px;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: #48bb78;
            color: white;
        }
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        .btn-info {
            background: #4299e1;
            color: white;
        }
        .btn-info:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        .btn-warning:hover {
            background: #dd6b20;
            transform: translateY(-2px);
        }
        .result {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .result.success {
            border-left-color: #48bb78;
            background: #f0fdf4;
        }
        .result.error {
            border-left-color: #f56565;
            background: #fdf2f2;
        }
        .result-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .result-details {
            font-size: 14px;
            color: #666;
        }
        .result-details li {
            margin-left: 20px;
            margin-top: 5px;
        }
        form {
            display: none;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-badge.enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.disabled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Notification System Test Dashboard</h1>
        
        <!-- User Info Section -->
        <div class="info-section">
            <h3 style="margin-bottom: 15px; color: #333;">Current User Information</h3>
            <div class="info-item">
                <span class="info-label">Name:</span>
                <span class="info-value"><?= htmlspecialchars($current_user['full_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value"><?= htmlspecialchars($user_contact['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Phone:</span>
                <span class="info-value"><?= htmlspecialchars($user_contact['contact_number'] ?? 'Not provided'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email Notifications:</span>
                <span class="info-value">
                    <span class="status-badge <?= $user_preferences['email_notifications'] ? 'enabled' : 'disabled'; ?>">
                        <?= $user_preferences['email_notifications'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">SMS Notifications:</span>
                <span class="info-value">
                    <span class="status-badge <?= $user_preferences['sms_notifications'] ? 'enabled' : 'disabled'; ?>">
                        <?= $user_preferences['sms_notifications'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Test Results -->
        <?php if (!empty($test_results)): ?>
        <div class="test-section">
            <h2>📋 Test Results</h2>
            <?php foreach ($test_results as $result): ?>
            <div class="result <?= $result['result'] ? 'success' : 'error'; ?>">
                <div class="result-title">
                    <?= htmlspecialchars($result['type']); ?> - 
                    <?= $result['result'] ? '✓ Success' : '✗ Failed'; ?>
                </div>
                <?php if (isset($result['details'])): ?>
                <div class="result-details">
                    <?php if (is_array($result['details'])): ?>
                    <ul>
                        <?php foreach ($result['details'] as $detail): ?>
                        <li><?= htmlspecialchars($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <?= nl2br(htmlspecialchars(print_r($result['details'], true))); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Individual Tests -->
        <div class="test-section">
            <h2>📧 Email & SMS Tests</h2>
            <div class="button-group">
                <form method="POST">
                    <input type="hidden" name="action" value="test_email">
                    <button type="submit" class="btn-primary">Send Test Email</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="test_sms">
                    <button type="submit" class="btn-primary">Send Test SMS</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="test_both">
                    <button type="submit" class="btn-success">Send Both Tests</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="test_broadcast">
                    <button type="submit" class="btn-warning">Broadcast to All Residents</button>
                </form>
            </div>
        </div>

        <!-- System Checks -->
        <div class="test-section">
            <h2>🔧 System Checks</h2>
            <div class="button-group">
                <form method="POST">
                    <input type="hidden" name="action" value="check_database">
                    <button type="submit" class="btn-info">Check Database</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="check_config">
                    <button type="submit" class="btn-info">Check Configuration</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="get_preferences">
                    <button type="submit" class="btn-info">Load User Preferences</button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_tables">
                    <button type="submit" class="btn-warning">Create Missing Tables</button>
                </form>
            </div>
        </div>

        <!-- Documentation -->
        <div class="test-section">
            <h2>📚 Quick Guide</h2>
            <div style="background: #f0f4ff; padding: 15px; border-radius: 5px;">
                <h4 style="margin-bottom: 10px;">Usage Instructions:</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>Test Notifications:</strong> Send test emails and SMS to verify the system works</li>
                    <li><strong>Check Database:</strong> Verify all required notification tables exist</li>
                    <li><strong>Check Configuration:</strong> Verify configuration files are in place</li>
                    <li><strong>Create Tables:</strong> Run this if database tables are missing</li>
                </ul>
                
                <h4 style="margin: 20px 0 10px;">SMS Configuration:</h4>
                <ul style="margin-left: 20px;">
                    <li>Default: <strong>Mock Gateway</strong> (logs to file)</li>
                    <li>To enable Twilio: Edit <code style="background: #e0e0e0; padding: 2px 5px;">config/sms_config.php</code></li>
                    <li>SMS logs are saved to <code style="background: #e0e0e0; padding: 2px 5px;">logs/sms_log.txt</code></li>
                </ul>
                
                <h4 style="margin: 20px 0 10px;">Notification Methods:</h4>
                <ul style="margin-left: 20px;">
                    <li>Users can toggle email/SMS preferences in their account settings</li>
                    <li>Notifications respect user preferences automatically</li>
                    <li>All notifications are logged for auditing</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Auto-submit forms -->
    <script>
        // Show test forms functionality
        document.querySelectorAll('form').forEach(form => {
            const button = form.querySelector('button');
            if (button) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    form.submit();
                });
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
