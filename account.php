<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get user's household info
$household_query = "SELECT h.*, hm.relationship FROM households h
                    INNER JOIN household_members hm ON h.household_id = hm.household_id
                    WHERE hm.user_id = ? AND hm.is_primary = 1";
$stmt = $conn->prepare($household_query);
$stmt->bind_param("i", $current_user['user_id']);
$stmt->execute();
$household = $stmt->get_result()->fetch_assoc();


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="account.css">
  <link rel="stylesheet" href="profile-edit.css">
  <link rel="icon" type="image/png" href="pics/Courtyard.png">
  <script src="script.js" defer></script>
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">
  ☰
</button>

<nav class="navbar" id="sidebar">
  <button class="close-btn" onclick="toggleMenu()">
    ✕
  </button>
  <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
  <div class="user-info-sidebar">
    <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
    <p class="user-unit"><?php echo htmlspecialchars($current_user['unit_number']); ?></p>
  </div>
  <ul class="nav-links">
    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>"><a href="home.php" onclick="closeMenu()"><span class="text">Home</span></a></li>
    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'account.php') ? 'active' : ''; ?>"><a href="account.php" onclick="closeMenu()"><span class="text">Account</span></a></li>
    <li><a href="calendar.php" onclick="closeMenu()"><span class="text">Calendar</span></a></li>
    <li><a href="amenities.php" onclick="closeMenu()"><span class="text">Amenities</span></a></li>
    <li><a href="auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
  </ul>
</nav>

<div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Account Management</h1>
      <p class="breadcrumb">Home > Account</p>
    </div>

    <section class="account-section">
      <div class="card edit-profile-card" id="editProfileCard">
        <div class="card-header">
          <span>Edit Profile</span>
          <button class="minimize-btn" onclick="toggleEditProfile()">
            <span class="minimize-icon">−</span>
          </button>
        </div>
        <div class="card-content">
          <form id="profileForm" onsubmit="updateProfile(event)">
            <div class="form-group">
              <div class="input-group">
                <label for="unitNumber">Unit Number</label>
                <input type="text" id="unitNumber" name="unitNumber" value="<?php echo htmlspecialchars($household['unit_number'] ?? ''); ?>" required>
                <span class="input-hint">Enter your complete unit number</span>
              </div>
              <div class="input-group">
                <label for="residentType">Resident Type</label>
                <select id="residentType" name="residentType" required>
                  <option value="owner" <?php echo ($household['resident_type'] ?? '') == 'owner' ? 'selected' : ''; ?>>Owner</option>
                  <option value="tenant" <?php echo ($household['resident_type'] ?? '') == 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="firstName">First Name</label>
                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($current_user['first_name']); ?>" required>
              </div>
              <div class="input-group">
                <label for="lastName">Last Name</label>
                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($current_user['last_name']); ?>" required>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                <span class="input-hint">This will be used for notifications</span>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="phone">Contact Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($current_user['contact_number'] ?? ''); ?>" required>
                <span class="input-hint">Format: 09XXXXXXXXX</span>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="emergencyContact">Emergency Contact</label>
                <input type="text" id="emergencyContact" name="emergencyContact" 
                       placeholder="Emergency contact name">
              </div>
              <div class="input-group">
                <label for="emergencyPhone">Emergency Contact Number</label>
                <input type="tel" id="emergencyPhone" name="emergencyPhone" 
                       placeholder="Emergency contact number">
              </div>
            </div>

            <div class="form-actions">
              <button type="button" class="secondary-btn" onclick="resetForm()">
                Reset
              </button>
              <button type="submit" class="primary-btn">
                Save Changes
              </button>
            </div>

            <div id="formStatus" class="form-status" style="display: none;">
              <span class="status-icon">✓</span>
              <span class="status-message">Changes saved successfully!</span>
            </div>
          </form>
        </div>
      </div>

      <div class="card user-info-card">
        <div class="card-header">
          <span>Resident Information</span>
        </div>
        <div class="card-content">
          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Account Number</div>
              <div class="info-value"><strong><?php echo htmlspecialchars($current_user['account_number']); ?></strong></div>
            </div>
            <div class="info-row">
              <div class="info-label">Unit Number</div>
              <div class="info-value"><?php echo htmlspecialchars($household['unit_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Resident Type</div>
              <div class="info-value"><?php echo ucfirst($household['resident_type'] ?? 'N/A'); ?></div>
            </div>
          </div>

          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Full Name</div>
              <div class="info-value"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
            </div>
            <div class="info-row sensitive-info">
              <div class="info-label">Email Address</div>
              <div class="info-value">
                <span class="hidden-content" id="email-content"><?php echo htmlspecialchars($current_user['email']); ?></span>
                <button class="toggle-visibility" onclick="toggleVisibility('email-content', this)">
                  <span class="eye-icon">👁</span>
                </button>
              </div>
            </div>
            <div class="info-row sensitive-info">
              <div class="info-label">Contact Number</div>
              <div class="info-value">
                <span class="hidden-content" id="phone-content"><?php echo htmlspecialchars($current_user['contact_number'] ?? 'N/A'); ?></span>
                <button class="toggle-visibility" onclick="toggleVisibility('phone-content', this)">
                  <span class="eye-icon">👁</span>
                </button>
              </div>
            </div>
          </div>

          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Member Since</div>
              <div class="info-value"><?php echo date('F Y', strtotime($household['move_in_date'] ?? 'now')); ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Status</div>
              <div class="info-value">
                <span class="status-badge active">Active</span>
              </div>
            </div>
          </div>

          <div class="actions-row">
            <button class="action-btn edit-info" onclick="editUserInfo()">
              Update Information
            </button>
            <button class="action-btn verify-id" onclick="verifyIdentity()">
              Verify Identity
            </button>
          </div>
        </div>
      </div>


    </section>
  </main>
</body>
</html>
<?php $conn->close(); ?>

