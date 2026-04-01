<?php
/**
 * JerseyFlow IMS — Dashboard
 * File: admin/ims/ims_dashboard.php
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

require_once 'ims_helpers.php';


$stats       = ims_dashboard_stats($conn);
$fast_movers = ims_fast_movers($conn, 5);
$dead_stock  = ims_dead_stock($conn, 90);

// Recent movements (last 10)
$recent = $conn->query("
    SELECT sm.*, p.product_name, pv.size, pv.color, u.full_name AS admin_name
    FROM stock_movements sm
    JOIN product_variants pv ON pv.variant_id = sm.variant_id
    JOIN products         p  ON p.product_id  = sm.product_id
    LEFT JOIN users       u  ON u.user_id     = sm.admin_id
    ORDER BY sm.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Low stock list
$low_stock = $conn->query("
    SELECT pv.*, p.product_name, p.image
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE pv.stock <= pv.reorder_level AND pv.stock > 0 AND pv.is_active = 1
    ORDER BY pv.stock ASC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Monthly movement totals (last 6 months) for chart
$chart_data = $conn->query("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month_label,
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        SUM(CASE WHEN movement_type='IN'  THEN quantity ELSE 0 END) AS stock_in,
        SUM(CASE WHEN movement_type='OUT' THEN quantity ELSE 0 END) AS stock_out
    FROM stock_movements
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>IMS Dashboard — JerseyFlow</title>
    <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../style/footer.css">
    <link rel="stylesheet" href="../../style/admin_menu.css">
    <link rel="stylesheet" href="../../style/admin_navbar.css">
    <link rel="stylesheet" href="../../style/all_products.css">
    <link rel="stylesheet" href="../../style/ims.css">

</head>
<body>
<?php include '../admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include '../admin_menu.php'; ?>

    <div class="main-content">

  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-warehouse"></i> Inventory Dashboard</h1>
      <p class="page-subtitle">Real-time overview of your jersey stock</p>
    </div>
    <div class="header-actions">
      <a href="ims_adjust.php" class="btn-ims-primary"><i class="fa-solid fa-sliders"></i> Adjust Stock</a>
      <a href="ims_stock_in.php" class="btn-ims-success"><i class="fa-solid fa-plus"></i> Stock In</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="ims-kpi-grid">
    <div class="ims-kpi-card">
      <div class="kpi-icon kpi-blue"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div class="kpi-body">
        <span class="kpi-val"><?= number_format($stats['total_stock'] ?? 0) ?></span>
        <span class="kpi-lbl">Total Stock Units</span>
        <span class="kpi-sub"><?= number_format($stats['total_variants'] ?? 0) ?> variants tracked</span>
      </div>
    </div>
    <div class="ims-kpi-card">
      <div class="kpi-icon kpi-green"><i class="fa-solid fa-circle-check"></i></div>
      <div class="kpi-body">
        <span class="kpi-val"><?= number_format($stats['healthy'] ?? 0) ?></span>
        <span class="kpi-lbl">Healthy Stock</span>
        <span class="kpi-sub">Above reorder level</span>
      </div>
    </div>
    <div class="ims-kpi-card kpi-card-warn">
      <div class="kpi-icon kpi-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="kpi-body">
        <span class="kpi-val"><?= number_format($stats['low_stock'] ?? 0) ?></span>
        <span class="kpi-lbl">Low Stock</span>
        <span class="kpi-sub">At or below reorder point</span>
      </div>
      <?php if (($stats['low_stock'] ?? 0) > 0): ?>
        <a href="ims_low_stock.php" class="kpi-action-link">View all <i class="fa-solid fa-arrow-right"></i></a>
      <?php endif; ?>
    </div>
    <div class="ims-kpi-card kpi-card-danger">
      <div class="kpi-icon kpi-red"><i class="fa-solid fa-ban"></i></div>
      <div class="kpi-body">
        <span class="kpi-val"><?= number_format($stats['out_of_stock'] ?? 0) ?></span>
        <span class="kpi-lbl">Out of Stock</span>
        <span class="kpi-sub">Need immediate restock</span>
      </div>
      <?php if (($stats['out_of_stock'] ?? 0) > 0): ?>
        <a href="ims_low_stock.php?filter=out" class="kpi-action-link">View all <i class="fa-solid fa-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="ims-dash-grid">

    <!-- Movement Chart -->
    <div class="ims-card ims-card-wide">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-chart-column"></i> Stock Movement (6 months)</span>
      </div>
      <div class="ims-chart-wrap">
        <canvas id="movementChart" height="220"></canvas>
      </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="ims-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</span>
        <a href="ims_low_stock.php" class="ims-link-sm">See all</a>
      </div>
      <?php if (empty($low_stock)): ?>
        <div class="ims-empty"><i class="fa-solid fa-circle-check"></i><p>All stock levels are healthy!</p></div>
      <?php else: ?>
        <ul class="ims-alert-list">
          <?php foreach ($low_stock as $ls): ?>
            <li class="ims-alert-item <?= $ls['stock'] == 0 ? 'alert-out' : 'alert-low' ?>">
              <div class="alert-icon">
                <?php if ($ls['stock'] == 0): ?>
                  <i class="fa-solid fa-ban"></i>
                <?php else: ?>
                  <i class="fa-solid fa-triangle-exclamation"></i>
                <?php endif; ?>
              </div>
              <div class="alert-body">
                <span class="alert-name"><?= htmlspecialchars($ls['product_name']) ?></span>
                <span class="alert-meta"><?= $ls['size'] ?> · <?= htmlspecialchars($ls['color']) ?></span>
              </div>
              <div class="alert-stock">
                <span class="alert-qty <?= $ls['stock'] == 0 ? 'qty-zero' : 'qty-low' ?>"><?= $ls['stock'] ?></span>
                <span class="alert-reorder">/ <?= $ls['reorder_level'] ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Recent Movements -->
    <div class="ims-card ims-card-wide">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Stock Movements</span>
        <a href="ims_movements.php" class="ims-link-sm">Full log</a>
      </div>
      <div class="ims-table-wrap">
        <table class="ims-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Variant</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Before → After</th>
              <th>By</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
              <tr><td colspan="7" class="ims-td-empty">No movements recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recent as $m): ?>
                <tr>
                  <td class="td-name"><?= htmlspecialchars($m['product_name']) ?></td>
                  <td><span class="variant-pill"><?= $m['size'] ?> · <?= htmlspecialchars($m['color']) ?></span></td>
                  <td><?= movement_badge($m['movement_type']) ?></td>
                  <td class="td-qty"><?= $m['movement_type'] === 'OUT' ? '-' : '+' ?><?= $m['quantity'] ?></td>
                  <td class="td-arrow"><?= $m['stock_before'] ?> <i class="fa-solid fa-arrow-right"></i> <?= $m['stock_after'] ?></td>
                  <td><?= htmlspecialchars($m['admin_name'] ?? 'System') ?></td>
                  <td class="td-date"><?= date('M d, H:i', strtotime($m['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Fast Movers -->
    <div class="ims-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-fire"></i> Fast Movers <span class="header-sub">30 days</span></span>
      </div>
      <?php if (empty($fast_movers)): ?>
        <div class="ims-empty"><i class="fa-solid fa-chart-bar"></i><p>No sales data yet.</p></div>
      <?php else: ?>
        <ul class="ims-rank-list">
          <?php foreach ($fast_movers as $i => $fm): ?>
            <li class="ims-rank-item">
              <span class="rank-num"><?= $i + 1 ?></span>
              <div class="rank-body">
                <span class="rank-name"><?= htmlspecialchars($fm['product_name']) ?></span>
                <span class="rank-meta"><?= $fm['size'] ?> · <?= htmlspecialchars($fm['color']) ?></span>
              </div>
              <span class="rank-sold"><?= $fm['units_sold'] ?> <small>sold</small></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Dead Stock -->
    <div class="ims-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-box-archive"></i> Dead Stock <span class="header-sub">90 days</span></span>
      </div>
      <?php if (empty($dead_stock)): ?>
        <div class="ims-empty"><i class="fa-solid fa-check"></i><p>No dead stock found.</p></div>
      <?php else: ?>
        <ul class="ims-rank-list">
          <?php foreach (array_slice($dead_stock, 0, 6) as $ds): ?>
            <li class="ims-rank-item">
              <div class="rank-body">
                <span class="rank-name"><?= htmlspecialchars($ds['product_name']) ?></span>
                <span class="rank-meta"><?= $ds['size'] ?> · <?= htmlspecialchars($ds['color']) ?></span>
              </div>
              <span class="rank-sold dead-qty"><?= $ds['stock'] ?> <small>units</small></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </div>
  </div>
  </div>
  </div>
</main>
<?php include '../footer.php'; ?>

<script src="../../script/chart.umd.min.js"></script>
<script src="../../script/admin_menu.js"></script>

<script>
(function() {
  const raw    = <?= json_encode($chart_data) ?>;
  const labels = raw.map(r => r.month_label);
  const inData = raw.map(r => parseInt(r.stock_in)  || 0);
  const outData= raw.map(r => parseInt(r.stock_out) || 0);

  const style  = getComputedStyle(document.documentElement);
  const red    = style.getPropertyValue('--red').trim()    || '#c0392b';
  const green  = '#16a34a';
  const border = style.getPropertyValue('--border').trim() || '#e5e7eb';

  new Chart(document.getElementById('movementChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Stock In',
          data: inData,
          backgroundColor: 'rgba(22,163,74,0.75)',
          borderColor: green,
          borderWidth: 1,
          borderRadius: 5,
        },
        {
          label: 'Stock Out',
          data: outData,
          backgroundColor: 'rgba(192,57,43,0.75)',
          borderColor: red,
          borderWidth: 1,
          borderRadius: 5,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { font: { family: 'Barlow', size: 12 }, color: '#6b7280', boxWidth: 12 }},
        tooltip: { mode: 'index', intersect: false }
      },
      scales: {
        x: { grid: { color: border }, ticks: { font: { family: 'Barlow' }, color: '#6b7280' }},
        y: { grid: { color: border }, ticks: { font: { family: 'Barlow' }, color: '#6b7280' }, beginAtZero: true }
      }
    }
  });
})();
</script>
</body>
</html>