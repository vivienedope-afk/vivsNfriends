<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$query = "SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS organizer_name
          FROM events e
          LEFT JOIN users u ON e.organizer_id = u.user_id
          WHERE 1=1";

if ($status_filter !== 'all') {
    $query .= " AND e.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $query .= " AND (e.event_name LIKE '%$s%' OR e.event_description LIKE '%$s%' OR e.location LIKE '%$s%')";
}

$query .= " ORDER BY e.event_date ASC, e.event_time ASC";
$events_result = $conn->query($query);

$stats_query = "SELECT
    COUNT(*) AS total_events,
    SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) AS upcoming_count,
    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
  FROM events";
$stats_result = $conn->query($stats_query);
$stats = [
  'total_events' => 0,
  'upcoming_count' => 0,
  'ongoing_count' => 0,
  'completed_count' => 0,
  'cancelled_count' => 0
];
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Events Management - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:16px; }
    .stat-card { background:#fff; border-radius:10px; padding:14px; box-shadow:0 2px 6px rgba(0,0,0,.08); }
    .stat-title { font-size:12px; color:#666; }
    .stat-value { font-size:24px; font-weight:700; color:#8b5a3c; }
    .filters { background:#fff; border-radius:10px; padding:14px; margin-bottom:14px; box-shadow:0 2px 6px rgba(0,0,0,.08); }
    .filters form { display:grid; grid-template-columns: 1fr 2fr auto auto; gap:10px; align-items:end; }
    .filters label { display:block; font-size:12px; color:#555; margin-bottom:6px; }
    .filters select, .filters input { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    .btn { border:none; border-radius:6px; padding:10px 12px; cursor:pointer; font-weight:600; }
    .btn-primary { background:#c17f59; color:#fff; }
    .btn-secondary { background:#6c757d; color:#fff; text-decoration:none; display:inline-flex; align-items:center; }
    .btn-success { background:#28a745; color:#fff; }
    .btn-danger { background:#dc3545; color:#fff; }
    .table-wrap { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,.08); }
    table { width:100%; border-collapse:collapse; }
    th { background:#f5d18a; color:#5e3a24; text-align:left; padding:12px; }
    td { padding:12px; border-top:1px solid #eee; vertical-align:top; }
    .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .upcoming { background:#d1ecf1; color:#0c5460; }
    .ongoing { background:#fff3cd; color:#856404; }
    .completed { background:#d4edda; color:#155724; }
    .cancelled { background:#f8d7da; color:#721c24; }
    .row-actions { white-space:nowrap; }
    .flash { margin-bottom:12px; padding:10px 12px; border-radius:6px; font-size:13px; }
    .flash.ok { background:#d4edda; color:#155724; }
    .flash.err { background:#f8d7da; color:#721c24; }
    .modal { display:none; position:fixed; inset:0; z-index:1200; background:rgba(0,0,0,.55); }
    .modal-content { background:#fff; max-width:760px; margin:40px auto; border-radius:10px; padding:18px; }
    .modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .form-grid .full { grid-column:1 / -1; }
    .modal label { font-size:12px; color:#555; display:block; margin-bottom:6px; }
    .modal input, .modal select, .modal textarea { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    .modal textarea { min-height:100px; resize:vertical; }
    @media (max-width: 980px) {
      .filters form, .form-grid { grid-template-columns:1fr; }
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
      <li class="active"><a href="events.php" onclick="closeMenu()"><span class="text">Events</span></a></li>
      <li><a href="reports.php" onclick="closeMenu()"><span class="text">Reports</span></a></li>
      <li><a href="../auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Events Management</h1>
      <p class="breadcrumb">Home > Events</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="flash ok">Event action completed successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="flash err">Event action failed. Please check your input.</div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-title">Total</div><div class="stat-value"><?php echo (int)$stats['total_events']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Upcoming</div><div class="stat-value"><?php echo (int)$stats['upcoming_count']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Ongoing</div><div class="stat-value"><?php echo (int)$stats['ongoing_count']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Completed</div><div class="stat-value"><?php echo (int)$stats['completed_count']; ?></div></div>
      <div class="stat-card"><div class="stat-title">Cancelled</div><div class="stat-value"><?php echo (int)$stats['cancelled_count']; ?></div></div>
    </div>

    <div class="filters">
      <form method="GET" action="events.php">
        <div>
          <label>Status</label>
          <select name="status">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
            <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>
        </div>
        <div>
          <label>Search</label>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, description, location">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        <button type="button" class="btn btn-success" onclick="openCreateModal()"><i class="fas fa-plus"></i> New Event</button>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Date / Time</th>
            <th>Location</th>
            <th>Participants</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($events_result && $events_result->num_rows > 0): ?>
            <?php while ($row = $events_result->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($row['event_name']); ?></strong>
                  <div style="font-size:12px;color:#666; margin-top:4px;"><?php echo htmlspecialchars(substr($row['event_description'] ?? '', 0, 120)); ?><?php echo strlen($row['event_description'] ?? '') > 120 ? '...' : ''; ?></div>
                </td>
                <td>
                  <?php echo date('M d, Y', strtotime($row['event_date'])); ?><br>
                  <small><?php echo $row['event_time'] ? date('g:i A', strtotime($row['event_time'])) : 'No time'; ?></small>
                </td>
                <td><?php echo htmlspecialchars($row['location'] ?? '-'); ?></td>
                <td><?php echo (int)($row['max_participants'] ?? 0); ?></td>
                <td><span class="badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <td class="row-actions">
                  <button class="btn btn-primary" type="button"
                    data-id="<?php echo (int)$row['event_id']; ?>"
                    data-name="<?php echo htmlspecialchars($row['event_name'], ENT_QUOTES); ?>"
                    data-description="<?php echo htmlspecialchars($row['event_description'] ?? '', ENT_QUOTES); ?>"
                    data-date="<?php echo htmlspecialchars($row['event_date']); ?>"
                    data-time="<?php echo htmlspecialchars($row['event_time'] ?? ''); ?>"
                    data-location="<?php echo htmlspecialchars($row['location'] ?? '', ENT_QUOTES); ?>"
                    data-max="<?php echo (int)($row['max_participants'] ?? 0); ?>"
                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                    onclick="openEditModal(this)">Edit</button>
                  <a class="btn btn-danger" href="events_action.php?action=delete&id=<?php echo (int)$row['event_id']; ?>" onclick="return confirm('Delete this event?');">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;color:#777;">No events found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal" id="createModal">
    <div class="modal-content">
      <div class="modal-head">
        <h3>Create Event</h3>
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Close</button>
      </div>
      <form method="POST" action="events_action.php">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
          <div class="full"><label>Event Name</label><input type="text" name="event_name" required></div>
          <div><label>Event Date</label><input type="date" name="event_date" required></div>
          <div><label>Event Time</label><input type="time" name="event_time"></div>
          <div><label>Location</label><input type="text" name="location"></div>
          <div><label>Max Participants</label><input type="number" name="max_participants" min="0"></div>
          <div><label>Status</label>
            <select name="status" required>
              <option value="upcoming">Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="full"><label>Description</label><textarea name="event_description"></textarea></div>
        </div>
        <button type="submit" class="btn btn-success">Create Event</button>
      </form>
    </div>
  </div>

  <div class="modal" id="editModal">
    <div class="modal-content">
      <div class="modal-head">
        <h3>Edit Event</h3>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Close</button>
      </div>
      <form method="POST" action="events_action.php">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="event_id" id="edit_event_id">
        <div class="form-grid">
          <div class="full"><label>Event Name</label><input type="text" name="event_name" id="edit_name" required></div>
          <div><label>Event Date</label><input type="date" name="event_date" id="edit_date" required></div>
          <div><label>Event Time</label><input type="time" name="event_time" id="edit_time"></div>
          <div><label>Location</label><input type="text" name="location" id="edit_location"></div>
          <div><label>Max Participants</label><input type="number" name="max_participants" id="edit_max" min="0"></div>
          <div><label>Status</label>
            <select name="status" id="edit_status" required>
              <option value="upcoming">Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="full"><label>Description</label><textarea name="event_description" id="edit_description"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
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

    function openCreateModal() { document.getElementById('createModal').style.display = 'block'; }
    function closeCreateModal() { document.getElementById('createModal').style.display = 'none'; }
    function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

    function openEditModal(btn) {
      document.getElementById('edit_event_id').value = btn.getAttribute('data-id');
      document.getElementById('edit_name').value = btn.getAttribute('data-name');
      document.getElementById('edit_description').value = btn.getAttribute('data-description');
      document.getElementById('edit_date').value = btn.getAttribute('data-date');
      document.getElementById('edit_time').value = btn.getAttribute('data-time');
      document.getElementById('edit_location').value = btn.getAttribute('data-location');
      document.getElementById('edit_max').value = btn.getAttribute('data-max');
      document.getElementById('edit_status').value = btn.getAttribute('data-status');
      document.getElementById('editModal').style.display = 'block';
    }

    window.onclick = function(e) {
      if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
      }
    }
  </script>
</body>
</html>
