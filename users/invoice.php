<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header('Location: orders.php');
    exit();
}

/* ── Fetch order ── */
$order_stmt = $conn->prepare("
    SELECT
        o.order_id, o.total_amount, o.order_status, o.payment_status,
        o.esewa_transaction_uuid, o.esewa_ref_id, o.created_at,
        pm.method_name,
        ua.full_name, ua.address_1, ua.city, ua.state, ua.country, ua.phone
    FROM orders o
    JOIN payment_methods pm ON o.method_id = pm.method_id
    JOIN user_addresses ua  ON o.address_id = ua.id
    WHERE o.order_id = ? AND o.user_id = ?
    LIMIT 1
");
$order_stmt->bind_param('ii', $order_id, $user_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    header('Location: orders.php');
    exit();
}

/* ── Fetch order items ── */
$items_stmt = $conn->prepare("
    SELECT
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.product_name,
        pi.image_path,
        pv.size,
        pv.color
    FROM order_items oi
    JOIN products p
        ON oi.product_id = p.product_id
    LEFT JOIN product_variants pv
        ON oi.variant_id = pv.variant_id
    LEFT JOIN product_images pi
        ON pi.product_id = p.product_id
        AND pi.is_primary = 1
    WHERE oi.order_id = ?
");
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$is_cod    = strtolower($order['method_name']) === 'cod';
$is_esewa  = strtolower($order['method_name']) === 'esewa';
$order_num = str_pad($order['order_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $order_num ?> — JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style/invoice.css">
</head>
<body>

<?php include 'users_navbar.php'; ?>

<main class="invoice-main">
    <div class="invoice-wrap" id="invoiceDoc">

        <!-- ── TOP STRIP ── -->
        <div class="invoice-top">
            <div class="brand-block">
                <span class="brand-logo">JerseyFlow</span>
                <span class="brand-tagline">Official Receipt</span>
            </div>
            <div class="invoice-meta">
                <div class="invoice-num">INV-<?= $order_num ?></div>
                <div class="invoice-date"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
            </div>
        </div>

        <!-- ── STATUS BANNER ── -->
        <?php if ($is_cod): ?>
        <div class="status-banner cod">
            <div class="status-icon-wrap">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="status-text">
                <strong>Cash on Delivery</strong>
                <span>Pay when your order arrives at your door</span>
            </div>
            <div class="status-badge pending">Pending</div>
        </div>
        <?php else: ?>
        <div class="status-banner paid">
            <div class="status-icon-wrap">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="status-text">
                <strong>Payment Confirmed</strong>
                <span>Your eSewa payment was successful</span>
            </div>
            <div class="status-badge paid-badge">Paid</div>
        </div>
        <?php endif; ?>

        <!-- ── TWO-COL: Bill To + Order Info ── -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Bill To</div>
                <div class="info-name"><?= htmlspecialchars($order['full_name']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($order['address_1']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($order['city']) ?><?= !empty($order['state']) ? ', ' . htmlspecialchars($order['state']) : '' ?></div>
                <div class="info-detail"><?= htmlspecialchars($order['country']) ?></div>
                <?php if (!empty($order['phone'])): ?>
                <div class="info-detail phone"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($order['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <div class="info-label">Order Details</div>
                <div class="info-row">
                    <span>Order ID</span>
                    <span class="mono">#<?= $order_num ?></span>
                </div>
                <div class="info-row">
                    <span>Date</span>
                    <span><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span>Method</span>
                    <span><?= $is_cod ? 'Cash on Delivery' : '<span style="color:#60bb46;font-weight:700;">e</span>Sewa' ?></span>
                </div>
                <div class="info-row">
                    <span>Order Status</span>
                    <span class="status-chip <?= strtolower($order['order_status']) ?>">
                        <?= ucfirst($order['order_status']) ?>
                    </span>
                </div>
                <?php if ($is_esewa && !empty($order['esewa_ref_id'])): ?>
                <div class="info-row">
                    <span>eSewa Ref</span>
                    <span class="mono"><?= htmlspecialchars($order['esewa_ref_id']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── ITEMS TABLE ── -->
        <div class="items-section">
            <div class="items-header">
                <span class="col-product">Product</span>
                <span class="col-qty">Qty</span>
                <span class="col-price">Unit Price</span>
                <span class="col-sub">Subtotal</span>
            </div>

            <?php foreach ($items as $item): ?>
            <div class="item-row">
                <div class="col-product item-product">
                    <?php if (!empty($item['image_url'])): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="" class="item-thumb" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="item-info">
                        <span class="item-name"><?= htmlspecialchars($item['product_name']) ?></span>
                        <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                        <span class="item-variant">
                            <?= !empty($item['size'])  ? htmlspecialchars($item['size'])  : '' ?>
                            <?= !empty($item['color']) ? ' · ' . htmlspecialchars($item['color']) : '' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="col-qty item-qty"><?= (int)$item['quantity'] ?></span>
                <span class="col-price item-price">Rs <?= number_format((float)$item['unit_price'], 2) ?></span>
                <span class="col-sub item-sub">Rs <?= number_format((float)$item['subtotal'], 2) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── TOTALS ── -->
        <div class="totals-section">
            <div class="totals-inner">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>Rs <?= number_format((float)$order['total_amount'], 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping</span>
                    <span class="free-tag">Free</span>
                </div>
                <div class="total-divider"></div>
                <div class="total-row grand">
                    <span>Total</span>
                    <span class="grand-amount">Rs <?= number_format((float)$order['total_amount'], 2) ?></span>
                </div>
                <?php if ($is_cod): ?>
                <div class="cod-note">
                    <i class="fa-solid fa-circle-info"></i>
                    Please keep <strong>Rs <?= number_format((float)$order['total_amount'], 2) ?></strong> ready for the delivery agent.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── FOOTER ── -->
        <div class="invoice-footer">
            <p>Thank you for shopping with <strong>JerseyFlow</strong>. For any queries, contact our support team.</p>
        </div>

    </div><!-- /.invoice-wrap -->

    <!-- ── ACTIONS (outside printable area) ── -->
    <div class="invoice-actions no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print Invoice
        </button>
        <a href="orders.php" class="btn-orders">
            <i class="fa-solid fa-box"></i> My Orders
        </a>
        <a href="../homepage.php" class="btn-home">
            <i class="fa-solid fa-shirt"></i> Continue Shopping
        </a>
    </div>
</main>

<?php include '../footer.php'; ?>

<script src="../script/invoice.js"></script>
</body>
</html>