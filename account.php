<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="account.css">
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">☰</button>

  <nav class="navbar" id="sidebar">
    <button class="close-btn" onclick="toggleMenu()">×</button>
    <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo" onclick="toggleDarkMode()">
    <ul class="nav-links">
      <li><a href="account.php" onclick="closeMenu()"><span class="icon">👤</span><span class="text">Account</span></a></li>
      <li><a href="index.php" onclick="closeMenu()"><span class="icon">📊</span><span class="text">Dashboard</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon">📅</span><span class="text">Calendar</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon">🏊</span><span class="text">Amenities</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon">🔑</span><span class="text">Login</span></a></li>
    </ul>
    <button class="back-button" onclick="goBack()">←</button>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <h1>Account</h1>

    <section class="account-section">
      <div class="card">
        <div class="card-header">👤 Edit Profile</div>
        <div class="card-content">
          <form action="#" method="post">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" value="John Doe" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="john.doe@example.com" required>

            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="(123) 456-7890">

            <button type="submit">Save Changes</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">ℹ️ User Info</div>
        <div class="card-content">
          <p><strong>Name:</strong> John Doe</p>
          <p><strong>Email:</strong> john.doe@example.com</p>
          <p><strong>Phone:</strong> (123) 456-7890</p>
          <p><strong>Member Since:</strong> January 2020</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header">🔔 Notifications</div>
        <div class="card-content">
          <label class="switch">
            <input type="checkbox" id="email-notif" checked>
            <span class="slider"></span>
            Email Notifications
          </label>
          <label class="switch">
            <input type="checkbox" id="sms-notif">
            <span class="slider"></span>
            SMS Notifications
          </label>
        </div>
      </div>

      <div class="card dues">
        <div class="card-header">💰 Missed Dues</div>
        <div class="card-content">
          <p>You have <strong>1 unpaid due</strong> for October.</p>
          <p>Please settle before <b>Nov 10</b> to avoid late fees.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header">📜 Payment Ledger</div>
        <div class="card-content">
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

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuBtn = document.querySelector('.menu-btn');

    function toggleDarkMode() {
      document.body.classList.toggle('dark-mode');
    }

    function goBack() {
      window.history.back();
    }

