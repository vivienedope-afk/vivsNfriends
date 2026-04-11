<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_month = isset($_GET['month']) ? $_GET['month'] : 'all';

$month_clause = '';
if ($selected_month !== 'all') {
    $month_clause = " AND md.due_month = '" . $conn->real_escape_string($selected_month) . "'";
}

$summary_query = "SELECT
    COUNT(*) AS total_dues,
    COALESCE(SUM(CASE WHEN md.status = 'paid' THEN md.amount ELSE 0 END), 0) AS total_collected,
    COALESCE(SUM(CASE WHEN md.status IN ('unpaid', 'overdue') THEN md.amount ELSE 0 END), 0) AS total_outstanding,
    COALESCE(SUM(CASE WHEN md.status = 'overdue' THEN md.amount ELSE 0 END), 0) AS total_overdue,
    SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count
  FROM monthly_dues md
  WHERE md.due_year = $selected_year $month_clause";
$summary = [
  'total_dues' => 0,
  'total_collected' => 0,
  'total_outstanding' => 0,
  'total_overdue' => 0,
  'paid_count' => 0
];
$summary_result = $conn->query($summary_query);
if ($summary_result) {
    $summary = $summary_result->fetch_assoc();
}

$collection_rate = ((int)$summary['total_dues'] > 0) ? (((int)$summary['paid_count'] / (int)$summary['total_dues']) * 100) : 0;

$monthly_collection_query = "SELECT md.due_month,
    SUM(CASE WHEN md.status = 'paid' THEN md.amount ELSE 0 END) AS collected,
    SUM(CASE WHEN md.status IN ('unpaid', 'overdue') THEN md.amount ELSE 0 END) AS outstanding
  FROM monthly_dues md
  WHERE md.due_year = $selected_year
  GROUP BY md.due_month
  ORDER BY FIELD(md.due_month, 'January','February','March','April','May','June','July','August','September','October','November','December')";
$monthly_collection = $conn->query($monthly_collection_query);

$facility_usage_query = "SELECT fb.facility_name,
    COUNT(*) AS total_bookings,
    SUM(CASE WHEN fb.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
    COALESCE(SUM(CASE WHEN fb.status = 'approved' THEN fb.booking_fee ELSE 0 END), 0) AS total_fees
  FROM facility_bookings fb
  WHERE YEAR(fb.booking_date) = $selected_year
  GROUP BY fb.facility_name
  ORDER BY total_bookings DESC";
$facility_usage = $conn->query($facility_usage_query);

$top_outstanding_query = "SELECT h.unit_number,
    CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
    COUNT(md.dues_id) AS unpaid_count,
    COALESCE(SUM(md.amount), 0) AS outstanding_amount
  FROM monthly_dues md
  INNER JOIN households h ON md.household_id = h.household_id
  LEFT JOIN users u ON h.owner_id = u.user_id
  WHERE md.status IN ('unpaid', 'overdue')
  GROUP BY h.household_id, h.unit_number, owner_name
  ORDER BY outstanding_amount DESC
  LIMIT 10";
$top_outstanding = $conn->query($top_outstanding_query);

$announcement_summary_query = "SELECT 
    COUNT(*) AS total_announcements,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_announcements,
    SUM(CASE WHEN announcement_type = 'urgent' THEN 1 ELSE 0 END) AS urgent_announcements
  FROM announcements";
$announcement_summary = [
  'total_announcements' => 0,
  'active_announcements' => 0,
  'urgent_announcements' => 0
];
$announcement_result = $conn->query($announcement_summary_query);
if ($announcement_result) {
    $announcement_summary = $announcement_result->fetch_assoc();
}
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
    .filters { background: #fff; padding: 14px; border-radius: 10px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .filters form { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
    .filters label { display: block; font-size: 12px; color: #555; margin-bottom: 6px; }
    .filters select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-width: 180px; }
    .btn { border: none; border-radius: 6px; padding: 10px 14px; font-weight: 600; cursor: pointer; }
    .btn-primary { background: #c17f59; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; margin-bottom: 20px; }
    .stat-card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .stat-title { font-size: 12px; color: #666; }
    .stat-value { font-size: 30px; font-weight: 700; color: #8b5a3c; margin-top: 6px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .panel { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 16px; }
    .panel h3 { margin: 0; padding: 12px; background: #f5d18a; color: #5e3a24; font-size: 16px; }
    .panel-body { padding: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 13px; }
    th { color: #5e3a24; }
    .rate-wrap { height: 10px; background: #eee; border-radius: 999px; margin-top: 8px; overflow: hidden; }
    .rate-fill { height: 10px; background: #28a745; }
    @media (max-width: 980px) {
      .grid-2 { grid-template-columns: 1fr; }
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
      <li><a href="events.php" onclick="closeMenu()"><span class="text">Events</span></a></li>
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

    <div class="filters">
      <form method="GET" action="reports.php">
        <div>
          <label>Year</label>
          <select name="year">
            <?php for ($y = (int)date('Y') + 1; $y >= 2020; $y--): ?>
              <option value="<?php echo $y; ?>" <?php echo $selected_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label>Month</label>
          <select name="month">
            <option value="all" <?php echo $selected_month === 'all' ? 'selected' : ''; ?>>All Months</option>
            <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
              <option value="<?php echo $m; ?>" <?php echo $selected_month === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Apply Filter</button>
        <a class="btn btn-secondary" href="reports.php">Reset</a>
        <a class="btn btn-secondary" href="reports_export.php?type=monthly_collection&year=<?php echo $selected_year; ?>">Export Collections CSV</a>
        <a class="btn btn-secondary" href="reports_export.php?type=facility_usage&year=<?php echo $selected_year; ?>">Export Facility CSV</a>
        <a class="btn btn-secondary" href="reports_export.php?type=outstanding">Export Outstanding CSV</a>
      </form>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-title">Total Collected</div><div class="stat-value">₱<?php echo number_format((float)$summary['total_collected'], 2); ?></div></div>
      <div class="stat-card"><div class="stat-title">Outstanding</div><div class="stat-value">₱<?php echo number_format((float)$summary['total_outstanding'], 2); ?></div></div>
      <div class="stat-card"><div class="stat-title">Overdue</div><div class="stat-value">₱<?php echo number_format((float)$summary['total_overdue'], 2); ?></div></div>
      <div class="stat-card">
        <div class="stat-title">Collection Rate</div>
        <div class="stat-value"><?php echo number_format($collection_rate, 1); ?>%</div>
        <div class="rate-wrap"><div class="rate-fill" style="width: <?php echo min(100, max(0, $collection_rate)); ?>%;"></div></div>
      </div>
    </div>

    <div class="grid-2">
      <div class="panel">
        <h3>Monthly Collections (<?php echo $selected_year; ?>)</h3>
        <div class="panel-body">
          <table>
            <thead>
              <tr><th>Month</th><th>Collected</th><th>Outstanding</th></tr>
            </thead>
            <tbody>
              <?php if ($monthly_collection && $monthly_collection->num_rows > 0): ?>
                <?php while ($row = $monthly_collection->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['due_month']); ?></td>
                    <td>₱<?php echo number_format((float)$row['collected'], 2); ?></td>
                    <td>₱<?php echo number_format((float)$row['outstanding'], 2); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="3">No records.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h3>Facility Usage (<?php echo $selected_year; ?>)</h3>
        <div class="panel-body">
          <table>
            <thead>
              <tr><th>Facility</th><th>Total Bookings</th><th>Approved</th><th>Fees</th></tr>
            </thead>
            <tbody>
              <?php if ($facility_usage && $facility_usage->num_rows > 0): ?>
                <?php while ($row = $facility_usage->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                    <td><?php echo (int)$row['total_bookings']; ?></td>
                    <td><?php echo (int)$row['approved_count']; ?></td>
                    <td>₱<?php echo number_format((float)$row['total_fees'], 2); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="4">No records.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="panel">
        <h3>Top Outstanding Residents</h3>
        <div class="panel-body">
          <table>
            <thead>
              <tr><th>Unit</th><th>Resident</th><th>Unpaid Months</th><th>Outstanding</th></tr>
            </thead>
            <tbody>
              <?php if ($top_outstanding && $top_outstanding->num_rows > 0): ?>
                <?php while ($row = $top_outstanding->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['unit_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['owner_name'] ?? 'N/A'); ?></td>
                    <td><?php echo (int)$row['unpaid_count']; ?></td>
                    <td>₱<?php echo number_format((float)$row['outstanding_amount'], 2); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="4">No outstanding balances.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h3>Announcements Snapshot</h3>
        <div class="panel-body">
          <table>
            <tbody>
              <tr><th>Total Announcements</th><td><?php echo (int)$announcement_summary['total_announcements']; ?></td></tr>
              <tr><th>Active Announcements</th><td><?php echo (int)$announcement_summary['active_announcements']; ?></td></tr>
              <tr><th>Urgent Announcements</th><td><?php echo (int)$announcement_summary['urgent_announcements']; ?></td></tr>
            </tbody>
          </table>
        </div>
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
