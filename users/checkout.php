<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* ══════════════════════════════════════════════════════
   1. PARSE SELECTED CART IDs FROM cart.php POST
   ══════════════════════════════════════════════════════ */
$selected_ids = [];

if (!empty($_POST['selected_cart_ids'])) {
    $raw = $_POST['selected_cart_ids'];

    if (is_array($raw)) {
        // sent as array inputs
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0) $selected_ids[] = $id;
        }
    } else {
        // sent as comma-separated string
        foreach (explode(',', $raw) as $id) {
            $id = (int) trim($id);
            if ($id > 0) $selected_ids[] = $id;
        }
    }
}

// Nothing selected or direct URL access — send back to cart
if (empty($selected_ids)) {
    header('Location: cart.php');
    exit();
}

/* ══════════════════════════════════════════════════════
   2. FETCH ONLY THE SELECTED CART ITEMS
   ══════════════════════════════════════════════════════ */
$cart_items   = [];
$total_amount = 0.00;

$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$types        = 'i' . str_repeat('i', count($selected_ids)); // user_id + each cart_id

$cart_sql = "
    SELECT
        c.cart_id,
        c.quantity,
        c.size,
        c.variant_id,                          -- ← ADD THIS
        p.product_id,
        p.product_name,
        COALESCE(pv.price, p.price) AS price,  -- ← use variant price if set
        pi.image_path
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    LEFT JOIN product_variants pv ON pv.variant_id = c.variant_id  -- ← ADD THIS JOIN
    LEFT JOIN product_images pi
        ON pi.product_id = p.product_id
        AND pi.is_primary = 1
    WHERE c.user_id = ?
      AND c.cart_id IN ($placeholders)
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($cart_sql);
$params = array_merge([$user_id], $selected_ids);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['subtotal'] = (float)$row['price'] * (int)$row['quantity'];
    $total_amount   += $row['subtotal'];
    $cart_items[]    = $row;
}
$stmt->close();

$cart_empty = empty($cart_items);

/* ══════════════════════════════════════════════════════
   3. FETCH USER ADDRESSES
   ══════════════════════════════════════════════════════ */
$addresses = [];

$addr_sql = "
    SELECT
        id, label, full_name, phone,
        address_1, address_2,
        city, state, postal, country,
        is_default
    FROM user_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, created_at ASC
";

$stmt = $conn->prepare($addr_sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$addr_result = $stmt->get_result();

while ($addr = $addr_result->fetch_assoc()) {
    $addresses[] = $addr;
}
$stmt->close();

$has_addresses = !empty($addresses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/checkout.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'users_navbar.php'; ?>

<main class="checkout-main">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="header-inner">
            <p class="breadcrumb">
                <a href="cart.php">Cart</a>
                <span class="sep">›</span>
                <strong>Review Order</strong>
                <span class="sep">›</span>
                <span class="muted">Payment</span>
            </p>
            <h1 class="page-title">Order Review</h1>
            <p class="page-sub">Confirm your items and delivery address before payment.</p>
        </div>
    </div>

    <div class="checkout-grid">

        <!-- ══ LEFT COLUMN ══ -->
        <div class="col-left">

            <!-- Section 1 — Items -->
            <section class="card" id="section-items">
                <div class="card-head">
                    <h2 class="card-title">
                        <span class="step-badge">1</span>
                        Your Items
                    </h2>
                    <?php if (!$cart_empty): ?>
                        <span class="item-count">
                            <?= count($cart_items) ?> item<?= count($cart_items) > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($cart_empty): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                <circle cx="9"  cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                        </div>
                        <p class="empty-title">No items selected</p>
                        <p class="empty-sub">Go back to your cart and select the items you want to buy.</p>
                        <a href="cart.php" class="btn-ghost">Back to Cart</a>
                    </div>

                <?php else: ?>
                    <div class="items-list">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">

                            <!-- Product Image -->
                            <div class="item-img-wrap">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img
                                        src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($item['image_path']) ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>"
                                        class="item-img"
                                        loading="lazy"
                                        onerror="this.src='/jerseyflow-ecommerce/images/no_image.png'"
                                    >
                                <?php else: ?>
                                    <div class="item-img-placeholder">
                                        <i class="fa-solid fa-shirt"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="item-info">
                                <p class="item-name"><?= htmlspecialchars($item['product_name']) ?></p>
                                <div class="item-meta">
                                    <?php if (!empty($item['size'])): ?>
                                        <span class="badge-size">
                                            <i class="fa-solid fa-ruler"></i>
                                            <?= htmlspecialchars(strtoupper($item['size'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="item-qty">Qty: <?= (int)$item['quantity'] ?></span>
                                </div>
                            </div>

                            <!-- Pricing -->
                            <div class="item-pricing">
                                <p class="item-unit-price">Rs <?= number_format((float)$item['price'], 2) ?> each</p>
                                <p class="item-subtotal">Rs <?= number_format($item['subtotal'], 2) ?></p>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Section 2 — Delivery Address -->
            <section class="card" id="section-address">
                <div class="card-head">
                    <h2 class="card-title">
                        <span class="step-badge">2</span>
                        Delivery Address
                    </h2>
                </div>

                <?php if (!$has_addresses): ?>
                    <div class="empty-state small">
                        <p class="empty-title">No saved addresses</p>
                        <p class="empty-sub">
                            Please <a href="address_book.php" class="link">add a delivery address</a> to your profile.
                        </p>
                    </div>

                <?php else: ?>
                    <div class="address-grid">
                        <?php foreach ($addresses as $addr): ?>
                        <label
                            class="address-card <?= $addr['is_default'] ? 'selected' : '' ?>"
                            for="addr_<?= (int)$addr['id'] ?>"
                        >
                            <input
                                type="radio"
                                name="address_id"
                                id="addr_<?= (int)$addr['id'] ?>"
                                value="<?= (int)$addr['id'] ?>"
                                form="checkout-form"
                                <?= $addr['is_default'] ? 'checked' : '' ?>
                                class="addr-radio"
                            >
                            <div class="addr-content">
                                <div class="addr-top">
                                    <span class="addr-label">
                                        <?= htmlspecialchars($addr['label'] ?: 'Address') ?>
                                    </span>
                                    <?php if ($addr['is_default']): ?>
                                        <span class="badge-default">Default</span>
                                    <?php endif; ?>
                                </div>
                                <p class="addr-name"><?= htmlspecialchars($addr['full_name']) ?></p>
                                <p class="addr-line">
                                    <?= htmlspecialchars($addr['address_1']) ?>
                                    <?= !empty($addr['address_2']) ? ', ' . htmlspecialchars($addr['address_2']) : '' ?>
                                </p>
                                <p class="addr-line">
                                    <?= htmlspecialchars($addr['city']) ?>
                                    <?= !empty($addr['state']) ? ', ' . htmlspecialchars($addr['state']) : '' ?>
                                    <?= htmlspecialchars($addr['postal']) ?>
                                </p>
                                <p class="addr-line"><?= htmlspecialchars($addr['country']) ?></p>
                                <?php if (!empty($addr['phone'])): ?>
                                    <p class="addr-phone">
                                        <i class="fa-solid fa-phone"></i>
                                        <?= htmlspecialchars($addr['phone']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="addr-check">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <a href="profile.php#addresses" class="add-address-link">
                        <i class="fa-solid fa-plus"></i>
                        Add new address
                    </a>
                <?php endif; ?>
            </section>

        </div><!-- /col-left -->

        <!-- ══ RIGHT COLUMN — Order Summary ══ -->
        <div class="col-right">
            <div class="summary-sticky">
                <section class="card summary-card">
                    <div class="card-head">
                        <h2 class="card-title">
                            <span class="step-badge">3</span>
                            Order Summary
                        </h2>
                    </div>

                    <!-- Per-item breakdown -->
                    <div class="summary-lines">
                        <?php if (!$cart_empty): ?>
                            <?php foreach ($cart_items as $item): ?>
                            <div class="summary-line">
                                <span class="sl-name">
                                    <?= htmlspecialchars(mb_strimwidth($item['product_name'], 0, 28, '…')) ?>
                                    <em class="sl-qty">×<?= (int)$item['quantity'] ?></em>
                                </span>
                                <span class="sl-val">Rs <?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted" style="font-size:13px;padding:8px 0;">No items selected.</p>
                        <?php endif; ?>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-shipping">
                        <span>Shipping</span>
                        <span class="shipping-free">Free</span>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <span class="total-val">Rs <?= number_format($total_amount, 2) ?></span>
                    </div>

                    <!-- Checkout form — posts to create_order.php -->
                    <form id="checkout-form" method="POST" action="esewa_payment.php">
<input type="hidden" name="payment_method" id="payment_method" value="esewa">
                        <!-- Pass total amount -->
                        <input
                            type="hidden"
                            name="total_amount"
                            value="<?= number_format($total_amount, 2, '.', '') ?>"
                        >

                        <!-- Pass selected cart IDs forward to create_order.php -->
                        <input
                            type="hidden"
                            name="selected_cart_ids"
                            value="<?= htmlspecialchars(implode(',', $selected_ids)) ?>"
                        >

                        <!-- address_id radio buttons bind to this form via form="checkout-form" -->

<button
    type="submit"
    class="btn-esewa <?= ($cart_empty || !$has_addresses) ? 'disabled' : '' ?>"
    <?= ($cart_empty || !$has_addresses) ? 'disabled' : '' ?>
    onclick="document.getElementById('payment_method').value='esewa'"
>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="#60BB46"/>
        <path d="M8 12.5l2.5 2.5L16 9" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
    Pay with eSewa
</button>
<button
    type="submit"
    formaction="/jerseyflow-ecommerce/users/cod_checkout.php"
    class="btn-cod <?= ($cart_empty || !$has_addresses) ? 'disabled' : '' ?>"
    <?= ($cart_empty || !$has_addresses) ? 'disabled' : '' ?>
    onclick="document.getElementById('payment_method').value='cod'"
>
    <i class="fa-solid fa-truck"></i>
    Cash on Delivery
</button>                        <?php if ($cart_empty): ?>
                            <p class="pay-hint warn">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                No items selected. <a href="cart.php" class="link">Back to cart</a>.
                            </p>
                        <?php elseif (!$has_addresses): ?>
                            <p class="pay-hint warn">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                Please add a delivery address first.
                            </p>
                        <?php else: ?>
                            <p class="pay-hint">
                                <i class="fa-solid fa-lock"></i>
                                You will be redirected to Khalti to complete payment.
                            </p>
                        <?php endif; ?>

                    </form>
                </section>

                <!-- Security badge -->
                <div class="security-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secured &amp; encrypted checkout
                </div>

            </div>
        </div><!-- /col-right -->

    </div><!-- /checkout-grid -->
</main>

<?php include '../footer.php'; ?>
<script src="../script/checkout.js"></script>
</body>
</html>