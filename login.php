<?php
session_start();
include ("connect.php");

// If already logged in, redirect straight to their area

if (!empty($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/admin_homepage.php');
        exit;
    } else {
        header('Location: users/users_homepage.php');
        exit;
    }
}
$reason = $_GET['reason'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | JerseyFlow</title>
<link rel="stylesheet" href="style/login.css">
<link rel="icon" href="images/logo_icon.ico">
</head>
<body>

<?php include("navbar.php"); ?>

<!-- SUCCESS POPUP -->
<div id="loginSuccessBox">
  <div class="success-content">

    <div class="check">✔</div>

    <h2 id="welcomeText">Welcome Back</h2>
    <p>Redirecting...</p>

    <div class="loader">
      <div class="bar"></div>
    </div>

  </div>
</div>

<div class="login-container">

  <div class="login-brand">
    <div class="bg-slide slide1"></div>
    <div class="bg-slide slide2"></div>
    <div class="bg-slide slide3"></div>
    <div class="bg-slide slide4"></div>
    <div class="bg-slide slide5"></div>
  </div>

  <div class="login-card">

    <h2>Welcome Back</h2>

    <!-- ── Unauthorized redirect alert ─────────────────── -->
    <?php if ($reason === 'unauthorized'): ?>
      <div class="alert-unauthorized">
        🔒 Please log in as an admin to access that page.
      </div>
    <?php endif; ?>

    <!-- ── Generic error (e.g. future use) ─────────────── -->
    <?php if ($reason === 'session_expired'): ?>
      <div class="alert-unauthorized">
        ⏱ Your session has expired. Please log in again.
      </div>
    <?php endif; ?>

    <form id="loginForm">

      <div class="input-group">
        <input type="email" name="email" id="email" required>
        <label>Email</label>
        <span class="error" id="emailError"></span>
      </div>

      <div class="input-group">
        <input type="password" name="password" id="password" required>
        <label>Password</label>
        <span class="toggle" onclick="togglePassword()">👁</span>
        <span class="error" id="passError"></span>
      </div>

      <button type="submit" class="btn">Login</button>

      <p class="register-link">
        Don't have an account?
        <a href="register.php">Register now</a>
      </p>

    </form>

  </div>

</div>

<script src="script/login.js"></script>

</body>
</html>