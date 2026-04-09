<?php
/**
 * create_order.php
 *
 * Called by checkout.php form submission (POST).
 * Responsibilities:
 *   1. Re-fetch cart items & prices from DB (NEVER trust frontend values)
 *   2. Validate stock availability
 *   3. Insert into `orders` (status: pending)
 *   4. Insert all items into `order_items`
 *   5. Store order_id in session
 *   6. Redirect:
 *        - eSewa  → esewa_payment.php
 *        - COD    → cod_handler.php
 */

session_start();
require_once 'connect.php';

/* ── Auth guard ─────────────────────────────────────────── */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* ── Only accept POST ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit();
}

/* ══════════════════════════════════════════════════════════
   1. PARSE & SANITISE INPUTS
   ══════════════════════════════════════════════════════════ */
$payment_method = trim($_POST['payment_method'] ?? '');
if (!in_array($payment_method, ['esewa', 'cod'], true)) {
    die('Invalid payment method.');
}

$address_id = (int) ($_POST['address_id'] ?? 0);
if ($address_id <= 0) {
    // Redirect back with error
    header('Location: checkout.php?error=no_address');
    exit();
}

// Parse selected cart IDs
$selected_ids = [];
if (!empty($_POST['selected_cart_ids'])) {
    foreach (explode(',', $_POST['selected_cart_ids']) as $id) {
        $id = (int) trim($id);
        if ($id > 0) $selected_ids[] = $id;
    }
}

if (empty($selected_ids)) {
    header('Location: cart.php?error=empty');
    exit();
}

/* ══════════════════════════════════════════════════════════
   2. VERIFY ADDRESS BELONGS TO THIS USER
   ══════════════════════════════════════════════════════════ */
$addr_stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
$addr_stmt->bind_param('ii', $address_id, $user_id);
$addr_stmt->execute();
$addr_stmt->store_result();
if ($addr_stmt->num_rows === 0) {
    $addr_stmt->close();
    die('Invalid address.');
}
$addr_stmt->close();

/* ══════════════════════════════════════════════════════════
   3. RE-FETCH CART ITEMS WITH PRICES FROM product_variants
      This is the AUTHORITATIVE price source — never trust
      anything sent from the browser.
   ══════════════════════════════════════════════════════════ */
$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$types        = str_repeat('i', count($selected_ids) + 1);

$cart_sql = "
    SELECT
        c.cart_id,
        c.product_id,
        c.variant_id,
        c.quantity,
        pv.price,
        pv.stock,
        pv.size
    FROM cart c
    JOIN product_variants pv ON c.variant_id = pv.variant_id
    WHERE c.user_id = ?
      AND c.cart_id IN ($placeholders)
";

$stmt = $conn->prepare($cart_sql);
$params = array_merge([$user_id], $selected_ids);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$cart_items   = [];
$total_amount = 0.00;

while ($row = $result->fetch_assoc()) {
    $row['subtotal'] = (float)$row['price'] * (int)$row['quantity'];
    $total_amount   += $row['subtotal'];
    $cart_items[]    = $row;
}
$stmt->close();

if (empty($cart_items)) {
    header('Location: cart.php?error=empty');
    exit();
}

/* ══════════════════════════════════════════════════════════
   4. STOCK VALIDATION — check before touching the DB
   ══════════════════════════════════════════════════════════ */
foreach ($cart_items as $item) {
    if ((int)$item['quantity'] > (int)$item['stock']) {
        // Redirect back with a stock error
        $msg = urlencode("Not enough stock for size " . $item['size'] . ". Available: " . $item['stock']);
        header("Location: checkout.php?error=stock&msg=$msg&selected_cart_ids=" . implode(',', $selected_ids));
        exit();
    }
}

/* ══════════════════════════════════════════════════════════
   5. RESOLVE method_id FROM payment_methods TABLE
      Assumes a `payment_methods` table with a `slug` column.
      Adjust if your table uses a different column name.
   ══════════════════════════════════════════════════════════ */
$method_slug  = ($payment_method === 'esewa') ? 'esewa' : 'cod';
$method_stmt  = $conn->prepare("SELECT method_id FROM payment_methods WHERE slug = ?");
$method_stmt->bind_param('s', $method_slug);
$method_stmt->execute();
$method_result = $method_stmt->get_result();
$method_row    = $method_result->fetch_assoc();
$method_stmt->close();

$method_id = $method_row ? (int)$method_row['method_id'] : null;

/* ══════════════════════════════════════════════════════════
   6. DATABASE TRANSACTION — insert order + order_items
   ══════════════════════════════════════════════════════════ */
$conn->begin_transaction();

try {

    /* ── 6a. Insert into orders ─────────────────────────── */
    $order_sql = "
        INSERT INTO orders
            (user_id, total_amount, order_status, payment_status, method_id, address_id, created_at, updated_at)
        VALUES
            (?, ?, 'pending', 'pending', ?, ?, NOW(), NOW())
    ";

    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param('idii', $user_id, $total_amount, $method_id, $address_id);
    $order_stmt->execute();

    $order_id = (int) $conn->insert_id;
    $order_stmt->close();

    if ($order_id <= 0) {
        throw new Exception('Failed to create order.');
    }

    /* ── 6b. Insert into order_items ────────────────────── */
    $item_sql = "
        INSERT INTO order_items
            (order_id, product_id, variant_id, quantity, unit_price, subtotal)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ";

    $item_stmt = $conn->prepare($item_sql);

    foreach ($cart_items as $item) {
        $item_stmt->bind_param(
            'iiidd',
            $order_id,
            $item['product_id'],
            $item['variant_id'],
            $item['quantity'],
            $item['price'],      // unit_price from product_variants
            $item['subtotal']
        );
        $item_stmt->execute();
    }

    $item_stmt->close();

    /* ── Commit ─────────────────────────────────────────── */
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log('[create_order] Transaction failed: ' . $e->getMessage());
    die('Order could not be placed. Please try again.');
}

/* ══════════════════════════════════════════════════════════
   7. STORE order_id IN SESSION
      esewa_payment.php and cod_handler.php will read this.
   ══════════════════════════════════════════════════════════ */
$_SESSION['pending_order_id']     = $order_id;
$_SESSION['pending_payment_method'] = $payment_method;

/* ══════════════════════════════════════════════════════════
   8. REDIRECT BASED ON PAYMENT METHOD
   ══════════════════════════════════════════════════════════ */
if ($payment_method === 'esewa') {
    header('Location: esewa_payment.php');
} else {
    header('Location: cod_handler.php');
}
exit();