<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | JerseyFlow</title>
<link rel="stylesheet" href="style/login.css">
<link rel="icon" href="images/logo_icon.ico" type="image/x-icon">
</head>
<body>

<!-- NAVBAR -->
<?php include("navbar.php"); ?>

<div class="login-container">

  <!-- LEFT -->
  <div class="login-brand">
    <div class="bg-slide slide1"></div>
    <div class="bg-slide slide2"></div>
    <div class="bg-slide slide3"></div>
    <div class="bg-slide slide4"></div>
    <div class="bg-slide slide5"></div>
  </div>

  <!-- RIGHT -->
  <div class="login-card">

    <h2>Welcome Back</h2>

    <form action="process_login.php" method="POST" id="loginForm">

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

      <!-- REGISTER LINK -->
      <p class="register-link">
        Don’t have an account?
        <a href="register.php">Register now</a>
      </p>
    </form>

  </div>

</div>

<script>
function togglePassword() {
  let p = document.getElementById("password");
  p.type = p.type === "password" ? "text" : "password";
}

document.getElementById("loginForm").addEventListener("submit", function(e){
  let valid = true;

  let email = document.getElementById("email");
  let pass = document.getElementById("password");

  document.getElementById("emailError").innerText = "";
  document.getElementById("passError").innerText = "";

  if(!email.value.includes("@")){
    document.getElementById("emailError").innerText = "Enter valid email";
    valid = false;
  }

  if(pass.value.length < 6){
    document.getElementById("passError").innerText = "Minimum 6 characters";
    valid = false;
  }

  if(!valid) e.preventDefault();
});
function showPopup(type, message) {
  let popup = document.createElement("div");
  popup.className = "popup " + type;
  popup.innerText = message;

  document.body.appendChild(popup);

  setTimeout(() => {
    popup.classList.add("show");
  }, 100);

  setTimeout(() => {
    popup.classList.remove("show");
    setTimeout(() => popup.remove(), 300);
  }, 2500);
}
</script>
<?php if(isset($_SESSION['popup'])): ?>
<script>
  window.onload = function() {
    showPopup(
      "<?= $_SESSION['popup']['type']; ?>",
      "<?= $_SESSION['popup']['message']; ?>"
    );
  }
</script>
<?php unset($_SESSION['popup']); endif; ?>
</body>
</html>