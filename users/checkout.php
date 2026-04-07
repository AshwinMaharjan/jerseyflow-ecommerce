<?php

session_start();
require_once 'connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=checkout.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── Resolve selected cart IDs ─────────────────────────────────
// Support POST from cart form OR session (for "Buy Now")
if (!empty($_POST['selected_cart_ids'])) {
    $raw_ids = array_map('intval', explode(',', $_POST['selected_cart_ids']));
    $selected_ids = array_filter($raw_ids, fn($id) => $id > 0);
    // Persist in session so page refreshes don't re-POST
    $_SESSION['checkout_cart_ids'] = $selected_ids;
} elseif (!empty($_SESSION['checkout_cart_ids'])) {
    $selected_ids = $_SESSION['checkout_cart_ids'];
} else {
    // No selection — redirect back
    header('Location: cart.php');
    exit;
}

// ── Fetch only selected items (scoped to this user for safety) ─
if (empty($selected_ids)) {
    header('Location: cart.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$types        = str_repeat('i', count($selected_ids) + 1);
$params       = array_merge([$user_id], $selected_ids);

$stmt = $conn->prepare(
    "SELECT c.cart_id, c.quantity, c.size,
            p.product_id, p.product_name, p.price, p.stock
     FROM cart c
     JOIN products p ON p.product_id = c.product_id
     WHERE c.user_id = ?
       AND c.cart_id IN ($placeholders)"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$checkout_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

