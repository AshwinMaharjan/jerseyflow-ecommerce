<?php
session_start();
require_once '../connect.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ── Validate order_id ─────────────────────────────────────────────────────────
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id || $order_id <= 0) {
    $fatal_error = "Invalid or missing order ID.";
}

// ── 1. Fetch Order Summary (scoped to logged-in user) ─────────────────────────
if (!isset($fatal_error)) {
    $sql_order = "
        SELECT
            o.order_id,
            o.total_amount,
            o.order_status,
            o.payment_status,
            o.method_id,
            o.created_at,
            o.esewa_ref_id
        FROM orders o
        WHERE o.order_id = ?
          AND o.user_id  = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_order);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $fatal_error = "Order #" . htmlspecialchars($order_id) . " was not found.";
    }
}

// ── 2. Fetch Order Items ──────────────────────────────────────────────────────
$items = [];
if (!isset($fatal_error)) {
    $sql_items = "
        SELECT
            p.product_name,
            pv.size,
            pv.color,
            oi.quantity,
            oi.unit_price,
            oi.subtotal
        FROM order_items oi
        JOIN products        p  ON oi.product_id = p.product_id
        JOIN product_variants pv ON oi.variant_id = pv.variant_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id ASC
    ";
    $stmt2 = $conn->prepare($sql_items);
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
}

// ── Helper ────────────────────────────────────────────────────────────────────
function payment_label(int $method_id): string {
    return match ($method_id) {
        2 => 'COD',
        1 => 'eSewa',
        default => 'Other',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details — JerseyFlow</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/users_menu.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/users_view_order.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
</head>
<body>

<?php include 'users_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'users_menu.php'; ?>

    <main class="orders-main">

        <?php if (isset($fatal_error)): ?>
        <!-- ── Fatal Error ──────────────────────────────────────────────────── -->
        <div class="error-state">
            <div class="error-icon">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <h2><?= htmlspecialchars($fatal_error, ENT_QUOTES, 'UTF-8') ?></h2>
            <a href="users_orders.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to My Orders
            </a>
        </div>

        <?php else:
            $method_id    = (int) $order['method_id'];
            $method_label = payment_label($method_id);
            $ord_status   = $order['order_status'];
            $pay_status   = $order['payment_status'];
            $txn_id       = ($method_id === 1 && !empty($order['esewa_ref_id']))
                            ? htmlspecialchars($order['esewa_ref_id'], ENT_QUOTES, 'UTF-8')
                            : null;
            $order_date   = date('M j, Y', strtotime($order['created_at']));
            $grand_total  = number_format((float) $order['total_amount'], 2);
        ?>

        <!-- ── Page Header ─────────────────────────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <a href="users_orders.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back to My Orders
                </a>
                <div>
                    <h1>Order <span class="order-id-accent">#<?= $order['order_id'] ?></span></h1>
                    <p class="subtitle">Placed on <?= $order_date ?></p>
                </div>
            </div>
            <div class="header-badges">
                <span class="badge ord-<?= htmlspecialchars($ord_status, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(ucfirst($ord_status), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="badge pay-<?= htmlspecialchars($pay_status, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(ucfirst($pay_status), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>

        <!-- ── Summary Cards ───────────────────────────────────────────────── -->
        <div class="summary-grid">

            <!-- Order Info -->
            <div class="summary-card">
                <div class="summary-card-header">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Order Info</span>
                </div>
                <div class="summary-rows">
                    <div class="summary-row">
                        <span class="s-label">Order ID</span>
                        <span class="s-value">#<?= $order['order_id'] ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="s-label">Date</span>
                        <span class="s-value"><?= $order_date ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="s-label">Order Status</span>
                        <span class="s-value">
                            <span class="badge ord-<?= htmlspecialchars($ord_status, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucfirst($ord_status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span class="s-label">Total Amount</span>
                        <span class="s-value s-amount">Rs <?= $grand_total ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment -->
            <div class="summary-card">
                <div class="summary-card-header">
                    <i class="fa-solid fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <div class="summary-rows">
                    <div class="summary-row">
                        <span class="s-label">Method</span>
                        <span class="s-value">
                            <?php if ($method_label === 'eSewa'): ?>
                                <span class="badge badge-esewa">eSewa</span>
                            <?php elseif ($method_label === 'COD'): ?>
                                <span class="badge badge-cod">COD</span>
                            <?php else: ?>
                                <span class="badge"><?= htmlspecialchars($method_label, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span class="s-label">Payment Status</span>
                        <span class="s-value">
                            <span class="badge pay-<?= htmlspecialchars($pay_status, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucfirst($pay_status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($txn_id): ?>
                    <div class="summary-row">
                        <span class="s-label">Transaction ID</span>
                        <span class="s-value txn-id"><?= $txn_id ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.summary-grid -->

        <!-- ── Order Items ─────────────────────────────────────────────────── -->
        <div class="section-heading">
            <i class="fa-solid fa-shirt"></i>
            <span>Order Items</span>
            <span class="item-count-pill"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
        </div>

        <div class="card">
            <div class="table-wrap">
                <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <p>No items found for this order.</p>
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $i => $item): ?>
                        <tr style="--row-index: <?= $i ?>;">
                            <td class="row-num"><?= $i + 1 ?></td>
                            <td class="product-name">
                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="variant-pill">
                                    <?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="color-cell">
                                <?= htmlspecialchars(ucfirst($item['color']), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="text-center"><?= (int) $item['quantity'] ?></td>
                            <td class="text-right">Rs <?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="text-right subtotal-cell">Rs <?= number_format((float) $item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Grand Total -->
                <div class="grand-total-row">
                    <span class="grand-label">Grand Total</span>
                    <span class="grand-value">Rs <?= $grand_total ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<?php include '../footer.php'; ?>
<script src="/jerseyflow-ecommerce/script/navbar.js"></script>
<script src="/jerseyflow-ecommerce/script/users_menu.js"></script>
</body>
</html>