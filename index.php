<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get user's unpaid dues
$unpaid_dues_query = "SELECT COUNT(*) as count FROM monthly_dues WHERE household_id = ? AND status = 'unpaid'";
$stmt = $conn->prepare($unpaid_dues_query);
$stmt->bind_param("i", $current_user['household_id']);
$stmt->execute();
$unpaid_count = $stmt->get_result()->fetch_assoc()['count'];

// Get upcoming events
$events_query = "SELECT * FROM events WHERE event_date >= CURDATE() AND status = 'upcoming' ORDER BY event_date ASC LIMIT 5";
$events = $conn->query($events_query);

// Get recent announcements
$announcements_query = "SELECT * FROM announcements WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY post_date DESC LIMIT 3";
$announcements = $conn->query($announcements_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
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
      <li><a href="home.php" onclick="closeMenu()"><span class="text">Home</span></a></li>
      <li><a href="account.php" onclick="closeMenu()"><span class="text">Account</span></a></li>
      <li class="active"><a href="index.php" onclick="closeMenu()"><span class="text">Dashboard</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="text">Calendar</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="text">Amenities</span></a></li>
      <li><a href="auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</h1>
      <p class="breadcrumb">Dashboard</p>
    </div>

    <div class="info-box">
      <button class="close-info-btn" onclick="closeInfoBox()">×</button>
      <p>Welcome to Courtyard of Maia Alta, a tranquil residential community nestled in nature, offering modern amenities and a peaceful lifestyle. Here, you can enjoy serene surroundings, state-of-the-art facilities, and a strong sense of community.</p>
      <h3>Rules and Regulations</h3>
      <ul>
        <li>Respect quiet hours from 10 PM to 6 AM to ensure a peaceful environment for all residents.</li>
        <li>Keep common areas clean and report any maintenance issues promptly.</li>
        <li>Parking is limited to designated spots; unauthorized vehicles may be towed.</li>
        <li>Pets must be leashed and cleaned up after in public spaces.</li>
        <li>Follow all HOA guidelines regarding property modifications and landscaping.</li>
      </ul>
    </div>

    <section class="dashboard">
      <div class="card event">
        <div class="card-header">
          Upcoming Events
        </div>
        <div class="card-content">
          <?php if ($events->num_rows > 0): ?>
            <?php while ($event = $events->fetch_assoc()): ?>
              <p><?php echo htmlspecialchars($event['event_name']); ?> - <?php echo date('M d', strtotime($event['event_date'])); ?></p>
            <?php endwhile; ?>
          <?php else: ?>
            <p>No upcoming events</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card dues">
        <div class="card-header">
          Payment Status
        </div>
        <div class="card-content">
          <?php if ($unpaid_count > 0): ?>
            <p>You have <strong><?php echo $unpaid_count; ?> unpaid due<?php echo $unpaid_count > 1 ? 's' : ''; ?></strong>.</p>
            <p>Please settle to avoid late fees.</p>
            <a href="account.php" class="btn-pay">View Dues</a>
          <?php else: ?>
            <p>✓ All dues are paid!</p>
            <p>Thank you for your prompt payment.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card notice">
        <div class="card-header">
          Announcements
        </div>
        <div class="card-content">
          <?php if ($announcements->num_rows > 0): ?>
            <?php while ($announcement = $announcements->fetch_assoc()): ?>
              <p><strong><?php echo htmlspecialchars($announcement['title']); ?></strong></p>
              <p style="font-size: 13px; color: #666;"><?php echo substr(htmlspecialchars($announcement['content']), 0, 100); ?>...</p>
              <hr style="border: none; border-top: 1px solid #eee; margin: 10px 0;">
            <?php endwhile; ?>
          <?php else: ?>
            <p>No announcements at this time</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuBtn = document.querySelector('.menu-btn');

    function goBack() {
      window.history.back();
    }

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

    function closeInfoBox() {
      document.querySelector('.info-box').style.display = 'none';
    }

    function toggleAccountSubmenu() {
      const submenu = document.getElementById('account-submenu');
      submenu.classList.toggle('open');
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
