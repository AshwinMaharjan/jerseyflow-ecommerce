<?php
session_start();

$conn = new mysqli("localhost", "root", "", "jerseyflow");

$email = $_POST['email'];
$password = $_POST['password'];

// prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    $user = $result->fetch_assoc();

    if(password_verify($password, $user['password'])){
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];

        // ✅ success popup (will show in homepage)
        $_SESSION['popup'] = [
            "type" => "success",
            "message" => "Login successful!"
        ];

        if($user['role'] === 'admin'){
            header("Location: admin/homepage.php");
        } else if($user['role'] === 'user'){
            header("Location: users/homepage.php");
        } else {
            $_SESSION['popup'] = [
                "type" => "error",
                "message" => "Invalid role!"
            ];
            header("Location: login.php");
        }

        exit();

    } else {
        $_SESSION['popup'] = [
            "type" => "error",
            "message" => "Incorrect password!"
        ];
        header("Location: login.php");
        exit();
    }

} else {
    $_SESSION['popup'] = [
        "type" => "error",
        "message" => "User not found!"
    ];
    header("Location: login.php");
    exit();
}
?>