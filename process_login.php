<?php 
session_start();

$conn = new mysqli("localhost", "root", "", "jerseyflow");

$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // ── Block soft-deleted accounts ───────────────────────
    if ($user['is_deleted'] == 1) {
        echo "Your account has been removed.";
        exit;
    }

    // ── Block suspended / inactive accounts ───────────────
    if ($user['status'] !== 'active') {
        echo "Your account is suspended. Contact support.";
        exit;
    }

    // ── Verify password ───────────────────────────────────
    if (password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['full_name'];

        echo "success|" . $user['role'] . "|" . $user['full_name'];

    } else {
        echo "Incorrect password!";
    }

} else {
    echo "User not found!";
}
?>