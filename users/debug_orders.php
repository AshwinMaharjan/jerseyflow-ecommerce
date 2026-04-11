<?php
// debug_orders.php — place in the same folder as all_orders.php, run once, then delete
session_start();
require_once '../connect.php';

if (empty($_SESSION['user_id'])) {
    die('Not logged in.');
}

$user_id = (int) $_SESSION['user_id'];

// 1. Show raw orders table columns
echo "<h2>orders table — column names</h2><pre>";
$r = $conn->query("DESCRIBE orders");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
echo "</pre>";

// 2. Show raw rows for this user (no GROUP BY, no JOIN)
echo "<h2>Raw orders rows for user_id = $user_id</h2><pre>";
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? LIMIT 5");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
print_r($rows);
echo "</pre>";

// 3. Show what the full query returns
echo "<h2>Full query result</h2><pre>";
$sql = "
    SELECT
        o.order_id,
        o.total_amount,
        o.order_status,
        o.payment_status,
        o.method_id,
        o.created_at,
        COUNT(oi.order_item_id) AS total_products,
        SUM(oi.quantity)        AS total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY
        o.order_id, o.total_amount, o.order_status, o.payment_status,
        o.method_id, o.created_at
    LIMIT 5
";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
print_r($rows2);
echo "</pre>";
?>