<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get filter and search values
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$facility_filter = isset($_GET['facility']) ? $_GET['facility'] : '';

// Get statistics
$total_bookings = 0;
$pending_bookings_count = 0;
$approved_bookings_count = 0;
$rejected_bookings_count = 0;

$stats_query = "SELECT status, COUNT(*) as count FROM facility_bookings GROUP BY status";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $total_bookings += $row['count'];
        if ($row['status'] == 'pending') $pending_bookings_count = $row['count'];
        elseif ($row['status'] == 'approved') $approved_bookings_count = $row['count'];
        elseif ($row['status'] == 'rejected') $rejected_bookings_count = $row['count'];
    }
}

// Build bookings query with filters
$bookings_query = "SELECT fb.*, h.unit_number, CONCAT(u.first_name, ' ', u.last_name) as requester_name,
                   CONCAT(au.first_name, ' ', au.last_name) as approved_by_name
                   FROM facility_bookings fb
                   INNER JOIN households h ON fb.household_id = h.household_id
                   INNER JOIN household_members hm ON h.household_id = hm.household_id AND hm.is_primary = 1
                   INNER JOIN users u ON hm.user_id = u.user_id
                   LEFT JOIN users au ON fb.approved_by = au.user_id
                   WHERE 1=1";

if ($filter != 'all') {
    $bookings_query .= " AND fb.status = '" . $conn->real_escape_string($filter) . "'";
}

if ($facility_filter) {
    $bookings_query .= " AND fb.facility_name = '" . $conn->real_escape_string($facility_filter) . "'";
}

if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $bookings_query .= " AND (h.unit_number LIKE '%$search_escaped%' 
                              OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search_escaped%'
                              OR fb.facility_name LIKE '%$search_escaped%')";
}

$bookings_query .= " ORDER BY fb.booking_date DESC, fb.start_time DESC";
$bookings_result = $conn->query($bookings_query);

// Get unique facilities for filter
$facilities_query = "SELECT DISTINCT facility_name FROM facility_bookings ORDER BY facility_name";
$facilities_result = $conn->query($facilities_query);
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
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .bookings-container {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }

    .booking-stat {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      border: 1px solid #e9ecef;
      text-align: center;
    }

    .booking-stat .stat-number {
      font-size: 32px;
      font-weight: 700;
      color: #2c3e50;
      margin: 10px 0;
    }

    .booking-stat .stat-label {
      font-size: 14px;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .booking-stat.pending .stat-number { color: #f39c12; }
    .booking-stat.approved .stat-number { color: #27ae60; }
    .booking-stat.rejected .stat-number { color: #e74c3c; }
    .booking-stat.total .stat-number { color: #c17f59; }

    .filters-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .filter-tabs {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .filter-tab {
      padding: 12px 20px;
      background: #f8f9fa;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      text-decoration: none;
      color: #555;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.3s;
      cursor: pointer;
    }

    .filter-tab:hover {
      background: #e9ecef;
      border-color: #c17f59;
    }

    .filter-tab.active {
      background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
      border-color: #c17f59;
      color: #79491b;
      font-weight: 600;
    }

    .search-form {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .search-input {
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      width: 250px;
      transition: border-color 0.3s;
    }

    .search-input:focus {
      outline: none;
      border-color: #d4a574;
    }

    .search-btn, .clear-search {
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

    .search-btn:hover {
      opacity: 0.9;
    }

    .clear-search {
      background: white;
      border: 2px solid #e0e0e0;
      color: #666;
    }

    .clear-search:hover {
      border-color: #c17f59;
      color: #79491b;
    }

    .bookings-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    }

    .bookings-table thead {
      background: #f8f9fa;
    }

    .bookings-table th {
      padding: 16px;
      text-align: left;
      font-weight: 600;
      color: #79491b;
      border-bottom: 2px solid #e9ecef;
      font-size: 14px;
    }

    .bookings-table td {
      padding: 16px;
      border-bottom: 1px solid #e9ecef;
    }

    .bookings-table tbody tr:hover {
      background: #f8f9fa;
    }

    .status-badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-pending {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-approved {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-rejected {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .status-cancelled {
      background: #e2e3e5;
      color: #383d41;
      border: 1px solid #d6d8db;
    }

    .action-buttons {
      display: flex;
      gap: 12px;
    }

    .btn-small {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-approve {
      background: #27ae60;
      color: white;
    }

    .btn-approve:hover {
      background: #229954;
    }

    .btn-reject {
      background: #e74c3c;
      color: white;
    }

    .btn-reject:hover {
      background: #c0392b;
    }

    .btn-view {
      background: #3498db;
      color: white;
    }

    .btn-view:hover {
      background: #2980b9;
    }

    .no-data {
      text-align: center;
      padding: 40px 20px;
      color: #7f8c8d;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      padding: 30px;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #e9ecef;
      padding-bottom: 16px;
    }

    .modal-header h2 {
      margin: 0;
      color: #2c3e50;
    }

    .close-modal {
      font-size: 28px;
      font-weight: bold;
      color: #aaa;
      cursor: pointer;
      border: none;
      background: none;
    }

    .close-modal:hover {
      color: #000;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #8b572a;
    }

    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-family: 'Roboto', sans-serif;
      font-size: 14px;
      resize: vertical;
      min-height: 100px;
    }

    .modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 24px;
    }

    .btn-submit {
      padding: 12px 24px;
      background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
      border: none;
      border-radius: 8px;
      color: #79491b;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.3s;
    }

    .btn-submit:hover {
      opacity: 0.9;
    }

    .btn-cancel {
      padding: 12px 24px;
      background: white;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      color: #666;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-cancel:hover {
      border-color: #c17f59;
      color: #79491b;
    }

    .alert {
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
      .bookings-container {
        grid-template-columns: repeat(2, 1fr);
      }
      .filters-section {
        flex-direction: column;
        align-items: stretch;
      }
      .search-form {
        flex-direction: column;
      }
      .search-input {
        width: 100%;
      }
      .filter-tabs {
        justify-content: flex-start;
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
      <li class="active"><a href="bookings.php" onclick="closeMenu()"><span class="text">Facility Bookings</span></a></li>
      <li><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li><a href="email_notifayer.php" onclick="closeMenu()"><span class="text">Email Notifayer</span></a></li>
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

    <!-- Alert Messages -->
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <span>
          <?php 
            $success_msg = '';
            switch ($_GET['success']) {
              case 'approved': $success_msg = 'Booking approved successfully!'; break;
              case 'rejected': $success_msg = 'Booking rejected successfully!'; break;
              default: $success_msg = 'Action completed successfully!';
            }
            echo $success_msg;
          ?>
        </span>
        <button onclick="this.parentElement.style.display='none';" style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="bookings-container">
      <div class="booking-stat total">
        <div class="stat-label">Total Bookings</div>
        <div class="stat-number"><?php echo $total_bookings; ?></div>
      </div>
      <div class="booking-stat pending">
        <div class="stat-label">Pending</div>
        <div class="stat-number"><?php echo $pending_bookings_count; ?></div>
      </div>
      <div class="booking-stat approved">
        <div class="stat-label">Approved</div>
        <div class="stat-number"><?php echo $approved_bookings_count; ?></div>
      </div>
      <div class="booking-stat rejected">
        <div class="stat-label">Rejected</div>
        <div class="stat-number"><?php echo $rejected_bookings_count; ?></div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="filter-tabs">
        <a href="bookings.php" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">All Bookings</a>
        <a href="bookings.php?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="bookings.php?filter=approved" class="filter-tab <?php echo $filter == 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="bookings.php?filter=rejected" class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
      </div>
      <form method="GET" class="search-form">
        <input type="text" name="search" class="search-input" placeholder="Search by unit, name, or facility..." value="<?php echo htmlspecialchars($search); ?>">
        <?php if ($facility_filter): ?>
          <input type="hidden" name="facility" value="<?php echo htmlspecialchars($facility_filter); ?>">
        <?php endif; ?>
        <?php if ($filter != 'all'): ?>
          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <?php endif; ?>
        <button type="submit" class="search-btn">Search</button>
        <?php if ($search || $facility_filter): ?>
          <a href="bookings.php" class="clear-search">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Bookings Table -->
    <div class="card">
      <div class="card-header">
        <h2>📋 Facility Booking Requests</h2>
      </div>
      <div class="card-content" style="padding: 0;">
        <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
          <table class="bookings-table">
            <thead>
              <tr>
                <th>Unit</th>
                <th>Requester</th>
                <th>Facility</th>
                <th>Date & Time</th>
                <th>Status</th>
                <th>Fee</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($booking['unit_number']); ?></strong></td>
                  <td><?php echo htmlspecialchars($booking['requester_name']); ?></td>
                  <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                  <td>
                    <div><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
                    <small style="color: #7f8c8d;">
                      <?php echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])); ?>
                    </small>
                  </td>
                  <td>
                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                      <?php echo ucfirst($booking['status']); ?>
                    </span>
                  </td>
                  <td>₱<?php echo number_format($booking['booking_fee'], 2); ?></td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn-small btn-view" onclick="viewBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)">View</button>
                      <?php if ($booking['status'] == 'pending'): ?>
                        <button class="btn-small btn-approve" onclick="openApproveModal(<?php echo $booking['booking_id']; ?>)">Approve</button>
                        <button class="btn-small btn-reject" onclick="openRejectModal(<?php echo $booking['booking_id']; ?>)">Reject</button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-data">
            <p style="font-size: 18px; margin-bottom: 10px;">📭 No bookings found</p>
            <p>There are currently no facility bookings matching your criteria.</p>
          </div>
        <?php endif; ?>
      </div>
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

    function viewBooking(booking) {
      const modal = document.getElementById('viewModal');
      const content = document.getElementById('viewContent');
      
      const html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Unit</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">Unit Details</p>
          </div>
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Requester</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">${booking.requester_name}</p>
          </div>
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Facility</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">${booking.facility_name}</p>
          </div>
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Date</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">${new Date(booking.booking_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}</p>
          </div>
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Time</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">${new Date('2000-01-01 ' + booking.start_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})} - ${new Date('2000-01-01 ' + booking.end_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})}</p>
          </div>
          <div>
            <p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 4px;">Status</p>
            <p style="font-size: 16px; font-weight: 600; color: #2c3e50;">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</p>
          </div>
        </div>
        ${booking.purpose ? `<div style="margin-bottom: 16px;"><p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 8px;">Purpose</p><p style="color: #555;">${booking.purpose}</p></div>` : ''}
        ${booking.remarks ? `<div><p style="font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 8px;">Remarks</p><p style="color: #555;">${booking.remarks}</p></div>` : ''}
      `;
      
      content.innerHTML = html;
      modal.style.display = 'block';
    }

    function closeViewModal() {
      document.getElementById('viewModal').style.display = 'none';
    }

    function openApproveModal(bookingId) {
      document.getElementById('approveBookingId').value = bookingId;
      document.getElementById('approveModal').style.display = 'block';
    }

    function closeApproveModal() {
      document.getElementById('approveModal').style.display = 'none';
      document.getElementById('approveBookingId').value = '';
      document.getElementById('approveFee').value = '0';
      document.getElementById('approveRemarks').value = '';
    }

    function openRejectModal(bookingId) {
      document.getElementById('rejectBookingId').value = bookingId;
      document.getElementById('rejectModal').style.display = 'block';
    }

    function closeRejectModal() {
      document.getElementById('rejectModal').style.display = 'none';
      document.getElementById('rejectBookingId').value = '';
      document.getElementById('rejectRemarks').value = '';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
      const viewModal = document.getElementById('viewModal');
      const approveModal = document.getElementById('approveModal');
      const rejectModal = document.getElementById('rejectModal');
      
      if (event.target == viewModal) viewModal.style.display = 'none';
      if (event.target == approveModal) approveModal.style.display = 'none';
      if (event.target == rejectModal) rejectModal.style.display = 'none';
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
