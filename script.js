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
