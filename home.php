<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get latest unpaid due
$unpaid_due_query = "SELECT md.*, h.unit_number 
                     FROM monthly_dues md
                     INNER JOIN households h ON md.household_id = h.household_id
                     WHERE md.household_id = ? AND md.status = 'unpaid'
                     ORDER BY md.due_date ASC
                     LIMIT 1";
$stmt = $conn->prepare($unpaid_due_query);
$stmt->bind_param("i", $current_user['household_id']);
$stmt->execute();
$unpaid_due = $stmt->get_result()->fetch_assoc();

// Get payment summary for current year
$year = date('Y');
$paid_summary_query = "SELECT COUNT(*) as paid_count, SUM(md.amount) as total_paid
                       FROM monthly_dues md
                       WHERE md.household_id = ? AND md.status = 'paid' AND md.due_year = ?";
$stmt2 = $conn->prepare($paid_summary_query);
$stmt2->bind_param("ii", $current_user['household_id'], $year);
$stmt2->execute();
$paid_summary = $stmt2->get_result()->fetch_assoc();

$outstanding_query = "SELECT COUNT(*) as unpaid_count, SUM(md.amount) as total_outstanding
                      FROM monthly_dues md
                      WHERE md.household_id = ? AND md.status IN ('unpaid', 'overdue')";
$stmt3 = $conn->prepare($outstanding_query);
$stmt3->bind_param("i", $current_user['household_id']);
$stmt3->execute();
$outstanding_summary = $stmt3->get_result()->fetch_assoc();

// Get recent announcements
$announcements_query = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as posted_by_name
                        FROM announcements a
                        INNER JOIN users u ON a.posted_by = u.user_id
                        WHERE a.status = 'active' 
                        AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                        ORDER BY a.post_date DESC
                        LIMIT 5";
$announcements = $conn->query($announcements_query);

// Get upcoming events
$events_query = "SELECT * FROM events 
                 WHERE event_date >= CURDATE() AND status = 'upcoming'
                 ORDER BY event_date ASC
                 LIMIT 3";
$events = $conn->query($events_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/home.css">
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">
    ☰
  </button>

  <nav class="navbar" id="sidebar">
    <button class="close-btn" onclick="toggleMenu()">
      ✕
    </button>
    <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
    <div class="user-info-sidebar">
      <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
      <p class="user-unit"><?php echo htmlspecialchars($current_user['unit_number']); ?></p>
    </div>
    <ul class="nav-links">
      <li class="active"><a href="home.php" onclick="closeMenu()"><span class="text">Home</span></a></li>
      <li><a href="account.php" onclick="closeMenu()"><span class="text">Account</span></a></li>
      <li><a href="dashboard.php" onclick="closeMenu()"><span class="text">Dashboard</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="text">Calendar</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="text">Amenities</span></a></li>
      <li><a href="auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
      <p class="breadcrumb">Home</p>
    </div>

    <div class="home-grid">
      <!-- Notification Settings Card -->
      <div class="card notification-settings-card">
        <div class="card-header">
          <span>Notification Settings</span>
        </div>
        <div class="card-content">
          <div class="notification-option">
            <div class="notification-info">
              <h3>Email Notifications</h3>
              <p>Receive updates about dues, events, and announcements via email</p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="email-notif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          
          <div class="notification-option">
            <div class="notification-info">
              <h3>SMS Notifications</h3>
              <p>Get important alerts and reminders via text message</p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="sms-notif">
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Payment Due Card -->
      <?php if ($unpaid_due): ?>
      <div class="card payment-due-card">
        <div class="card-header">
          <span>Payment Due</span>
        </div>
        <div class="card-content">
          <div class="payment-due-info">
            <h3><?php echo htmlspecialchars($unpaid_due['due_month'] . ' ' . $unpaid_due['due_year']); ?> Payment Due</h3>
            <div class="amount-display">
              <span class="label">Amount:</span>
              <span class="amount">₱<?php echo number_format($unpaid_due['amount'], 2); ?></span>
            </div>
            <div class="due-date-display">
              <span class="label">Due by:</span>
              <span class="due-date"><?php echo date('F d, Y', strtotime($unpaid_due['due_date'])); ?></span>
            </div>
            <div class="warning-message">
              Please settle to avoid late fees
            </div>
          </div>
          <div class="payment-actions">
            <button class="pay-now-btn">
              Pay Now
            </button>
            <a href="account.php" class="payment-history-link">
              Payment History
            </a>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="card payment-due-card all-paid">
        <div class="card-header">
          <span>Payment Status</span>
        </div>
        <div class="card-content">
          <div class="all-paid-message">
            <h3>All Payments Up to Date!</h3>
            <p>Thank you for your prompt payment</p>
          </div>
          <a href="account.php" class="payment-history-link">
            View Payment History
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payment Ledger Summary -->
    <div class="card ledger-summary-card">
      <div class="card-header">
        <span>Payment Ledger Summary</span>
        <a href="account.php" class="view-all-link">View Full Ledger</a>
      </div>
      <div class="card-content">
        <div class="ledger-stats">
          <div class="stat-item">
            <div class="stat-label">Total Paid (<?php echo $year; ?>)</div>
            <div class="stat-value paid">₱<?php echo number_format($paid_summary['total_paid'] ?? 0, 2); ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-label">Outstanding</div>
            <div class="stat-value outstanding">₱<?php echo number_format($outstanding_summary['total_outstanding'] ?? 0, 2); ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Announcements Section -->
    <div class="card announcements-card">
      <div class="card-header">
        <span>Latest Announcements</span>
      </div>
      <div class="card-content">
        <?php if ($announcements->num_rows > 0): ?>
          <div class="announcements-list">
            <?php while ($announcement = $announcements->fetch_assoc()): ?>
              <div class="announcement-item">
                <div class="announcement-type-badge <?php echo $announcement['announcement_type']; ?>">
                  <?php echo ucfirst($announcement['announcement_type']); ?>
                </div>
                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <p><?php echo htmlspecialchars($announcement['content']); ?></p>
                <div class="announcement-meta">
                  <span>By: <?php echo htmlspecialchars($announcement['posted_by_name']); ?></span>
                  <span><?php echo date('M d, Y', strtotime($announcement['post_date'])); ?></span>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <p class="no-data">No announcements at this time</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Upcoming Events -->
    <?php if ($events->num_rows > 0): ?>
    <div class="card events-card">
      <div class="card-header">
        <span>Upcoming Events</span>
      </div>
      <div class="card-content">
        <div class="events-list">
          <?php while ($event = $events->fetch_assoc()): ?>
            <div class="event-item">
              <div class="event-date">
                <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
              </div>
              <div class="event-info">
                <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                <p><?php echo htmlspecialchars($event['event_description']); ?></p>
                <div class="event-meta">
                  <?php if ($event['event_time']): ?>
                    <span>Time: <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                  <?php endif; ?>
                  <?php if ($event['location']): ?>
                    <span>Location: <?php echo htmlspecialchars($event['location']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
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

    // Save notification settings
    document.querySelectorAll('.toggle input').forEach(toggle => {
      toggle.addEventListener('change', function() {
        // TODO: Save to database via AJAX
        console.log(this.id + ' changed to: ' + this.checked);
      });
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>
