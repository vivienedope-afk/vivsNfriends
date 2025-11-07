<?php
require_once('../auth/session_check.php');
require_once('../config/database.php');
requireAdmin();

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get all residents
$residents_query = "SELECT u.*, h.unit_number, h.lot_number, h.block_number, h.resident_type
                    FROM users u
                    LEFT JOIN household_members hm ON u.user_id = hm.user_id AND hm.is_primary = 1
                    LEFT JOIN households h ON hm.household_id = h.household_id
                    WHERE u.user_role = 'resident'
                    ORDER BY u.created_at DESC";
$residents = $conn->query($residents_query);

// Generate next account number
function generateAccountNumber($conn) {
    $year = date('Y');
    $query = "SELECT account_number FROM users WHERE account_number LIKE 'MAIA-$year-%' ORDER BY account_number DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $last_acc = $result->fetch_assoc()['account_number'];
        $last_num = intval(substr($last_acc, -3));
        $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_num = '001';
    }
    
    return "MAIA-$year-$next_num";
}

$next_account_number = generateAccountNumber($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Residents - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="icon" type="image/png" href="../pics/Courtyard.png">
  <style>
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    
    .modal.show {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background-color: white;
      padding: 30px;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .modal-header h2 {
      font-size: 22px;
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
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .form-field {
      margin-bottom: 15px;
    }
    
    .form-field label {
      display: block;
      font-weight: 500;
      margin-bottom: 5px;
      color: #2c3e50;
      font-size: 14px;
    }
    
    .form-field input,
    .form-field select {
      width: 100%;
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 14px;
    }
    
    .form-field input:focus,
    .form-field select:focus {
      outline: none;
      border-color: #d4a574;
    }
    
    .account-number-display {
      background: #fef8f0;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      border: 2px solid rgba(193, 127, 89, 0.2);
    }
    
    .account-number-display strong {
      font-size: 24px;
      color: #c17f59;
      display: block;
      margin-top: 5px;
    }
    
    .btn-submit {
      background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
      color: #79491b;
      padding: 12px 30px;
      border: 2px solid rgba(193, 127, 89, 0.3);
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
    }
    
    .btn-submit:hover {
      opacity: 0.9;
    }
    
.add-resident-btn {
  background: linear-gradient(135deg, #fedea3 0%, #f5d18a 100%);
  color: #79491b;
  padding: 12px 24px;
  border: 2px solid rgba(193, 127, 89, 0.3);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}    .residents-table {
      margin-top: 20px;
    }
    
    .status-active {
      color: #50C878;
      font-weight: 600;
    }
    
    .status-inactive {
      color: #E74C3C;
      font-weight: 600;
    }
    
    .btn-edit,
    .btn-deactivate {
      padding: 6px 12px;
      border: none;
      border-radius: 5px;
      font-size: 12px;
      cursor: pointer;
      margin-right: 5px;
    }
    
    .btn-edit {
      background: #3498db;
      color: white;
    }
    
    .btn-deactivate {
      background: #e74c3c;
      color: white;
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
      <li class="active"><a href="residents.php" onclick="closeMenu()"><span class="text">Residents</span></a></li>
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
      <h1>Manage Residents</h1>
      <button class="add-resident-btn" onclick="showAddResidentModal()">+ Add New Resident</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php 
          if ($_GET['success'] == 'added') echo 'Resident account created successfully!';
          elseif ($_GET['success'] == 'updated') echo 'Resident information updated successfully!';
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-error">
        <?php 
          if ($_GET['error'] == 'exists') echo 'Account number or email already exists!';
          elseif ($_GET['error'] == 'failed') echo 'Failed to create account. Please try again.';
        ?>
      </div>
    <?php endif; ?>

    <div class="card residents-table">
      <div class="card-header">
        <h2>All Residents (<?php echo $residents->num_rows; ?>)</h2>
      </div>
      <div class="card-content">
        <table class="data-table">
          <thead>
            <tr>
              <th>Account Number</th>
              <th>Name</th>
              <th>Unit</th>
              <th>Email</th>
              <th>Contact</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($resident = $residents->fetch_assoc()): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($resident['account_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                <td><?php echo htmlspecialchars($resident['unit_number'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($resident['email']); ?></td>
                <td><?php echo htmlspecialchars($resident['contact_number'] ?? 'N/A'); ?></td>
                <td class="status-<?php echo $resident['status']; ?>">
                  <?php echo ucfirst($resident['status']); ?>
                </td>
                <td>
                  <button class="btn-edit" onclick="editResident(<?php echo $resident['user_id']; ?>)">Edit</button>
                  <button class="btn-deactivate" onclick="toggleStatus(<?php echo $resident['user_id']; ?>, '<?php echo $resident['status']; ?>')">
                    <?php echo $resident['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Add Resident Modal -->
  <div id="addResidentModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Resident</h2>
        <button class="close-modal" onclick="closeAddResidentModal()">×</button>
      </div>
      
      <div class="account-number-display">
        <p>Account Number (Auto-Generated):</p>
        <strong id="displayAccountNumber"><?php echo $next_account_number; ?></strong>
      </div>

      <form action="residents_action.php" method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="account_number" value="<?php echo $next_account_number; ?>">
        
        <div class="form-row">
          <div class="form-field">
            <label for="first_name">First Name *</label>
            <input type="text" id="first_name" name="first_name" required>
          </div>
          <div class="form-field">
            <label for="last_name">Last Name *</label>
            <input type="text" id="last_name" name="last_name" required>
          </div>
        </div>

        <div class="form-field">
          <label for="email">Email Address *</label>
          <input type="email" id="email" name="email" required>
        </div>

        <div class="form-field">
          <label for="contact_number">Contact Number</label>
          <input type="tel" id="contact_number" name="contact_number" placeholder="09XXXXXXXXX">
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="unit_number">Unit Number *</label>
            <input type="text" id="unit_number" name="unit_number" required>
          </div>
          <div class="form-field">
            <label for="resident_type">Resident Type *</label>
            <select id="resident_type" name="resident_type" required>
              <option value="owner">Owner</option>
              <option value="tenant">Tenant</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="lot_number">Lot Number</label>
            <input type="text" id="lot_number" name="lot_number">
          </div>
          <div class="form-field">
            <label for="block_number">Block Number</label>
            <input type="text" id="block_number" name="block_number">
          </div>
        </div>

        <div class="form-field">
          <label for="default_password">Default Password *</label>
          <input type="text" id="default_password" name="default_password" value="maia2025" required>
          <small style="color: #999;">Resident can change this after first login</small>
        </div>

        <button type="submit" class="btn-submit">Create Resident Account</button>
      </form>
    </div>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuBtn = document.querySelector('.menu-btn');
    const modal = document.getElementById('addResidentModal');

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

    function showAddResidentModal() {
      modal.classList.add('show');
    }

    function closeAddResidentModal() {
      modal.classList.remove('show');
    }

    function editResident(userId) {
      alert('Edit functionality coming soon for user ID: ' + userId);
    }

    function toggleStatus(userId, currentStatus) {
      const action = currentStatus === 'active' ? 'deactivate' : 'activate';
      if (confirm('Are you sure you want to ' + action + ' this account?')) {
        window.location.href = 'residents_action.php?action=toggle_status&user_id=' + userId + '&status=' + currentStatus;
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
