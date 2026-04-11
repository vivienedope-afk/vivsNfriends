<?php
// test_notif_system.php - Direct test ng notification system

require_once('config/database.php');
require_once('config/NotificationHelper.php');

echo "========================================\n";
echo "📧 NOTIFICATION SYSTEM TEST\n";
echo "========================================\n\n";

$conn = getDBConnection();

// 1. Check admin email
echo "1️⃣ CHECKING ADMIN EMAIL...\n";
$admin_email = getAdminEmail($conn);
if ($admin_email) {
    echo "   ✅ Admin email found: " . $admin_email . "\n";
} else {
    echo "   ❌ NO admin email found! Run this SQL:\n";
    echo "      UPDATE users SET email = 'your_email@gmail.com' WHERE user_role = 'admin';\n";
    exit();
}

echo "\n";

// 2. Check EmailService
echo "2️⃣ TESTING EMAIL SERVICE...\n";
$email_svc = getEmailService($conn);
if ($email_svc) {
    echo "   ✅ EmailService loaded\n";
    
    // Test email to yourself
    $test_subject = "TEST: MAIA ALTA HOA Notification System";
    $test_body = "<h2>Test Notification</h2><p>This is a test from your notification system.</p><p>Time: " . date('Y-m-d H:i:s') . "</p>";
    
    $result = $email_svc->sendEmail($admin_email, 'Admin Test', $test_subject, $test_body, 'test', null, null);
    
    if ($result['success']) {
        echo "   ✅ Test email SENT to " . $admin_email . "\n";
    } else {
        echo "   ❌ Test email FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
        echo "\n   💡 COMMON FIXES:\n";
        echo "      - Check EmailService.php SMTP credentials\n";
        echo "      - Enable 'Allow less secure apps' or use App Password\n";
        echo "      - Verify Gmail password is correct\n";
    }
} else {
    echo "   ❌ EmailService NOT loaded\n";
}

echo "\n";

// 3. Check SMS Service (optional)
echo "3️⃣ TESTING SMS SERVICE...\n";
$sms_svc = getSmsService($conn);
if ($sms_svc) {
    echo "   ✅ SmsService loaded\n";
    
    // Get a resident phone number for test
    $sql = "SELECT contact_number FROM users WHERE user_role = 'resident' AND contact_number IS NOT NULL AND contact_number != '' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $test_phone = $row['contact_number'];
        echo "   📱 Test phone number: " . $test_phone . "\n";
        
        $test_sms = "TEST: MAIA ALTA HOA notification system is working! Time: " . date('Y-m-d H:i:s');
        $sms_result = $sms_svc->sendSms($test_phone, $test_sms);
        
        if ($sms_result['success']) {
            echo "   ✅ Test SMS SENT to " . $test_phone . "\n";
        } else {
            echo "   ❌ Test SMS FAILED: " . ($sms_result['error'] ?? 'Unknown error') . "\n";
            echo "\n   💡 FIXES:\n";
            echo "      - Check if phone is on same WiFi as PC\n";
            echo "      - Verify Traccar SMS Gateway app is RUNNING on phone\n";
            echo "      - Check IP address in SmsService.php\n";
        }
    } else {
        echo "   ⚠️ No resident phone number found in database\n";
    }
} else {
    echo "   ❌ SmsService NOT loaded\n";
}

echo "\n";

// 4. Check recent notifications in database
echo "4️⃣ RECENT NOTIFICATION LOGS (last 5)...\n";
$sql = "SELECT notification_type, subject, status, error_message, created_at 
        FROM notification_log 
        ORDER BY created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status_icon = $row['status'] === 'sent' ? '✅' : '❌';
        echo "   $status_icon " . $row['notification_type'] . " - " . substr($row['subject'], 0, 50) . "... - " . $row['status'] . "\n";
        if ($row['error_message']) {
            echo "      Error: " . $row['error_message'] . "\n";
        }
    }
} else {
    echo "   ⚠️ No notification logs found\n";
}

echo "\n";
echo "========================================\n";
echo "✅ TEST COMPLETED\n";
echo "========================================\n";

$conn->close();
?>