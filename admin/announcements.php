<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get filter and search values
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get statistics
$total_announcements = 0;
$active_count = 0;
$archived_count = 0;

$stats_query = "SELECT status, COUNT(*) as count FROM announcements GROUP BY status";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $total_announcements += $row['count'];
        if ($row['status'] == 'active') $active_count = $row['count'];
        elseif ($row['status'] == 'archived') $archived_count = $row['count'];
    }
}

// Build announcements query with filters
$announcements_query = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as posted_by_name
                        FROM announcements a
                        LEFT JOIN users u ON a.posted_by = u.user_id
                        WHERE 1=1";

if ($filter != 'all') {
    $announcements_query .= " AND a.status = '" . $conn->real_escape_string($filter) . "'";
}

if ($type_filter) {
    $announcements_query .= " AND a.announcement_type = '" . $conn->real_escape_string($type_filter) . "'";
}

if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $announcements_query .= " AND (a.title LIKE '%$search_escaped%' OR a.content LIKE '%$search_escaped%')";
}

$announcements_query .= " ORDER BY a.post_date DESC";
$announcements_result = $conn->query($announcements_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .announcements-container {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }

    .announcement-stat {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      border: 1px solid #e9ecef;
      text-align: center;
    }

    .announcement-stat .stat-number {
      font-size: 32px;
      font-weight: 700;
      color: #2c3e50;
      margin: 10px 0;
    }

    .announcement-stat .stat-label {
      font-size: 14px;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .announcement-stat.active .stat-number { color: #27ae60; }
    .announcement-stat.archived .stat-number { color: #95a5a6; }
    .announcement-stat.total .stat-number { color: #c17f59; }

    .action-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .create-btn {
      padding: 12px 24px;
      background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
      border: 2px solid rgba(193, 127, 89, 0.3);
      border-radius: 8px;
      color: #79491b;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
      transition: opacity 0.3s;
      text-decoration: none;
      display: inline-block;
    }

    .create-btn:hover {
      opacity: 0.9;
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

    .announcements-list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    .announcement-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
      border: 1px solid #e9ecef;
      transition: all 0.3s;
    }

    .announcement-card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px;
    }

    .announcement-title {
      font-size: 18px;
      font-weight: 600;
      color: #2c3e50;
      margin: 0 0 8px 0;
      flex: 1;
    }

    .announcement-badges {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .type-badge {
      padding: 6px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .type-general {
      background: #e3f2fd;
      color: #1976d2;
    }

    .type-maintenance {
      background: #fff3cd;
      color: #856404;
    }

    .type-event {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .type-urgent {
      background: #ffebee;
      color: #c62828;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-active {
      background: #d4edda;
      color: #155724;
    }

    .status-archived {
      background: #e2e3e5;
      color: #383d41;
    }

    .announcement-content {
      color: #555;
      margin-bottom: 12px;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .announcement-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: #7f8c8d;
      padding-top: 12px;
      border-top: 1px solid #e9ecef;
    }

    .announcement-meta-left {
      display: flex;
      gap: 16px;
    }

    .announcement-actions {
      display: flex;
      gap: 8px;
    }

    .btn-small {
      padding: 6px 12px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-edit {
      background: #3498db;
      color: white;
    }

    .btn-edit:hover {
      background: #2980b9;
    }

    .btn-delete {
      background: #e74c3c;
      color: white;
    }

    .btn-delete:hover {
      background: #c0392b;
    }

    .btn-archive {
      background: #95a5a6;
      color: white;
    }

    .btn-archive:hover {
      background: #7f8c8d;
    }

    .no-data {
      text-align: center;
      padding: 40px 20px;
      color: #7f8c8d;
      background: white;
      border-radius: 12px;
      border: 1px solid #e9ecef;
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
      overflow-y: auto;
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      padding: 30px;
      border-radius: 12px;
      width: 90%;
      max-width: 700px;
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

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-family: 'Roboto', sans-serif;
      font-size: 14px;
    }

    .form-group textarea {
      resize: vertical;
      min-height: 150px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
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
      .announcements-container {
        grid-template-columns: 1fr;
      }
      .action-bar {
        flex-direction: column;
        align-items: stretch;
      }
      .search-form {
        flex-direction: column;
      }
      .search-input {
        width: 100%;
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
      <li class="active"><a href="announcements.php" onclick="closeMenu()"><span class="text">Announcements</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Announcements Management</h1>
      <p class="breadcrumb">Home > Announcements</p>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <span>
          <?php 
            $success_msg = '';
            switch ($_GET['success']) {
              case 'created': $success_msg = 'Announcement created successfully!'; break;
              case 'updated': $success_msg = 'Announcement updated successfully!'; break;
              case 'deleted': $success_msg = 'Announcement deleted successfully!'; break;
              case 'archived': $success_msg = 'Announcement archived successfully!'; break;
              default: $success_msg = 'Action completed successfully!';
            }
            echo $success_msg;
          ?>
        </span>
        <button onclick="this.parentElement.style.display='none';" style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="announcements-container">
      <div class="announcement-stat total">
        <div class="stat-label">Total Announcements</div>
        <div class="stat-number"><?php echo $total_announcements; ?></div>
      </div>
      <div class="announcement-stat active">
        <div class="stat-label">Active</div>
        <div class="stat-number"><?php echo $active_count; ?></div>
      </div>
      <div class="announcement-stat archived">
        <div class="stat-label">Archived</div>
        <div class="stat-number"><?php echo $archived_count; ?></div>
      </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
      <button class="create-btn" onclick="openCreateModal()">➕ Create Announcement</button>
      <form method="GET" class="search-form">
        <input type="text" name="search" class="search-input" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>">
        <?php if ($filter != 'active'): ?>
          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <?php endif; ?>
        <button type="submit" class="search-btn">Search</button>
        <?php if ($search): ?>
          <a href="announcements.php" class="clear-search">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Filters -->
    <div style="margin-bottom: 20px;">
      <div class="filter-tabs">
        <a href="announcements.php" class="filter-tab <?php echo $filter == 'active' ? 'active' : ''; ?>">Active</a>
        <a href="announcements.php?filter=archived" class="filter-tab <?php echo $filter == 'archived' ? 'active' : ''; ?>">Archived</a>
        <a href="announcements.php?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">All Announcements</a>
      </div>
    </div>

    <!-- Announcements List -->
    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
      <div class="announcements-list">
        <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
          <div class="announcement-card">
            <div class="announcement-header">
              <div>
                <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
              </div>
              <div class="announcement-badges">
                <span class="type-badge type-<?php echo strtolower($announcement['announcement_type']); ?>">
                  <?php echo ucfirst($announcement['announcement_type']); ?>
                </span>
                <span class="status-badge status-<?php echo strtolower($announcement['status']); ?>">
                  <?php echo ucfirst($announcement['status']); ?>
                </span>
              </div>
            </div>
            <p class="announcement-content"><?php echo htmlspecialchars($announcement['content']); ?></p>
            <div class="announcement-meta">
              <div class="announcement-meta-left">
                <span>📅 <?php echo date('M d, Y', strtotime($announcement['post_date'])); ?></span>
                <span>✍️ <?php echo htmlspecialchars($announcement['posted_by_name']); ?></span>
                <?php if ($announcement['expiry_date']): ?>
                  <span>⏰ Expires: <?php echo date('M d, Y', strtotime($announcement['expiry_date'])); ?></span>
                <?php endif; ?>
              </div>
              <div class="announcement-actions">
                <button class="btn-small btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">Edit</button>
                <?php if ($announcement['status'] == 'active'): ?>
                  <button class="btn-small btn-archive" onclick="archiveAnnouncement(<?php echo $announcement['announcement_id']; ?>)">Archive</button>
                <?php endif; ?>
                <button class="btn-small btn-delete" onclick="deleteAnnouncement(<?php echo $announcement['announcement_id']; ?>)">Delete</button>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="no-data">
        <p style="font-size: 18px; margin-bottom: 10px;">📭 No announcements found</p>
        <p>There are currently no announcements matching your criteria.</p>
      </div>
    <?php endif; ?>
  </main>

  <!-- Create/Edit Modal -->
  <div id="announcementModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Create Announcement</h2>
        <button class="close-modal" onclick="closeAnnouncementModal()">&times;</button>
      </div>
      <form method="POST" action="announcements_action.php">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="announcement_id" id="announcementId">
        
        <div class="form-group">
          <label for="title">Title *</label>
          <input type="text" id="title" name="title" required placeholder="Enter announcement title...">
        </div>

        <div class="form-group">
          <label for="content">Content *</label>
          <textarea id="content" name="content" required placeholder="Enter announcement content..."></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="type">Announcement Type *</label>
            <select id="type" name="announcement_type" required>
              <option value="">Select Type</option>
              <option value="general">General</option>
              <option value="maintenance">Maintenance</option>
              <option value="event">Event</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div class="form-group">
            <label for="expiryDate">Expiry Date (Optional)</label>
            <input type="date" id="expiryDate" name="expiry_date">
          </div>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn-cancel" onclick="closeAnnouncementModal()">Cancel</button>
          <button type="submit" class="btn-submit">Save Announcement</button>
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

    function openCreateModal() {
      document.getElementById('formAction').value = 'create';
      document.getElementById('modalTitle').textContent = 'Create Announcement';
      document.getElementById('announcementId').value = '';
      document.getElementById('title').value = '';
      document.getElementById('content').value = '';
      document.getElementById('type').value = '';
      document.getElementById('expiryDate').value = '';
      document.getElementById('announcementModal').style.display = 'block';
    }

    function openEditModal(announcement) {
      document.getElementById('formAction').value = 'update';
      document.getElementById('modalTitle').textContent = 'Edit Announcement';
      document.getElementById('announcementId').value = announcement.announcement_id;
      document.getElementById('title').value = announcement.title;
      document.getElementById('content').value = announcement.content;
      document.getElementById('type').value = announcement.announcement_type;
      document.getElementById('expiryDate').value = announcement.expiry_date || '';
      document.getElementById('announcementModal').style.display = 'block';
    }

    function closeAnnouncementModal() {
      document.getElementById('announcementModal').style.display = 'none';
    }

    function deleteAnnouncement(id) {
      if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        window.location.href = 'announcements_action.php?action=delete&announcement_id=' + id;
      }
    }

    function archiveAnnouncement(id) {
      if (confirm('Are you sure you want to archive this announcement?')) {
        window.location.href = 'announcements_action.php?action=archive&announcement_id=' + id;
      }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('announcementModal');
      if (event.target == modal) modal.style.display = 'none';
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
