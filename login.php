<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Maia Alta HOA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/login.css">
  <link rel="icon" type="image/png" href="pics/Courtyard.png">
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <img src="pics/Courtyard.png" alt="Courtyard Logo" class="login-logo">
        <h1>Maia Alta Homes</h1>
        <p>Homeowners Association Portal</p>
      </div>

      <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <?php 
            if($_GET['error'] == 'invalid') {
              echo 'Invalid username or password';
            } elseif($_GET['error'] == 'empty') {
              echo 'Please fill in all fields';
            } elseif($_GET['error'] == 'logout') {
              echo 'You have been logged out';
            }
          ?>
        </div>
      <?php endif; ?>

      <?php if(isset($_GET['success']) && $_GET['success'] == 'logout'): ?>
        <div class="alert alert-success">
          Successfully logged out
        </div>
      <?php endif; ?>

      <form action="auth/login_process.php" method="POST" class="login-form">
        <div class="form-group">
          <label for="username">Account Number</label>
          <input type="text" id="username" name="username" placeholder="Enter your account number" required autofocus>
          <span class="input-hint">Provided by the Treasurer's Office</span>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
          <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" class="login-btn">Login</button>
      </form>

      <div class="login-footer">
        <p>Don't have an account? Contact the Treasurer's Office</p>
        <p class="demo-text">Demo Accounts:<br>
          Admin: <strong>ADMIN001</strong> | Resident: <strong>MAIA-2025-001</strong><br>
          Password: <strong>admin123</strong></p>
      </div>
    </div>
  </div>
</body>
</html>
