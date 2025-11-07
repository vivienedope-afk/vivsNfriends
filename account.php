<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="account.css">
  <link rel="stylesheet" href="profile-edit.css">
  <script src="script.js" defer></script>
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">
  <i class="fas fa-bars"></i>
</button>

<nav class="navbar" id="sidebar">
  <button class="close-btn" onclick="toggleMenu()">
    <i class="fas fa-times"></i>
  </button>
  <button class="back-button" onclick="goBack()">
    <i class="fas fa-arrow-left"></i>
  </button>
  <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
  <ul class="nav-links">
    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'account.php') ? 'active' : ''; ?>"><a href="account.php" onclick="closeMenu()"><span class="icon"><i class="fas fa-user"></i></span><span class="text">Account</span></a></li>
    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><a href="index.php" onclick="closeMenu()"><span class="icon"><i class="fas fa-chart-line"></i></span><span class="text">Dashboard</span></a></li>
    <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="text">Calendar</span></a></li>
    <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-swimming-pool"></i></span><span class="text">Amenities</span></a></li>
    <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-sign-in-alt"></i></span><span class="text">Login</span></a></li>
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
          <span class="header-icon"><i class="fas fa-user-edit"></i></span>
          <span>Edit Profile</span>
          <button class="minimize-btn" onclick="toggleEditProfile()">
            <span class="minimize-icon"><i class="fas fa-minus"></i></span>
          </button>
        </div>
        <div class="card-content">
          <form id="profileForm" onsubmit="updateProfile(event)">
            <div class="form-group">
              <div class="input-group">
                <label for="unitNumber">Unit Number</label>
                <input type="text" id="unitNumber" name="unitNumber" value="Unit 1234" required>
                <span class="input-hint">Enter your complete unit number</span>
              </div>
              <div class="input-group">
                <label for="residentType">Resident Type</label>
                <select id="residentType" name="residentType" required>
                  <option value="owner">Owner</option>
                  <option value="tenant">Tenant</option>
                  <option value="familyMember">Family Member</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="firstName">First Name</label>
                <input type="text" id="firstName" name="firstName" value="John" required>
              </div>
              <div class="input-group">
                <label for="lastName">Last Name</label>
                <input type="text" id="lastName" name="lastName" value="Doe" required>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="john.doe@example.com" required>
                <span class="input-hint">This will be used for notifications</span>
              </div>
            </div>

            <div class="form-group">
              <div class="input-group">
                <label for="phone">Contact Number</label>
                <input type="tel" id="phone" name="phone" value="(123) 456-7890" 
                       pattern="\(\d{3}\) \d{3}-\d{4}" required>
                <span class="input-hint">Format: (123) 456-7890</span>
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
                <span class="icon"><i class="fas fa-undo"></i></span> Reset
              </button>
              <button type="submit" class="primary-btn">
                <span class="icon"><i class="fas fa-save"></i></span> Save Changes
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
          <span class="header-icon"><i class="fas fa-id-card"></i></span>
          <span>Resident Information</span>
        </div>
        <div class="card-content">
          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Unit Number</div>
              <div class="info-value">Unit 1234</div>
            </div>
            <div class="info-row">
              <div class="info-label">Resident Type</div>
              <div class="info-value">Owner</div>
            </div>
          </div>

          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Full Name</div>
              <div class="info-value">John Doe</div>
            </div>
            <div class="info-row sensitive-info">
              <div class="info-label">Email Address</div>
              <div class="info-value">
                <span class="hidden-content" id="email-content">john.doe@example.com</span>
                <button class="toggle-visibility" onclick="toggleVisibility('email-content', this)">
                  <span class="eye-icon"><i class="fas fa-eye"></i></span>
                </button>
              </div>
            </div>
            <div class="info-row sensitive-info">
              <div class="info-label">Contact Number</div>
              <div class="info-value">
                <span class="hidden-content" id="phone-content">(123) 456-7890</span>
                <button class="toggle-visibility" onclick="toggleVisibility('phone-content', this)">
                  <span class="eye-icon"><i class="fas fa-eye"></i></span>
                </button>
              </div>
            </div>
          </div>

          <div class="info-group">
            <div class="info-row">
              <div class="info-label">Member Since</div>
              <div class="info-value">January 2020</div>
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
              <span class="icon"><i class="fas fa-pencil-alt"></i></span>
              Update Information
            </button>
            <button class="action-btn verify-id" onclick="verifyIdentity()">
              <span class="icon"><i class="fas fa-shield-alt"></i></span>
              Verify Identity
            </button>
          </div>
        </div>
      </div>

      <div class="card notification-card">
        <div class="card-header">
          <span class="header-icon">🔔</span>
          <span>Notification Settings</span>
        </div>
        <div class="card-content">
          <div class="notification-option">
            <div class="notification-info">
              <h3>Email Notifications</h3>
              <p>Receive updates about dues, events, and announcements via email</p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="email-notif" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          
          <div class="notification-option">
            <div class="notification-info">
              <h3>SMS Notifications</h3>
              <p>Get important alerts and reminders via text message</p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="sms-notif">
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="card dues-card">
        <div class="card-header">
          <span class="header-icon"><i class="fas fa-exclamation-circle"></i></span>
          <span>Payment Due</span>
        </div>
        <div class="card-content">
          <div class="dues-alert">
            <div class="dues-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="dues-info">
              <h3>October 2023 Payment Due</h3>
              <p>Amount: <strong>$150.00</strong></p>
              <p class="due-date">Due by: <b>November 10, 2023</b></p>
              <div class="warning-text">
                <i class="fas fa-exclamation-triangle"></i>
                Please settle to avoid late fees
              </div>
            </div>
          </div>
          <div class="dues-actions">
            <button class="pay-now-btn">
              <i class="fas fa-credit-card"></i>
              Pay Now
            </button>
            <button class="payment-history-btn">
              <i class="fas fa-history"></i>
              Payment History
            </button>
          </div>
        </div>
      </div>

      <div class="card ledger-card">
        <div class="card-header">
          <span class="header-icon"><i class="fas fa-file-invoice-dollar"></i></span>
          <span>Payment Ledger</span>
          <div class="ledger-actions">
            <button class="filter-btn" title="Filter Transactions">
              <i class="fas fa-filter"></i>
            </button>
            <button class="download-btn" title="Download Statement">
              <i class="fas fa-download"></i>
            </button>
          </div>
        </div>
        <div class="card-content">
          <div class="ledger-summary">
            <div class="summary-item">
              <span class="label">Total Paid (2023)</span>
              <span class="value">$1,350.00</span>
            </div>
            <div class="summary-item">
              <span class="label">Outstanding</span>
              <span class="value text-warning">$150.00</span>
            </div>
          </div>
          <table class="ledger-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody> 
              <tr>
                <td>Oct 1, 2023</td>
                <td>Monthly Dues</td>
                <td>$150.00</td>
                <td>Paid</td>
              </tr>
              <tr>
                <td>Sep 1, 2023</td>
                <td>Monthly Dues</td>
                <td>$150.00</td>
                <td>Paid</td>
              </tr>
              <tr>
                <td>Aug 1, 2023</td>
                <td>Monthly Dues</td>
                <td>$150.00</td>
                <td>Paid</td>
              </tr>
              <tr>
                <td>Nov 1, 2023</td>
                <td>Monthly Dues</td>
                <td>$150.00</td>
                <td>Unpaid</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>


