<?php
/**
 * khalti_payment.php — JerseyFlow
 *
 * Flow:
 *  1. Validate session + POST data
 *  2. Validate selected cart items still belong to this user
 *  3. Fetch chosen delivery address
 *  4. Create pending order + order_items in a transaction
 *  5. Hit Khalti initiate API (sandbox)
 *  6. Store pidx in session + order, redirect user to Khalti gateway
 *
 * DOES NOT: verify payment (that is payment_verify.php)
 * DOES NOT: mark payment as paid
 */

session_start();
require_once '../connect.php';

/* ══════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */

/**
 * Abort with a styled error page.
 * Keeps the user informed without exposing internals.
 */
function abort(string $message, string $back_url = 'checkout.php'): never
{
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error — JerseyFlow</title>
        <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
        <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg:     #121212;
                --panel:  #1A1A1A;
                --text:   #EEE5D8;
                --muted:  rgba(238,229,216,.6);
                --border: rgba(255,255,255,.08);
                --accent: #C8A96E;
                --danger: #e07070;
                --danger-bg: rgba(224,112,112,.1);
                --radius: 12px;
            }
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background: var(--bg);
                color: var(--text);
                font-family: 'DM Sans', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .error-card {
                background: var(--panel);
                border: 1px solid rgba(224,112,112,.25);
                border-radius: var(--radius);
                padding: 48px 40px;
                max-width: 480px;
                width: 100%;
                text-align: center;
            }
            .error-icon {
                width: 64px; height: 64px;
                background: var(--danger-bg);
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 24px;
                color: var(--danger);
                font-size: 26px;
            }
            .error-title {
                font-family: 'Syne', sans-serif;
                font-size: 22px;
                font-weight: 700;
                letter-spacing: -.02em;
                margin-bottom: 12px;
            }
            .error-msg {
                font-size: 14px;
                color: var(--muted);
                line-height: 1.6;
                margin-bottom: 32px;
            }
            .btn-back {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 28px;
                background: transparent;
                border: 1px solid var(--border);
                border-radius: 8px;
                color: var(--text);
                font-family: 'DM Sans', sans-serif;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                transition: background .2s, border-color .2s;
            }
            .btn-back:hover {
                background: rgba(255,255,255,.06);
                border-color: rgba(255,255,255,.16);
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h1 class="error-title">Payment Error</h1>
            <p class="error-msg"><?= htmlspecialchars($message) ?></p>
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i>
                Go Back
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/* ══════════════════════════════════════════════════════════════
   STEP 1 — AUTH + METHOD CHECK
══════════════════════════════════════════════════════════════ */

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Direct access is not allowed. Please proceed from the checkout page.', 'checkout.php');
}

$user_id = (int) $_SESSION['user_id'];

/* ══════════════════════════════════════════════════════════════
   STEP 2 — VALIDATE & SANITISE POST DATA
══════════════════════════════════════════════════════════════ */

// --- total_amount ---
$total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
if ($total_amount === false || $total_amount === null || $total_amount <= 0) {
    abort('Invalid or missing total amount. Please return to checkout and try again.');
}
$total_amount = round($total_amount, 2);

// --- address_id ---
$address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
if (!$address_id || $address_id <= 0) {
    abort('No delivery address selected. Please go back and choose an address.');
}

// --- selected_cart_ids ---
$selected_ids = [];
$raw_ids = $_POST['selected_cart_ids'] ?? '';

if (is_array($raw_ids)) {
    foreach ($raw_ids as $id) {
        $id = (int) $id;
        if ($id > 0) $selected_ids[] = $id;
    }
} else {
    foreach (explode(',', (string) $raw_ids) as $id) {
        $id = (int) trim($id);
        if ($id > 0) $selected_ids[] = $id;
    }
}

if (empty($selected_ids)) {
    abort('No cart items received. Please return to checkout and try again.', 'cart.php');
}

/* ══════════════════════════════════════════════════════════════
   STEP 3 — VERIFY CART ITEMS STILL EXIST FOR THIS USER
   (Prevents tampered IDs or stale sessions)
══════════════════════════════════════════════════════════════ */

$placeholders  = implode(',', array_fill(0, count($selected_ids), '?'));
$bind_types    = 'i' . str_repeat('i', count($selected_ids));
$bind_params   = array_merge([$user_id], $selected_ids);

$verify_sql = "
    SELECT
        c.cart_id,
        c.quantity,
        c.size,
        p.product_id,
        p.product_name,
        p.price,
        p.stock
    FROM cart c
    JOIN products p ON p.product_id = c.product_id
    WHERE c.user_id = ?
      AND c.cart_id IN ($placeholders)
";

$stmt = $conn->prepare($verify_sql);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($cart_items)) {
    abort('Your cart items could not be found. They may have been removed. Please start over.', 'cart.php');
}

// Count returned rows must match what was submitted
if (count($cart_items) !== count($selected_ids)) {
    abort('Some cart items are no longer available. Please return to cart and review your selection.', 'cart.php');
}

// Stock check + recalculate total server-side (never trust client total)
$verified_total = 0.00;
foreach ($cart_items as $item) {
    if ((int)$item['quantity'] > (int)$item['stock']) {
        abort(
            htmlspecialchars($item['product_name']) .
            ' only has ' . (int)$item['stock'] . ' unit(s) in stock but you ordered ' .
            (int)$item['quantity'] . '. Please update your cart.',
            'cart.php'
        );
    }
    $verified_total += (float)$item['price'] * (int)$item['quantity'];
}
$verified_total = round($verified_total, 2);

// Sanity-check: submitted total must match server-calculated total (allow ±1 paisa rounding)
if (abs($verified_total - $total_amount) > 0.01) {
    abort('Order total mismatch detected. Please return to checkout and try again.');
}

/* ══════════════════════════════════════════════════════════════
   STEP 4 — FETCH & SNAPSHOT DELIVERY ADDRESS
══════════════════════════════════════════════════════════════ */

$addr_sql = "
    SELECT
        id, label, full_name, phone,
        address_1, address_2,
        city, state, postal, country
    FROM user_addresses
    WHERE id = ? AND user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($addr_sql);
$stmt->bind_param('ii', $address_id, $user_id);
$stmt->execute();
$address = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$address) {
    abort('Selected address not found or does not belong to your account.');
}

// Build a JSON snapshot — stored in orders.shipping_address
// so the order record is self-contained even if the address is later edited/deleted
$address_snapshot = json_encode([
    'label'     => $address['label'],
    'full_name' => $address['full_name'],
    'phone'     => $address['phone'],
    'address_1' => $address['address_1'],
    'address_2' => $address['address_2'],
    'city'      => $address['city'],
    'state'     => $address['state'],
    'postal'    => $address['postal'],
    'country'   => $address['country'],
], JSON_UNESCAPED_UNICODE);

/* ══════════════════════════════════════════════════════════════
   STEP 5 — CREATE PENDING ORDER IN A TRANSACTION
   INSERT orders → INSERT order_items (one per product)
   Rolls back entirely if anything fails.
══════════════════════════════════════════════════════════════ */

$conn->begin_transaction();

try {

    // 5a. Insert master order row
    $order_sql = "
        INSERT INTO orders
            (user_id, total_amount, order_status, payment_status, payment_method, shipping_address)
        VALUES
            (?, ?, 'pending', 'unpaid', 'khalti', ?)
    ";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param('ids', $user_id, $verified_total, $address_snapshot);
    $stmt->execute();
    $order_id = (int) $conn->insert_id;
    $stmt->close();

    if ($order_id <= 0) {
        throw new RuntimeException('Failed to create order record.');
    }

    // 5b. Insert one order_items row per cart item
    $item_sql = "
        INSERT INTO order_items
            (order_id, product_id, quantity, price, subtotal)
        VALUES
            (?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($item_sql);

    foreach ($cart_items as $item) {
        $prod_id  = (int)   $item['product_id'];
        $qty      = (int)   $item['quantity'];
        $price    = (float) $item['price'];
        $subtotal = round($price * $qty, 2);

        $stmt->bind_param('iidd d', $order_id, $prod_id, $qty, $price, $subtotal);
        // Note: 'iiidd' — order_id(i), product_id(i), quantity(i), price(d), subtotal(d)
        $stmt->bind_param('iiidd', $order_id, $prod_id, $qty, $price, $subtotal);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();
    error_log('[JerseyFlow] Order creation failed: ' . $e->getMessage());
    abort('We could not create your order due to a server error. Please try again.');
}

/* ══════════════════════════════════════════════════════════════
   STEP 6 — INITIATE KHALTI PAYMENT (Sandbox)
   Docs: https://docs.khalti.com/khalti-epayment/
══════════════════════════════════════════════════════════════ */

// ── CONFIG ──────────────────────────────────────────────────
define('KHALTI_SECRET_KEY',  'key live_secret_key_68791341fdd94846a146f0457ff7b455');   // <-- replace with your sandbox secret key
define('KHALTI_INITIATE_URL','https://a.khalti.com/api/v2/epayment/initiate/');

// Return URL: Khalti redirects here after payment (success or failure)
// Must be an absolute URL accessible from the internet (use ngrok for local dev)
$return_url = 'http://localhost/jerseyflow-ecommerce/users/payment_verify.php';

// Build product detail for Khalti payload
// Khalti accepts an array of purchase_order_detail objects
$purchase_details = [];
foreach ($cart_items as $item) {
    $purchase_details[] = [
        'identity' => (string) $item['product_id'],
        'name'     => $item['product_name'],
        'total_price' => (int) round((float)$item['price'] * (int)$item['quantity'] * 100), // in paisa
        'quantity' => (int) $item['quantity'],
        'unit_price'  => (int) round((float)$item['price'] * 100), // in paisa
    ];
}

// Khalti expects amount in PAISA (1 NPR = 100 paisa)
$amount_paisa = (int) round($verified_total * 100);

$payload = [
    'return_url'           => $return_url,
    'website_url'          => 'http://localhost/jerseyflow-ecommerce',
    'amount'               => $amount_paisa,
    'purchase_order_id'    => 'JF-ORDER-' . $order_id,          // your internal order reference
    'purchase_order_name'  => 'JerseyFlow Order #' . $order_id,
    'customer_info'        => [
        'name'  => $address['full_name'],
        'email' => '',   // optional — fill from users table if you wish
        'phone' => $address['phone'] ?? '',
    ],
    'amount_breakdown'     => [
        ['label' => 'Subtotal', 'amount' => $amount_paisa],
        ['label' => 'Shipping', 'amount' => 0],
    ],
    'product_details'      => $purchase_details,
];

// ── cURL request to Khalti ───────────────────────────────────
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => KHALTI_INITIATE_URL,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Key ' . KHALTI_SECRET_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response     = curl_exec($ch);
$curl_err     = curl_error($ch);
$http_status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ── Handle cURL failure ──────────────────────────────────────
if ($curl_err) {
    error_log('[JerseyFlow] Khalti cURL error for order #' . $order_id . ': ' . $curl_err);
    abort(
        'Could not reach the Khalti payment gateway. Please check your connection and try again.',
        'checkout.php'
    );
}

// ── Parse Khalti response ────────────────────────────────────
$khalti_data = json_decode($response, true);

if ($http_status !== 200 || empty($khalti_data['pidx']) || empty($khalti_data['payment_url'])) {
    // Log full response for debugging, show generic message to user
    error_log(
        '[JerseyFlow] Khalti initiation failed for order #' . $order_id .
        ' | HTTP ' . $http_status .
        ' | Response: ' . $response
    );
    abort(
        'Khalti payment initiation failed. ' .
        (!empty($khalti_data['detail']) ? htmlspecialchars($khalti_data['detail']) : 'Please try again.'),
        'checkout.php'
    );
}

$pidx        = $khalti_data['pidx'];
$payment_url = $khalti_data['payment_url'];

/* ══════════════════════════════════════════════════════════════
   STEP 7 — STORE pidx IN ORDER + SESSION
   pidx is needed in payment_verify.php to confirm the payment
══════════════════════════════════════════════════════════════ */

$update_sql = "UPDATE orders SET khalti_pidx = ? WHERE order_id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param('si', $pidx, $order_id);
$stmt->execute();
$stmt->close();

// Also keep in session as a backup reference for payment_verify.php
$_SESSION['pending_order_id']    = $order_id;
$_SESSION['pending_khalti_pidx'] = $pidx;
$_SESSION['pending_total']       = $verified_total;

/* ══════════════════════════════════════════════════════════════
   STEP 8 — REDIRECT TO KHALTI GATEWAY
   User completes payment on Khalti's page.
   Khalti redirects back to return_url with pidx + status params.
══════════════════════════════════════════════════════════════ */

header('Location: ' . $payment_url);
exit();