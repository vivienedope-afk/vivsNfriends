<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get statistics
$total_households_query = "SELECT COUNT(*) as total FROM households WHERE status = 'occupied'";
$total_households = $conn->query($total_households_query)->fetch_assoc()['total'];

$total_residents_query = "SELECT COUNT(*) as total FROM users WHERE user_role = 'resident' AND status = 'active'";
$total_residents = $conn->query($total_residents_query)->fetch_assoc()['total'];

$unpaid_dues_query = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM monthly_dues WHERE status = 'unpaid'";
$unpaid_dues_result = $conn->query($unpaid_dues_query)->fetch_assoc();
$unpaid_count = $unpaid_dues_result['total'];
$unpaid_amount = $unpaid_dues_result['total_amount'] ?? 0;

$paid_this_month_query = "SELECT COUNT(*) as total, SUM(amount_paid) as total_amount FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
$paid_this_month_result = $conn->query($paid_this_month_query)->fetch_assoc();
$paid_count = $paid_this_month_result['total'];
$paid_amount = $paid_this_month_result['total_amount'] ?? 0;

// Get recent payments
$recent_payments_query = "SELECT p.*, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as payer_name, md.due_month, md.due_year
                          FROM payments p
                          INNER JOIN households h ON p.household_id = h.household_id
                          INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                          INNER JOIN users u ON hm.user_id = u.user_id
                          INNER JOIN monthly_dues md ON p.dues_id = md.dues_id
                          ORDER BY p.created_at DESC
                          LIMIT 10";
$recent_payments = $conn->query($recent_payments_query);

// Get pending facility bookings
$pending_bookings_query = "SELECT fb.*, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as requester_name
                           FROM facility_bookings fb
                           INNER JOIN households h ON fb.household_id = h.household_id
                           INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                           INNER JOIN users u ON hm.user_id = u.user_id
                           WHERE fb.status = 'pending'
                           ORDER BY fb.created_at DESC
                           LIMIT 5";
$pending_bookings = $conn->query($pending_bookings_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
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
      <li class="active"><a href="dashboard.php" onclick="closeMenu()"><span class="text">Dashboard</span></a></li>
      <li><a href="residents.php" onclick="closeMenu()"><span class="text">Residents</span></a></li>
      <li><a href="payments.php" onclick="closeMenu()"><span class="text">Payments & Dues</span></a></li>
      <li><a href="bookings.php" onclick="closeMenu()"><span class="text">Facility Bookings</span></a></li>
      <li><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Admin Dashboard</h1>
      <p class="breadcrumb">Home > Dashboard</p>
    </div>

    <section class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-info">
          <h3><?php echo $total_households; ?></h3>
          <p>Total Households</p>
        </div>
      </div>

      <div class="stat-card green">
        <div class="stat-info">
          <h3><?php echo $total_residents; ?></h3>
          <p>Active Residents</p>
        </div>
      </div>

      <div class="stat-card red">
        <div class="stat-info">
          <h3><?php echo $unpaid_count; ?></h3>
          <p>Unpaid Dues</p>
          <span class="stat-detail">₱<?php echo number_format($unpaid_amount, 2); ?></span>
        </div>
      </div>

      <div class="stat-card purple">
        <div class="stat-info">
          <h3>₱<?php echo number_format($paid_amount, 2); ?></h3>
          <p>Collected This Month</p>
          <span class="stat-detail"><?php echo $paid_count; ?> payments</span>
        </div>
      </div>
    </section>

    <div class="dashboard-grid">
      <div class="card">
        <div class="card-header">
          <h2>Recent Payments</h2>
          <a href="payments.php" class="view-all-link">View All</a>
        </div>
        <div class="card-content">
          <?php if ($recent_payments->num_rows > 0): ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Unit</th>
                  <th>Payer</th>
                  <th>Period</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($payment['unit_number']); ?></td>
                    <td><?php echo htmlspecialchars($payment['payer_name']); ?></td>
                    <td><?php echo $payment['due_month'] . ' ' . $payment['due_year']; ?></td>
                    <td>₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                    <td>
                      <?php if ($payment['verified_by']): ?>
                        <span class="badge badge-success">Verified</span>
                      <?php else: ?>
                        <span class="badge badge-warning">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="no-data">No recent payments</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2>Pending Bookings</h2>
          <a href="bookings.php" class="view-all-link">View All</a>
        </div>
        <div class="card-content">
          <?php if ($pending_bookings->num_rows > 0): ?>
            <div class="bookings-list">
              <?php while ($booking = $pending_bookings->fetch_assoc()): ?>
                <div class="booking-item">
                  <div class="booking-info">
                    <h4><?php echo htmlspecialchars($booking['facility_name']); ?></h4>
                    <p><strong><?php echo htmlspecialchars($booking['unit_number']); ?></strong> - <?php echo htmlspecialchars($booking['requester_name']); ?></p>
                    <p class="booking-date">
                      <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?> 
                      (<?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?>)
                    </p>
                  </div>
                  <div class="booking-actions">
                    <button class="btn-approve" onclick="approveBooking(<?php echo $booking['booking_id']; ?>)">Approve</button>
                    <button class="btn-reject" onclick="rejectBooking(<?php echo $booking['booking_id']; ?>)">Reject</button>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <p class="no-data">No pending bookings</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="quick-actions">
      <h2>Quick Actions</h2>
      <div class="actions-grid">
        <a href="announcements.php?action=new" class="action-btn">
          <span>Post Announcement</span>
        </a>
        <a href="payments.php?action=record" class="action-btn">
          <span>Record Payment</span>
        </a>
        <a href="residents.php?action=add" class="action-btn">
          <span>Add Resident</span>
        </a>
        <a href="reports.php" class="action-btn">
          <span>Generate Report</span>
        </a>
      </div>
    </div>
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

    function approveBooking(bookingId) {
      if (confirm('Approve this booking?')) {
        window.location.href = 'bookings_action.php?action=approve&id=' + bookingId;
      }
    }

    function rejectBooking(bookingId) {
      if (confirm('Reject this booking?')) {
        window.location.href = 'bookings_action.php?action=reject&id=' + bookingId;
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
