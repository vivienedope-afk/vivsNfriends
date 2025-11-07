<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <button class="menu-btn" onclick="toggleMenu()">
    <i class="fas fa-bars"></i>
  </button>

  <nav class="navbar" id="sidebar">
    <button class="close-btn" onclick="toggleMenu()">
      <i class="fas fa-times"></i>
    </button>
    <img src="pics/Courtyard.png" alt="Courtyard Logo" class="logo">
    <ul class="nav-links">
      <li><a href="account.php" onclick="closeMenu()"><span class="icon"><i class="fas fa-user"></i></span><span class="text">Account</span></a></li>
      <li class="active"><a href="index.php" onclick="closeMenu()"><span class="icon"><i class="fas fa-chart-line"></i></span><span class="text">Dashboard</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="text">Calendar</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-swimming-pool"></i></span><span class="text">Amenities</span></a></li>
      <li><a href="#" onclick="closeMenu()"><span class="icon"><i class="fas fa-sign-in-alt"></i></span><span class="text">Login</span></a></li>
    </ul>
    <button class="back-button" onclick="goBack()">
      <i class="fas fa-arrow-left"></i>
    </button>
  </nav>

  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <main>
    <div class="info-box">
      <button class="close-info-btn" onclick="closeInfoBox()">×</button>
      <p>Welcome to Courtyard of Maia Alta, a tranquil residential community nestled in nature, offering modern amenities and a peaceful lifestyle. Here, you can enjoy serene surroundings, state-of-the-art facilities, and a strong sense of community. Whether you're relaxing in our lush courtyards or participating in neighborhood events, Courtyard of Maia Alta is your home away from the hustle and bustle.</p>
      <h3>Rules and Regulations</h3>
      <ul>
        <li>Respect quiet hours from 10 PM to 6 AM to ensure a peaceful environment for all residents.</li>
        <li>Keep common areas clean and report any maintenance issues promptly.</li>
        <li>Parking is limited to designated spots; unauthorized vehicles may be towed.</li>
        <li>Pets must be leashed and cleaned up after in public spaces.</li>
        <li>Follow all HOA guidelines regarding property modifications and landscaping.</li>
      </ul>
    </div>
    <h1>Dashboard</h1>
    <section class="dashboard">
      <div class="card event">
        <div class="card-header">
          <i class="fas fa-calendar-alt"></i> Upcoming Events
        </div>
        <div class="card-content">
          <p><i class="fas fa-home"></i> Community Cleanup - Nov 10</p>
          <p><i class="fas fa-tree"></i> Christmas Party - Dec 20</p>
        </div>
      </div>

      <div class="card dues">
        <div class="card-header">
          <i class="fas fa-money-bill-wave"></i> Missed Dues
        </div>
        <div class="card-content">
          <p>You have <strong>1 unpaid due</strong> for October.</p>
          <p>Please settle before <b>Nov 10</b> to avoid late fees.</p>
        </div>
      </div>

      <div class="card notice">
        <div class="card-header">
          <i class="fas fa-wrench"></i> Maintenance Notices
        </div>
        <div class="card-content">
          <p>Water maintenance scheduled for <b>Nov 15</b>.</p>
          <p>Expect low pressure between 8 AM - 2 PM.</p>
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
