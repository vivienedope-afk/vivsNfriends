<?php
/**
 * send_due_reminders.php
 * Cron job: Sends automated due reminders to residents
 * 
 * For manual test: http://localhost/vivsNfriends-veve2/config/send_due_reminders.php?manual=1
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/NotificationHelper.php';

$conn = getDBConnection();
$today = date('Y-m-d');
$in3 = date('Y-m-d', strtotime('+3 days'));
$manual = isset($_GET['manual']) && $_GET['manual'] == '1';

$sent_count = 0;
$fail_count = 0;
$skip_count = 0;
$log_entries = [];

// Helper: Check if already notified today
function alreadyNotifiedToday($conn, $dues_id, $reminder_type) {
    $today = date('Y-m-d');
    $subject_like = $reminder_type === '3days' ? '%3-Day Reminder%' : '%Due Date Reminder%';
    
    $sql = "SELECT COUNT(*) as cnt FROM email_logs 
            WHERE related_id = ? AND email_type = 'monthly_due' 
            AND subject LIKE ? AND DATE(sent_at) = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("iss", $dues_id, $subject_like, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['cnt'] > 0;
}

// ========== 3 DAYS BEFORE DUE DATE ==========
$query_3days = "SELECT dues_id FROM monthly_dues 
                WHERE due_date = ? AND status IN ('unpaid', 'overdue')";
$stmt = $conn->prepare($query_3days);
$stmt->bind_param("s", $in3);
$stmt->execute();
$dues_3days = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($dues_3days as $due) {
    if (alreadyNotifiedToday($conn, $due['dues_id'], '3days')) {
        $skip_count++;
        $log_entries[] = "[3-DAY] dues_id={$due['dues_id']} SKIPPED (already notified today)";
        continue;
    }
    
    $result = sendDueReminder($conn, $due['dues_id'], '3days');
    if ($result['email'] || $result['sms']) {
        $sent_count++;
        $log_entries[] = "[3-DAY] dues_id={$due['dues_id']} email=" . ($result['email'] ? 'OK' : 'FAIL') . " sms=" . ($result['sms'] ? 'OK' : 'FAIL');
    } else {
        $fail_count++;
        $log_entries[] = "[3-DAY] dues_id={$due['dues_id']} BOTH FAILED";
    }
}

// ========== DUE DATE TODAY ==========
$query_today = "SELECT dues_id FROM monthly_dues 
                WHERE due_date = ? AND status IN ('unpaid', 'overdue')";
$stmt = $conn->prepare($query_today);
$stmt->bind_param("s", $today);
$stmt->execute();
$dues_today = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($dues_today as $due) {
    if (alreadyNotifiedToday($conn, $due['dues_id'], 'due_date')) {
        $skip_count++;
        $log_entries[] = "[DUE DATE] dues_id={$due['dues_id']} SKIPPED (already notified today)";
        continue;
    }
    
    $result = sendDueReminder($conn, $due['dues_id'], 'due_date');
    if ($result['email'] || $result['sms']) {
        $sent_count++;
        $log_entries[] = "[DUE DATE] dues_id={$due['dues_id']} email=" . ($result['email'] ? 'OK' : 'FAIL') . " sms=" . ($result['sms'] ? 'OK' : 'FAIL');
    } else {
        $fail_count++;
        $log_entries[] = "[DUE DATE] dues_id={$due['dues_id']} BOTH FAILED";
    }
}

$conn->close();
$timestamp = date('Y-m-d H:i:s');
$summary = "[{$timestamp}] Complete. Sent: {$sent_count} | Failed: {$fail_count} | Skipped: {$skip_count}";

if ($manual) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Due Reminder Cron – Maia Alta HOA</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5}
          .box{background:#fff;padding:20px;border-radius:8px;max-width:700px;margin:0 auto}
          h2{color:#c17f59}.ok{color:#27ae60}.fail{color:#e74c3c}.skip{color:#888}
          .summary{margin-top:16px;padding:12px;background:#f9f9f9;border-left:4px solid #c17f59}</style>";
    echo "</head><body><div class='box'>";
    echo "<h2>Maia Alta HOA – Due Reminder Cron</h2>";
    echo "<p>Run time: <strong>{$timestamp}</strong></p>";
    echo "<p>Checking: <strong>{$today}</strong> (today) and <strong>{$in3}</strong> (3 days from now)</p><hr>";
    
    if (empty($log_entries)) {
        echo "<p class='skip'>No dues require notification today.</p>";
    } else {
        foreach ($log_entries as $entry) {
            $cls = str_contains($entry, 'FAIL') ? 'fail' : (str_contains($entry, 'SKIPPED') ? 'skip' : 'ok');
            echo "<p class='{$cls}'>{$entry}</p>";
        }
    }
    echo "<div class='summary'>{$summary}</div>";
    echo "</div></body></html>";
} else {
    echo $summary . PHP_EOL;
    foreach ($log_entries as $entry) {
        echo "  " . $entry . PHP_EOL;
    }
}
?>