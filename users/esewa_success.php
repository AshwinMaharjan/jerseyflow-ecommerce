<?php
session_start();
require_once 'connect.php';

/* ══════════════════════════════════════════════════════
   SECURITY — must be logged in
   ══════════════════════════════════════════════════════ */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* ══════════════════════════════════════════════════════
   eSewa SANDBOX CREDENTIALS
   Change for production:
     $merchant_code = "YOUR_MERCHANT_CODE";
     $secret_key    = "YOUR_SECRET_KEY";
   ══════════════════════════════════════════════════════ */
$merchant_code = "EPAYTEST";
$secret_key    = "8gBm/:&EnhH.1/q";

/* ══════════════════════════════════════════════════════
   1. DECODE & PARSE eSewa BASE64 RESPONSE
      eSewa sends: ?data=<base64-encoded-json>
   ══════════════════════════════════════════════════════ */
$error_message = '';
$order_id      = null;
$esewa_data    = [];

if (empty($_GET['data'])) {
    $error_message = "No payment response received from eSewa.";
} else {
    $decoded = base64_decode($_GET['data'], true);

    if ($decoded === false) {
        $error_message = "Invalid payment response (decode failed).";
    } else {
        $esewa_data = json_decode($decoded, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($esewa_data)) {
            $error_message = "Invalid payment response (JSON parse failed).";
        }
    }
}

/* ══════════════════════════════════════════════════════
   2. VERIFY RESPONSE SIGNATURE
      Same algorithm as request:
      HMAC-SHA256 over the signed_field_names values
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {

    $signed_fields = isset($esewa_data['signed_field_names'])
        ? explode(',', $esewa_data['signed_field_names'])
        : [];

    // Build the message string from the signed fields in the response
    $message_parts = [];
    foreach ($signed_fields as $field) {
        $field = trim($field);
        if (!isset($esewa_data[$field])) {
            $error_message = "Signature field '{$field}' missing from response.";
            break;
        }
        $message_parts[] = "{$field}={$esewa_data[$field]}";
    }

    if (empty($error_message)) {
        $message            = implode(',', $message_parts);
        $expected_signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));
        $received_signature = $esewa_data['signature'] ?? '';

        if (!hash_equals($expected_signature, $received_signature)) {
            $error_message = "Payment signature verification failed. Please contact support.";
        }
    }
}

/* ══════════════════════════════════════════════════════
   3. CONFIRM STATUS = COMPLETE
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {
    $status = strtoupper($esewa_data['status'] ?? '');
    if ($status !== 'COMPLETE') {
        $error_message = "Payment status is '{$status}'. Only COMPLETE payments are accepted.";
    }
}

/* ══════════════════════════════════════════════════════
   4. VALIDATE SESSION PENDING ORDER
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {
    if (empty($_SESSION['pending_order'])) {
        $error_message = "Session expired or order data missing. Please check your order history.";
    } else {
        $pending        = $_SESSION['pending_order'];
        $selected_ids   = $pending['selected_cart_ids'] ?? [];
        $address_id     = (int)($pending['address_id']    ?? 0);
        $session_amount = (float)($pending['total_amount'] ?? 0);

        if (empty($selected_ids) || $address_id <= 0) {
            $error_message = "Incomplete order data in session.";
        }

        // Cross-check amount from session vs eSewa response
        $esewa_amount = (float)($esewa_data['total_amount'] ?? 0);
        if (abs($session_amount - $esewa_amount) > 0.01) {
            $error_message = "Amount mismatch: expected Rs {$session_amount}, eSewa returned Rs {$esewa_amount}.";
        }
    }
}

/* ══════════════════════════════════════════════════════
   5. CHECK FOR DUPLICATE TRANSACTION
      Prevent double-order if user refreshes the success page
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {
    $transaction_uuid = $esewa_data['transaction_uuid'] ?? '';
    $esewa_ref_id     = $esewa_data['transaction_code'] ?? '';

    $dup_stmt = $conn->prepare(
        "SELECT order_id FROM orders WHERE esewa_transaction_uuid = ? LIMIT 1"
    );
    $dup_stmt->bind_param('s', $transaction_uuid);
    $dup_stmt->execute();
    $dup_stmt->store_result();

    if ($dup_stmt->num_rows > 0) {
        // Already processed — fetch the existing order ID and show success
        $dup_stmt->bind_result($order_id);
        $dup_stmt->fetch();
        $dup_stmt->close();
        // Skip to display — order already created
        goto show_success;
    }
    $dup_stmt->close();
}

/* ══════════════════════════════════════════════════════
   6. CREATE ORDER IN DATABASE (transaction-safe)
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {

    $transaction_uuid = $esewa_data['transaction_uuid'] ?? '';
    $esewa_ref_id     = $esewa_data['transaction_code'] ?? '';

    $conn->begin_transaction();

    try {

        /* ── 6a. Fetch cart items to get product/price details ── */
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $types        = 'i' . str_repeat('i', count($selected_ids));
        $params       = array_merge([$user_id], $selected_ids);

        $cart_sql = "
            SELECT c.cart_id, c.product_id, c.quantity, p.price
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?
              AND c.cart_id IN ($placeholders)
        ";

        $cart_stmt = $conn->prepare($cart_sql);
        $cart_stmt->bind_param($types, ...$params);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        $cart_rows = [];
        $db_total  = 0.00;
        while ($row = $cart_result->fetch_assoc()) {
            $row['subtotal'] = (float)$row['price'] * (int)$row['quantity'];
            $db_total       += $row['subtotal'];
            $cart_rows[]     = $row;
        }
        $cart_stmt->close();

        if (empty($cart_rows)) {
            throw new Exception("Cart items not found. They may have already been ordered.");
        }

        /* ── 6b. Insert into orders table ── */
        $order_stmt = $conn->prepare("
            INSERT INTO orders
                (user_id, address_id, total_amount,
                 payment_method, payment_status,
                 esewa_transaction_uuid, esewa_ref_id,
                 order_status, created_at)
            VALUES
                (?, ?, ?,
                 'esewa', 'paid',
                 ?, ?,
                 'processing', NOW())
        ");
        $order_stmt->bind_param(
            'iidss',
            $user_id,
            $address_id,
            $db_total,
            $transaction_uuid,
            $esewa_ref_id
        );
        $order_stmt->execute();
        $order_id = $conn->insert_id;
        $order_stmt->close();

        if (!$order_id) {
            throw new Exception("Failed to create order record.");
        }

        /* ── 6d. Remove purchased items from cart ── */
        $del_placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $del_types        = 'i' . str_repeat('i', count($selected_ids));
        $del_params       = array_merge([$user_id], $selected_ids);

        $del_stmt = $conn->prepare("
            DELETE FROM cart
            WHERE user_id = ?
              AND cart_id IN ($del_placeholders)
        ");
        $del_stmt->bind_param($del_types, ...$del_params);
        $del_stmt->execute();
        $del_stmt->close();

        /* ── 6e. Commit ── */
        $conn->commit();

        // Clear pending order from session
        unset($_SESSION['pending_order']);

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Order processing failed: " . $e->getMessage();
    }
}

/* ══════════════════════════════════════════════════════
   7. FETCH ORDER SUMMARY FOR DISPLAY
   ══════════════════════════════════════════════════════ */
show_success:

$order_summary = null;
if (empty($error_message) && $order_id) {

    $summary_stmt = $conn->prepare("
        SELECT
            o.order_id,
            o.total_amount,
            o.order_status,
            o.payment_status,
            o.esewa_transaction_uuid,
            o.esewa_ref_id,
            o.created_at,
            ua.full_name,
            ua.address_1,
            ua.city,
            ua.country
        FROM orders o
        JOIN user_addresses ua ON o.address_id = ua.id
        WHERE o.order_id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $summary_stmt->bind_param('ii', $order_id, $user_id);
    $summary_stmt->execute();
    $order_summary = $summary_stmt->get_result()->fetch_assoc();
    $summary_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= empty($error_message) ? 'Order Confirmed' : 'Payment Issue' ?> — JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f5f5f7;
            color: #1a1a1a;
        }

        /* ── Page wrapper ── */
        .success-main {
            min-height: calc(100vh - 140px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 60px 20px 80px;
        }

        .container {
            width: 100%;
            max-width: 640px;
        }

        /* ── Status header ── */
        .status-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .status-icon.success { background: #d1fae5; }
        .status-icon.error   { background: #fee2e2; }

        .status-icon svg { display: block; }

        .status-title {
            font-family: 'Syne', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .status-title.success { color: #065f46; }
        .status-title.error   { color: #991b1b; }

        .status-sub {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            padding: 28px 32px;
            margin-bottom: 20px;
        }

        .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #9ca3af;
            margin-bottom: 18px;
        }

        /* ── Detail rows ── */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
        .detail-row:first-of-type { padding-top: 0; }

        .dr-label { color: #6b7280; flex-shrink: 0; }
        .dr-val   { font-weight: 500; color: #111827; text-align: right; }

        .dr-val.amount {
            font-family: 'Syne', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #065f46;
        }

        /* Status badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
        }
        .badge.paid        { background: #d1fae5; color: #065f46; }
        .badge.processing  { background: #dbeafe; color: #1e40af; }

        /* ── Action buttons ── */
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-primary {
            flex: 1;
            min-width: 160px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #111827;
            color: #fff;
            font-family: 'Syne', sans-serif;
            font-size: 14px;
            font-weight: 600;
            padding: 13px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-primary:hover { background: #1f2937; }

        .btn-secondary {
            flex: 1;
            min-width: 160px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f3f4f6;
            color: #374151;
            font-family: 'Syne', sans-serif;
            font-size: 14px;
            font-weight: 600;
            padding: 13px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-secondary:hover { background: #e5e7eb; }

        /* ── Error card ── */
        .error-card {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            border-radius: 12px;
            padding: 20px 24px;
            font-size: 14px;
            color: #7f1d1d;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .error-card strong { display: block; margin-bottom: 4px; font-size: 15px; }

        /* ── eSewa ref badge ── */
        .ref-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 500;
            color: #15803d;
            font-family: monospace;
        }

        @media (max-width: 480px) {
            .card { padding: 22px 18px; }
            .actions { flex-direction: column; }
            .btn-primary, .btn-secondary { min-width: unset; }
        }
    </style>
</head>
<body>

<?php include 'users_navbar.php'; ?>

<main class="success-main">
<div class="container">

<?php if (!empty($error_message)): ?>
    <!-- ════════════ ERROR STATE ════════════ -->
    <div class="status-header">
        <div class="status-icon error">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <h1 class="status-title error">Something went wrong</h1>
        <p class="status-sub">Your payment may have been processed, but we could not confirm your order.</p>
    </div>

    <div class="error-card">
        <strong>Error Details</strong>
        <?= htmlspecialchars($error_message) ?>
    </div>

    <div class="card">
        <p class="card-title">What to do next</p>
        <p style="font-size:14px;color:#374151;line-height:1.7;margin-bottom:18px;">
            If money was deducted from your eSewa wallet, please
            <strong>do not pay again</strong>. Contact our support team with your
            transaction details and we will resolve it promptly.
        </p>
        <div class="actions">
            <a href="orders.php" class="btn-primary">
                <i class="fa-solid fa-box"></i>
                My Orders
            </a>
            <a href="../homepage.php" class="btn-secondary">
                <i class="fa-solid fa-house"></i>
                Go Home
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- ════════════ SUCCESS STATE ════════════ -->
    <div class="status-header">
        <div class="status-icon success">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <h1 class="status-title success">Order Confirmed!</h1>
        <p class="status-sub">
            Thank you for your purchase. Your payment was successful<br>
            and your order is now being processed.
        </p>
    </div>

    <!-- Payment details -->
    <div class="card">
        <p class="card-title">Payment Details</p>

        <div class="detail-row">
            <span class="dr-label">Order ID</span>
            <span class="dr-val">#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></span>
        </div>

        <div class="detail-row">
            <span class="dr-label">Amount Paid</span>
            <span class="dr-val amount">
                Rs <?= number_format((float)($order_summary['total_amount'] ?? $esewa_data['total_amount']), 2) ?>
            </span>
        </div>

        <div class="detail-row">
            <span class="dr-label">Payment Method</span>
            <span class="dr-val">
                <span style="color:#60bb46;font-weight:700;">e</span>Sewa Wallet
            </span>
        </div>

        <div class="detail-row">
            <span class="dr-label">Payment Status</span>
            <span class="dr-val">
                <span class="badge paid">
                    <i class="fa-solid fa-circle-check" style="font-size:10px;"></i>
                    Paid
                </span>
            </span>
        </div>

        <div class="detail-row">
            <span class="dr-label">Order Status</span>
            <span class="dr-val">
                <span class="badge processing">
                    <i class="fa-solid fa-clock" style="font-size:10px;"></i>
                    Processing
                </span>
            </span>
        </div>

        <?php if (!empty($esewa_data['transaction_code'])): ?>
        <div class="detail-row">
            <span class="dr-label">eSewa Ref</span>
            <span class="dr-val">
                <span class="ref-chip">
                    <i class="fa-solid fa-hashtag" style="font-size:11px;"></i>
                    <?= htmlspecialchars($esewa_data['transaction_code']) ?>
                </span>
            </span>
        </div>
        <?php endif; ?>

        <div class="detail-row">
            <span class="dr-label">Transaction ID</span>
            <span class="dr-val" style="font-family:monospace;font-size:13px;">
                <?= htmlspecialchars($esewa_data['transaction_uuid'] ?? '') ?>
            </span>
        </div>

        <?php if ($order_summary): ?>
        <div class="detail-row">
            <span class="dr-label">Date</span>
            <span class="dr-val">
                <?= date('d M Y, h:i A', strtotime($order_summary['created_at'])) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delivery address -->
    <?php if ($order_summary): ?>
    <div class="card">
        <p class="card-title">Delivering to</p>
        <div class="detail-row" style="border:none;padding:0;">
            <span class="dr-label" style="padding-top:2px;">
                <i class="fa-solid fa-location-dot" style="color:#60bb46;margin-right:6px;"></i>
            </span>
            <span class="dr-val" style="text-align:left;line-height:1.7;">
                <strong><?= htmlspecialchars($order_summary['full_name']) ?></strong><br>
                <?= htmlspecialchars($order_summary['address_1']) ?><br>
                <?= htmlspecialchars($order_summary['city']) ?>,
                <?= htmlspecialchars($order_summary['country']) ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="card" style="background:transparent;box-shadow:none;padding:0;">
        <div class="actions">
            <a href="orders.php" class="btn-primary">
                <i class="fa-solid fa-box"></i>
                View My Orders
            </a>
            <a href="../homepage.php" class="btn-secondary">
                <i class="fa-solid fa-shirt"></i>
                Continue Shopping
            </a>
        </div>
    </div>

<?php endif; ?>

</div>
</main>

<?php include '../footer.php'; ?>

</body>
</html>