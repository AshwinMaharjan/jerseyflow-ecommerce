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

/* ══════════════════════════════════════════════════════
   1. VALIDATE INCOMING POST FROM checkout.php
   ══════════════════════════════════════════════════════ */

// --- total_amount ---
if (empty($_POST['total_amount']) || !is_numeric($_POST['total_amount'])) {
    header('Location: cart.php');
    exit();
}
$total_amount = (float) $_POST['total_amount'];
if ((float) $total_amount <= 0) {
    header('Location: cart.php');
    exit();
}

// --- selected_cart_ids ---
$selected_ids = [];
if (!empty($_POST['selected_cart_ids'])) {
    foreach (explode(',', $_POST['selected_cart_ids']) as $id) {
        $id = (int) trim($id);
        if ($id > 0) $selected_ids[] = $id;
    }
}
if (empty($selected_ids)) {
    header('Location: cart.php');
    exit();
}

// --- address_id ---
$address_id = isset($_POST['address_id']) ? (int) $_POST['address_id'] : 0;
if ($address_id <= 0) {
    // No address selected — send back to checkout
    header('Location: checkout.php');
    exit();
}

/* ══════════════════════════════════════════════════════
   2. VERIFY address belongs to this user
   ══════════════════════════════════════════════════════ */
$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $address_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    header('Location: checkout.php');
    exit();
}
$stmt->close();

/* ══════════════════════════════════════════════════════
   3. PERSIST PENDING ORDER DATA IN SESSION
      (used by esewa_success.php to create the DB order)
   ══════════════════════════════════════════════════════ */
$_SESSION['pending_order'] = [
    'selected_cart_ids' => $selected_ids,
    'address_id'        => $address_id,
    'total_amount'      => $total_amount,
];

/* ══════════════════════════════════════════════════════
   4. eSewa PAYMENT PARAMETERS
   ══════════════════════════════════════════════════════ */

// --- Sandbox credentials (replace for production) ---
$merchant_code = "EPAYTEST";          // → your real merchant code from eSewa
$secret_key    = "8gBm/:&EnhH.1/q";  // → your real secret key
// --- Unique transaction UUID (alphanumeric + hyphen only) ---
$transaction_uuid = date('Ymd-His') . '-' . $user_id;

// --- Amounts (no tax / service / delivery charges for now) ---
$amount                   = $total_amount;   // product amount
$tax_amount               = "0";
$product_service_charge   = "0";
$product_delivery_charge  = "0";
// total_amount = amount + tax + service + delivery  (all zero extras here)

// --- URLs ---
$base_url    = "http://localhost/jerseyflow-ecommerce";   // ← change in production
$success_url = $base_url . "/users/esewa_success.php";
$failure_url = $base_url . "/users/esewa_failure.php";

// --- eSewa endpoint ---
// Sandbox: https://rc-epay.esewa.com.np/api/epay/main/v2/form
// Production: https://epay.esewa.com.np/api/epay/main/v2/form
$esewa_url = "https://rc-epay.esewa.com.np/api/epay/main/v2/form";

/* ══════════════════════════════════════════════════════
   5. GENERATE HMAC-SHA256 SIGNATURE
      Mandatory order: total_amount,transaction_uuid,product_code
   ══════════════════════════════════════════════════════ */
$message   = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$merchant_code}";
$signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with eSewa — JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f5f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 32px rgba(0,0,0,.09);
            padding: 48px 40px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        /* eSewa green logo mark */
        .esewa-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }
        .esewa-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #60bb46;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .esewa-circle svg { display: block; }
        .esewa-wordmark {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -.3px;
        }
        .esewa-wordmark span { color: #60bb46; }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 6px;
        }
        .sub {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        /* Amount box */
        .amount-box {
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .amount-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #9ca3af;
            margin-bottom: 6px;
        }
        .amount-value {
            font-family: 'Syne', sans-serif;
            font-size: 30px;
            font-weight: 700;
            color: #111827;
            letter-spacing: -.5px;
        }
        .amount-value .currency {
            font-size: 16px;
            font-weight: 600;
            color: #6b7280;
            margin-right: 3px;
        }

        /* Items summary */
        .items-summary {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 28px;
        }
        .items-summary strong { color: #374151; }

        /* Spinner + button */
        .redirect-msg {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            color: #374151;
            font-weight: 500;
            margin-bottom: 24px;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2.5px solid #d1fae5;
            border-top-color: #60bb46;
            border-radius: 50%;
            animation: spin .75s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-cancel {
            display: inline-block;
            font-size: 13px;
            color: #9ca3af;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: color .2s, border-color .2s;
        }
        .btn-cancel:hover { color: #6b7280; border-color: #6b7280; }

        .secure-note {
            margin-top: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: #9ca3af;
        }
        .secure-note svg { flex-shrink: 0; }
    </style>
</head>
<body>

<div class="card">

    <!-- Logo -->
    <div class="esewa-logo">
        <div class="esewa-circle">
            <!-- eSewa "e" icon -->
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14 6C9.58 6 6 9.58 6 14s3.58 8 8 8c2.1 0 4-.8 5.46-2.1l-2.12-2.12A4.97 4.97 0 0 1 14 19a5 5 0 0 1-4.9-4h12.8c.07-.32.1-.65.1-.99C22 9.58 18.42 6 14 6Zm-4.9 7a5 5 0 0 1 9.8 0H9.1Z" fill="#fff"/>
            </svg>
        </div>
        <span class="esewa-wordmark">e<span>Sewa</span></span>
    </div>

    <h1>Redirecting to eSewa</h1>
    <p class="sub">Please wait. You are being securely redirected<br>to complete your payment.</p>

    <!-- Amount display -->
    <div class="amount-box">
        <p class="amount-label">Amount to pay</p>
        <p class="amount-value">
            <span class="currency">Rs</span><?= number_format((float)$total_amount, 2) ?>
        </p>
    </div>

    <p class="items-summary">
        <strong><?= count($selected_ids) ?> item<?= count($selected_ids) > 1 ? 's' : '' ?></strong>
        · Free shipping · Transaction ID: <strong><?= htmlspecialchars($transaction_uuid) ?></strong>
    </p>

    <div class="redirect-msg">
        <div class="spinner"></div>
        Connecting to eSewa…
    </div>

    <a href="checkout.php" class="btn-cancel">← Cancel &amp; go back</a>

    <div class="secure-note">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        256-bit encrypted &amp; secured by eSewa
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     eSewa payment form — auto-submitted via JS
     ══════════════════════════════════════════════════ -->
<form id="esewaForm" action="<?= htmlspecialchars($esewa_url) ?>" method="POST" style="display:none;">

    <input type="hidden" name="amount"                    value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="tax_amount"                value="<?= htmlspecialchars($tax_amount) ?>">
    <input type="hidden" name="product_service_charge"    value="<?= htmlspecialchars($product_service_charge) ?>">
    <input type="hidden" name="product_delivery_charge"   value="<?= htmlspecialchars($product_delivery_charge) ?>">
    <input type="hidden" name="total_amount"              value="<?= htmlspecialchars($total_amount) ?>">
    <input type="hidden" name="transaction_uuid"          value="<?= htmlspecialchars($transaction_uuid) ?>">
    <input type="hidden" name="product_code"              value="<?= htmlspecialchars($merchant_code) ?>">
    <input type="hidden" name="success_url"               value="<?= htmlspecialchars($success_url) ?>">
    <input type="hidden" name="failure_url"               value="<?= htmlspecialchars($failure_url) ?>">
    <input type="hidden" name="signed_field_names"        value="total_amount,transaction_uuid,product_code">
    <input type="hidden" name="signature"                 value="<?= htmlspecialchars($signature) ?>">

</form>

<script>
    // Small delay so the user sees the loading UI, then redirect
    setTimeout(function () {
        document.getElementById('esewaForm').submit();
    }, 1200);
</script>

</body>
</html>