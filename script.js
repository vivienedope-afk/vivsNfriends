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
