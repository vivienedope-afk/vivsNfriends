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
  menuBtn.style.display = 'none';
}

function goBack() {
  window.history.back();
}

function toggleVisibility(contentId, button) {
  const content = document.getElementById(contentId);
  content.classList.toggle('visible');
  // Optionally, change the eye icon to indicate state
  const eyeIcon = button.querySelector('.eye-icon');
  if (content.classList.contains('visible')) {
    eyeIcon.textContent = '🙈'; // Closed eye when visible
  } else {
    eyeIcon.textContent = '👁'; // Open eye when hidden
  }
}

// Amenities Reservation Modal Functions
function openReservationModal(facility) {
  const modal = document.getElementById('reservationModal');
  const facilityNameSpan = document.getElementById('facilityName');
  const facilityInput = document.getElementById('facility');
  const purposeSelect = document.getElementById('purpose');

  facilityNameSpan.textContent = facility;
  facilityInput.value = facility;

  // Clear previous options
  purposeSelect.innerHTML = '<option value="">Select Purpose</option>';

  // Populate purpose options based on facility
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

  modal.style.display = 'block';
}

function closeReservationModal() {
  const modal = document.getElementById('reservationModal');
  modal.style.display = 'none';
  // Reset form
  document.getElementById('reservationForm').reset();
}

// Handle form submission
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

// Settings Modal Functions
function openSettingsModal() {
  const modal = document.getElementById('settingsModal');
  modal.style.display = 'block';

  // Load current preferences
  loadNotificationPreferences();
}

function closeSettingsModal() {
  const modal = document.getElementById('settingsModal');
  modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('settingsModal');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
}

// Load notification preferences from server
function loadNotificationPreferences() {
  fetch('save_notification_settings.php?action=get', {
    credentials: 'same-origin'  // Include session cookies
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('email_notifications').checked = data.preferences.email_notifications;
        document.getElementById('sms_notifications').checked = data.preferences.sms_notifications;
      } else {
        console.error('Error loading preferences:', data.message);
      }
    })
    .catch(error => {
      console.error('Error loading preferences:', error);
    });
}

// Save notification settings
function saveNotificationSettings() {
  const emailNotifications = document.getElementById('email_notifications').checked;
  const smsNotifications = document.getElementById('sms_notifications').checked;

  const formData = new FormData();
  formData.append('email_notifications', emailNotifications ? 1 : 0);
  formData.append('sms_notifications', smsNotifications ? 1 : 0);

  fetch('save_notification_settings.php?action=save', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'  // Include session cookies
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok: ' + response.status);
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      alert('Notification settings saved successfully!');
      closeSettingsModal();
    } else {
      alert('Error saving settings: ' + data.message);
      console.error('Server error:', data);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while saving settings: ' + error.message);
  });
}

// Ledger Modal Functions
function openLedgerModal() {
  const modal = document.getElementById('ledgerModal');
  modal.style.display = 'block';
}

function closeLedgerModal() {
  const modal = document.getElementById('ledgerModal');
  modal.style.display = 'none';
}

// Send Test Notification
function sendTestNotification() {
  if (!confirm('Send a test notification to your email and SMS (if enabled)?')) {
    return;
  }

  fetch('save_notification_settings.php?action=test', {
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Test notification sent! Check your email and SMS (if enabled).');
    } else {
      alert('Error sending test notification: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while sending test notification.');
  });
}

// Load Notification History
function loadNotificationHistory() {
  fetch('save_notification_settings.php?action=history&limit=50', {
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      displayNotificationHistory(data.history);
    } else {
      alert('Error loading notification history: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while loading notification history.');
  });
}

// Display Notification History
function displayNotificationHistory(history) {
  const modal = document.getElementById('notificationHistoryModal');
  const historyList = document.getElementById('notificationHistoryList');
  
  if (!modal) {
    // Create modal if it doesn't exist
    const newModal = document.createElement('div');
    newModal.id = 'notificationHistoryModal';
    newModal.className = 'modal';
    newModal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h2>Notification History</h2>
          <span class="close" onclick="closeNotificationHistoryModal()">&times;</span>
        </div>
        <div class="modal-body">
          <div id="notificationHistoryList" style="max-height: 400px; overflow-y: auto;"></div>
        </div>
      </div>
    `;
    document.body.appendChild(newModal);
  }

  const list = document.getElementById('notificationHistoryList');
  
  if (history.length === 0) {
    list.innerHTML = '<p>No notifications sent yet.</p>';
  } else {
    let html = '<table style="width: 100%; border-collapse: collapse;">';
    html += '<tr style="border-bottom: 2px solid #ddd;"><th style="text-align: left; padding: 10px;">Type</th><th style="text-align: left; padding: 10px;">Subject</th><th style="text-align: left; padding: 10px;">Status</th><th style="text-align: left; padding: 10px;">Date</th></tr>';
    
    history.forEach(item => {
      const statusColor = item.status === 'sent' ? '#4CAF50' : item.status === 'skipped' ? '#FFC107' : '#F44336';
      html += `<tr style="border-bottom: 1px solid #ddd;">
        <td style="padding: 10px;">${item.notification_type}</td>
        <td style="padding: 10px;">${item.subject}</td>
        <td style="padding: 10px; color: ${statusColor}; font-weight: bold;">${item.status}</td>
        <td style="padding: 10px;">${item.created_at}</td>
      </tr>`;
    });
    
    html += '</table>';
    list.innerHTML = html;
  }
  
  document.getElementById('notificationHistoryModal').style.display = 'block';
}

function closeNotificationHistoryModal() {
  const modal = document.getElementById('notificationHistoryModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

// Close modal when clicking outside
window.onclick = function(event) {
  const settingsModal = document.getElementById('settingsModal');
  const ledgerModal = document.getElementById('ledgerModal');
  const historyModal = document.getElementById('notificationHistoryModal');
  
  if (event.target == settingsModal) {
    settingsModal.style.display = 'none';
  }
  if (event.target == ledgerModal) {
    ledgerModal.style.display = 'none';
  }
  if (event.target == historyModal) {
    historyModal.style.display = 'none';
  }
}
