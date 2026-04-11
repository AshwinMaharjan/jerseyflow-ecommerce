<?php
session_start();
require_once '../connect.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ── Filter inputs ─────────────────────────────────────────────────────────────
$allowed_order_statuses   = ['', 'pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$allowed_payment_statuses = ['', 'unpaid', 'paid', 'failed', 'refunded'];

$filter_status  = in_array($_GET['filter_status']  ?? '', $allowed_order_statuses,   true) ? ($_GET['filter_status']  ?? '') : '';
$filter_payment = in_array($_GET['filter_payment'] ?? '', $allowed_payment_statuses, true) ? ($_GET['filter_payment'] ?? '') : '';

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page     = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where_parts  = ['o.user_id = ?'];
$bind_types   = 'i';
$bind_params  = [$user_id];

if ($filter_status !== '') {
    $where_parts[] = 'o.order_status = ?';
    $bind_types   .= 's';
    $bind_params[] = $filter_status;
}
if ($filter_payment !== '') {
    $where_parts[] = 'o.payment_status = ?';
    $bind_types   .= 's';
    $bind_params[] = $filter_payment;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ── Total count ───────────────────────────────────────────────────────────────
$count_sql  = "SELECT COUNT(DISTINCT o.order_id) AS total FROM orders o $where_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($bind_types, ...$bind_params);
$count_stmt->execute();
$total_orders = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages  = max(1, (int) ceil($total_orders / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

// ── Fetch paginated orders ────────────────────────────────────────────────────
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
    $where_sql
    GROUP BY
        o.order_id, o.total_amount, o.order_status,
        o.payment_status, o.method_id, o.created_at
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$all_types  = $bind_types . 'ii';
$all_params = array_merge($bind_params, [$per_page, $offset]);
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function payment_label(int $method_id): string {
    return match ($method_id) {
        2       => 'COD',
        1       => 'eSewa',
        default => 'Other',
    };
}

function pagination_url(array $params): string {
    return 'users_orders.php?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — JerseyFlow</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/users_orders.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/users_menu.css">
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>

<?php include 'users_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'users_menu.php'; ?>

    <main class="orders-main">

        <!-- Page header -->
        <div class="page-header">
            <div class="header-left">
                <h1>My Orders</h1>
                <p class="subtitle">Track and review your purchase history</p>
            </div>
            <?php if ($total_orders > 0): ?>
                <span class="count-pill"><?= $total_orders ?> order<?= $total_orders !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────────── -->
        <div class="filters-bar">
            <form method="GET" action="users_orders.php" class="filters-form">
                <div class="filter-group">
                    <label for="filter_status" class="filter-label">Order Status</label>
                    <div class="select-wrap">
                        <select name="filter_status" id="filter_status" class="filter-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>

                <div class="filter-group">
                    <label for="filter_payment" class="filter-label">Payment Status</label>
                    <div class="select-wrap">
                        <select name="filter_payment" id="filter_payment" class="filter-select">
                            <option value="">All Payments</option>
                            <?php foreach (['unpaid','paid','failed','refunded'] as $p): ?>
                                <option value="<?= $p ?>" <?= $filter_payment === $p ? 'selected' : '' ?>>
                                    <?= ucfirst($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Apply
                    </button>
                    <?php if ($filter_status !== '' || $filter_payment !== ''): ?>
                        <a href="users_orders.php" class="btn-clear-filters">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Active filter pills -->
            <?php if ($filter_status !== '' || $filter_payment !== ''): ?>
                <div class="active-filters">
                    <?php if ($filter_status !== ''): ?>
                        <span class="filter-pill">
                            Status: <?= ucfirst($filter_status) ?>
                            <a href="<?= pagination_url(['filter_payment' => $filter_payment]) ?>" class="pill-remove" title="Remove">&#x2715;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_payment !== ''): ?>
                        <span class="filter-pill">
                            Payment: <?= ucfirst($filter_payment) ?>
                            <a href="<?= pagination_url(['filter_status' => $filter_status]) ?>" class="pill-remove" title="Remove">&#x2715;</a>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Orders table ──────────────────────────────────────────────────── -->
        <div class="card">
            <div class="orders-table-wrap">

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <path d="M6 2 3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <path d="M16 10a4 4 0 01-8 0"/>
                        </svg>
                        <p><?= ($filter_status || $filter_payment) ? 'No orders match the selected filters.' : "You haven't placed any orders yet." ?></p>
                        <?php if (!$filter_status && !$filter_payment): ?>
                            <a href="/jerseyflow-ecommerce/shop.php" class="btn-shop">Start Shopping</a>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#&nbsp;Order</th>
                            <th>Products</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $index => $order):
                        $oid            = (int) $order['order_id'];
                        $total_products = (int) $order['total_products'];
                        $total_items    = (int) $order['total_items'];
                        $amount         = number_format((float) $order['total_amount'], 2);
                        $method_label   = payment_label((int) $order['method_id']);
                        $pay_status     = $order['payment_status'];
                        $ord_status     = $order['order_status'];
                        $date           = date('M j, Y', strtotime($order['created_at']));
                        // Display order number as descending (most recent = #1)
                        $display_num    = $total_orders - ($offset + $index);
                    ?>
                        <tr class="order-row" style="--row-index: <?= $index ?>;">
                            <td class="order-num">#<?= $display_num ?></td>

                            <td class="center"><?= $total_products ?></td>
                            <td class="center"><?= $total_items ?></td>

                            <td class="amount">Rs <?= $amount ?></td>

                            <!-- Payment method badge -->
                            <td>
                                <?php if ($method_label === 'eSewa'): ?>
                                    <span class="badge badge-esewa">eSewa</span>
                                <?php elseif ($method_label === 'COD'): ?>
                                    <span class="badge badge-cod">COD</span>
                                <?php else: ?>
                                    <span class="badge"><?= htmlspecialchars($method_label) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Payment status badge (read-only) -->
                            <td>
                                <span class="status-badge pay-<?= htmlspecialchars($pay_status, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= ucfirst($pay_status) ?>
                                </span>
                            </td>

                            <!-- Order status badge (read-only) -->
                            <td>
                                <span class="status-badge ord-<?= htmlspecialchars($ord_status, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= ucfirst($ord_status) ?>
                                </span>
                            </td>

                            <td class="date"><?= $date ?></td>

                            <!-- Actions -->
                            <td>
                                <a href="view_order.php?order_id=<?= $oid ?>" class="btn-view">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

            </div><!-- /.orders-table-wrap -->
        </div><!-- /.card -->

        <!-- ── Pagination ────────────────────────────────────────────────────── -->
        <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Orders pagination">
            <span class="pagination-info">
                Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_orders) ?> of <?= $total_orders ?> orders
            </span>

            <div class="pagination-controls">
                <!-- Previous -->
                <?php if ($current_page > 1): ?>
                    <a href="<?= pagination_url(['page' => $current_page - 1, 'filter_status' => $filter_status, 'filter_payment' => $filter_payment]) ?>"
                       class="page-btn page-prev" aria-label="Previous page">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                <?php else: ?>
                    <span class="page-btn page-prev disabled" aria-disabled="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </span>
                <?php endif; ?>

                <!-- Page numbers -->
                <?php
                $window = 2;
                $start  = max(1, $current_page - $window);
                $end    = min($total_pages, $current_page + $window);
                if ($start > 1): ?>
                    <a href="<?= pagination_url(['page' => 1, 'filter_status' => $filter_status, 'filter_payment' => $filter_payment]) ?>" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <?php if ($p === $current_page): ?>
                        <span class="page-btn page-active" aria-current="page"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= pagination_url(['page' => $p, 'filter_status' => $filter_status, 'filter_payment' => $filter_payment]) ?>" class="page-btn"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                    <a href="<?= pagination_url(['page' => $total_pages, 'filter_status' => $filter_status, 'filter_payment' => $filter_payment]) ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <!-- Next -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?= pagination_url(['page' => $current_page + 1, 'filter_status' => $filter_status, 'filter_payment' => $filter_payment]) ?>"
                       class="page-btn page-next" aria-label="Next page">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                <?php else: ?>
                    <span class="page-btn page-next disabled" aria-disabled="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </span>
                <?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>

    </main>
</div>

<?php include '../footer.php'; ?>
<script src="/jerseyflow-ecommerce/script/navbar.js"></script>
<script src="/jerseyflow-ecommerce/script/users_orders.js"></script>
<script src="/jerseyflow-ecommerce/script/users_menu.js"></script>
</body>
</html>