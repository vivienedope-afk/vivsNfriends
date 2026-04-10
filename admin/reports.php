<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Financial Data
$total_dues = 0;
$total_paid = 0;
$total_overdue = 0;
$paid_percentage = 0;

$financial_query = "SELECT 
                    SUM(CASE WHEN md.status = 'unpaid' THEN md.amount ELSE 0 END) as unpaid,
                    SUM(CASE WHEN md.status = 'overdue' THEN md.amount ELSE 0 END) as overdue,
                    SUM(CASE WHEN md.status = 'paid' THEN md.amount ELSE 0 END) as paid,
                    SUM(md.amount) as total
                    FROM monthly_dues md
                    WHERE md.due_date BETWEEN ? AND ?";
$stmt = $conn->prepare($financial_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$financial = $stmt->get_result()->fetch_assoc();

$total_dues = $financial['total'] ?? 0;
$total_paid = $financial['paid'] ?? 0;
$total_overdue = $financial['overdue'] ?? 0;
$paid_percentage = $total_dues > 0 ? round(($total_paid / $total_dues) * 100) : 0;

// Collection Status
$paid_count = 0;
$unpaid_count = 0;
$overdue_count = 0;

$collection_query = "SELECT 
                     SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                     SUM(CASE WHEN md.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                     SUM(CASE WHEN md.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count
                     FROM monthly_dues md
                     WHERE md.due_date BETWEEN ? AND ?";
$stmt = $conn->prepare($collection_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$collection = $stmt->get_result()->fetch_assoc();

$paid_count = $collection['paid_count'] ?? 0;
$unpaid_count = $collection['unpaid_count'] ?? 0;
$overdue_count = $collection['overdue_count'] ?? 0;

// Resident Statistics
$total_residents = 0;
$active_residents = 0;
$pending_applications = 0;

$residents_query = "SELECT COUNT(*) as total FROM users WHERE user_role = 'resident'";
$result = $conn->query($residents_query);
if ($result) {
    $total_residents = $result->fetch_assoc()['total'];
}

$active_query = "SELECT COUNT(*) as total FROM users WHERE user_role = 'resident' AND status = 'active'";
$result = $conn->query($active_query);
if ($result) {
    $active_residents = $result->fetch_assoc()['total'];
}

$applications_query = "SELECT COUNT(*) as total FROM maintenance_requests WHERE status = 'pending'";
$result = $conn->query($applications_query);
if ($result) {
    $pending_applications = $result->fetch_assoc()['total'];
}

// Recent Transactions
$recent_payments_query = "SELECT p.payment_id, p.payment_date, p.amount_paid, p.payment_method, 
                         h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as resident_name,
                         md.due_month, md.due_year
                         FROM payments p
                         INNER JOIN households h ON p.household_id = h.household_id
                         INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                         INNER JOIN users u ON hm.user_id = u.user_id
                         INNER JOIN monthly_dues md ON p.dues_id = md.dues_id
                         WHERE p.payment_date BETWEEN ? AND ?
                         ORDER BY p.payment_date DESC
                         LIMIT 10";
$stmt = $conn->prepare($recent_payments_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$recent_payments = $stmt->get_result();

// Facility Bookings Analytics
$total_bookings = 0;
$approved_bookings = 0;
$pending_bookings = 0;
$total_booking_revenue = 0;

$bookings_query = "SELECT 
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(booking_fee) as revenue
                   FROM facility_bookings
                   WHERE booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$bookings_data = $stmt->get_result()->fetch_assoc();

$total_bookings = $bookings_data['total'] ?? 0;
$approved_bookings = $bookings_data['approved'] ?? 0;
$pending_bookings = $bookings_data['pending'] ?? 0;
$total_booking_revenue = $bookings_data['revenue'] ?? 0;

// Top Debtors
$debtors_query = "SELECT h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as resident_name, 
                 SUM(md.amount) as total_debt
                 FROM household_members hm
                 INNER JOIN households h ON hm.household_id = h.household_id
                 INNER JOIN users u ON hm.user_id = u.user_id
                 INNER JOIN monthly_dues md ON h.household_id = md.household_id
                 WHERE md.status IN ('unpaid', 'overdue') AND hm.is_primary = 1
                 GROUP BY h.household_id
                 ORDER BY total_debt DESC
                 LIMIT 10";
$debtors = $conn->query($debtors_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .reports-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .date-filter {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .date-input {
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      width: 150px;
    }

    .filter-btn {
      padding: 12px 20px;
      background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
      border: 2px solid rgba(193, 127, 89, 0.3);
      border-radius: 8px;
      color: #79491b;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
      transition: opacity 0.3s;
    }

    .filter-btn:hover {
      opacity: 0.9;
    }

    .export-btn {
      padding: 12px 20px;
      background: #27ae60;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.3s;
      text-decoration: none;
      display: inline-block;
    }

    .export-btn:hover {
      background: #229954;
    }

    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }

    .metric-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      border: 1px solid #e9ecef;
    }

    .metric-card .metric-label {
      font-size: 12px;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .metric-card .metric-value {
      font-size: 28px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 4px;
    }

    .metric-card .metric-subtitle {
      font-size: 12px;
      color: #95a5a6;
    }

    .metric-card.financial {
      border-left: 4px solid #3498db;
    }

    .metric-card.collection-paid {
      border-left: 4px solid #27ae60;
    }

    .metric-card.collection-unpaid {
      border-left: 4px solid #f39c12;
    }

    .metric-card.collection-overdue {
      border-left: 4px solid #e74c3c;
    }

    .metric-card.residents {
      border-left: 4px solid #9b59b6;
    }

    .metric-card.bookings {
      border-left: 4px solid #1abc9c;
    }

    .charts-section {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }

    .chart-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      border: 1px solid #e9ecef;
    }

    .chart-card h3 {
      color: #2c3e50;
      margin: 0 0 24px 0;
      font-size: 16px;
      font-weight: 600;
    }

    .bar-chart {
      display: flex;
      align-items: flex-end;
      gap: 16px;
      height: 250px;
      margin-bottom: 40px;
      padding-top: 20px;
      position: relative;
    }

    .bar {
      flex: 1;
      background: linear-gradient(180deg, #fedea3, #f5d18a);
      border-radius: 8px 8px 0 0;
      position: relative;
      min-height: 40px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      padding-bottom: 8px;
      transition: all 0.3s ease;
    }

    .bar:hover {
      filter: brightness(1.1);
      box-shadow: 0 4px 12px rgba(193, 127, 89, 0.2);
    }

    .bar-label {
      position: absolute;
      bottom: -30px;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 13px;
      font-weight: 500;
      color: #2c3e50;
      white-space: nowrap;
    }

    .bar-value {
      color: #2c3e50;
      font-weight: 700;
      font-size: 14px;
      text-align: center;
      width: 100%;
      margin-bottom: 4px;
    }

    .pie-chart {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 200px;
      margin-bottom: 12px;
    }

    .pie-legend {
      display: flex;
      flex-direction: column;
      gap: 12px;
      font-size: 13px;
      padding-top: 16px;
      border-top: 2px solid #e9ecef;
    }

    .pie-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 0;
    }

    .pie-color {
      width: 16px;
      height: 16px;
      border-radius: 3px;
      flex-shrink: 0;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      margin-bottom: 24px;
    }

    .data-table thead {
      background: #f8f9fa;
    }

    .data-table th {
      padding: 16px;
      text-align: left;
      font-weight: 600;
      color: #79491b;
      border-bottom: 2px solid #e9ecef;
      font-size: 14px;
    }

    .data-table td {
      padding: 12px 16px;
      border-bottom: 1px solid #e9ecef;
      font-size: 13px;
    }

    .data-table tbody tr:hover {
      background: #f8f9fa;
    }

    .data-table .amount {
      text-align: right;
      font-weight: 600;
      color: #2c3e50;
    }

    .no-data {
      text-align: center;
      padding: 40px 20px;
      color: #7f8c8d;
    }

    @media (max-width: 1200px) {
      .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .charts-section {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .metrics-grid {
        grid-template-columns: 1fr;
      }
      .reports-header {
        flex-direction: column;
        align-items: stretch;
      }
      .date-filter {
        flex-direction: column;
      }
      .date-input {
        width: 100%;
      }
      .bar-chart {
        height: 280px;
        margin-bottom: 50px;
      }
    }
  </style>
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
      <li class="active"><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Reports & Analytics</h1>
      <p class="breadcrumb">Home > Reports</p>
    </div>

    <!-- Report Header -->
    <div class="reports-header">
      <div class="date-filter">
        <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <label style="font-weight: 500; color: #79491b;">Date Range:</label>
          <input type="date" name="start_date" class="date-input" value="<?php echo htmlspecialchars($start_date); ?>" required>
          <span style="color: #7f8c8d;">to</span>
          <input type="date" name="end_date" class="date-input" value="<?php echo htmlspecialchars($end_date); ?>" required>
          <button type="submit" class="filter-btn">Filter</button>
          <a href="reports.php" class="filter-btn" style="background: white; border: 2px solid #e0e0e0; color: #666;">Reset</a>
        </form>
      </div>
      <a href="reports_action.php?action=export_csv&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="export-btn">📥 Export CSV</a>
    </div>

    <!-- Key Metrics -->
    <div class="metrics-grid">
      <div class="metric-card financial">
        <div class="metric-label">Total Dues</div>
        <div class="metric-value">₱<?php echo number_format($total_dues, 2); ?></div>
        <div class="metric-subtitle"><?php echo $start_date; ?> to <?php echo $end_date; ?></div>
      </div>

      <div class="metric-card collection-paid">
        <div class="metric-label">Total Paid</div>
        <div class="metric-value">₱<?php echo number_format($total_paid, 2); ?></div>
        <div class="metric-subtitle"><?php echo $paid_percentage; ?>% Collection Rate</div>
      </div>

      <div class="metric-card collection-overdue">
        <div class="metric-label">Overdue Amount</div>
        <div class="metric-value">₱<?php echo number_format($total_overdue, 2); ?></div>
        <div class="metric-subtitle">Requires Follow-up</div>
      </div>

      <div class="metric-card bookings">
        <div class="metric-label">Booking Revenue</div>
        <div class="metric-value">₱<?php echo number_format($total_booking_revenue, 2); ?></div>
        <div class="metric-subtitle"><?php echo $total_bookings; ?> Total Bookings</div>
      </div>
    </div>

    <!-- Collection Status Metrics -->
    <div class="metrics-grid">
      <div class="metric-card collection-paid">
        <div class="metric-label">Paid Accounts</div>
        <div class="metric-value"><?php echo $paid_count; ?></div>
        <div class="metric-subtitle">Dues Fully Paid</div>
      </div>

      <div class="metric-card collection-unpaid">
        <div class="metric-label">Unpaid Accounts</div>
        <div class="metric-value"><?php echo $unpaid_count; ?></div>
        <div class="metric-subtitle">Payment Pending</div>
      </div>

      <div class="metric-card collection-overdue">
        <div class="metric-label">Overdue Accounts</div>
        <div class="metric-value"><?php echo $overdue_count; ?></div>
        <div class="metric-subtitle">Past Due Date</div>
      </div>

      <div class="metric-card residents">
        <div class="metric-label">Total Residents</div>
        <div class="metric-value"><?php echo $total_residents; ?></div>
        <div class="metric-subtitle"><?php echo $active_residents; ?> Active</div>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
      <!-- Collection Chart -->
      <div class="chart-card">
        <h3>💰 Collection Status</h3>
        <div class="bar-chart">
          <div class="bar" style="height: <?php echo max(($paid_count / max($paid_count, $unpaid_count, $overdue_count, 1)) * 100, 20); ?>%">
            <div class="bar-value">₱<?php echo number_format($total_paid, 0); ?></div>
            <div class="bar-label">Paid</div>
          </div>
          <div class="bar" style="height: <?php echo max(($unpaid_count / max($paid_count, $unpaid_count, $overdue_count, 1)) * 100, 20); ?>%; background: linear-gradient(180deg, #fff3cd, #ffe8a8)">
            <div class="bar-value"><?php echo $unpaid_count; ?></div>
            <div class="bar-label">Unpaid</div>
          </div>
          <div class="bar" style="height: <?php echo max(($overdue_count / max($paid_count, $unpaid_count, $overdue_count, 1)) * 100, 20); ?>%; background: linear-gradient(180deg, #f8d7da, #f5c2c7)">
            <div class="bar-value"><?php echo $overdue_count; ?></div>
            <div class="bar-label">Overdue</div>
          </div>
        </div>
        <div class="pie-legend">
          <div class="pie-item">
            <div class="pie-color" style="background: #27ae60;"></div>
            <span><strong>Paid:</strong> <?php echo $paid_count; ?> accounts</span>
          </div>
          <div class="pie-item">
            <div class="pie-color" style="background: #f39c12;"></div>
            <span><strong>Unpaid:</strong> <?php echo $unpaid_count; ?> accounts</span>
          </div>
          <div class="pie-item">
            <div class="pie-color" style="background: #e74c3c;"></div>
            <span><strong>Overdue:</strong> <?php echo $overdue_count; ?> accounts</span>
          </div>
        </div>
      </div>

      <!-- Facility Bookings Chart -->
      <div class="chart-card">
        <h3>🏢 Facility Bookings Overview</h3>
        <div class="bar-chart">
          <div class="bar" style="height: <?php echo max(($approved_bookings / max($total_bookings, 1)) * 100, 20); ?>%">
            <div class="bar-value"><?php echo $approved_bookings; ?></div>
            <div class="bar-label">Approved</div>
          </div>
          <div class="bar" style="height: <?php echo max(($pending_bookings / max($total_bookings, 1)) * 100, 20); ?>%; background: linear-gradient(180deg, #fff3cd, #ffe8a8)">
            <div class="bar-value"><?php echo $pending_bookings; ?></div>
            <div class="bar-label">Pending</div>
          </div>
        </div>
        <div class="pie-legend">
          <div class="pie-item">
            <div class="pie-color" style="background: #27ae60;"></div>
            <span><strong>Approved:</strong> <?php echo $approved_bookings; ?> bookings</span>
          </div>
          <div class="pie-item">
            <div class="pie-color" style="background: #f39c12;"></div>
            <span><strong>Pending:</strong> <?php echo $pending_bookings; ?> bookings</span>
          </div>
          <div class="pie-item">
            <div class="pie-color" style="background: #3498db;"></div>
            <span><strong>Revenue:</strong> ₱<?php echo number_format($total_booking_revenue, 0); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
      <div class="card-header">
        <h2>💳 Recent Transactions</h2>
      </div>
      <div class="card-content" style="padding: 0;">
        <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Unit</th>
                <th>Resident Name</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Month</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                  <td><strong><?php echo htmlspecialchars($payment['unit_number']); ?></strong></td>
                  <td><?php echo htmlspecialchars($payment['resident_name']); ?></td>
                  <td class="amount">₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                  <td><span style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $payment['payment_method']); ?></span></td>
                  <td><?php echo htmlspecialchars($payment['due_month']) . ' ' . htmlspecialchars($payment['due_year']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-data">
            <p>No transactions found for the selected period.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Debtors -->
    <div class="card">
      <div class="card-header">
        <h2>⚠️ Top Debtors</h2>
      </div>
      <div class="card-content" style="padding: 0;">
        <?php if ($debtors && $debtors->num_rows > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Unit</th>
                <th>Resident Name</th>
                <th>Total Debt</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($debtor = $debtors->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($debtor['unit_number']); ?></strong></td>
                  <td><?php echo htmlspecialchars($debtor['resident_name']); ?></td>
                  <td class="amount" style="color: #e74c3c;">₱<?php echo number_format($debtor['total_debt'], 2); ?></td>
                  <td><span style="color: #e74c3c; font-weight: 600;">ACTION REQUIRED</span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-data">
            <p style="color: #27ae60;">✓ No current debtors. All accounts are up to date!</p>
          </div>
        <?php endif; ?>
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
  </script>
</body>
</html>
<?php $conn->close(); ?>
