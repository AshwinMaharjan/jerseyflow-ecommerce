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
(full_name, email, password, phone, address, profile_image)
VALUES
('$full_name', '$email', '$hashed', '$phone', '$address', '$profile_image')";

if(mysqli_query($conn, $query)){
    echo "success";
} else {
    echo "Error occurred";
}
?>