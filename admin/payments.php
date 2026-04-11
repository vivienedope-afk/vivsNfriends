<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '2026';

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT md.dues_id) as total_dues,
    COALESCE(SUM(CASE WHEN md.status = 'paid' THEN md.amount ELSE 0 END), 0) as total_collected,
    COALESCE(SUM(CASE WHEN md.status = 'unpaid' THEN md.amount ELSE 0 END), 0) as total_pending,
    COALESCE(SUM(CASE WHEN md.status = 'overdue' THEN md.amount ELSE 0 END), 0) as total_overdue,
    COUNT(DISTINCT CASE WHEN md.status = 'unpaid' THEN md.dues_id END) as count_unpaid,
    COUNT(DISTINCT CASE WHEN md.status = 'overdue' THEN md.dues_id END) as count_overdue
FROM monthly_dues md";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Build query for dues list
$dues_query = "SELECT md.*, h.unit_number, h.block_number, h.lot_number,
    CONCAT(u.first_name, ' ', u.last_name) as owner_name,
    p.payment_id, p.payment_date, p.amount_paid, p.payment_method, p.reference_number, p.verified_at
FROM monthly_dues md
INNER JOIN households h ON md.household_id = h.household_id
LEFT JOIN users u ON h.owner_id = u.user_id
LEFT JOIN payments p ON md.dues_id = p.dues_id
WHERE 1=1";

if ($status_filter != 'all') {
    $dues_query .= " AND md.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $dues_query .= " AND (h.unit_number LIKE '%$search_escaped%' OR u.first_name LIKE '%$search_escaped%' OR u.last_name LIKE '%$search_escaped%')";
}

if (!empty($month_filter)) {
    $dues_query .= " AND md.due_month = '" . $conn->real_escape_string($month_filter) . "'";
}

if (!empty($year_filter)) {
    $dues_query .= " AND md.due_year = " . (int)$year_filter;
}

$dues_query .= " ORDER BY md.due_year DESC, md.due_date DESC, h.unit_number ASC";
$dues_result = $conn->query($dues_query);
$dues_list = [];
if ($dues_result) {
    while ($row = $dues_result->fetch_assoc()) {
        $dues_list[] = $row;
    }
}

// Auto-update overdue status
$update_overdue = "UPDATE monthly_dues SET status = 'overdue' WHERE status = 'unpaid' AND due_date < CURDATE()";
$conn->query($update_overdue);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments & Dues - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .stat-card .stat-value { font-size: 32px; font-weight: 700; color: #c17f59; margin: 10px 0; }
    .stat-card .stat-label { color: #666; font-size: 14px; }
    .stat-card.collected { border-bottom: 4px solid #28a745; }
    .stat-card.collected .stat-value { color: #28a745; }
    .stat-card.pending { border-bottom: 4px solid #faa500; }
    .stat-card.pending .stat-value { color: #faa500; }
    .stat-card.overdue { border-bottom: 4px solid #dc3545; }
    .stat-card.overdue .stat-value { color: #dc3545; }
    .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .filters form { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
    .filter-group { flex: 1; min-width: 180px; }
    .filter-group label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 500; color: #333; }
    .filter-group input, .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    .filter-btn { padding: 10px 20px; background: #c17f59; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; height: 42px; }
    .filter-btn:hover { background: #8b5a3c; }
    .reset-btn { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; height: 42px; text-decoration: none; display: inline-flex; align-items: center; }
    .reset-btn:hover { background: #5a6268; }
    .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .btn-primary { padding: 12px 24px; background: #c17f59; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary:hover { background: #8b5a3c; }
    .dues-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .dues-table table { width: 100%; border-collapse: collapse; }
    .dues-table th { background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%); padding: 14px 12px; text-align: left; font-weight: 600; color: #79491b; border-bottom: 2px solid #c17f59; font-size: 13px; }
    .dues-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
    .dues-table tr:hover { background: #f8f9fa; }
    .badge { padding: 5px 10px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-block; }
    .badge.paid { background: #d4edda; color: #155724; }
    .badge.unpaid { background: #fff3cd; color: #856404; }
    .badge.overdue { background: #f8d7da; color: #721c24; }
    .badge.verified { background: #d1ecf1; color: #0c5460; }
    .badge.pending-verify { background: #f8d7da; color: #721c24; }
    .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; white-space: nowrap; transition: all 0.2s ease; }
    .btn-record { background: #28a745; color: white; }
    .btn-record:hover { background: #218838; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .btn-view { background: #17a2b8; color: white; }
    .btn-view:hover { background: #138496; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .btn-verify { background: #faa500; color: white; }
    .btn-verify:hover { background: #ff8c00; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .btn-edit { background: #007bff; color: white; }
    .btn-edit:hover { background: #0056b3; }
    .btn-archive { background: #6c757d; color: white; }
    .btn-archive:hover { background: #5a6268; }
    .btn-delete { background: #dc3545; color: white; }
    .btn-delete:hover { background: #c82333; }
    .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
    .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
    
    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
    .modal-content { background: white; margin: 50px auto; padding: 30px; width: 90%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .modal-header h2 { margin: 0; color: #333; font-size: 24px; }
    .close { font-size: 32px; font-weight: 700; color: #999; cursor: pointer; line-height: 1; }
    .close:hover { color: #333; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .btn-submit { width: 100%; padding: 14px; background: #c17f59; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px; }
    .btn-submit:hover { background: #8b5a3c; }
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee; }
    .info-row:last-child { border-bottom: none; }
    .info-label { font-weight: 600; color: #666; }
    .info-value { color: #333; }
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
      <li class="active"><a href="payments.php" onclick="closeMenu()"><span class="text">Payments & Dues</span></a></li>
      <li><a href="bookings.php" onclick="closeMenu()"><span class="text">Facility Bookings</span></a></li>
      <li><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li><a href="events.php" onclick="closeMenu()"><span class="text">Events</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Payments & Dues Management</h1>
      <p class="breadcrumb">Home > Payments & Dues</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php
          if ($_GET['success'] == 'dues_added') echo 'Monthly dues added successfully.';
          elseif ($_GET['success'] == 'payment_recorded') echo 'Payment recorded successfully.';
          elseif ($_GET['success'] == 'dues_updated') echo 'Dues updated successfully.';
          elseif ($_GET['success'] == 'payment_verified') echo 'Payment verified successfully.';
          elseif ($_GET['success'] == 'payment_archived') echo 'Payment archived successfully.';
          elseif ($_GET['success'] == 'payment_deleted') echo 'Payment deleted successfully.';
          elseif ($_GET['success'] == 'dues_deleted') echo 'Dues record deleted successfully.';
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-error">
        <?php
          if ($_GET['error'] == 'duplicate') echo 'This dues period already exists for the selected household.';
          elseif ($_GET['error'] == 'dues_has_payment') echo 'Cannot delete dues with payment records. Delete payment first.';
          elseif ($_GET['error'] == 'payment_not_found') echo 'Payment record not found.';
          elseif ($_GET['error'] == 'delete_failed') echo 'Delete action failed.';
          elseif ($_GET['error'] == 'archive_failed') echo 'Archive action failed.';
          else echo 'An action failed. Please try again.';
        ?>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card collected">
        <div class="stat-label"><i class="fas fa-check-circle"></i> Total Collected</div>
        <div class="stat-value">₱<?php echo number_format($stats['total_collected'] ?? 0, 2); ?></div>
        <div class="stat-label"><?php echo $stats['total_dues'] ?? 0; ?> dues records</div>
      </div>
      <div class="stat-card pending">
        <div class="stat-label"><i class="fas fa-clock"></i> Pending Payment</div>
        <div class="stat-value">₱<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></div>
        <div class="stat-label"><?php echo $stats['count_unpaid'] ?? 0; ?> unpaid</div>
      </div>
      <div class="stat-card overdue">
        <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Overdue</div>
        <div class="stat-value">₱<?php echo number_format($stats['total_overdue'] ?? 0, 2); ?></div>
        <div class="stat-label"><?php echo $stats['count_overdue'] ?? 0; ?> overdue</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <form method="GET" action="payments.php">
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="unpaid" <?php echo $status_filter == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Month</label>
          <select name="month">
            <option value="">All Months</option>
            <?php
            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            foreach ($months as $month) {
                $selected = ($month_filter == $month) ? 'selected' : '';
                echo "<option value='$month' $selected>$month</option>";
            }
            ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Year</label>
          <select name="year">
            <?php
            $current_year = 2026;
            for ($y = $current_year; $y >= 2020; $y--) {
                $selected = ($year_filter == $y) ? 'selected' : '';
                echo "<option value='$y' $selected>$y</option>";
            }
            ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Search Unit/Owner</label>
          <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
        <a href="payments.php" class="reset-btn">Reset</a>
      </form>
    </div>

    <!-- Actions Bar -->
    <div class="actions-bar">
      <h2>Monthly Dues List</h2>
      <button class="btn-primary" onclick="openAddDuesModal()">
        <i class="fas fa-plus"></i> Add Monthly Dues
      </button>
    </div>

    <!-- Dues Table -->
    <div class="dues-table">
      <?php if (count($dues_list) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Unit</th>
              <th>Owner</th>
              <th>Due Period</th>
              <th>Due Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment Info</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dues_list as $due): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($due['unit_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($due['owner_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($due['due_month']) . ' ' . $due['due_year']; ?></td>
                <td><?php echo date('M d, Y', strtotime($due['due_date'])); ?></td>
                <td><strong>₱<?php echo number_format($due['amount'], 2); ?></strong></td>
                <td>
                  <?php
                  $status_class = strtolower($due['status']);
                  echo "<span class='badge $status_class'>" . ucfirst($due['status']) . "</span>";
                  ?>
                </td>
                <td>
                  <?php if ($due['payment_id']): ?>
                    <div style="font-size: 13px;">
                      <div><strong><?php echo strtoupper($due['payment_method']); ?></strong></div>
                      <div style="color: #666;"><?php echo htmlspecialchars($due['reference_number'] ?? 'N/A'); ?></div>
                      <div style="color: #666;"><?php echo date('M d, Y', strtotime($due['payment_date'])); ?></div>
                      <?php if ($due['verified_at']): ?>
                        <span class="badge verified" style="font-size: 10px;">Verified</span>
                      <?php else: ?>
                        <span class="badge pending-verify" style="font-size: 10px;">Pending Verification</span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="color: #999; font-size: 13px;">No payment yet</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($due['status'] == 'unpaid' || $due['status'] == 'overdue'): ?>
                    <button class="action-btn btn-record" onclick="openRecordPaymentModal(<?php echo $due['dues_id']; ?>, '<?php echo htmlspecialchars($due['unit_number']); ?>', <?php echo $due['amount']; ?>, '<?php echo $due['due_month'] . ' ' . $due['due_year']; ?>')">
                      <i class="fas fa-money-bill"></i> Record Payment
                    </button>
                  <?php elseif ($due['payment_id'] && !$due['verified_at']): ?>
                    <button class="action-btn btn-verify" onclick="verifyPayment(<?php echo $due['payment_id']; ?>)">
                      <i class="fas fa-check"></i> Verify
                    </button>
                  <?php endif; ?>
                  <?php if ($due['payment_id']): ?>
                    <button class="action-btn btn-view" onclick="viewPaymentDetails(<?php echo $due['payment_id']; ?>)">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <button class="action-btn btn-archive" onclick="archivePayment(<?php echo $due['payment_id']; ?>)">
                      <i class="fas fa-box-archive"></i> Archive
                    </button>
                    <button class="action-btn btn-delete" onclick="deletePayment(<?php echo $due['payment_id']; ?>)">
                      <i class="fas fa-trash"></i> Delete Payment
                    </button>
                  <?php endif; ?>
                  <button class="action-btn btn-edit" onclick="editDues(<?php echo $due['dues_id']; ?>)">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <?php if (!$due['payment_id']): ?>
                    <button class="action-btn btn-delete" onclick="deleteDues(<?php echo $due['dues_id']; ?>)">
                      <i class="fas fa-trash"></i> Delete Dues
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-receipt"></i>
          <h3>No dues records found</h3>
          <p>Try adjusting your filters or add new monthly dues.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Add Dues Modal -->
  <div id="addDuesModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add Monthly Dues</h2>
        <span class="close" onclick="closeAddDuesModal()">&times;</span>
      </div>
      <form id="addDuesForm" method="POST" action="payments_action.php">
        <input type="hidden" name="action" value="add_dues">
        <div class="form-group">
          <label>Household/Unit *</label>
          <select name="household_id" required>
            <option value="">Select Unit</option>
            <?php
            $households_query = "SELECT h.household_id, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as owner_name 
                                 FROM households h 
                                 LEFT JOIN users u ON h.owner_id = u.user_id
                                 WHERE h.status = 'occupied'
                                 ORDER BY h.unit_number ASC";
            $households_result = $conn->query($households_query);
            if ($households_result) {
                while ($household = $households_result->fetch_assoc()) {
                    echo "<option value='{$household['household_id']}'>{$household['unit_number']} - {$household['owner_name']}</option>";
                }
            }
            ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Month *</label>
            <select name="due_month" required>
              <?php
              foreach ($months as $month) {
                  echo "<option value='$month'>$month</option>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>Year *</label>
            <select name="due_year" required>
              <?php
              $current_year = 2026;
              for ($y = $current_year; $y <= $current_year + 1; $y++) {
                  echo "<option value='$y'>$y</option>";
              }
              ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Amount (₱) *</label>
            <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
          </div>
          <div class="form-group">
            <label>Due Date *</label>
            <input type="date" name="due_date" required>
          </div>
        </div>
        <button type="submit" class="btn-submit">Add Dues Record</button>
      </form>
    </div>
  </div>

  <!-- Record Payment Modal -->
  <div id="recordPaymentModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Record Payment</h2>
        <span class="close" onclick="closeRecordPaymentModal()">&times;</span>
      </div>
      <form id="recordPaymentForm" method="POST" action="payments_action.php">
        <input type="hidden" name="action" value="record_payment">
        <input type="hidden" name="dues_id" id="payment_dues_id">
        <div class="info-row">
          <span class="info-label">Unit:</span>
          <span class="info-value" id="payment_unit"></span>
        </div>
        <div class="info-row">
          <span class="info-label">Due Period:</span>
          <span class="info-value" id="payment_period"></span>
        </div>
        <div class="info-row">
          <span class="info-label">Amount Due:</span>
          <span class="info-value" id="payment_amount_due"></span>
        </div>
        <hr style="margin: 20px 0;">
        <div class="form-row">
          <div class="form-group">
            <label>Payment Date *</label>
            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="form-group">
            <label>Amount Paid (₱) *</label>
            <input type="number" name="amount_paid" id="amount_paid_input" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label>Payment Method *</label>
          <select name="payment_method" required>
            <option value="">Select Method</option>
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="gcash">GCash</option>
            <option value="check">Check</option>
          </select>
        </div>
        <div class="form-group">
          <label>Reference Number</label>
          <input type="text" name="reference_number" placeholder="Transaction/Check number">
        </div>
        <div class="form-group">
          <label>Remarks</label>
          <textarea name="remarks" placeholder="Optional notes..."></textarea>
        </div>
        <button type="submit" class="btn-submit">Record Payment</button>
      </form>
    </div>
  </div>

  <script src="../script.js"></script>
  <script>
    function openAddDuesModal() {
      document.getElementById('addDuesModal').style.display = 'block';
    }
    
    function closeAddDuesModal() {
      document.getElementById('addDuesModal').style.display = 'none';
    }
    
    function openRecordPaymentModal(duesId, unit, amount, period) {
      document.getElementById('payment_dues_id').value = duesId;
      document.getElementById('payment_unit').textContent = unit;
      document.getElementById('payment_period').textContent = period;
      document.getElementById('payment_amount_due').textContent = '₱' + parseFloat(amount).toFixed(2);
      document.getElementById('amount_paid_input').value = parseFloat(amount).toFixed(2);
      document.getElementById('recordPaymentModal').style.display = 'block';
    }
    
    function closeRecordPaymentModal() {
      document.getElementById('recordPaymentModal').style.display = 'none';
    }
    
    function verifyPayment(paymentId) {
      if (confirm('Verify this payment as received and authentic?')) {
        window.location.href = 'payments_action.php?action=verify_payment&payment_id=' + paymentId;
      }
    }
    
    function viewPaymentDetails(paymentId) {
      window.location.href = 'view_payment.php?id=' + paymentId;
    }
    
    function editDues(duesId) {
      window.location.href = 'edit_dues.php?id=' + duesId;
    }

    function archivePayment(paymentId) {
      if (confirm('Archive this payment record?')) {
        window.location.href = 'payments_action.php?action=archive_payment&payment_id=' + paymentId;
      }
    }

    function deletePayment(paymentId) {
      if (confirm('Delete this payment record permanently? This will reset the dues status to unpaid.')) {
        window.location.href = 'payments_action.php?action=delete_payment&payment_id=' + paymentId;
      }
    }

    function deleteDues(duesId) {
      if (confirm('Delete this dues record permanently?')) {
        window.location.href = 'payments_action.php?action=delete_dues&dues_id=' + duesId;
      }
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
