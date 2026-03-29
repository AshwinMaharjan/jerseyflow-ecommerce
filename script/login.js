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
