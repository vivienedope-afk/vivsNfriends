<?php
require_once('auth/session_check.php');
require_once('config/database.php');

$conn = getDBConnection();
$current_user = getCurrentUser();

// Get current month and year from GET parameters or default to current
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 2020 || $current_year > 2030) {
    $current_year = date('Y');
}

// Calculate previous and next month/year
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get first day of the month and number of days
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$month_name = date('F', $first_day_of_month);

// Get day of week for first day (0 = Sunday, 6 = Saturday)
$day_of_week = date('w', $first_day_of_month);

// Query upcoming dues for the current user
$dues_query = "SELECT md.due_date, md.amount, md.status, h.unit_number
               FROM monthly_dues md
               INNER JOIN households h ON md.household_id = h.household_id
               WHERE md.household_id = ? AND md.due_date >= CURDATE()
               ORDER BY md.due_date ASC";
$stmt_dues = $conn->prepare($dues_query);
$stmt_dues->bind_param("i", $current_user['household_id']);
$stmt_dues->execute();
$dues_result = $stmt_dues->get_result();
$upcoming_dues = [];
while ($due = $dues_result->fetch_assoc()) {
    $date_key = date('Y-m-d', strtotime($due['due_date']));
    $upcoming_dues[$date_key] = $due;
}

// Query upcoming events
$events_query = "SELECT event_date, event_name, event_description, event_time, location
                 FROM events
                 WHERE event_date >= CURDATE() AND status = 'upcoming'
                 ORDER BY event_date ASC";
$events_result = $conn->query($events_query);
$upcoming_events = [];
while ($event = $events_result->fetch_assoc()) {
    $date_key = $event['event_date'];
    if (!isset($upcoming_events[$date_key])) {
        $upcoming_events[$date_key] = [];
    }
    $upcoming_events[$date_key][] = $event;
}

// Query pending maintenance requests for the user
$maintenance_query = "SELECT mr.*, h.unit_number
                      FROM maintenance_requests mr
                      INNER JOIN households h ON mr.household_id = h.household_id
                      WHERE mr.household_id = ? AND mr.status IN ('pending', 'in_progress')
                      ORDER BY mr.created_at DESC";
$stmt_maint = $conn->prepare($maintenance_query);
$stmt_maint->bind_param("i", $current_user['household_id']);
$stmt_maint->execute();
$maintenance_result = $stmt_maint->get_result();
$pending_maintenance = [];
while ($maint = $maintenance_result->fetch_assoc()) {
    $pending_maintenance[] = $maint;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="calendar.css">
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
    <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
    <div class="user-info-sidebar">
      <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
      <p class="user-unit"><?php echo htmlspecialchars($current_user['unit_number']); ?></p>
    </div>
    <ul class="nav-links">
      <li><a href="home.php" onclick="closeMenu()"><span class="text">Home</span></a></li>
      <li><a href="account.php" onclick="closeMenu()"><span class="text">Account</span></a></li>
      <li class="active"><a href="calendar.php" onclick="closeMenu()"><span class="text">Calendar</span></a></li>
      <li><a href="amenities.php" onclick="closeMenu()"><span class="text">Amenities</span></a></li>
      <li><a href="auth/logout.php" onclick="closeMenu()"><span class="text">Logout</span></a></li>
    </ul>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="page-header">
      <h1>Calendar</h1>
      <p class="breadcrumb">Home / Calendar</p>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
        <h2 class="calendar-title"><?php echo $month_name . ' ' . $current_year; ?></h2>
        <div class="calendar-nav">
          <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn">
            <i class="fas fa-chevron-left"></i> Previous
          </a>
          <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="nav-btn">
            Today
          </a>
          <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">
            Next <i class="fas fa-chevron-right"></i>
          </a>
        </div>
      </div>

      <div class="calendar-grid">
        <!-- Day headers -->
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>

        <!-- Calendar days -->
        <?php
        // Fill in blank cells for days before the first day of the month
        for ($i = 0; $i < $day_of_week; $i++) {
            $prev_month_days = date('t', mktime(0, 0, 0, $current_month - 1, 1, $current_year));
            $prev_day = $prev_month_days - $day_of_week + $i + 1;
            echo '<div class="calendar-day other-month"><div class="day-number">' . $prev_day . '</div></div>';
        }

        // Fill in the days of the current month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
            $is_today = ($current_date == date('Y-m-d'));
            $day_class = $is_today ? 'calendar-day today' : 'calendar-day';

            echo '<div class="' . $day_class . '">';
            echo '<div class="day-number">' . $day . '</div>';

            // Display dues for this day
            if (isset($upcoming_dues[$current_date])) {
                $due = $upcoming_dues[$current_date];
                $status_text = $due['status'] == 'unpaid' ? 'Due' : 'Overdue';
                echo '<div class="due-item">' . $status_text . ': ₱' . number_format($due['amount'], 2) . '</div>';
            }

            // Display events for this day
            if (isset($upcoming_events[$current_date])) {
                foreach ($upcoming_events[$current_date] as $event) {
                    echo '<div class="event-item">' . htmlspecialchars($event['event_name']) . '</div>';
                }
            }

            // Add reserve amenity indicator for today and future dates
            if ($current_date >= date('Y-m-d')) {
                echo '<div class="reserve-amenity" onclick="openReservationModalForDate(\'' . $current_date . '\')">Reserve Amenity</div>';
            }

            echo '</div>';
        }

        // Fill in blank cells for days after the last day of the month
        $total_cells = $day_of_week + $days_in_month;
        $remaining_cells = 42 - $total_cells; // 6 weeks * 7 days = 42 cells
        for ($i = 1; $i <= $remaining_cells; $i++) {
            echo '<div class="calendar-day other-month"><div class="day-number">' . $i . '</div></div>';
        }
        ?>
      </div>

      <!-- Sidebar with pending maintenance -->
      <div class="sidebar">
        <div class="sidebar-card">
          <h3 class="sidebar-title">Pending Maintenance</h3>
          <?php if (count($pending_maintenance) > 0): ?>
            <ul class="maintenance-list">
              <?php foreach ($pending_maintenance as $maint): ?>
                <li data-id="<?php echo $maint['request_id']; ?>">
                  <div>
                    <strong><?php echo htmlspecialchars($maint['subject']); ?></strong>
                    <br>
                    <small><?php echo htmlspecialchars($maint['category']); ?> - <?php echo date('M d, Y', strtotime($maint['created_at'])); ?></small>
                  </div>
                  <span class="maintenance-status status-<?php echo $maint['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $maint['status'])); ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>No pending maintenance requests.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Maintenance Modal -->
  <div id="maintenance-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h2 id="modal-title">Maintenance Request Details</h2>
      <div id="modal-body">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>

  <!-- Reservation Modal -->
  <div id="reservationModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeReservationModal()">&times;</span>
      <h2>Reserve Amenity for <span id="reservationDate"></span></h2>
      <form id="reservationForm">
        <input type="hidden" id="facility" name="facility">
        <div class="form-group">
          <label for="facility_select">Amenity:</label>
          <select id="facility_select" name="facility" required>
            <option value="">Select Amenity</option>
            <option value="Clubhouse">Clubhouse</option>
            <option value="Basketball Court">Basketball Court</option>
          </select>
        </div>
        <div class="form-group">
          <label for="purpose">Purpose:</label>
          <select id="purpose" name="purpose" required>
            <option value="">Select Purpose</option>
            <!-- Options will be populated based on facility -->
          </select>
        </div>
        <div class="form-group">
          <label for="booking_date">Date:</label>
          <input type="date" id="booking_date" name="booking_date" required readonly>
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

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuBtn = document.querySelector('.menu-btn');
    const modal = document.getElementById('maintenance-modal');
    const closeModal = document.querySelector('.close-modal');

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

    // Maintenance modal functionality
    document.querySelectorAll('.maintenance-list li').forEach(item => {
      item.addEventListener('click', function() {
        const maintenanceId = this.dataset.id;
        showMaintenanceDetails(maintenanceId);
      });
    });

    function showMaintenanceDetails(id) {
      // Fetch maintenance details via AJAX
      fetch('get_maintenance_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const details = data.maintenance;
            const modalBody = document.getElementById('modal-body');
            modalBody.innerHTML = `
              <div class="maintenance-details">
                <div class="detail-row">
                  <div class="detail-label">Subject:</div>
                  <div class="detail-value">${details.subject}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Category:</div>
                  <div class="detail-value">${details.category}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Description:</div>
                  <div class="detail-value">${details.description || 'No description provided'}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Status:</div>
                  <div class="detail-value">${details.status.replace('_', ' ').toUpperCase()}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Created:</div>
                  <div class="detail-value">${new Date(details.created_at).toLocaleDateString()}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Updated:</div>
                  <div class="detail-value">${new Date(details.updated_at).toLocaleDateString()}</div>
                </div>
              </div>
            `;
            modal.style.display = 'block';
          } else {
            alert('Error loading maintenance details: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading maintenance details');
        });
    }

    // Close modal when clicking the X
    closeModal.onclick = function() {
      modal.style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }

    // Reservation modal functionality
    function openReservationModalForDate(date) {
      const modal = document.getElementById('reservationModal');
      const reservationDateSpan = document.getElementById('reservationDate');
      const bookingDateInput = document.getElementById('booking_date');
      const facilitySelect = document.getElementById('facility_select');
      const purposeSelect = document.getElementById('purpose');

      reservationDateSpan.textContent = new Date(date).toLocaleDateString();
      bookingDateInput.value = date;
      facilitySelect.value = '';
      purposeSelect.innerHTML = '<option value="">Select Purpose</option>';

      modal.style.display = 'block';
    }

    function closeReservationModal() {
      const modal = document.getElementById('reservationModal');
      modal.style.display = 'none';
      // Reset form
      document.getElementById('reservationForm').reset();
    }

    // Populate purpose options based on selected facility
    document.getElementById('facility_select').addEventListener('change', function() {
      const facility = this.value;
      const purposeSelect = document.getElementById('purpose');
      purposeSelect.innerHTML = '<option value="">Select Purpose</option>';

      if (facility === 'Clubhouse') {
        purposeSelect.innerHTML += `
          <option value="Birthday Party">Birthday Party</option>
          <option value="Dance Practice">Dance Practice</option>
          <option value="Wedding Reception">Wedding Reception</option>
          <option value="Community Meeting">Community Meeting</option>
          <option value="Other">Other</option>
        `;
      } else if (facility === 'Basketball Court') {
        purposeSelect.innerHTML += `
          <option value="General Reservation">General Reservation</option>
          <option value="Tournament">Tournament</option>
          <option value="Practice">Practice</option>
          <option value="Other">Other</option>
        `;
      }
    });

    // Handle reservation form submission
    document.getElementById('reservationForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('process_reservation.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Reservation submitted successfully!');
          closeReservationModal();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the reservation.');
      });
    });

    // Close reservation modal when clicking outside
    window.onclick = function(event) {
      const maintenanceModal = document.getElementById('maintenance-modal');
      const reservationModal = document.getElementById('reservationModal');
      if (event.target == maintenanceModal) {
        maintenanceModal.style.display = 'none';
      }
      if (event.target == reservationModal) {
        closeReservationModal();
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
