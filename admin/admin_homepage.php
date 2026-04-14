<?php
require_once '../connect.php';

// ── KPI: Total Revenue (paid only) ───────────────────────────────────────────
$row = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS total_revenue FROM orders WHERE payment_status='paid'")->fetch_assoc();
$total_revenue = (float) $row['total_revenue'];

// ── KPI: Total Orders ─────────────────────────────────────────────────────────
$row = $conn->query("SELECT COUNT(*) AS cnt FROM orders")->fetch_assoc();
$total_orders = (int) $row['cnt'];

// ── KPI: Pending Orders ───────────────────────────────────────────────────────
$row = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE order_status='pending'")->fetch_assoc();
$pending_orders = (int) $row['cnt'];

// ── KPI: Total Customers ──────────────────────────────────────────────────────
$row = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role='user' AND is_deleted=0")->fetch_assoc();
$total_customers = (int) $row['cnt'];

// ── KPI: Total Products ───────────────────────────────────────────────────────
$row = $conn->query("SELECT COUNT(*) AS cnt FROM products")->fetch_assoc();
$total_products = (int) $row['cnt'];

// ── KPI: Low Stock Items (stock <= 5) ────────────────────────────────────────
$row = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE stock <= 5")->fetch_assoc();
$low_stock_count = (int) $row['cnt'];

// ── Chart: Revenue Over Time (last 12 months) ────────────────────────────────
$result = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS mo,
           DATE_FORMAT(created_at,'%b %Y') AS label,
           COALESCE(SUM(total_amount),0)   AS revenue
    FROM orders
    WHERE payment_status='paid'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mo, label
    ORDER BY mo ASC
");
$rev_labels = $rev_data = [];
while ($r = $result->fetch_assoc()) {
    $rev_labels[] = $r['label'];
    $rev_data[]   = (float) $r['revenue'];
}

// ── Chart: Orders Over Time (last 12 months) ─────────────────────────────────
$result = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS mo,
           DATE_FORMAT(created_at,'%b %Y') AS label,
           COUNT(*) AS cnt
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mo, label
    ORDER BY mo ASC
");
$ord_labels = $ord_data = [];
while ($r = $result->fetch_assoc()) {
    $ord_labels[] = $r['label'];
    $ord_data[]   = (int) $r['cnt'];
}

// ── Chart: Top 5 Selling Products ────────────────────────────────────────────
$result = $conn->query("
    SELECT p.product_name, SUM(oi.quantity) AS qty_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    GROUP BY oi.product_id, p.product_name
    ORDER BY qty_sold DESC
    LIMIT 5
");
$prod_labels = $prod_data = [];
while ($r = $result->fetch_assoc()) {
    $prod_labels[] = $r['product_name'];
    $prod_data[]   = (int) $r['qty_sold'];
}

// ── Chart: Order Status Distribution ─────────────────────────────────────────
$result = $conn->query("
    SELECT order_status, COUNT(*) AS cnt
    FROM orders
    GROUP BY order_status
");
$ostatus_map = [];
while ($r = $result->fetch_assoc()) {
    $ostatus_map[$r['order_status']] = (int) $r['cnt'];
}
$ostatus_keys   = ['pending','delivered','cancelled','shipped'];
$ostatus_labels = array_map('ucfirst', $ostatus_keys);
$ostatus_data   = array_map(fn($k) => $ostatus_map[$k] ?? 0, $ostatus_keys);

// ── Chart: Payment Status Distribution ───────────────────────────────────────
$result = $conn->query("
    SELECT payment_status, COUNT(*) AS cnt
    FROM payments
    GROUP BY payment_status
");
$pstatus_map = [];
while ($r = $result->fetch_assoc()) {
    $pstatus_map[$r['payment_status']] = (int) $r['cnt'];
}
$pstatus_keys   = ['paid','unpaid','failed','refunded'];
$pstatus_labels = array_map('ucfirst', $pstatus_keys);
$pstatus_data   = array_map(fn($k) => $pstatus_map[$k] ?? 0, $pstatus_keys);

// ── Inventory: Total Stock & Low Stock ───────────────────────────────────────
$row = $conn->query("SELECT COALESCE(SUM(stock),0) AS total_stock FROM products")->fetch_assoc();
$total_stock = (int) $row['total_stock'];
$in_stock_count = $total_products - $low_stock_count;

// ── Users: Active Users (ordered in last 30 days) ────────────────────────────
$row = $conn->query("
    SELECT COUNT(DISTINCT user_id) AS cnt
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc();
$active_users = (int) $row['cnt'];

// ── Users: New Registrations (last 30 days) ──────────────────────────────────
$row = $conn->query("
    SELECT COUNT(*) AS cnt FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND is_deleted = 0
")->fetch_assoc();
$new_registrations = (int) $row['cnt'];

// ── Chart: New Users Per Month (last 6 months) ───────────────────────────────
$result = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS mo,
           DATE_FORMAT(created_at,'%b %Y') AS label,
           COUNT(*) AS cnt
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      AND is_deleted = 0
    GROUP BY mo, label
    ORDER BY mo ASC
");
$usr_labels = $usr_data = [];
while ($r = $result->fetch_assoc()) {
    $usr_labels[] = $r['label'];
    $usr_data[]   = (int) $r['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_navbar.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_menu.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/footer.css">
    <link rel="stylesheet" href="/jerseyflow-ecommerce/style/admin_homepage.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
</head>
<body>

<?php include 'admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="page-wrapper">

    <?php include 'admin_menu.php'; ?>

    <main class="admin-content">

        <!-- ── Page Header ──────────────────────────────────────────────── -->
        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p class="subtitle">Welcome back — here's what's happening today</p>
            </div>
            <div class="page-date"><?= date('l, d F Y') ?></div>
        </div>

        <!-- ── ROW 1: KPI Cards ──────────────────────────────────────────── -->
        <section class="kpi-grid">

            <div class="kpi-card">
                <div class="kpi-icon kpi-green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Revenue</span>
                    <span class="kpi-value">Rs <?= number_format($total_revenue, 2) ?></span>
                    <span class="kpi-sub">Paid orders only</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon kpi-blue"><i class="fa-solid fa-bag-shopping"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Orders</span>
                    <span class="kpi-value"><?= number_format($total_orders) ?></span>
                    <span class="kpi-sub">All time</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon kpi-amber"><i class="fa-solid fa-clock"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Pending Orders</span>
                    <span class="kpi-value"><?= number_format($pending_orders) ?></span>
                    <span class="kpi-sub">Awaiting processing</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon kpi-purple"><i class="fa-solid fa-users"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Customers</span>
                    <span class="kpi-value"><?= number_format($total_customers) ?></span>
                    <span class="kpi-sub">Registered accounts</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon kpi-teal"><i class="fa-solid fa-shirt"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Products</span>
                    <span class="kpi-value"><?= number_format($total_products) ?></span>
                    <span class="kpi-sub">In catalogue</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon kpi-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Low Stock Items</span>
                    <span class="kpi-value"><?= number_format($low_stock_count) ?></span>
                    <span class="kpi-sub">Stock &le; 5 units</span>
                </div>
            </div>

        </section>

        <!-- ── ROW 2: Revenue Chart (full width) ────────────────────────── -->
        <section class="chart-row-full">
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Revenue Over Time</h2>
                        <p class="chart-sub">Paid orders · Last 12 months</p>
                    </div>
                    <span class="chart-badge">Monthly</span>
                </div>
                <div class="chart-wrap chart-wrap-tall">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </section>

        <!-- ── ROW 3: Orders Over Time + Top Products ───────────────────── -->
        <section class="chart-row-split">

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Orders Over Time</h2>
                        <p class="chart-sub">All orders · Last 12 months</p>
                    </div>
                    <span class="chart-badge">Monthly</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Top Selling Products</h2>
                        <p class="chart-sub">By quantity sold · All time</p>
                    </div>
                    <span class="chart-badge">Top 5</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

        </section>

        <!-- ── ROW 4: Pie / Doughnut Charts ─────────────────────────────── -->
        <section class="chart-row-quad">

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Order Status</h2>
                        <p class="chart-sub">Distribution</p>
                    </div>
                </div>
                <div class="chart-wrap chart-wrap-circle">
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Payment Status</h2>
                        <p class="chart-sub">Distribution</p>
                    </div>
                </div>
                <div class="chart-wrap chart-wrap-circle">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Inventory Health</h2>
                        <p class="chart-sub">Stock vs Low Stock</p>
                    </div>
                </div>
                <div class="chart-wrap chart-wrap-circle">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>New Users</h2>
                        <p class="chart-sub">Last 6 months</p>
                    </div>
                </div>
                <div class="chart-wrap chart-wrap-circle">
                    <canvas id="newUsersChart"></canvas>
                </div>
            </div>

        </section>

        <!-- ── Inventory + User Insights KPIs ───────────────────────────── -->
        <section class="insights-row">

            <div class="insight-block">
                <h3 class="insight-title"><i class="fa-solid fa-warehouse"></i> Inventory Insights</h3>
                <div class="insight-kpi-row">
                    <div class="insight-kpi">
                        <span class="insight-kpi-value"><?= number_format($total_stock) ?></span>
                        <span class="insight-kpi-label">Total Stock Units</span>
                    </div>
                    <div class="insight-kpi">
                        <span class="insight-kpi-value insight-red"><?= number_format($low_stock_count) ?></span>
                        <span class="insight-kpi-label">Low Stock Products</span>
                    </div>
                    <div class="insight-kpi">
                        <span class="insight-kpi-value insight-green"><?= number_format($in_stock_count) ?></span>
                        <span class="insight-kpi-label">Healthy Stock</span>
                    </div>
                </div>
            </div>

            <div class="insight-block">
                <h3 class="insight-title"><i class="fa-solid fa-user-group"></i> User Insights</h3>
                <div class="insight-kpi-row">
                    <div class="insight-kpi">
                        <span class="insight-kpi-value insight-blue"><?= number_format($active_users) ?></span>
                        <span class="insight-kpi-label">Active Users (30d)</span>
                    </div>
                    <div class="insight-kpi">
                        <span class="insight-kpi-value insight-green"><?= number_format($new_registrations) ?></span>
                        <span class="insight-kpi-label">New Registrations (30d)</span>
                    </div>
                    <div class="insight-kpi">
                        <span class="insight-kpi-value"><?= number_format($total_customers) ?></span>
                        <span class="insight-kpi-label">Total Customers</span>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>
</div>

<?php include '../footer.php'; ?>

<script src="/jerseyflow-ecommerce/script/chart.umd.min.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_menu.js"></script>
<script src="/jerseyflow-ecommerce/script/admin_navbar.js"></script>

<script>
window.DASH = {
    revenue:       { labels: <?= json_encode($rev_labels) ?>,     data: <?= json_encode($rev_data) ?> },
    orders:        { labels: <?= json_encode($ord_labels) ?>,     data: <?= json_encode($ord_data) ?> },
    topProducts:   { labels: <?= json_encode($prod_labels) ?>,    data: <?= json_encode($prod_data) ?> },
    orderStatus:   { labels: <?= json_encode($ostatus_labels) ?>, data: <?= json_encode($ostatus_data) ?> },
    paymentStatus: { labels: <?= json_encode($pstatus_labels) ?>, data: <?= json_encode($pstatus_data) ?> },
    inventory:     { inStock: <?= $in_stock_count ?>, lowStock: <?= $low_stock_count ?> },
    newUsers:      { labels: <?= json_encode($usr_labels) ?>,     data: <?= json_encode($usr_data) ?> }
};
</script>
<script src="/jerseyflow-ecommerce/script/admin_homepage.js"></script>

</body>
</html>