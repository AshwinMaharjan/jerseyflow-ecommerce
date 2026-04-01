<?php
/**
 * JerseyFlow IMS — Low Stock Alerts
 * File: admin/ims/ims_low_stock.php
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

require_once 'ims_helpers.php';

// ── AJAX: dismiss notification ─────────────────────────────
if (!empty($_POST['dismiss_notif'])) {
    header('Content-Type: application/json');
    ims_dismiss_notif($conn, (int)$_POST['notif_id']);
    echo json_encode(['ok' => true]);
    exit;
}

$filter = $_GET['filter'] ?? 'all'; // all | low | out

$where = ['pv.is_active = 1'];
if ($filter === 'low') $where[] = 'pv.stock > 0 AND pv.stock <= pv.reorder_level';
elseif ($filter === 'out') $where[] = 'pv.stock = 0';
else $where[] = 'pv.stock <= pv.reorder_level';

$wsql = 'WHERE ' . implode(' AND ', $where);

$variants = $conn->query("
    SELECT pv.*, p.product_name, p.product_id, p.image
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    $wsql
    ORDER BY pv.stock ASC, p.product_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Unread notifications
$notifications = $conn->query("
    SELECT n.*, p.product_name, pv.size, pv.color
    FROM inventory_notifications n
    LEFT JOIN product_variants pv ON pv.variant_id = n.variant_id
    LEFT JOIN products p ON p.product_id = n.product_id
    WHERE n.is_dismissed = 0
    ORDER BY n.created_at DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Mark all as read
$conn->query("UPDATE inventory_notifications SET is_read=1 WHERE is_read=0");

$counts = [
    'all' => $conn->query("SELECT COUNT(*) FROM product_variants WHERE stock <= reorder_level AND is_active=1")->fetch_row()[0],
    'low' => $conn->query("SELECT COUNT(*) FROM product_variants WHERE stock > 0 AND stock <= reorder_level AND is_active=1")->fetch_row()[0],
    'out' => $conn->query("SELECT COUNT(*) FROM product_variants WHERE stock = 0 AND is_active=1")->fetch_row()[0],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Low Stock Alerts — JerseyFlow IMS</title>
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
      <h1 class="page-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h1>
      <p class="page-subtitle">Variants at or below their reorder threshold</p>
    </div>
    <a href="ims_reorder.php" class="btn-ims-primary"><i class="fa-solid fa-cart-plus"></i> Reorder Management</a>
  </div>

  <!-- Filter Tabs -->
  <div class="ims-tab-row">
    <a href="?filter=all" class="ims-tab <?= $filter==='all' ? 'active' : '' ?>">
      All Critical <span class="tab-badge"><?= $counts['all'] ?></span>
    </a>
    <a href="?filter=low" class="ims-tab <?= $filter==='low' ? 'active' : '' ?>">
      <i class="fa-solid fa-triangle-exclamation"></i> Low Stock
      <span class="tab-badge tab-badge-warn"><?= $counts['low'] ?></span>
    </a>
    <a href="?filter=out" class="ims-tab <?= $filter==='out' ? 'active' : '' ?>">
      <i class="fa-solid fa-ban"></i> Out of Stock
      <span class="tab-badge tab-badge-danger"><?= $counts['out'] ?></span>
    </a>
  </div>

  <div class="low-stock-layout">

    <!-- Main Grid -->
    <div class="low-stock-main">
      <?php if (empty($variants)): ?>
        <div class="ims-card">
          <div class="ims-empty" style="padding:60px 0;">
            <i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:40px;"></i>
            <p>All variants have healthy stock levels!</p>
          </div>
        </div>
      <?php else: ?>
        <div class="low-stock-grid">
          <?php foreach ($variants as $v): ?>
            <?php $pct = $v['reorder_level'] > 0 ? min(100, round(($v['stock'] / max(1,$v['reorder_level'])) * 100)) : 0; ?>
            <div class="low-stock-card <?= $v['stock']==0 ? 'lsc-out' : 'lsc-low' ?>">
              <div class="lsc-top">
                <div class="lsc-info">
                  <span class="lsc-name"><?= htmlspecialchars($v['product_name']) ?></span>
                  <span class="lsc-variant"><?= $v['size'] ?> · <?= htmlspecialchars($v['color']) ?></span>
                  <code class="lsc-sku"><?= htmlspecialchars($v['sku']) ?></code>
                </div>
                <div class="lsc-stock-block">
                  <span class="lsc-qty <?= $v['stock']==0 ? 'lsq-zero' : 'lsq-low' ?>"><?= $v['stock'] ?></span>
                  <span class="lsc-qty-label">units left</span>
                </div>
              </div>

              <!-- Progress bar -->
              <div class="lsc-bar-wrap">
                <div class="lsc-bar" style="width:<?= $pct ?>%"></div>
              </div>
              <div class="lsc-bar-labels">
                <span>0</span>
                <span>Reorder: <?= $v['reorder_level'] ?></span>
              </div>

              <div class="lsc-actions">
                <a href="ims_adjust.php?variant_id=<?= $v['variant_id'] ?>" class="btn-ims-xs btn-ims-xs-primary">
                  <i class="fa-solid fa-plus"></i> Stock In
                </a>
                <a href="ims_reorder.php?variant_id=<?= $v['variant_id'] ?>" class="btn-ims-xs btn-ims-xs-ghost">
                  <i class="fa-solid fa-cart-plus"></i> Reorder
                </a>
                <a href="ims_movements.php?variant_id=<?= $v['variant_id'] ?>" class="btn-ims-xs btn-ims-xs-ghost">
                  <i class="fa-solid fa-clock-rotate-left"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Notification Panel -->
    <div class="ims-card notif-panel">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-bell"></i> System Alerts</span>
        <?php if ($notifications): ?>
          <button class="ims-link-sm" onclick="dismissAll()">Dismiss All</button>
        <?php endif; ?>
      </div>
      <?php if (empty($notifications)): ?>
        <div class="ims-empty" style="padding:32px 0;">
          <i class="fa-solid fa-bell-slash"></i><p>No active alerts.</p>
        </div>
      <?php else: ?>
        <ul class="notif-list" id="notifList">
          <?php foreach ($notifications as $n): ?>
            <li class="notif-item notif-<?= strtolower($n['type']) ?>" id="notif-<?= $n['notif_id'] ?>">
              <div class="notif-icon">
                <?php if ($n['type']==='OUT_OF_STOCK'): ?><i class="fa-solid fa-ban"></i>
                <?php elseif ($n['type']==='LOW_STOCK'):  ?><i class="fa-solid fa-triangle-exclamation"></i>
                <?php else: ?>                             <i class="fa-solid fa-circle-info"></i>
                <?php endif; ?>
              </div>
              <div class="notif-body">
                <span class="notif-msg"><?= htmlspecialchars($n['message']) ?></span>
                <?php if ($n['product_name']): ?>
                  <span class="notif-meta"><?= htmlspecialchars($n['product_name']) ?>
                    <?= $n['size'] ? '· ' . $n['size'] : '' ?>
                    <?= $n['color'] ? '· ' . htmlspecialchars($n['color']) : '' ?>
                  </span>
                <?php endif; ?>
                <span class="notif-time"><?= date('M d, H:i', strtotime($n['created_at'])) ?></span>
              </div>
              <button class="notif-dismiss" onclick="dismissNotif(<?= $n['notif_id'] ?>)" title="Dismiss">
                <i class="fa-solid fa-xmark"></i>
              </button>
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
<script src="../../script/admin_menu.js"></script>

<script>
function dismissNotif(id) {
  const item = document.getElementById('notif-' + id);
  fetch('ims_low_stock.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: `dismiss_notif=1&notif_id=${id}`
  }).then(r => r.json()).then(d => {
    if (d.ok && item) { item.style.opacity='0'; setTimeout(()=>item.remove(), 300); }
  });
}

function dismissAll() {
  document.querySelectorAll('.notif-item').forEach(item => {
    const id = item.id.replace('notif-','');
    dismissNotif(parseInt(id));
  });
}
</script>
</body>
</html>