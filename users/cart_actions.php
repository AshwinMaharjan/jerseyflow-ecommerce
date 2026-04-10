<?php
/**
 * cart_actions.php — JerseyFlow
 * AJAX endpoint for cart operations (add / update / remove / clear)
 * Always returns JSON.
 */

session_start();
require_once 'connect.php';

header('Content-Type: application/json');

function respond(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $extra
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

if (empty($_SESSION['user_id'])) {
    respond(false, 'You must be logged in to manage your cart.');
}

$user_id = (int)$_SESSION['user_id'];
$action  = trim($_POST['action'] ?? '');

// ════════════════════════════════════════════════════════════
// ACTION: add
// POST: product_id, variant_id, size, qty
// ════════════════════════════════════════════════════════════
if ($action === 'add') {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $variant_id = isset($_POST['variant_id']) && (int)$_POST['variant_id'] > 0
                    ? (int)$_POST['variant_id']
                    : null;  // store as true NULL if not provided or 0
    $size       = trim($_POST['size'] ?? '');
    $qty        = max(1, min(10, (int)($_POST['qty'] ?? 1)));

    if ($product_id <= 0 || $size === '') {
        respond(false, 'Invalid product or size.');
    }

    // ── Verify variant exists, is active, and has stock ──────
    if ($variant_id !== null) {
        $v_stmt = $conn->prepare(
            "SELECT variant_id, stock, price FROM product_variants
             WHERE variant_id = ? AND product_id = ? AND is_active = 1
             LIMIT 1"
        );
        $v_stmt->bind_param('ii', $variant_id, $product_id);
        $v_stmt->execute();
        $variant = $v_stmt->get_result()->fetch_assoc();
        $v_stmt->close();

        if (!$variant) {
            respond(false, 'Selected variant not found or unavailable.');
        }
        $stock = (int)$variant['stock'];
    } else {
        // Fallback: no variant — check base product stock
        $p_stmt = $conn->prepare(
            "SELECT stock FROM products WHERE product_id = ? LIMIT 1"
        );
        $p_stmt->bind_param('i', $product_id);
        $p_stmt->execute();
        $p_row = $p_stmt->get_result()->fetch_assoc();
        $p_stmt->close();
        if (!$p_row) respond(false, 'Product not found.');
        $stock = (int)$p_row['stock'];
    }

    if ($stock <= 0) {
        respond(false, 'This item is out of stock.');
    }
    if ($qty > $stock) {
        respond(false, "Only {$stock} units available.");
    }

    // ── Check if same product + variant + size already in cart ─
    // Split into two queries to avoid binding NULL with 'i' type
    if ($variant_id !== null) {
        $chk = $conn->prepare(
            "SELECT cart_id, quantity FROM cart
             WHERE user_id = ? AND product_id = ? AND size = ? AND variant_id = ?
             LIMIT 1"
        );
        $chk->bind_param('iisi', $user_id, $product_id, $size, $variant_id);
    } else {
        $chk = $conn->prepare(
            "SELECT cart_id, quantity FROM cart
             WHERE user_id = ? AND product_id = ? AND size = ? AND variant_id IS NULL
             LIMIT 1"
        );
        $chk->bind_param('iis', $user_id, $product_id, $size);
    }
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        // Update quantity of existing cart row
        $new_qty = min($existing['quantity'] + $qty, $stock);
        $upd = $conn->prepare(
            "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?"
        );
        $upd->bind_param('ii', $new_qty, $existing['cart_id']);
        $upd->execute();
        $upd->close();
    } else {
        // Insert new cart row — variant_id stored as proper NULL if not set
        $ins = $conn->prepare(
            "INSERT INTO cart (user_id, product_id, variant_id, size, quantity, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $ins->bind_param('iiisi', $user_id, $product_id, $variant_id, $size, $qty);
        $ins->execute();
        $ins->close();
    }

    respond(true, 'Item added to cart.', [
        'cart_count' => cart_count($conn, $user_id)
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: update  (qty only, no variant change)
// POST: cart_id, qty
// ════════════════════════════════════════════════════════════
if ($action === 'update') {

    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $qty     = max(1, min(10, (int)($_POST['qty'] ?? 1)));

    if ($cart_id <= 0) respond(false, 'Invalid cart item.');

    // Join product_variants if variant_id present, else fall back to products.stock
    $own = $conn->prepare(
        "SELECT c.cart_id, c.quantity, c.variant_id,
                COALESCE(pv.stock, p.stock) AS stock,
                COALESCE(pv.price, p.price) AS price
         FROM cart c
         JOIN products p ON p.product_id = c.product_id
         LEFT JOIN product_variants pv ON pv.variant_id = c.variant_id
         WHERE c.cart_id = ? AND c.user_id = ?
         LIMIT 1"
    );
    $own->bind_param('ii', $cart_id, $user_id);
    $own->execute();
    $row = $own->get_result()->fetch_assoc();
    $own->close();

    if (!$row) respond(false, 'Cart item not found.');
    if ($qty > (int)$row['stock']) respond(false, "Only {$row['stock']} units available.");

    $upd = $conn->prepare(
        "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?"
    );
    $upd->bind_param('ii', $qty, $cart_id);
    $upd->execute();
    $upd->close();

    $line_total = $row['price'] * $qty;
    $cart_total = cart_total($conn, $user_id);
    $cart_count = cart_count($conn, $user_id);

    respond(true, 'Cart updated.', [
        'line_total' => number_format($line_total, 2),
        'cart_total' => number_format($cart_total, 2),
        'cart_count' => $cart_count,
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: remove
// ════════════════════════════════════════════════════════════
if ($action === 'remove') {

    $cart_id = (int)($_POST['cart_id'] ?? 0);
    if ($cart_id <= 0) respond(false, 'Invalid cart item.');

    $del = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
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
// ════════════════════════════════════════════════════════════
if ($action === 'clear') {

    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $del->bind_param('i', $user_id);
    $del->execute();
    $del->close();

    respond(true, 'Cart cleared.', ['cart_count' => 0]);
}

respond(false, 'Unknown action.');

// ── Helpers ───────────────────────────────────────────────────

function cart_count(mysqli $conn, int $user_id): int {
    $s = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $s->bind_param('i', $user_id);
    $s->execute();
    $count = (int)$s->get_result()->fetch_row()[0];
    $s->close();
    return $count;
}

function cart_total(mysqli $conn, int $user_id): float {
    $s = $conn->prepare(
        "SELECT COALESCE(SUM(COALESCE(pv.price, p.price) * c.quantity), 0)
         FROM cart c
         JOIN products p ON p.product_id = c.product_id
         LEFT JOIN product_variants pv ON pv.variant_id = c.variant_id
         WHERE c.user_id = ?"
    );
    $s->bind_param('i', $user_id);
    $s->execute();
    $total = (float)$s->get_result()->fetch_row()[0];
    $s->close();
    return $total;
}