<?php
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ── Fetch user info ──────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT full_name, profile_image FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── KPI: Total Orders ────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$total_orders = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

// ── KPI: Pending Orders ──────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'pending'");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$pending_orders = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

// ── KPI: Delivered Orders ────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'delivered'");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$delivered_orders = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

// ── KPI: Total Amount Spent (paid orders only) ───────────────────
$stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND payment_status = 'paid'");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$total_spent = (float) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

// ── Chart: Orders Over Time (last 12 months) ─────────────────────
$stmt = mysqli_prepare($conn, "
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
           DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           COUNT(*) AS order_count
    FROM orders
    WHERE user_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$orders_over_time = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders_over_time[] = $row;
}
$oot_labels = array_column($orders_over_time, 'month_label');
$oot_data   = array_column($orders_over_time, 'order_count');

// ── Chart: Spending Trends (last 12 months) ──────────────────────
$stmt = mysqli_prepare($conn, "
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
           DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           COALESCE(SUM(total_amount), 0) AS total_spent
    FROM orders
    WHERE user_id = ?
      AND payment_status = 'paid'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$spending_trends = [];
while ($row = mysqli_fetch_assoc($result)) {
    $spending_trends[] = $row;
}
$st_labels = array_column($spending_trends, 'month_label');
$st_data   = array_column($spending_trends, 'total_spent');

// ── Chart: Order Status Distribution ─────────────────────────────
// order_status enum: pending, processing, shipped, delivered
$stmt = mysqli_prepare($conn, "
    SELECT order_status, COUNT(*) AS cnt
    FROM orders
    WHERE user_id = ?
    GROUP BY order_status
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$status_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $status_rows[$row['order_status']] = (int) $row['cnt'];
}
$status_labels = ['pending', 'processing', 'shipped', 'delivered'];
$status_data   = array_map(fn($s) => $status_rows[$s] ?? 0, $status_labels);

// ── Chart: Payment Method Usage ──────────────────────────────────
$stmt = mysqli_prepare($conn, "
    SELECT pm.method_name, COUNT(o.order_id) AS cnt
    FROM orders o
    JOIN payment_methods pm ON o.method_id = pm.method_id
    WHERE o.user_id = ?
    GROUP BY pm.method_name
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $payment_rows[$row['method_name']] = (int) $row['cnt'];
}
$esewa_count = $payment_rows['Esewa'] ?? 0;
$cod_count   = $payment_rows['COD']   ?? 0;

// ── Recent Orders (last 5) ───────────────────────────────────────
$stmt = mysqli_prepare($conn, "
    SELECT o.order_id, o.total_amount, o.order_status, o.payment_status,
           o.created_at, pm.method_name,
           COUNT(oi.order_item_id) AS item_count
    FROM orders o
    LEFT JOIN payment_methods pm ON o.method_id = pm.method_id
    LEFT JOIN order_items oi    ON o.order_id   = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id, o.total_amount, o.order_status,
             o.payment_status, o.created_at, pm.method_name
    ORDER BY o.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$recent_orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recent_orders[] = $row;
}

// ── Helper: badge CSS class ──────────────────────────────────────
// order_status  : pending | processing | shipped | delivered
// payment_status: unpaid  | paid       | failed  | refunded
function statusClass(string $status): string {
    return match(strtolower($status)) {
        'delivered'  => 'badge-delivered',
        'paid'       => 'badge-delivered',
        'pending'    => 'badge-pending',
        'unpaid'     => 'badge-pending',
        'processing' => 'badge-processing',
        'shipped'    => 'badge-shipped',
        'failed'     => 'badge-failed',
        'refunded'   => 'badge-refunded',
        default      => 'badge-muted',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Homepage | Dashboard</title>
</head>
<body>
    <?php include 'users_navbar.php'; ?>
    <div class="page-wrapper">
<?php include 'users_menu.php'; ?>

<link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../style/users_navbar.css" />
  <link rel="stylesheet" href="../style/footer.css" />
  <link rel="stylesheet" href="../style/users_homepage.css" />
<div class="dashboard-root">

    <!-- ── Welcome Bar ──────────────────────────────────── -->
    <div class="welcome-bar">
        <div class="welcome-text">
            <span class="welcome-label">Welcome back,</span>
            <span class="welcome-name"><?= htmlspecialchars($user['full_name']) ?></span>
        </div>
        <div class="welcome-date"><?= date('l, d F Y') ?></div>
    </div>

    <!-- ── KPI Cards ────────────────────────────────────── -->
    <section class="kpi-grid">

        <div class="kpi-card">
            <div class="kpi-icon kpi-icon--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                    <path d="M9 12h6M9 16h4"/>
                </svg>
            </div>
            <div class="kpi-body">
                <span class="kpi-value"><?= number_format($total_orders) ?></span>
                <span class="kpi-label">Total Orders</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon kpi-icon--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 3"/>
                </svg>
            </div>
            <div class="kpi-body">
                <span class="kpi-value"><?= number_format($pending_orders) ?></span>
                <span class="kpi-label">Pending Orders</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon kpi-icon--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M5 13l4 4L19 7"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            </div>
            <div class="kpi-body">
                <span class="kpi-value"><?= number_format($delivered_orders) ?></span>
                <span class="kpi-label">Delivered Orders</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon kpi-icon--red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/>
                    <line x1="10" y1="14" x2="14" y2="14"/>
                </svg>
            </div>
            <div class="kpi-body">
                <span class="kpi-value">Rs. <?= number_format($total_spent, 2) ?></span>
                <span class="kpi-label">Total Amount Spent</span>
            </div>
        </div>

    </section>

    <!-- ── Charts Row 1: Line Charts ────────────────────── -->
    <section class="charts-row">

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Orders Over Time</span>
                <span class="chart-subtitle">Last 12 months</span>
            </div>
            <div class="chart-body">
                <canvas id="ordersLineChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Spending Trends</span>
                <span class="chart-subtitle">Paid orders · Last 12 months</span>
            </div>
            <div class="chart-body">
                <canvas id="spendingLineChart"></canvas>
            </div>
        </div>

    </section>

    <!-- ── Charts Row 2: Pie / Doughnut ─────────────────── -->
    <section class="charts-row charts-row--sm">

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Order Status</span>
                <span class="chart-subtitle">Distribution</span>
            </div>
            <div class="chart-body chart-body--sm">
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">Payment Methods</span>
                <span class="chart-subtitle">eSewa vs COD</span>
            </div>
            <div class="chart-body chart-body--sm">
                <canvas id="paymentDoughnutChart"></canvas>
            </div>
        </div>

    </section>

    <!-- ── Recent Orders Table ──────────────────────────── -->
    <section class="table-card">
        <div class="table-header">
            <span class="chart-title">Recent Orders</span>
            <span class="chart-subtitle">Last 5 orders</span>
        </div>

        <?php if (empty($recent_orders)): ?>
            <div class="empty-state">No orders found yet.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Order Status</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td class="order-id">#<?= $order['order_id'] ?></td>
                        <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                        <td><?= (int)$order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></td>
                        <td class="order-amount">Rs. <?= number_format($order['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($order['method_name'] ?? '—') ?></td>
                        <td><span class="badge <?= statusClass($order['order_status']) ?>"><?= ucfirst($order['order_status']) ?></span></td>
                        <td><span class="badge <?= statusClass($order['payment_status']) ?>"><?= ucfirst($order['payment_status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    
</div><!-- /.dashboard-root -->
</div><!-- /.dashboard-root -->

<?php include '../footer.php'; ?>
</body>

<!-- Chart.js offline -->
<script src="/jerseyflow-ecommerce/script/chart.umd.min.js"></script>
<script>
    const dashData = {
        ordersOverTime: {
            labels: <?= json_encode($oot_labels) ?>,
            data:   <?= json_encode(array_map('intval', $oot_data)) ?>
        },
        spendingTrends: {
            labels: <?= json_encode($st_labels) ?>,
            data:   <?= json_encode(array_map('floatval', $st_data)) ?>
        },
        statusDist: {
            labels: <?= json_encode(array_map('ucfirst', $status_labels)) ?>,
            data:   <?= json_encode($status_data) ?>
        },
        paymentMethods: {
            esewa: <?= $esewa_count ?>,
            cod:   <?= $cod_count ?>
        }
    };
</script>
<script src="../script/users_homepage.js"></script>
</html>