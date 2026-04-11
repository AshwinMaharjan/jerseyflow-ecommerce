<?php
require_once '../connect.php';

// ── KPI Stats ─────────────────────────────────────────────────────────────────
$kpi = $conn->query("
    SELECT
        COUNT(*)                                          AS total_transactions,
        COALESCE(SUM(amount), 0)                         AS total_revenue,
        COALESCE(SUM(CASE WHEN payment_status='paid'     THEN amount END), 0) AS paid_revenue,
        COALESCE(SUM(CASE WHEN payment_status='refunded' THEN amount END), 0) AS refunded_amount,
        COALESCE(SUM(CASE WHEN payment_status='failed'   THEN amount END), 0) AS failed_amount,
        COUNT(CASE WHEN payment_status='paid'     THEN 1 END) AS paid_count,
        COUNT(CASE WHEN payment_status='failed'   THEN 1 END) AS failed_count,
        COUNT(CASE WHEN payment_status='refunded' THEN 1 END) AS refunded_count,
        COUNT(CASE WHEN payment_status='unpaid'   THEN 1 END) AS unpaid_count
    FROM payments
")->fetch_assoc();

// ── Revenue by day (last 30 days) ────────────────────────────────────────────
$daily = $conn->query("
    SELECT DATE(paid_at) AS day, SUM(amount) AS revenue
    FROM payments
    WHERE payment_status = 'paid'
      AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(paid_at)
    ORDER BY day ASC
");
$daily_labels = $daily_data = [];
while ($r = $daily->fetch_assoc()) {
    $daily_labels[] = date('M j', strtotime($r['day']));
    $daily_data[]   = (float) $r['revenue'];
}

// ── Payment status breakdown ──────────────────────────────────────────────────
$status_rows = $conn->query("
    SELECT payment_status, COUNT(*) AS cnt
    FROM payments GROUP BY payment_status
")->fetch_all(MYSQLI_ASSOC);
$status_labels = $status_counts = [];
foreach ($status_rows as $r) {
    $status_labels[] = ucfirst($r['payment_status']);
    $status_counts[] = (int) $r['cnt'];
}

// ── Gateway breakdown ─────────────────────────────────────────────────────────
$gw_rows = $conn->query("
    SELECT gateway, COUNT(*) AS cnt, SUM(amount) AS total
    FROM payments
    WHERE payment_status = 'paid'
    GROUP BY gateway
")->fetch_all(MYSQLI_ASSOC);
$gw_labels = $gw_counts = $gw_totals = [];
foreach ($gw_rows as $r) {
    $gw_labels[] = $r['gateway'] ?: 'Unknown';
    $gw_counts[] = (int)   $r['cnt'];
    $gw_totals[] = (float) $r['total'];
}

// ── Revenue by month (last 12 months) ────────────────────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(paid_at, '%Y-%m') AS mo, SUM(amount) AS revenue
    FROM payments
    WHERE payment_status = 'paid'
      AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mo ORDER BY mo ASC
");
$mo_labels = $mo_data = [];
while ($r = $monthly->fetch_assoc()) {
    $mo_labels[] = date('M Y', strtotime($r['mo'] . '-01'));
    $mo_data[]   = (float) $r['revenue'];
}

// ── Recent transactions ───────────────────────────────────────────────────────
$recent = $conn->query("
    SELECT payment_id, order_id, amount, payment_status, gateway, transaction_id, failure_reason, paid_at, created_at
    FROM payments
    ORDER BY created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt_currency(float $n): string {
    return 'Rs ' . number_format($n, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reports — Admin</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_menu.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/payment_reports.css">
    <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
</head>
<body>

<?php include '../admin_navbar.php'; ?>
<div class="page-wrapper">
    <?php include '../admin_menu.php'; ?>
<div class="admin-wrapper">

    <main class="admin-content">

        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1>Payment Reports</h1>
                <p class="subtitle">Analytics and insights across all transactions</p>
            </div>
        </div>

        <!-- ── KPI Cards ─────────────────────────────────────────────────── -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon kpi-green"><i class="fa-solid fa-circle-check"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Paid Revenue</span>
                    <span class="kpi-value"><?= fmt_currency($kpi['paid_revenue']) ?></span>
                    <span class="kpi-sub"><?= $kpi['paid_count'] ?> transactions</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-red"><i class="fa-solid fa-circle-xmark"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Failed Payments</span>
                    <span class="kpi-value"><?= fmt_currency($kpi['failed_amount']) ?></span>
                    <span class="kpi-sub"><?= $kpi['failed_count'] ?> transactions</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-amber"><i class="fa-solid fa-rotate-left"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Refunded</span>
                    <span class="kpi-value"><?= fmt_currency($kpi['refunded_amount']) ?></span>
                    <span class="kpi-sub"><?= $kpi['refunded_count'] ?> transactions</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-muted"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Unpaid</span>
                    <span class="kpi-value"><?= $kpi['unpaid_count'] ?></span>
                    <span class="kpi-sub">pending orders</span>
                </div>
            </div>
        </div>

        <!-- ── Charts row 1 ──────────────────────────────────────────────── -->
        <div class="charts-row">
            <div class="chart-card chart-wide">
                <div class="chart-header">
                    <h2>Daily Revenue <span class="chart-badge">Last 30 days</span></h2>
                </div>
                <div class="chart-wrap">
                    <canvas id="dailyRevenueChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h2>Payment Status</h2>
                </div>
                <div class="chart-wrap chart-wrap-doughnut">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- ── Charts row 2 ──────────────────────────────────────────────── -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h2>Gateway Breakdown</h2>
                </div>
                <div class="chart-wrap chart-wrap-doughnut">
                    <canvas id="gatewayChart"></canvas>
                </div>
            </div>
            <div class="chart-card chart-wide">
                <div class="chart-header">
                    <h2>Monthly Revenue <span class="chart-badge">Last 12 months</span></h2>
                </div>
                <div class="chart-wrap">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- ── Recent Transactions ───────────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
            </div>
            <div class="orders-table-wrap">
                <?php if (empty($recent)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-receipt" style="font-size:2rem;color:var(--muted)"></i>
                        <p>No transactions yet</p>
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Order</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Gateway</th>
                            <th>Transaction ID</th>
                            <th>Failure Reason</th>
                            <th>Paid At</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $p): ?>
                        <tr>
                            <td style="color:var(--muted);font-weight:600;">#<?= $p['payment_id'] ?></td>
                            <td>#<?= $p['order_id'] ?></td>
                            <td style="font-weight:600;"><?= fmt_currency((float)$p['amount']) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($p['payment_status']) ?>">
                                    <?= ucfirst($p['payment_status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['gateway'] ?: '—') ?></td>
                            <td class="tx-id"><?= $p['transaction_id'] ? htmlspecialchars($p['transaction_id']) : '—' ?></td>
                            <td class="failure-reason"><?= $p['failure_reason'] ? htmlspecialchars($p['failure_reason']) : '—' ?></td>
                            <td style="white-space:nowrap;color:var(--muted);"><?= $p['paid_at'] ? date('M j, Y H:i', strtotime($p['paid_at'])) : '—' ?></td>
                            <td style="white-space:nowrap;color:var(--muted);"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        </div>

    </main>
</div>

<?php include '../footer.php'; ?>

<!-- Chart.js (offline) -->
<script src="/jerseyflow-ecommerce/script/chart.umd.min.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_menu.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_navbar.js"></script>

<!-- Inject PHP data for charts -->
<script>
window.CHART_DATA = {
    daily: {
        labels: <?= json_encode($daily_labels) ?>,
        data:   <?= json_encode($daily_data) ?>
    },
    status: {
        labels: <?= json_encode($status_labels) ?>,
        data:   <?= json_encode($status_counts) ?>
    },
    gateway: {
        labels: <?= json_encode($gw_labels) ?>,
        counts: <?= json_encode($gw_counts) ?>,
        totals: <?= json_encode($gw_totals) ?>
    },
    monthly: {
        labels: <?= json_encode($mo_labels) ?>,
        data:   <?= json_encode($mo_data) ?>
    }
};
</script>
<script src="/jerseyflow-ecommerce/script/payment_reports.js"></script>
</body>
</html>