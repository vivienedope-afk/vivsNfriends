<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$active_count = 0;
$archived_count = 0;
$expiring_count = 0;

$stats_result = $conn->query("SELECT 
  SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
  SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_count,
  SUM(CASE WHEN status = 'active' AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring_count
  FROM announcements");
if ($stats_result) {
  $stats = $stats_result->fetch_assoc();
  $active_count = (int)($stats['active_count'] ?? 0);
  $archived_count = (int)($stats['archived_count'] ?? 0);
  $expiring_count = (int)($stats['expiring_count'] ?? 0);
}

$query = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS posted_by_name
      FROM announcements a
      LEFT JOIN users u ON a.posted_by = u.user_id
      WHERE 1=1";

if ($status_filter !== 'all') {
  $query .= " AND a.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($type_filter !== 'all') {
  $query .= " AND a.announcement_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if ($search !== '') {
  $s = $conn->real_escape_string($search);
  $query .= " AND (a.title LIKE '%$s%' OR a.content LIKE '%$s%')";
}

$query .= " ORDER BY a.post_date DESC, a.announcement_id DESC";
$announcements_result = $conn->query($query);

$show_modal = (isset($_GET['action']) && $_GET['action'] === 'new');
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .stat-title { font-size: 13px; color: #666; }
    .stat-value { font-size: 30px; font-weight: 700; color: #8b5a3c; margin-top: 6px; }
    .filters { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .filters form { display: grid; grid-template-columns: 1fr 1fr 2fr auto auto; gap: 10px; align-items: end; }
    .filters label { display: block; font-size: 12px; color: #555; margin-bottom: 6px; }
    .filters select, .filters input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
    .btn { border: none; border-radius: 6px; padding: 10px 14px; cursor: pointer; font-weight: 600; }
    .btn-primary { background: #c17f59; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-primary:hover { background: #8b5a3c; }
    .btn-secondary:hover { background: #5b636a; }
    .actions-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .table-wrap { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f5d18a; color: #5e3a24; padding: 12px; text-align: left; }
    td { padding: 12px; border-top: 1px solid #eee; vertical-align: top; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .type-general { background: #e2e3e5; color: #41464b; }
    .type-maintenance { background: #d1ecf1; color: #0c5460; }
    .type-event { background: #d4edda; color: #155724; }
    .type-urgent { background: #f8d7da; color: #721c24; }
    .status-active { background: #d4edda; color: #155724; }
    .status-archived { background: #e2e3e5; color: #41464b; }
    .row-actions a, .row-actions button { margin-right: 6px; font-size: 12px; }
    .flash { margin-bottom: 16px; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
    .flash.ok { background: #d4edda; color: #155724; }
    .flash.err { background: #f8d7da; color: #721c24; }
    .modal { display: none; position: fixed; inset: 0; z-index: 1200; background: rgba(0,0,0,0.5); }
    .modal-content { background: #fff; max-width: 680px; margin: 40px auto; border-radius: 10px; padding: 20px; }
    .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .form-grid .full { grid-column: 1 / -1; }
    .modal label { font-size: 12px; color: #555; display: block; margin-bottom: 6px; }
    .modal input, .modal select, .modal textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
    .modal textarea { min-height: 120px; resize: vertical; }
    @media (max-width: 900px) {
      .filters form { grid-template-columns: 1fr; }
      .form-grid { grid-template-columns: 1fr; }
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
      <li><a href="events.php" onclick="closeMenu()"><span class="text">Events</span></a></li>
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

    <?php if (isset($_GET['success'])): ?>
      <div class="flash ok">Announcement action completed successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="flash err">Failed to process announcement action.</div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-title">Active Announcements</div><div class="stat-value"><?php echo $active_count; ?></div></div>
      <div class="stat-card"><div class="stat-title">Archived Announcements</div><div class="stat-value"><?php echo $archived_count; ?></div></div>
      <div class="stat-card"><div class="stat-title">Expiring in 7 Days</div><div class="stat-value"><?php echo $expiring_count; ?></div></div>
    </div>

    <div class="filters">
      <form method="GET" action="announcements.php">
        <div>
          <label>Status</label>
          <select name="status">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
          </select>
        </div>
        <div>
          <label>Type</label>
          <select name="type">
            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>General</option>
            <option value="maintenance" <?php echo $type_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
            <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
            <option value="urgent" <?php echo $type_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
          </select>
        </div>
        <div>
          <label>Search</label>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title or content">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        <a href="announcements.php" class="btn btn-secondary">Reset</a>
      </form>
    </div>

    <div class="actions-row">
      <h2>Announcement List</h2>
      <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> New Announcement</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Posted</th>
            <th>Expiry</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
            <?php while ($row = $announcements_result->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                  <div style="font-size: 12px; color: #666; margin-top: 4px;"><?php echo nl2br(htmlspecialchars(substr($row['content'], 0, 120))); ?><?php echo strlen($row['content']) > 120 ? '...' : ''; ?></div>
                </td>
                <td><span class="badge type-<?php echo htmlspecialchars($row['announcement_type']); ?>"><?php echo ucfirst($row['announcement_type']); ?></span></td>
                <td><span class="badge status-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <td>
                  <?php echo date('M d, Y g:i A', strtotime($row['post_date'])); ?><br>
                  <small>By <?php echo htmlspecialchars($row['posted_by_name'] ?? 'System'); ?></small>
                </td>
                <td><?php echo $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : 'No expiry'; ?></td>
                <td class="row-actions">
                  <button class="btn btn-primary" type="button"
                    data-id="<?php echo (int)$row['announcement_id']; ?>"
                    data-title="<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>"
                    data-content="<?php echo htmlspecialchars($row['content'], ENT_QUOTES); ?>"
                    data-type="<?php echo htmlspecialchars($row['announcement_type']); ?>"
                    data-expiry="<?php echo htmlspecialchars($row['expiry_date'] ?? ''); ?>"
                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                    onclick="openEditModal(this)">Edit</button>

                  <?php if ($row['status'] === 'active'): ?>
                    <a class="btn btn-secondary" href="announcements_action.php?action=archive&id=<?php echo (int)$row['announcement_id']; ?>" onclick="return confirm('Archive this announcement?');">Archive</a>
                  <?php else: ?>
                    <a class="btn btn-secondary" href="announcements_action.php?action=activate&id=<?php echo (int)$row['announcement_id']; ?>" onclick="return confirm('Set this announcement active?');">Activate</a>
                  <?php endif; ?>

                  <a class="btn btn-secondary" href="announcements_action.php?action=delete&id=<?php echo (int)$row['announcement_id']; ?>" onclick="return confirm('Delete this announcement permanently?');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align:center; color:#777;">No announcements found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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

  <div class="modal" id="createModal">
    <div class="modal-content">
      <div class="modal-head">
        <h3>Create Announcement</h3>
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Close</button>
      </div>
      <form method="POST" action="announcements_action.php">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
          <div class="full">
            <label>Title</label>
            <input type="text" name="title" maxlength="255" required>
          </div>
          <div>
            <label>Type</label>
            <select name="announcement_type" required>
              <option value="general">General</option>
              <option value="maintenance">Maintenance</option>
              <option value="event">Event</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label>Expiry Date</label>
            <input type="date" name="expiry_date">
          </div>
          <div class="full">
            <label>Content</label>
            <textarea name="content" required></textarea>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Publish Announcement</button>
      </form>
    </div>
  </div>

  <div class="modal" id="editModal">
    <div class="modal-content">
      <div class="modal-head">
        <h3>Edit Announcement</h3>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Close</button>
      </div>
      <form method="POST" action="announcements_action.php">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="announcement_id" id="edit_announcement_id">
        <div class="form-grid">
          <div class="full">
            <label>Title</label>
            <input type="text" name="title" id="edit_title" maxlength="255" required>
          </div>
          <div>
            <label>Type</label>
            <select name="announcement_type" id="edit_type" required>
              <option value="general">General</option>
              <option value="maintenance">Maintenance</option>
              <option value="event">Event</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label>Expiry Date</label>
            <input type="date" name="expiry_date" id="edit_expiry">
          </div>
          <div>
            <label>Status</label>
            <select name="status" id="edit_status" required>
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
          </div>
          <div class="full">
            <label>Content</label>
            <textarea name="content" id="edit_content" required></textarea>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Save Changes</button>
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
      document.getElementById('createModal').style.display = 'block';
    }

    function closeCreateModal() {
      document.getElementById('createModal').style.display = 'none';
    }

    function openEditModal(button) {
      document.getElementById('edit_announcement_id').value = button.getAttribute('data-id');
      document.getElementById('edit_title').value = button.getAttribute('data-title');
      document.getElementById('edit_content').value = button.getAttribute('data-content');
      document.getElementById('edit_type').value = button.getAttribute('data-type');
      document.getElementById('edit_expiry').value = button.getAttribute('data-expiry');
      document.getElementById('edit_status').value = button.getAttribute('data-status');
      document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    };

    <?php if ($show_modal): ?>
      openCreateModal();
    <?php endif; ?>
  </script>
</body>
</html>
<?php $conn->close(); ?>
