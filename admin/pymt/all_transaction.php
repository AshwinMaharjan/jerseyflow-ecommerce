<?php
require_once '../connect.php';

// ── Handle status update via POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    $payment_id = (int) $_POST['payment_id'];
    $updated    = false;

    if (isset($_POST['payment_status'])) {
        $allowed_statuses = ['pending', 'paid', 'failed'];
        $new_status       = $_POST['payment_status'];

        if (in_array($new_status, $allowed_statuses, true) && $payment_id > 0) {
            // Auto-set paid_at when marked as paid
            if ($new_status === 'paid') {
                $upd = $conn->prepare("UPDATE payments SET payment_status = ?, paid_at = NOW() WHERE payment_id = ?");
            } else {
                $upd = $conn->prepare("UPDATE payments SET payment_status = ? WHERE payment_id = ?");
            }
            $upd->bind_param("si", $new_status, $payment_id);
            $upd->execute();
            $upd->close();
            $updated = true;
        }
    }

    if ($updated) {
        $qs = http_build_query([
            'updated'        => 1,
            'filter_status'  => $_POST['filter_status']  ?? '',
            'filter_gateway' => $_POST['filter_gateway'] ?? '',
            'page'           => $_POST['page']           ?? 1,
        ]);
        header("Location: all_transaction.php?$qs");
        exit;
    }
}

// ── Filter inputs ─────────────────────────────────────────────────────────────
$allowed_statuses = ['', 'pending', 'paid', 'failed'];
$allowed_gateways = ['', 'cod', 'esewa'];

$filter_status  = in_array($_GET['filter_status']  ?? '', $allowed_statuses, true) ? ($_GET['filter_status']  ?? '') : '';
$filter_gateway = in_array($_GET['filter_gateway'] ?? '', $allowed_gateways, true) ? ($_GET['filter_gateway'] ?? '') : '';

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page     = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where_parts = [];
$bind_types  = '';
$bind_params = [];

if ($filter_status !== '') {
    $where_parts[] = 'p.payment_status = ?';
    $bind_types   .= 's';
    $bind_params[] = $filter_status;
}
if ($filter_gateway !== '') {
    $where_parts[] = 'p.gateway = ?';
    $bind_types   .= 's';
    $bind_params[] = $filter_gateway;
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Total count ───────────────────────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS total FROM payments p $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($bind_params) {
    $count_stmt->bind_param($bind_types, ...$bind_params);
}
$count_stmt->execute();
$total_payments = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages  = max(1, (int) ceil($total_payments / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

// ── Summary stats (unfiltered totals) ────────────────────────────────────────
$stats_result = $conn->query("
    SELECT payment_status, COUNT(*) AS cnt, SUM(amount) AS total
    FROM payments
    GROUP BY payment_status
");
$stats = [
    'pending' => ['cnt' => 0, 'total' => 0],
    'paid'    => ['cnt' => 0, 'total' => 0],
    'failed'  => ['cnt' => 0, 'total' => 0],
];
while ($row = $stats_result->fetch_assoc()) {
    if (isset($stats[$row['payment_status']])) {
        $stats[$row['payment_status']] = ['cnt' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }
}
$total_revenue = $stats['paid']['total'];

// ── Fetch paginated payments ──────────────────────────────────────────────────
$sql = "
    SELECT
        p.payment_id,
        p.order_id,
        p.amount,
        p.payment_status,
        p.gateway,
        p.transaction_id,
        p.failure_reason,
        p.paid_at,
        p.created_at
    FROM payments p
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt       = $conn->prepare($sql);
$all_types  = $bind_types . 'ii';
$all_params = array_merge($bind_params, [$per_page, $offset]);
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helper ────────────────────────────────────────────────────────────────────
function pagination_url_pay(array $params): string {
    return 'all_transaction.php?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Payments — Admin</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_menu.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/all_transaction.css">
    <link rel="icon"       href="../../images/logo_icon.ico" type="image/x-icon">
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
                        All Payments
                        <span class="count-pill"><?= $total_payments ?></span>
                    </h1>
                    <p class="subtitle">Monitor and manage all payment transactions</p>
                </div>
            </div>

            <!-- Success alert -->
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert-success">
                    &#10003; &nbsp;Payment status updated successfully.
                </div>
            <?php endif; ?>

            <!-- ── Summary Cards ──────────────────────────────────────────── -->
            <div class="summary-cards">
                <div class="summary-card">
                    <span class="sc-label">Total Revenue</span>
                    <span class="sc-value sc-revenue">Rs <?= number_format($total_revenue, 2) ?></span>
                </div>
                <div class="summary-card">
                    <span class="sc-label">Paid</span>
                    <span class="sc-value sc-paid"><?= $stats['paid']['cnt'] ?></span>
                </div>
                <div class="summary-card">
                    <span class="sc-label">Pending</span>
                    <span class="sc-value sc-pending"><?= $stats['pending']['cnt'] ?></span>
                </div>
                <div class="summary-card">
                    <span class="sc-label">Failed</span>
                    <span class="sc-value sc-failed"><?= $stats['failed']['cnt'] ?></span>
                </div>
            </div>

            <!-- ── Filters ────────────────────────────────────────────────── -->
            <div class="filters-bar">
                <form method="GET" action="all_transaction.php" class="filters-form">
                    <div class="filter-group">
                        <label for="filter_status" class="filter-label">Payment Status</label>
                        <div class="select-wrap">
                            <select name="filter_status" id="filter_status" class="filter-select">
                                <option value="">All Statuses</option>
                                <?php foreach (['pending', 'paid', 'failed'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                                        <?= ucfirst($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label for="filter_gateway" class="filter-label">Gateway</label>
                        <div class="select-wrap">
                            <select name="filter_gateway" id="filter_gateway" class="filter-select">
                                <option value="">All Gateways</option>
                                <?php foreach (['cod', 'esewa'] as $g): ?>
                                    <option value="<?= $g ?>" <?= $filter_gateway === $g ? 'selected' : '' ?>>
                                        <?= strtoupper($g) ?>
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
                        <?php if ($filter_status !== '' || $filter_gateway !== ''): ?>
                            <a href="all_transaction.php" class="btn-clear-filters">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Active filter pills -->
                <?php if ($filter_status !== '' || $filter_gateway !== ''): ?>
                    <div class="active-filters">
                        <?php if ($filter_status !== ''): ?>
                            <span class="filter-pill">
                                Status: <?= ucfirst($filter_status) ?>
                                <a href="<?= pagination_url_pay(['filter_gateway' => $filter_gateway]) ?>" class="pill-remove" title="Remove">&#x2715;</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filter_gateway !== ''): ?>
                            <span class="filter-pill">
                                Gateway: <?= strtoupper($filter_gateway) ?>
                                <a href="<?= pagination_url_pay(['filter_status' => $filter_status]) ?>" class="pill-remove" title="Remove">&#x2715;</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Payments table ─────────────────────────────────────────── -->
            <div class="card">
                <div class="orders-table-wrap">

                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="2" y="5" width="20" height="14" rx="2"/>
                                <path d="M2 10h20"/>
                            </svg>
                            <p>No payments found<?= ($filter_status || $filter_gateway) ? ' for the selected filters' : '' ?></p>
                        </div>

                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#&nbsp;ID</th>
                                <th>Order</th>
                                <th>Transaction ID</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Failure Reason</th>
                                <th>Paid At</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $pay):
                            $pid      = (int) $pay['payment_id'];
                            $oid      = (int) $pay['order_id'];
                            $amount   = number_format((float) $pay['amount'], 2);
                            $status   = $pay['payment_status'];
                            $gateway  = strtolower($pay['gateway']);
                            $txn_id   = $pay['transaction_id'] ?? '';
                            $fail_rsn = $pay['failure_reason'] ?? '';
                            $paid_at  = $pay['paid_at']    ? date('M j, Y', strtotime($pay['paid_at']))    : '—';
                            $created  = $pay['created_at'] ? date('M j, Y', strtotime($pay['created_at'])) : '—';
                        ?>
                            <tr>
                                <!-- Payment ID -->
                                <td style="font-weight:600; color:var(--muted);">#<?= $pid ?></td>

                                <!-- Order ID -->
                                <td>
                                    <a href="/jerseyflow-ecommerce/admin/orders_management/order_details.php?order_id=<?= $oid ?>" class="order-link">#<?= $oid ?></a>
                                </td>

                                <!-- Transaction ID -->
                                <td class="col-txn">
                                    <?php if ($txn_id): ?>
                                        <span class="txn-id" title="<?= htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(strlen($txn_id) > 22 ? substr($txn_id, 0, 22) . '…' : $txn_id, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--muted);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Gateway badge -->
                                <td>
                                    <?php if ($gateway === 'esewa'): ?>
                                        <span class="badge badge-esewa">eSewa</span>
                                    <?php else: ?>
                                        <span class="badge badge-cod">COD</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Amount -->
                                <td style="font-weight:600;">Rs <?= $amount ?></td>

                                <!-- Status select — auto-submits on change -->
                                <td>
                                    <form method="POST" action="all_transaction.php" class="status-form">
                                        <input type="hidden" name="payment_id"     value="<?= $pid ?>">
                                        <input type="hidden" name="filter_status"  value="<?= htmlspecialchars($filter_status,  ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="filter_gateway" value="<?= htmlspecialchars($filter_gateway, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="page"           value="<?= $current_page ?>">
                                        <select name="payment_status"
                                                class="status-select pay-select pay-select-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                                onchange="this.form.submit()">
                                            <?php foreach (['pending', 'paid', 'failed'] as $ps): ?>
                                                <option value="<?= $ps ?>" <?= $ps === $status ? 'selected' : '' ?>>
                                                    <?= ucfirst($ps) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>

                                <!-- Failure reason -->
                                <td>
                                    <?php if ($fail_rsn): ?>
                                        <span class="failure-reason" title="<?= htmlspecialchars($fail_rsn, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(strlen($fail_rsn) > 30 ? substr($fail_rsn, 0, 30) . '…' : $fail_rsn, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--muted);">—</span>
                                    <?php endif; ?>
                                </td>

                                <td style="white-space:nowrap; color:var(--muted);"><?= $paid_at ?></td>
                                <td style="white-space:nowrap; color:var(--muted);"><?= $created ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                </div><!-- /.orders-table-wrap -->
            </div><!-- /.card -->

            <!-- ── Pagination ─────────────────────────────────────────────── -->
            <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Payments pagination">
                <span class="pagination-info">
                    Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_payments) ?> of <?= $total_payments ?> payments
                </span>

                <div class="pagination-controls">
                    <!-- Previous -->
                    <?php if ($current_page > 1): ?>
                        <a href="<?= pagination_url_pay(['page' => $current_page - 1, 'filter_status' => $filter_status, 'filter_gateway' => $filter_gateway]) ?>"
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
                        <a href="<?= pagination_url_pay(['page' => 1, 'filter_status' => $filter_status, 'filter_gateway' => $filter_gateway]) ?>" class="page-btn">1</a>
                        <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <?php if ($p === $current_page): ?>
                            <span class="page-btn page-active" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= pagination_url_pay(['page' => $p, 'filter_status' => $filter_status, 'filter_gateway' => $filter_gateway]) ?>" class="page-btn"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                        <a href="<?= pagination_url_pay(['page' => $total_pages, 'filter_status' => $filter_status, 'filter_gateway' => $filter_gateway]) ?>" class="page-btn"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <!-- Next -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= pagination_url_pay(['page' => $current_page + 1, 'filter_status' => $filter_status, 'filter_gateway' => $filter_gateway]) ?>"
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
</div>

<?php include '../../footer.php'; ?>
<script src="/jerseyflow-ecommerce/script/admin_menu.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_navbar.js"></script>
</body>
</html>