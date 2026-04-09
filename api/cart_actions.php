<?php
/**
 * cart_actions.php — JerseyFlow
 * AJAX endpoint for cart operations (add / update / remove / clear)
 * Always returns JSON.
 */

session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// ── Helper: send JSON response and exit ──────────────────────
function respond(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $extra
    ));
    exit;
}

// ── Only accept POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// ── User must be logged in ───────────────────────────────────
if (empty($_SESSION['user_id'])) {
    respond(false, 'You must be logged in to manage your cart.');
}

$user_id = (int)$_SESSION['user_id'];
$action  = trim($_POST['action'] ?? '');

// ════════════════════════════════════════════════════════════
// ACTION: add
// POST: product_id, size, qty
// ════════════════════════════════════════════════════════════
if ($action === 'add') {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $size       = trim($_POST['size']        ?? '');
    $qty        = max(1, min(10, (int)($_POST['qty'] ?? 1)));

    if ($product_id <= 0 || $size === '') {
        respond(false, 'Invalid product or size.');
    }

    // ── Verify product exists and has stock ──────────────────
    $p_stmt = $conn->prepare(
        "SELECT product_id, product_name, price, stock FROM products WHERE product_id = ? LIMIT 1"
    );
    $p_stmt->bind_param('i', $product_id);
    $p_stmt->execute();
    $product = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();

    if (!$product) {
        respond(false, 'Product not found.');
    }
    if ((int)$product['stock'] <= 0) {
        respond(false, 'This product is out of stock.');
    }
    if ($qty > (int)$product['stock']) {
        respond(false, "Only {$product['stock']} units available.");
    }

    // ── Check if same product+size already in cart ───────────
    $chk = $conn->prepare(
        "SELECT cart_id, quantity FROM cart
         WHERE user_id = ? AND product_id = ? AND size = ?
         LIMIT 1"
    );
    $chk->bind_param('iis', $user_id, $product_id, $size);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        // Update quantity (cap at stock)
        $new_qty = min($existing['quantity'] + $qty, (int)$product['stock']);
        $upd = $conn->prepare(
            "UPDATE cart SET quantity = ?, updated_at = NOW()
             WHERE cart_id = ?"
        );
        $upd->bind_param('ii', $new_qty, $existing['cart_id']);
        $upd->execute();
        $upd->close();
    } else {
        // Insert new cart row
        $ins = $conn->prepare(
            "INSERT INTO cart (user_id, product_id, size, quantity, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $ins->bind_param('iisi', $user_id, $product_id, $size, $qty);
        $ins->execute();
        $ins->close();
    }

    respond(true, 'Item added to cart.', [
        'cart_count' => cart_count($conn, $user_id)
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: update
// POST: cart_id, qty
// ════════════════════════════════════════════════════════════
if ($action === 'update') {

    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $qty     = max(1, min(10, (int)($_POST['qty'] ?? 1)));

    if ($cart_id <= 0) {
        respond(false, 'Invalid cart item.');
    }

    // Ensure this cart item belongs to this user
    $own = $conn->prepare(
        "SELECT c.cart_id, c.quantity, p.stock, p.price
         FROM cart c
         JOIN products p ON p.product_id = c.product_id
         WHERE c.cart_id = ? AND c.user_id = ?
         LIMIT 1"
    );
    $own->bind_param('ii', $cart_id, $user_id);
    $own->execute();
    $row = $own->get_result()->fetch_assoc();
    $own->close();

    if (!$row) {
        respond(false, 'Cart item not found.');
    }
    if ($qty > (int)$row['stock']) {
        respond(false, "Only {$row['stock']} units available.");
    }

    $upd = $conn->prepare(
        "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?"
    );
    $upd->bind_param('ii', $qty, $cart_id);
    $upd->execute();
    $upd->close();

    // Return new line total and cart total for live UI update
    $line_total  = $row['price'] * $qty;
    $cart_total  = cart_total($conn, $user_id);
    $cart_count  = cart_count($conn, $user_id);

    respond(true, 'Cart updated.', [
        'line_total'  => number_format($line_total, 2),
        'cart_total'  => number_format($cart_total, 2),
        'cart_count'  => $cart_count,
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: remove
// POST: cart_id
// ════════════════════════════════════════════════════════════
if ($action === 'remove') {

    $cart_id = (int)($_POST['cart_id'] ?? 0);

    if ($cart_id <= 0) {
        respond(false, 'Invalid cart item.');
    }

    $del = $conn->prepare(
        "DELETE FROM cart WHERE cart_id = ? AND user_id = ?"
    );
    $del->bind_param('ii', $cart_id, $user_id);
    $del->execute();
    $del->close();

    respond(true, 'Item removed.', [
        'cart_total' => number_format(cart_total($conn, $user_id), 2),
        'cart_count' => cart_count($conn, $user_id),
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: clear
// Removes all cart items for this user
// ════════════════════════════════════════════════════════════
if ($action === 'clear') {

    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $del->bind_param('i', $user_id);
    $del->execute();
    $del->close();

    respond(true, 'Cart cleared.', ['cart_count' => 0]);
}

// Unknown action
respond(false, 'Unknown action.');

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

/** Total number of items (sum of quantities) in user's cart */
function cart_count(mysqli $conn, int $user_id): int {
    $s = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $s->bind_param('i', $user_id);
    $s->execute();
    $count = (int)$s->get_result()->fetch_row()[0];
    $s->close();
    return $count;
}

/** Sum of (price × qty) for all items in user's cart */
function cart_total(mysqli $conn, int $user_id): float {
    $s = $conn->prepare(
        "SELECT COALESCE(SUM(p.price * c.quantity), 0)
         FROM cart c
         JOIN products p ON p.product_id = c.product_id
         WHERE c.user_id = ?"
    );
    $s->bind_param('i', $user_id);
    $s->execute();
    $total = (float)$s->get_result()->fetch_row()[0];
    $s->close();
    return $total;
}