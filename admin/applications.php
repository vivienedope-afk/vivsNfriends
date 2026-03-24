<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get filter from query string
$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query based on filter
$where_clause = "WHERE 1=1";
$params = [];
$param_types = "";

if ($filter !== 'all') {
    $where_clause .= " AND status = ?";
    $params[] = $filter;
    $param_types .= "s";
}

if (!empty($search)) {
    $where_clause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR unit_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Get applications
$applications_query = "SELECT * FROM account_applications 
                      $where_clause 
                      ORDER BY created_at DESC";

$stmt = $conn->prepare($applications_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

// Get counts for badges
$pending_count = $conn->query("SELECT COUNT(*) as count FROM account_applications WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM account_applications WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM account_applications WHERE status = 'rejected'")->fetch_assoc()['count'];
$total_count = $pending_count + $approved_count + $rejected_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Applications - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="stylesheet" href="css/applications.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
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
      <li class="active"><a href="applications.php" onclick="closeMenu()"><span class="text">Applications</span></a></li>
      <li><a href="payments.php" onclick="closeMenu()"><span class="text">Payments & Dues</span></a></li>
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
      <h1>Account Applications</h1>
      <p class="breadcrumb">Home > Applications</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php 
          if ($_GET['success'] == 'approved') echo 'Application approved successfully! Account created and credentials sent.';
          elseif ($_GET['success'] == 'rejected') echo 'Application rejected.';
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-error">
        <?php 
          if ($_GET['error'] == 'failed') echo 'Failed to process application. Please try again.';
          elseif ($_GET['error'] == 'exists') echo 'Account with this email already exists.';
        ?>
      </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card yellow">
        <div class="stat-info">
          <h3><?php echo $pending_count; ?></h3>
          <p>Pending Applications</p>
        </div>
      </div>

      <div class="stat-card green">
        <div class="stat-info">
          <h3><?php echo $approved_count; ?></h3>
          <p>Approved</p>
        </div>
      </div>

      <div class="stat-card red">
        <div class="stat-info">
          <h3><?php echo $rejected_count; ?></h3>
          <p>Rejected</p>
        </div>
      </div>

      <div class="stat-card blue">
        <div class="stat-info">
          <h3><?php echo $total_count; ?></h3>
          <p>Total Applications</p>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="card">
      <div class="card-header">
        <h2>Applications List</h2>
      </div>
      <div class="card-content">
        <div class="filters-section">
          <div class="filter-tabs">
            <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
              All (<?php echo $total_count; ?>)
            </a>
            <a href="?filter=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">
              Pending (<?php echo $pending_count; ?>)
            </a>
            <a href="?filter=approved<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $filter == 'approved' ? 'active' : ''; ?>">
              Approved (<?php echo $approved_count; ?>)
            </a>
            <a href="?filter=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
              Rejected (<?php echo $rejected_count; ?>)
            </a>
          </div>

          <form method="GET" class="search-form">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="search" placeholder="Search by name, email, or unit..." 
                   value="<?php echo htmlspecialchars($search); ?>" class="search-input">
            <button type="submit" class="search-btn">Search</button>
            <?php if (!empty($search)): ?>
              <a href="?filter=<?php echo htmlspecialchars($filter); ?>" class="clear-search">Clear</a>
            <?php endif; ?>
          </form>
        </div>

        <?php if ($applications->num_rows > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Date Applied</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Unit</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($app = $applications->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                  <td><strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($app['email']); ?></td>
                  <td><?php echo htmlspecialchars($app['contact_number']); ?></td>
                  <td><?php echo htmlspecialchars($app['unit_number']); ?></td>
                  <td><?php echo ucfirst($app['resident_type']); ?></td>
                  <td>
                    <span class="status-badge status-<?php echo $app['status']; ?>">
                      <?php echo ucfirst($app['status']); ?>
                    </span>
                  </td>
                  <td>
                    <button class="btn-view" onclick="viewApplication(<?php echo $app['application_id']; ?>)">
                      View Details
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-data">
            <p>No applications found.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- View Application Modal -->
  <div id="viewApplicationModal" class="modal">
    <div class="modal-content large">
      <div class="modal-header">
        <h2>Application Details</h2>
        <button class="close-modal" onclick="closeModal()">×</button>
      </div>
      <div id="applicationDetails" class="modal-body">
        <!-- Content loaded via JavaScript -->
      </div>
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

    function viewApplication(appId) {
      const modal = document.getElementById('viewApplicationModal');
      const detailsDiv = document.getElementById('applicationDetails');
      
      // Show loading
      detailsDiv.innerHTML = '<p class="loading">Loading application details...</p>';
      modal.classList.add('show');
      
      // Fetch application details
      fetch(`view_application.php?id=${appId}`)
        .then(response => response.text())
        .then(html => {
          detailsDiv.innerHTML = html;
        })
        .catch(error => {
          detailsDiv.innerHTML = '<p class="error">Failed to load application details.</p>';
          console.error('Error:', error);
        });
    }

    function closeModal() {
      document.getElementById('viewApplicationModal').classList.remove('show');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('viewApplicationModal');
      if (event.target == modal) {
        closeModal();
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
