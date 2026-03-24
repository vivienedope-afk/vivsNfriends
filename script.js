const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const menuBtn = document.querySelector('.menu-btn');

function toggleMenu() {
  if (!sidebar || !overlay || !menuBtn) return;
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
  menuBtn.style.opacity = sidebar.classList.contains('open') ? '0' : '1';
}

function closeMenu() {
  if (!sidebar || !overlay || !menuBtn) return;
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
  menuBtn.style.opacity = '1';
}

function goBack() {
  window.history.back();
}

function toggleVisibility(contentId, button) {
  const content = document.getElementById(contentId);
  if (!content || !button) return;
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

  if (!modal || !facilityNameSpan || !facilityInput || !purposeSelect) return;

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
  const form = document.getElementById('reservationForm');
  if (!modal) return;
  modal.style.display = 'none';
  if (form) {
    form.reset();
  }
}

// Handle form submission
const reservationForm = document.getElementById('reservationForm');
if (reservationForm) {
  reservationForm.addEventListener('submit', function(e) {
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
}

// Settings Modal Functions
function openSettingsModal() {
  const modal = document.getElementById('settingsModal');
  if (!modal) return;
  modal.style.display = 'block';

  // Load current preferences
  loadNotificationPreferences();
}

function closeSettingsModal() {
  const modal = document.getElementById('settingsModal');
  if (!modal) return;
  modal.style.display = 'none';
}

// Load notification preferences from server
function loadNotificationPreferences() {
  const emailEl = document.getElementById('email_notifications');
  const smsEl = document.getElementById('sms_notifications');
  if (!emailEl || !smsEl) return;

  fetch('save_notification_settings.php?action=get', {
    credentials: 'same-origin'  // Include session cookies
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        emailEl.checked = data.preferences.email_notifications;
        smsEl.checked = data.preferences.sms_notifications;
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
  const emailEl = document.getElementById('email_notifications');
  const smsEl = document.getElementById('sms_notifications');
  if (!emailEl || !smsEl) return;

  const emailNotifications = emailEl.checked;
  const smsNotifications = smsEl.checked;

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
  if (!modal) return;
  modal.style.display = 'block';
}

function closeLedgerModal() {
  const modal = document.getElementById('ledgerModal');
  if (!modal) return;
  modal.style.display = 'none';
}

function toggleEditProfile() {
  const card = document.getElementById('editProfileCard');
  if (!card) return;
  card.classList.toggle('collapsed');
}

function resetForm() {
  const form = document.getElementById('profileForm');
  if (!form) return;
  form.reset();
}

function editUserInfo() {
  const card = document.getElementById('editProfileCard');
  if (!card) return;
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function verifyIdentity() {
  alert('Identity verification request recorded. Admin will contact you if documents are needed.');
}

function updateProfile(event) {
  event.preventDefault();

  const form = document.getElementById('profileForm');
  if (!form) return;

  const status = document.getElementById('formStatus');
  const formData = new FormData(form);

  fetch('update_profile.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (status) {
          status.style.display = 'flex';
          setTimeout(() => {
            status.style.display = 'none';
          }, 2500);
        } else {
          alert('Profile updated successfully!');
        }
      } else {
        alert('Error: ' + (data.message || 'Unable to update profile'));
      }
    })
    .catch(error => {
      console.error('Update profile error:', error);
      alert('An error occurred while updating profile.');
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
  const settingsModal = document.getElementById('settingsModal');
  const ledgerModal = document.getElementById('ledgerModal');
  const reservationModal = document.getElementById('reservationModal');
  if (event.target == settingsModal) {
    settingsModal.style.display = 'none';
  }
  if (event.target == ledgerModal) {
    ledgerModal.style.display = 'none';
  }
  if (event.target == reservationModal) {
    reservationModal.style.display = 'none';
  }
}
