<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

$create_damage_table_sql = "CREATE TABLE IF NOT EXISTS amenity_damage_reports (
  report_id INT PRIMARY KEY AUTO_INCREMENT,
  booking_id INT NOT NULL,
  household_id INT NOT NULL,
  reported_by INT NOT NULL,
  incident_date DATE NOT NULL,
  incident_type VARCHAR(100) DEFAULT 'damage',
  description TEXT NOT NULL,
  estimated_cost DECIMAL(10,2) DEFAULT NULL,
  status ENUM('reported','under_review','resolved') DEFAULT 'reported',
  admin_notes TEXT,
  resolved_by INT DEFAULT NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES facility_bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
)";
$conn->query($create_damage_table_sql);

$booking_history = [];
$history_query = "SELECT fb.*, adr.report_id, adr.status AS damage_status, adr.incident_date, adr.created_at AS damage_created_at
                  FROM facility_bookings fb
                  LEFT JOIN amenity_damage_reports adr ON fb.booking_id = adr.booking_id
                  WHERE fb.household_id = ?
                  ORDER BY fb.booking_date DESC, fb.start_time DESC, fb.created_at DESC
                  LIMIT 30";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $current_user['household_id']);
$stmt->execute();
$history_result = $stmt->get_result();
if ($history_result) {
  while ($row = $history_result->fetch_assoc()) {
    $booking_history[] = $row;
  }
}
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
  <style>
    .history-wrap { margin-top: 26px; background: #fff; border-radius: 10px; box-shadow: 0 2px 7px rgba(0,0,0,0.08); overflow: hidden; }
    .history-head { background: #f5d18a; color: #5e3a24; padding: 12px 16px; font-weight: 700; }
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th, .history-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; font-size: 13px; }
    .status-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .st-pending { background: #fff3cd; color: #856404; }
    .st-approved { background: #d4edda; color: #155724; }
    .st-rejected { background: #f8d7da; color: #721c24; }
    .st-cancelled { background: #e2e3e5; color: #41464b; }
    .st-reported { background: #ffe5b4; color: #7a4b00; }
    .st-under-review { background: #d1ecf1; color: #0c5460; }
    .st-resolved { background: #d4edda; color: #155724; }
    .btn-mini { border: none; border-radius: 6px; padding: 6px 10px; cursor: pointer; font-size: 12px; font-weight: 600; background: #c17f59; color: #fff; }
    .btn-mini:hover { background: #8b5a3c; }
    .flash { margin-bottom: 14px; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
    .flash.ok { background: #d4edda; color: #155724; }
    .flash.err { background: #f8d7da; color: #721c24; }
  </style>
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

    <?php if (isset($_GET['success'])): ?>
      <div class="flash ok">Damage/incident report submitted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="flash err">Failed to submit damage/incident report. Please check your input.</div>
    <?php endif; ?>

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

    <section class="history-wrap">
      <div class="history-head">Amenities Reservation History</div>
      <table class="history-table">
        <thead>
          <tr>
            <th>Facility</th>
            <th>Schedule</th>
            <th>Purpose</th>
            <th>Booking Status</th>
            <th>Damage Report</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($booking_history) > 0): ?>
            <?php foreach ($booking_history as $booking): ?>
              <tr>
                <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                <td>
                  <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?><br>
                  <small><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></small>
                </td>
                <td><?php echo htmlspecialchars($booking['purpose'] ?? '-'); ?></td>
                <td><span class="status-pill st-<?php echo htmlspecialchars($booking['status']); ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                <td>
                  <?php if (!empty($booking['report_id'])): ?>
                    <span class="status-pill st-<?php echo htmlspecialchars($booking['damage_status']); ?>"><?php echo ucwords(str_replace('_', ' ', $booking['damage_status'])); ?></span>
                  <?php else: ?>
                    <span style="color:#777;">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($booking['status'] === 'approved' && strtotime($booking['booking_date']) <= strtotime(date('Y-m-d')) && empty($booking['report_id'])): ?>
                    <button class="btn-mini" onclick="openDamageModal(<?php echo (int)$booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['facility_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($booking['booking_date']); ?>')">Report Damage</button>
                  <?php else: ?>
                    <span style="color:#888; font-size:12px;">No action</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center; color:#777;">No amenity reservation history yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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

  <div id="damageModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeDamageModal()">&times;</span>
      <h2>Report Amenity Damage/Incident</h2>
      <form method="POST" action="amenities_action.php">
        <input type="hidden" name="action" value="report_damage">
        <input type="hidden" name="booking_id" id="damage_booking_id">
        <div class="form-group">
          <label>Facility</label>
          <input type="text" id="damage_facility" readonly>
        </div>
        <div class="form-group">
          <label>Incident Date</label>
          <input type="date" name="incident_date" id="damage_incident_date" required>
        </div>
        <div class="form-group">
          <label>Incident Type</label>
          <input type="text" name="incident_type" placeholder="Example: Broken chair, court ring damage" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" required placeholder="Describe what happened and where"></textarea>
        </div>
        <div class="form-group">
          <label>Estimated Cost (optional)</label>
          <input type="number" step="0.01" min="0" name="estimated_cost" placeholder="0.00">
        </div>
        <button type="submit" class="btn-submit">Submit Report</button>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    function openDamageModal(bookingId, facilityName, bookingDate) {
      document.getElementById('damage_booking_id').value = bookingId;
      document.getElementById('damage_facility').value = facilityName;
      document.getElementById('damage_incident_date').value = bookingDate;
      document.getElementById('damageModal').style.display = 'block';
    }

    function closeDamageModal() {
      document.getElementById('damageModal').style.display = 'none';
    }

    window.addEventListener('click', function(event) {
      const damageModal = document.getElementById('damageModal');
      if (event.target === damageModal) {
        closeDamageModal();
      }
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>
