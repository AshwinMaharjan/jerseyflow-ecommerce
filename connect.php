<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$user = "root";       
$pass = "";           
$dbname = "jerseyflow";

$conn = mysqli_connect($servername, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
