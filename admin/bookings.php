<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$facility_filter = isset($_GET['facility']) ? $_GET['facility'] : 'all';
$date_filter = isset($_GET['booking_date']) ? $_GET['booking_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$stats = [
  'pending' => 0,
  'approved' => 0,
  'rejected' => 0,
  'cancelled' => 0
];

$stats_result = $conn->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
  FROM facility_bookings");
if ($stats_result) {
    $stats_row = $stats_result->fetch_assoc();
    $stats['pending'] = (int)($stats_row['pending'] ?? 0);
    $stats['approved'] = (int)($stats_row['approved'] ?? 0);
    $stats['rejected'] = (int)($stats_row['rejected'] ?? 0);
    $stats['cancelled'] = (int)($stats_row['cancelled'] ?? 0);
}

$query = "SELECT fb.*, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) AS requester_name
          FROM facility_bookings fb
          INNER JOIN households h ON fb.household_id = h.household_id
          LEFT JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
          LEFT JOIN users u ON hm.user_id = u.user_id
          WHERE 1=1";

if ($status_filter !== 'all') {
  $query .= " AND fb.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($facility_filter !== 'all') {
  $query .= " AND fb.facility_name = '" . $conn->real_escape_string($facility_filter) . "'";
}
if ($date_filter !== '') {
  $query .= " AND fb.booking_date = '" . $conn->real_escape_string($date_filter) . "'";
}
if ($search !== '') {
  $s = $conn->real_escape_string($search);
  $query .= " AND (h.unit_number LIKE '%$s%' OR u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR fb.purpose LIKE '%$s%')";
}

$query .= " ORDER BY fb.booking_date DESC, fb.start_time DESC, fb.created_at DESC";
$bookings_result = $conn->query($query);

$schedule_result = $conn->query("SELECT facility_name, booking_date, start_time, end_time, purpose
                                FROM facility_bookings
                                WHERE status = 'approved' AND booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                                ORDER BY booking_date ASC, start_time ASC");

$create_damage_table_sql = "CREATE TABLE IF NOT EXISTS amenity_damage_reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  booking_id INT NOT NULL,
  household_id INT NOT NULL,
  reported_by INT NOT NULL,
  incident_date DATE NOT NULL,
  incident_type VARCHAR(100) DEFAULT 'damage',
  description TEXT NOT NULL,
  estimated_cost DECIMAL(10,2) DEFAULT NULL,
  status ENUM('reported','under_review','resolved') DEFAULT 'reported',
  admin_notes TEXT,
  resolved_by INT DEFAULT NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES facility_bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
)";
$conn->query($create_damage_table_sql);

$damage_reports_query = "SELECT adr.*, fb.facility_name, fb.booking_date, fb.start_time, fb.end_time,
                                h.unit_number, CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
                                CONCAT(a.first_name, ' ', a.last_name) AS resolved_by_name
                         FROM amenity_damage_reports adr
                         INNER JOIN facility_bookings fb ON adr.booking_id = fb.booking_id
                         INNER JOIN households h ON adr.household_id = h.household_id
                         LEFT JOIN users u ON adr.reported_by = u.user_id
                         LEFT JOIN users a ON adr.resolved_by = a.user_id
                         ORDER BY adr.created_at DESC";
$damage_reports = $conn->query($damage_reports_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facility Bookings - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
    .stat-card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .stat-title { font-size: 12px; color: #666; }
    .stat-value { font-size: 28px; font-weight: 700; color: #8b5a3c; margin-top: 6px; }
    .filters { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .filters form { display: grid; grid-template-columns: repeat(4, 1fr) 2fr auto auto; gap: 10px; align-items: end; }
    .filters label { display: block; font-size: 12px; color: #555; margin-bottom: 6px; }
    .filters select, .filters input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
    .btn { border: none; border-radius: 6px; padding: 10px 12px; font-weight: 600; cursor: pointer; }
    .btn-primary { background: #c17f59; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-success { background: #28a745; color: #fff; }
    .btn-danger { background: #dc3545; color: #fff; }
    .btn-warning { background: #faa500; color: #fff; }
    .btn-primary:hover { background: #8b5a3c; }
    .table-wrap { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f5d18a; color: #5e3a24; padding: 12px; text-align: left; }
    td { border-top: 1px solid #eee; padding: 12px; vertical-align: top; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .pending { background: #fff3cd; color: #856404; }
    .approved { background: #d4edda; color: #155724; }
    .rejected { background: #f8d7da; color: #721c24; }
    .cancelled { background: #e2e3e5; color: #41464b; }
    .schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
    .schedule-item { background: #fff; border-left: 4px solid #c17f59; border-radius: 8px; padding: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.06); }
    .schedule-title { font-weight: 700; color: #8b5a3c; margin-bottom: 4px; }
    .flash { margin-bottom: 14px; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
    .flash.ok { background: #d4edda; color: #155724; }
    .flash.err { background: #f8d7da; color: #721c24; }
    .report-badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .reported { background:#ffe5b4; color:#7a4b00; }
    .under_review { background:#d1ecf1; color:#0c5460; }
    .resolved { background:#d4edda; color:#155724; }
    .mini-select, .mini-input { width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; margin-bottom: 6px; }
    .btn-mini { border: none; border-radius: 6px; padding: 7px 10px; font-size: 12px; font-weight: 600; background: #c17f59; color: #fff; cursor: pointer; }
    @media (max-width: 1100px) {
      .filters form { grid-template-columns: 1fr; }
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
      <li class="active"><a href="bookings.php" onclick="closeMenu()"><span class="text">Facility Bookings</span></a></li>
      <li><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li><a href="events.php" onclick="closeMenu()"><span class="text">Events</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Facility Bookings Management</h1>
      <p class="breadcrumb">Home > Facility Bookings</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="flash ok">
        <?php
          if ($_GET['success'] === 'status') echo 'Booking status updated successfully.';
          elseif ($_GET['success'] === 'payment') echo 'Booking payment status updated successfully.';
          elseif ($_GET['success'] === 'deleted') echo 'Booking deleted successfully.';
          elseif ($_GET['success'] === 'damage_updated') echo 'Damage report status updated successfully.';
          else echo 'Action completed successfully.';
        ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="flash err">
        <?php
          if ($_GET['error'] === 'delete_blocked') echo 'Cannot delete approved and paid booking records.';
          elseif ($_GET['error'] === 'damage_update_failed') echo 'Failed to update damage report status.';
          else echo 'Failed to update booking status.';
        ?>
      </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-title">Pending</div><div class="stat-value"><?php echo $stats['pending']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Approved</div><div class="stat-value"><?php echo $stats['approved']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Rejected</div><div class="stat-value"><?php echo $stats['rejected']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Cancelled</div><div class="stat-value"><?php echo $stats['cancelled']; ?></div></div>
    </div>

    <div class="filters">
      <form method="GET" action="bookings.php">
        <div>
          <label>Status</label>
          <select name="status">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>
        </div>
        <div>
          <label>Facility</label>
          <select name="facility">
            <option value="all" <?php echo $facility_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="Clubhouse" <?php echo $facility_filter === 'Clubhouse' ? 'selected' : ''; ?>>Clubhouse</option>
            <option value="Basketball Court" <?php echo $facility_filter === 'Basketball Court' ? 'selected' : ''; ?>>Basketball Court</option>
          </select>
        </div>
        <div>
          <label>Date</label>
          <input type="date" name="booking_date" value="<?php echo htmlspecialchars($date_filter); ?>">
        </div>
        <div>
          <label>Search</label>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Unit, resident, purpose">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        <a href="bookings.php" class="btn btn-secondary">Reset</a>
      </form>
    </div>

    <h2 style="margin-bottom:10px;">Booking Requests</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Facility</th>
            <th>Resident</th>
            <th>Schedule</th>
            <th>Purpose</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
            <?php while ($row = $bookings_result->fetch_assoc()): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($row['facility_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['unit_number']); ?><br><small><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></small></td>
                <td>
                  <?php echo date('M d, Y', strtotime($row['booking_date'])); ?><br>
                  <small><?php echo date('g:i A', strtotime($row['start_time'])); ?> - <?php echo date('g:i A', strtotime($row['end_time'])); ?></small>
                </td>
                <td><?php echo htmlspecialchars($row['purpose'] ?? '-'); ?></td>
                <td><span class="badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <td>
                  Fee: ₱<?php echo number_format((float)($row['booking_fee'] ?? 0), 2); ?><br>
                  <small><?php echo ucfirst($row['payment_status'] ?? 'unpaid'); ?></small>
                </td>
                <td>
                  <?php if ($row['status'] === 'pending'): ?>
                    <button class="btn btn-success" onclick="applyAction(<?php echo (int)$row['booking_id']; ?>, 'approve')">Approve</button>
                    <button class="btn btn-danger" onclick="applyAction(<?php echo (int)$row['booking_id']; ?>, 'reject')">Reject</button>
                  <?php endif; ?>
                  <?php if ($row['status'] === 'approved' && $row['payment_status'] === 'unpaid'): ?>
                    <button class="btn btn-warning" onclick="applyAction(<?php echo (int)$row['booking_id']; ?>, 'mark_paid')">Mark Paid</button>
                  <?php endif; ?>
                  <?php if ($row['status'] !== 'cancelled' && $row['status'] !== 'rejected'): ?>
                    <button class="btn btn-secondary" onclick="applyAction(<?php echo (int)$row['booking_id']; ?>, 'archive')">Archive</button>
                  <?php endif; ?>
                  <button class="btn btn-danger" onclick="applyAction(<?php echo (int)$row['booking_id']; ?>, 'delete')">Delete</button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align:center; color:#777;">No booking records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h2 style="margin-bottom:10px;">Approved Schedule (Next 14 Days)</h2>
    <div class="schedule-grid">
      <?php if ($schedule_result && $schedule_result->num_rows > 0): ?>
        <?php while ($s = $schedule_result->fetch_assoc()): ?>
          <div class="schedule-item">
            <div class="schedule-title"><?php echo htmlspecialchars($s['facility_name']); ?></div>
            <div><?php echo date('D, M d, Y', strtotime($s['booking_date'])); ?></div>
            <div><?php echo date('g:i A', strtotime($s['start_time'])); ?> - <?php echo date('g:i A', strtotime($s['end_time'])); ?></div>
            <div style="margin-top:6px; color:#666;"><?php echo htmlspecialchars($s['purpose'] ?? '-'); ?></div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="schedule-item">No approved schedules in the next 14 days.</div>
      <?php endif; ?>
    </div>

    <h2 style="margin:22px 0 10px;">Amenities Damage/Incident Reports</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Facility / Schedule</th>
            <th>Reporter</th>
            <th>Incident</th>
            <th>Status</th>
            <th>Admin Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($damage_reports && $damage_reports->num_rows > 0): ?>
            <?php while ($report = $damage_reports->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($report['facility_name']); ?></strong><br>
                  <small><?php echo date('M d, Y', strtotime($report['booking_date'])); ?>, <?php echo date('g:i A', strtotime($report['start_time'])); ?> - <?php echo date('g:i A', strtotime($report['end_time'])); ?></small>
                </td>
                <td><?php echo htmlspecialchars($report['unit_number']); ?><br><small><?php echo htmlspecialchars($report['reporter_name'] ?? 'N/A'); ?></small></td>
                <td>
                  <strong><?php echo htmlspecialchars($report['incident_type']); ?></strong><br>
                  <small><?php echo htmlspecialchars($report['description']); ?></small><br>
                  <small>Date: <?php echo date('M d, Y', strtotime($report['incident_date'])); ?></small>
                  <?php if ($report['estimated_cost'] !== null): ?>
                    <br><small>Est. Cost: ₱<?php echo number_format((float)$report['estimated_cost'], 2); ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="report-badge <?php echo htmlspecialchars($report['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $report['status'])); ?></span>
                  <?php if (!empty($report['resolved_by_name'])): ?>
                    <br><small>By: <?php echo htmlspecialchars($report['resolved_by_name']); ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" action="amenities_damage_action.php">
                    <input type="hidden" name="action" value="update_damage_status">
                    <input type="hidden" name="report_id" value="<?php echo (int)$report['report_id']; ?>">
                    <select class="mini-select" name="status" required>
                      <option value="reported" <?php echo $report['status'] === 'reported' ? 'selected' : ''; ?>>Reported</option>
                      <option value="under_review" <?php echo $report['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                      <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                    <input class="mini-input" type="text" name="admin_notes" value="<?php echo htmlspecialchars($report['admin_notes'] ?? '', ENT_QUOTES); ?>" placeholder="Admin notes">
                    <button class="btn-mini" type="submit">Update</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align:center; color:#777;">No damage/incident reports yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- View Details Modal -->
  <div id="viewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Booking Details</h2>
        <button class="close-modal" onclick="closeViewModal()">&times;</button>
      </div>
      <div id="viewContent">
        <!-- Content loaded dynamically -->
      </div>
      <div class="modal-buttons">
        <button class="btn-cancel" onclick="closeViewModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Approve Modal -->
  <div id="approveModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Approve Booking</h2>
        <button class="close-modal" onclick="closeApproveModal()">&times;</button>
      </div>
      <form method="POST" action="bookings_action.php">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="booking_id" id="approveBookingId">
        
        <div class="form-group">
          <label for="approveFee">Booking Fee (₱)</label>
          <input type="number" id="approveFee" name="booking_fee" step="0.01" min="0" value="0" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
        </div>

        <div class="form-group">
          <label for="approveRemarks">Remarks (Optional)</label>
          <textarea id="approveRemarks" name="remarks" placeholder="Add any approval remarks..."></textarea>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn-cancel" onclick="closeApproveModal()">Cancel</button>
          <button type="submit" class="btn-submit">Approve Booking</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reject Modal -->
  <div id="rejectModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Reject Booking</h2>
        <button class="close-modal" onclick="closeRejectModal()">&times;</button>
      </div>
      <form method="POST" action="bookings_action.php">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="booking_id" id="rejectBookingId">
        
        <div class="form-group">
          <label for="rejectRemarks">Reason for Rejection</label>
          <textarea id="rejectRemarks" name="remarks" placeholder="Please provide a reason for rejecting this booking..." required></textarea>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
          <button type="submit" class="btn-submit" style="background: #e74c3c;">Reject Booking</button>
        </div>
      </form>
    </div>
  </div>

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

    function applyAction(bookingId, action) {
      let remarks = '';
      if (action === 'reject' || action === 'archive') {
        remarks = prompt('Optional remarks:') || '';
      }
      if (action === 'delete' && !confirm('Delete this booking record permanently?')) {
        return;
      }
      const url = 'bookings_action.php?action=' + encodeURIComponent(action) + '&id=' + bookingId + '&remarks=' + encodeURIComponent(remarks);
      window.location.href = url;
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
