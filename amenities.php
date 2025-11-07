<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amenities - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/amenities.css">
  <link rel="icon" type="image/png" href="pics/Courtyard.png">
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">
    ☰
  </button>

  <nav class="navbar" id="sidebar">
    <button class="close-btn" onclick="toggleMenu()">
      ✕
    </button>
    <button class="back-button" onclick="window.location.href='home.php'">
      ←
    </button>
    <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
    <div class="user-info-sidebar">
      <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
      <p class="user-unit"><?php echo htmlspecialchars($current_user['unit_number']); ?></p>
    </div>
    <ul class="nav-links">
      <li><a href="home.php" onclick="closeMenu()"><span class="text">Home</span></a></li>
      <li><a href="account.php" onclick="closeMenu()"><span class="text">Account</span></a></li>
      <li><a href="calendar.php" onclick="closeMenu()"><span class="text">Calendar</span></a></li>
      <li class="active"><a href="amenities.php" onclick="closeMenu()"><span class="text">Amenities</span></a></li>
      <li><a href="auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <p class="breadcrumb">Amenities</p>
    </div>

    <section class="amenities">
      <div class="amenity-card">
        <div class="amenity-image">
          <i class="fas fa-building"></i>
        </div>
        <h3>Clubhouse</h3>
        <p>Perfect for events, parties, and community gatherings. Available for reservations.</p>
        <button class="btn-reserve" onclick="openReservationModal('Clubhouse')">Reserve</button>
      </div>

      <div class="amenity-card">
        <div class="amenity-image">
          <i class="fas fa-basketball-ball"></i>
        </div>
        <h3>Basketball Court</h3>
        <p>Outdoor basketball court for sports and recreational activities.</p>
        <button class="btn-reserve" onclick="openReservationModal('Basketball Court')">Reserve</button>
      </div>
    </section>
  </main>

  <!-- Reservation Modal -->
  <div id="reservationModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeReservationModal()">&times;</span>
      <h2>Reserve <span id="facilityName"></span></h2>
      <form id="reservationForm">
        <input type="hidden" id="facility" name="facility">
        <div class="form-group">
          <label for="purpose">Purpose:</label>
          <select id="purpose" name="purpose" required>
            <option value="">Select Purpose</option>
            <!-- Options will be populated based on facility -->
          </select>
        </div>
        <div class="form-group">
          <label for="booking_date">Date:</label>
          <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label for="start_time">Start Time:</label>
          <input type="time" id="start_time" name="start_time" required>
        </div>
        <div class="form-group">
          <label for="end_time">End Time:</label>
          <input type="time" id="end_time" name="end_time" required>
        </div>
        <button type="submit" class="btn-submit">Submit Reservation</button>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
