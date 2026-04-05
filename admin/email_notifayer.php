<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
require_once('../helpers/EmailService.php');
require_once('../helpers/SmsService.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();
$message = '';
$errors = [];
$send_results = [];

// DEBUG: Check database status
$debug_info = [];

// Check unpaid dues count
$check_dues = $conn->query("SELECT COUNT(*) as count FROM monthly_dues WHERE status IN ('unpaid', 'overdue')");
$dues_count = $check_dues->fetch_assoc()['count'];
$debug_info['unpaid_dues_count'] = $dues_count;

// Check residents with contact numbers
$check_residents = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND user_role = 'resident' AND contact_number <> ''");
$residents_count = $check_residents->fetch_assoc()['count'];
$debug_info['residents_with_contact'] = $residents_count;

// Check active residents
$check_active = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND user_role = 'resident'");
$active_count = $check_active->fetch_assoc()['count'];
$debug_info['active_residents'] = $active_count;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notifications'])) {
    $debug_info['form_submitted'] = true;
    
    // Send Email Notifications
    $emailService = getEmailService($conn);

    $unpaid_dues_query = "SELECT md.*, h.unit_number, u.email, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.user_id
                         FROM monthly_dues md
                         INNER JOIN households h ON md.household_id = h.household_id
                         INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                         INNER JOIN users u ON hm.user_id = u.user_id
                         INNER JOIN notification_preferences np ON np.user_id = u.user_id
                         WHERE md.status IN ('unpaid', 'overdue')
                           AND u.status = 'active'
                           AND u.user_role = 'resident'
                           AND np.email_notifications = 1
                           AND u.email <> ''";

    $unpaid_dues = $conn->query($unpaid_dues_query);
    $debug_info['email_query_rows'] = $unpaid_dues ? $unpaid_dues->num_rows : 0;
    
    if ($unpaid_dues && $unpaid_dues->num_rows > 0) {
        while ($due = $unpaid_dues->fetch_assoc()) {
            $result = $emailService->sendMonthlyDueEmail(
                $due['user_id'],
                $due['email'],
                $due['full_name'],
                $due['amount'],
                $due['due_date'],
                $due['due_month'],
                $due['due_year']
            );

            $send_results[] = [
                'type' => 'EMAIL',
                'contact' => $due['email'],
                'name' => $due['full_name'],
                'unit' => $due['unit_number'],
                'amount' => $due['amount'],
                'month' => $due['due_month'],
                'year' => $due['due_year'],
                'success' => $result['success'] ?? false,
                'message' => $result['success'] ? 'Sent' : 'Failed',
                'details' => $result['success'] ? '' : ($result['error'] ?? 'Unknown error')
            ];
        }
    }

    // Send SMS Notifications
    $smsService = getSmsService($conn);

    $unpaid_dues_query_sms = "SELECT md.*, h.unit_number, u.contact_number AS phone, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.user_id
                             FROM monthly_dues md
                             INNER JOIN households h ON md.household_id = h.household_id
                             INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                             INNER JOIN users u ON hm.user_id = u.user_id
                             WHERE md.status IN ('unpaid', 'overdue')
                               AND u.status = 'active'
                               AND u.user_role = 'resident'
                               AND u.contact_number <> ''";

    $unpaid_dues_sms = $conn->query($unpaid_dues_query_sms);
    $debug_info['sms_query_rows'] = $unpaid_dues_sms ? $unpaid_dues_sms->num_rows : 0;
    
    if ($unpaid_dues_sms && $unpaid_dues_sms->num_rows > 0) {
        while ($due = $unpaid_dues_sms->fetch_assoc()) {
            $result = $smsService->sendMonthlyDueSms(
                $due['user_id'],
                $due['phone'],
                $due['full_name'],
                $due['amount'],
                $due['due_date'],
                $due['due_month'],
                $due['due_year']
            );

            $send_results[] = [
                'type' => 'SMS',
                'contact' => $due['phone'],
                'name' => $due['full_name'],
                'unit' => $due['unit_number'],
                'amount' => $due['amount'],
                'month' => $due['due_month'],
                'year' => $due['due_year'],
                'success' => $result['success'] ?? false,
                'message' => $result['success'] ? 'Sent' : 'Failed',
                'status_code' => $result['status'] ?? null,
                'details' => $result['success'] ? '' : ($result['error'] ?? $result['response'] ?? 'Unknown error')
            ];
        }
    }

    $email_count = count(array_filter($send_results, fn($r) => $r['type'] === 'EMAIL'));
    $sms_count = count(array_filter($send_results, fn($r) => $r['type'] === 'SMS'));
    $message = "Notifications sent: {$email_count} email(s), {$sms_count} SMS(es).";
    
    $debug_info['total_emails_sent'] = $email_count;
    $debug_info['total_sms_sent'] = $sms_count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Notifayer - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">☰</button>

  <nav class="navbar" id="sidebar">
    <button class="close-btn" onclick="toggleMenu()">×</button>
    <img src="../pics/Courtyard.png" alt="Courtyard Logo" class="logo">
    <div class="user-info">
      <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
      <p class="user-role">Administrator</p>
    </div>
    <ul class="nav-links">
      <li><a href="dashboard.php" onclick="closeMenu()"><span class="text">Dashboard</span></a></li>
      <li><a href="residents.php" onclick="closeMenu()"><span class="text">Residents</span></a></li>
      <li><a href="applications.php" onclick="closeMenu()"><span class="text">Applications</span></a></li>
      <li><a href="payments.php" onclick="closeMenu()"><span class="text">Payments & Dues</span></a></li>
      <li><a href="bookings.php" onclick="closeMenu()"><span class="text">Facility Bookings</span></a></li>
      <li><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li class="active"><a href="email_notifayer.php" onclick="closeMenu()"><span class="text">Email Notifayer</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Email Notifayer</h1>
      <p class="breadcrumb">Home > Email Notifayer</p>
    </div>

    <!-- DEBUG PANEL (only visible to admin) -->
    <div class="card" style="background-color: #f8f9fa; border-left: 4px solid #007bff;">
      <div class="card-header">
        <h2 style="font-size: 1rem; margin: 0;">🔍 System Status (Debug)</h2>
      </div>
      <div class="card-content" style="padding: 10px;">
        <table style="width: 100%; font-size: 0.85rem;">
          <tr><td>Unpaid/Overdue Dues:</td><td><strong><?php echo $debug_info['unpaid_dues_count'] ?? '?'; ?></strong></td></tr>
          <tr><td>Active Residents:</td><td><strong><?php echo $debug_info['active_residents'] ?? '?'; ?></strong></td></tr>
          <tr><td>Residents with Contact Numbers:</td><td><strong><?php echo $debug_info['residents_with_contact'] ?? '?'; ?></strong></td></tr>
          <?php if (isset($debug_info['form_submitted'])): ?>
            <tr><td>Form Submitted:</td><td><strong style="color: green;">✅ Yes</strong></td></tr>
            <tr><td>Email Query Results:</td><td><strong><?php echo $debug_info['email_query_rows']; ?></strong> resident(s)</td></tr>
            <tr><td>SMS Query Results:</td><td><strong><?php echo $debug_info['sms_query_rows']; ?></strong> resident(s)</td></tr>
            <tr><td>Emails Sent:</td><td><strong><?php echo $debug_info['total_emails_sent']; ?></strong></td></tr>
            <tr><td>SMS Sent:</td><td><strong><?php echo $debug_info['total_sms_sent']; ?></strong></td></tr>
          <?php else: ?>
            <tr><td colspan="2"><em>Click "Send All Notifications" to see results</em></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Send Monthly Dues Notifications</h2>
      </div>
      <div class="card-content">
        <?php if (!empty($message)): ?>
          <div class="notification success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="notification error">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <p>This will send email and SMS notifications to all residents with unpaid or overdue monthly dues who have the respective notifications enabled.</p>
        <br>

        <form method="post" onsubmit="console.log('Form submitted!');">
          <div class="form-actions">
            <button type="submit" name="send_notifications" class="action-btn blue-action" onclick="console.log('Button clicked!');">Send All Notifications</button>
            <a href="dashboard.php" class="action-btn gray-action" style="text-decoration:none;">Back to Dashboard</a>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($send_results)): ?>
      <div class="card">
        <div class="card-header">
          <h2>Send Results</h2>
        </div>
        <div class="card-content">
          <table class="data-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Unit</th>
                <th>Resident</th>
                <th>Contact</th>
                <th>Amount</th>
                <th>Period</th>
                <th>Status</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($send_results as $result): ?>
                <tr>
                  <td><?php echo strtoupper($result['type']); ?></td>
                  <td><?php echo htmlspecialchars($result['unit']); ?></td>
                  <td><?php echo htmlspecialchars($result['name']); ?></td>
                  <td><?php echo htmlspecialchars($result['contact']); ?></td>
                  <td>₱<?php echo number_format($result['amount'], 2); ?></td>
                  <td><?php echo $result['month'] . ' ' . $result['year']; ?></td>
                  <td><?php echo $result['success'] ? '<span class="badge badge-success">Sent</span>' : '<span class="badge badge-danger">Failed</span>'; ?></td>
                  <td><?php echo htmlspecialchars($result['details'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuBtn = document.querySelector('.menu-btn');

    function toggleMenu() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
      menuBtn.style.opacity = sidebar.classList.contains('open') ? '0' : '1';
    }

    function closeMenu() {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
      menuBtn.style.opacity = '1';
    }
    
    console.log('Page loaded!');
  </script>
</body>
</html>
<?php $conn->close(); ?>