<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register | JerseyFlow</title>
<link rel="stylesheet" href="style/register.css">
<link rel="icon" href="images/logo_icon.ico" type="image/x-icon">

</head>
<body>

<?php include("navbar.php"); ?>

<div id="successBox">
  <div class="success-content">

    <div class="check">✔</div>

    <h2>Registration Successful</h2>
    <p>Redirecting to login...</p>

    <div class="loader">
      <div class="bar"></div>
    </div>

  </div>
</div>

<div class="register-container">

  <div class="register-brand">
    <div class="bg-slide slide1"></div>
    <div class="bg-slide slide2"></div>
    <div class="bg-slide slide3"></div>
    <div class="bg-slide slide4"></div>
    <div class="bg-slide slide5"></div>
  </div>

  <div class="register-card">

    <h2 style="padding-bottom: 30px;">Create Account</h2>

    <form action="process_register.php" method="POST" enctype="multipart/form-data" id="registerForm">

      <div class="input-group">
        <input type="text" name="full_name" id="full_name">
        <label>Full Name</label>
        <span class="error" id="nameError"></span>
      </div>

      <div class="input-group">
        <input type="email" name="email" id="email">
        <label>Email</label>
        <span class="error" id="emailError"></span>
      </div>

      <div class="input-group">
        <input type="password" name="password" id="password">
        <label>Password</label>
      </div>

      <div class="input-group">
        <input type="password" name="confirm_password" id="confirm_password">
        <label>Confirm Password</label>
        <span class="error" id="passError"></span>
      </div>

      <div class="input-group">
        <input type="text" name="phone" id="phone">
        <label>Phone</label>
        <span class="error" id="phoneError"></span>
      </div>

      <div class="input-group">
        <textarea name="address" id="address"></textarea>
        <label>Address</label>
      </div>

      <div class="input-group">
        <input type="file" name="profile_image" id="profile_image" accept="image/*">
        <label>Profile Image</label>
      </div>

      <button type="submit" class="btn">Register</button>

      <p class="login-link">
        Already have account? <a href="login.php">Login</a>
      </p>

    </form>

  </div>

</div>

<script>
// ---------------- TOAST ----------------
function showToast(message, type){
  let toast = document.getElementById("toast");
  toast.innerText = message;
  toast.className = type;
  toast.style.opacity = "1";

  setTimeout(() => {
    toast.style.opacity = "0";
  }, 3000);
}

// ---------------- LIVE VALIDATION ----------------

// Name validation (alphabets only)
document.getElementById("full_name").addEventListener("input", function(){
  let name = this.value;
  if(!/^[A-Za-z\s]*$/.test(name)){
    document.getElementById("nameError").innerText = "Only letters allowed";
  } else {
    document.getElementById("nameError").innerText = "";
  }
});

// Email validation
document.getElementById("email").addEventListener("input", function(){
  let email = this.value;
  let regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if(!regex.test(email)){
    document.getElementById("emailError").innerText = "Invalid email format";
  } else {
    document.getElementById("emailError").innerText = "";

    // AJAX check email exists
    fetch("check_email.php?email=" + email)
    .then(res => res.text())
    .then(data => {
      if(data == "exists"){
        document.getElementById("emailError").innerText = "Email already exists";
      }
    });
  }
});

// Phone validation
document.getElementById("phone").addEventListener("input", function(){
  let phone = this.value;

  if(!/^\d*$/.test(phone)){
    document.getElementById("phoneError").innerText = "Only numbers allowed";
  } else if(phone.length > 10){
    this.value = phone.slice(0,10);
  } else {
    document.getElementById("phoneError").innerText = "";
  }
});

// Password match check
document.getElementById("confirm_password").addEventListener("input", function(){
  let pass = document.getElementById("password").value;
  let confirm = this.value;

  if(pass !== confirm){
    document.getElementById("passError").innerText = "Passwords do not match";
  } else {
    document.getElementById("passError").innerText = "";
  }
});

// ---------------- FINAL SUBMIT ----------------
document.getElementById("registerForm").addEventListener("submit", function(e){
  e.preventDefault();

  let name = document.getElementById("full_name").value;
  let emailError = document.getElementById("emailError").innerText;
  let passError = document.getElementById("passError").innerText;
  let phoneError = document.getElementById("phoneError").innerText;

  if(name === "" || emailError || passError || phoneError){
    showToast("Fix errors before submitting", "error");
    return;
  }

  let formData = new FormData(this);

  fetch("process_register.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
.then(data => {
  if(data == "success"){

    let box = document.getElementById("successBox");
    box.style.display = "flex";

    setTimeout(() => {
      window.location.href = "login.php";
    }, 2500);

  } else {
    document.getElementById("emailError").innerText = data;
  }
});

});
</script>
<?php
  include("connect.php");

$full_name = $_POST['full_name'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm = $_POST['confirm_password'] ?? '';
$phone = $_POST['phone'];
$address = $_POST['address'];

/* VALIDATION (same as before) */
if($password !== $confirm){
    echo "Passwords do not match";
    exit();
}

/* CHECK DUPLICATE EMAIL */
$check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
if(mysqli_num_rows($check) > 0){
    echo "Email already exists";
    exit();
}

/* IMAGE UPLOAD HANDLING */
$profile_image = "default.png";

if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0){

    $img_name = $_FILES['profile_image']['name'];
    $img_tmp = $_FILES['profile_image']['tmp_name'];

    $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));

    $allowed = array("jpg", "jpeg", "png", "webp");

    if(in_array($img_ext, $allowed)){

        $new_name = time() . "_" . rand(1000,9999) . "." . $img_ext;

        $upload_path = "uploads/" . $new_name;

        move_uploaded_file($img_tmp, $upload_path);

        $profile_image = $new_name;

    } else {
        echo "Invalid image format";
        exit();
    }
}

/* HASH PASSWORD */
$hashed = password_hash($password, PASSWORD_DEFAULT);

/* INSERT INTO DATABASE */
$query = "INSERT INTO users 
(full_name, email, password, phone, address, profile_image, status, role)
VALUES
('$full_name', '$email', '$hashed', '$phone', '$address', '$profile_image', 'active', 'user')";

if(mysqli_query($conn, $query)){
    echo "success";
} else {
    echo "Error occurred";
}
?>

</body>
</html>