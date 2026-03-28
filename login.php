<?php session_start(); ?>
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

<!-- ✅ SUCCESS POPUP (CENTER LIKE REGISTER) -->
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

// ✅ LOGIN HANDLER (AJAX LIKE REGISTER)
document.getElementById("loginForm").addEventListener("submit", function(e){
  e.preventDefault();

  let email = document.getElementById("email").value;
  let password = document.getElementById("password").value;

  fetch("process_login.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: `email=${email}&password=${password}`
  })
  .then(res => res.text())
  .then(data => {

    if(data.startsWith("success")){
      let parts = data.split("|");
      let role = parts[1];
      let name = parts[2];

      // dynamic name
      document.getElementById("welcomeText").innerText = "Welcome back, " + name + "!";

      let box = document.getElementById("loginSuccessBox");
      box.style.display = "flex";

      setTimeout(() => {
        if(role === "admin"){
          window.location.href = "admin/admin_homepage.php";
        } else {
          window.location.href = "users/users_homepage.php";
        }
      }, 2500);

    } else {
      document.getElementById("passError").innerText = data;
    }

  });
});
</script>

</body>
</html>