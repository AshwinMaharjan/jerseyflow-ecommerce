<?php
require_once '../connect.php';

// ── Handle status update via POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int) $_POST['order_id'];
    $updated  = false;

    // Order status update
    if (isset($_POST['order_status'])) {
        $allowed_order_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $new_order_status = $_POST['order_status'];

        if (in_array($new_order_status, $allowed_order_statuses, true) && $order_id > 0) {
            $upd = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?");
            $upd->bind_param("si", $new_order_status, $order_id);
            $upd->execute();
            $upd->close();
            $updated = true;
        }
    }

    // Payment status update
    if (isset($_POST['payment_status'])) {
        $allowed_payment_statuses = ['unpaid', 'paid', 'failed', 'refunded'];
        $new_payment_status = $_POST['payment_status'];

        if (in_array($new_payment_status, $allowed_payment_statuses, true) && $order_id > 0) {
            $upd = $conn->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE order_id = ?");
            $upd->bind_param("si", $new_payment_status, $order_id);
            $upd->execute();
            $upd->close();
            $updated = true;
        }
    }

    if ($updated) {
        $qs = http_build_query([
            'updated'        => 1,
            'filter_status'  => $_POST['filter_status']  ?? '',
            'filter_payment' => $_POST['filter_payment'] ?? '',
            'page'           => $_POST['page']           ?? 1,
        ]);
        header("Location: all_orders.php?$qs");
        exit;
    }
}

// ── Filter inputs ─────────────────────────────────────────────────────────────
$allowed_order_statuses   = ['', 'pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$allowed_payment_statuses = ['', 'unpaid', 'paid', 'failed', 'refunded'];

$filter_status  = in_array($_GET['filter_status']  ?? '', $allowed_order_statuses,   true) ? ($_GET['filter_status']  ?? '') : '';
$filter_payment = in_array($_GET['filter_payment'] ?? '', $allowed_payment_statuses, true) ? ($_GET['filter_payment'] ?? '') : '';

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page    = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where_parts  = [];
$bind_types   = '';
$bind_params  = [];

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

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Total count (for pagination) ──────────────────────────────────────────────
$count_sql  = "SELECT COUNT(DISTINCT o.order_id) AS total FROM orders o $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($bind_params) {
    $count_stmt->bind_param($bind_types, ...$bind_params);
}
$count_stmt->execute();
$total_orders = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int) ceil($total_orders / $per_page));
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
        o.esewa_ref_id,
        u.full_name,
        COUNT(oi.order_item_id) AS total_products,
        SUM(oi.quantity)        AS total_items
    FROM orders o
    JOIN users u  ON o.user_id  = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    $where_sql
    GROUP BY
        o.order_id, o.total_amount, o.order_status, o.payment_status,
        o.method_id, o.created_at, o.esewa_ref_id, u.full_name
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

// Merge filter params with pagination params
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
    return 'all_orders.php?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Orders — Admin</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_menu.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/all_orders.css">
    <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>

<?php include '../admin_navbar.php'; ?>
<div class="page-wrapper">
    <div class="admin-wrapper">
        <?php include '../admin_menu.php'; ?>

        <main class="admin-content">

            <!-- Page header -->
            <div class="page-header">
                <div>
                    <h1>
                        All Orders
                        <span class="count-pill"><?= $total_orders ?></span>
                    </h1>
                    <p class="subtitle">Manage and track every customer order</p>
                </div>
            </div>

            <!-- Success alert -->
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert-success">
                    &#10003; &nbsp;Order status updated successfully.
                </div>
            <?php endif; ?>

            <!-- ── Filters ────────────────────────────────────────────────── -->
            <div class="filters-bar">
                <form method="GET" action="all_orders.php" class="filters-form" id="filters-form">
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
                            <a href="all_orders.php" class="btn-clear-filters">
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

            <!-- ── Orders table ───────────────────────────────────────────── -->
            <div class="card">
                <div class="orders-table-wrap">

                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <path d="M9 12h6M9 16h4"/>
                            </svg>
                            <p>No orders found<?= ($filter_status || $filter_payment) ? ' for the selected filters' : '' ?></p>
                        </div>

                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#&nbsp;Order</th>
                                <th>Customer</th>
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
                        <?php foreach ($orders as $order):
                            $oid            = (int) $order['order_id'];
                            $customer       = htmlspecialchars($order['full_name'], ENT_QUOTES, 'UTF-8');
                            $total_products = (int) $order['total_products'];
                            $total_items    = (int) $order['total_items'];
                            $amount         = number_format((float) $order['total_amount'], 2);
                            $method_id      = (int) $order['method_id'];
                            $method_label   = payment_label($method_id);
                            $pay_status     = $order['payment_status'];
                            $ord_status     = $order['order_status'];
                            $date           = date('M j, Y', strtotime($order['created_at']));
                        ?>
                            <tr>
                                <td style="font-weight:600; color:var(--muted);">#<?= $oid ?></td>
                                <td><?= $customer ?></td>
                                <td style="text-align:center;"><?= $total_products ?></td>
                                <td style="text-align:center;"><?= $total_items ?></td>
                                <td style="font-weight:600;">Rs <?= $amount ?></td>

                                <!-- Payment method -->
                                <td>
                                    <?php if ($method_label === 'eSewa'): ?>
                                        <span class="badge badge-esewa">eSewa</span>
                                    <?php elseif ($method_label === 'COD'): ?>
                                        <span class="badge badge-cod">COD</span>
                                    <?php else: ?>
                                        <span class="badge"><?= htmlspecialchars($method_label) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Payment status (editable) -->
                                <td>
                                    <form method="POST" action="all_orders.php" class="status-form payment-status-form">
                                        <input type="hidden" name="order_id"       value="<?= $oid ?>">
                                        <input type="hidden" name="filter_status"  value="<?= htmlspecialchars($filter_status) ?>">
                                        <input type="hidden" name="filter_payment" value="<?= htmlspecialchars($filter_payment) ?>">
                                        <input type="hidden" name="page"           value="<?= $current_page ?>">
                                        <select name="payment_status" class="status-select payment-select pay-select-<?= htmlspecialchars($pay_status, ENT_QUOTES, 'UTF-8') ?>">
                                            <?php foreach (['unpaid', 'paid', 'failed', 'refunded'] as $ps): ?>
                                                <option value="<?= $ps ?>" <?= $ps === $pay_status ? 'selected' : '' ?>>
                                                    <?= ucfirst($ps) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>

<!-- Order status (Status column) -->
<td>
    <form method="POST" action="all_orders.php" class="status-form order-status-form">
        <input type="hidden" name="order_id"       value="<?= $oid ?>">
        <input type="hidden" name="filter_status"  value="<?= htmlspecialchars($filter_status) ?>">
        <input type="hidden" name="filter_payment" value="<?= htmlspecialchars($filter_payment) ?>">
        <input type="hidden" name="page"           value="<?= $current_page ?>">
        <select name="order_status" class="status-select order-status-select">
            <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $s === $ord_status ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</td>
                                <td style="white-space:nowrap; color:var(--muted);"><?= $date ?></td>

                                <!-- Actions -->
                                <td>
                                    <div class="actions">
                                        <a href="order_details.php?order_id=<?= $oid ?>" class="btn-view">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                </div><!-- /.orders-table-wrap -->
            </div><!-- /.card -->

            <!-- ── Pagination ─────────────────────────────────────────────── -->
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
</div>

<?php include '../footer.php'; ?>
<script src="/jerseyflow-ecommerce/script/admin_menu.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_navbar.js"></script>
<script src="/jerseyflow-ecommerce/script/all_orders.js"></script>
</body>
</html>