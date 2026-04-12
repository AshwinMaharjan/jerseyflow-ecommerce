<?php
/**
 * JerseyFlow IMS — Low Stock Alert
 * File: admin/ims/ims_low_stock.php
 *
 * Shows all products with stock < 10, grouped by urgency.
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

// Fetch low stock products (stock < 10), ordered most critical first
$stmt = $conn->prepare("
    SELECT 
        p.product_id, 
        p.product_name, 
        p.price, 
        p.stock,
        pi.image_path AS image
    FROM products p
    LEFT JOIN product_images pi 
        ON p.product_id = pi.product_id 
        AND pi.is_primary = 1
    WHERE p.stock < 10
    ORDER BY p.stock ASC, p.product_name ASC
");
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary counts
$out_of_stock  = array_filter($low_stock, fn($p) => $p['stock'] == 0);
$critical      = array_filter($low_stock, fn($p) => $p['stock'] > 0 && $p['stock'] <= 3);
$low           = array_filter($low_stock, fn($p) => $p['stock'] > 3 && $p['stock'] < 10);

$total = count($low_stock);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Low Stock Alert — JerseyFlow IMS</title>
  <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../../style/footer.css">
  <link rel="stylesheet" href="../../style/admin_menu.css">
  <link rel="stylesheet" href="../../style/admin_navbar.css">
  <link rel="stylesheet" href="../../style/all_products.css">
  <link rel="stylesheet" href="../../style/ims.css">

  <style>
    :root {
      --bg:          #121212;
      --panel:       #1A1A1A;
      --panel2:      #202020;
      --text:        #EEE5D8;
      --muted:       rgba(238,229,216,.6);
      --border:      rgba(255,255,255,.08);
      --red:         #681010;
      --red-hover:   #7d1414;
      --red-soft:    rgba(104,16,16,.2);
      --green:       #166534;
      --hover:       rgba(255,255,255,.06);
      --shadow:      0 4px 18px rgba(0,0,0,.4);
      --nav-h:       64px;
      --radius:      8px;

      /* Alert tiers */
      --tier-out-bg:   rgba(127,0,0,.18);
      --tier-out-brd:  rgba(220,38,38,.35);
      --tier-out-clr:  #fca5a5;
      --tier-crit-bg:  rgba(120,53,15,.18);
      --tier-crit-brd: rgba(234,88,12,.35);
      --tier-crit-clr: #fdba74;
      --tier-low-bg:   rgba(120,100,0,.15);
      --tier-low-brd:  rgba(202,138,4,.3);
      --tier-low-clr:  #fde68a;
      --green-soft:    rgba(22,101,52,.25);
    }

    /* ── Page layout ──────────────────────────────────────── */
    .main-content { padding: 28px 32px; }

    /* ── Page header ──────────────────────────────────────── */
    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 28px;
    }

    .page-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .page-title .title-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: var(--tier-out-bg);
      border: 1px solid var(--tier-out-brd);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--tier-out-clr);
      font-size: 1rem;
      animation: pulse-icon 2.4s ease-in-out infinite;
    }

    @keyframes pulse-icon {
      0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,.3); }
      50%       { box-shadow: 0 0 0 8px rgba(220,38,38,0); }
    }

    .page-subtitle {
      color: var(--muted);
      font-size: .875rem;
      margin: 5px 0 0;
    }

    .btn-ims-ghost {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 16px;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: transparent;
      color: var(--muted);
      font-size: .85rem;
      font-weight: 500;
      text-decoration: none;
      transition: all .18s;
      white-space: nowrap;
    }

    .btn-ims-ghost:hover { background: var(--hover); color: var(--text); }

    /* ── Summary strip ────────────────────────────────────── */
    .summary-strip {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-bottom: 28px;
    }

    .summary-card {
      padding: 16px 20px;
      border-radius: var(--radius);
      border: 1px solid;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .summary-card.tier-out  { background: var(--tier-out-bg);  border-color: var(--tier-out-brd); }
    .summary-card.tier-crit { background: var(--tier-crit-bg); border-color: var(--tier-crit-brd); }
    .summary-card.tier-low  { background: var(--tier-low-bg);  border-color: var(--tier-low-brd); }

    .sc-icon {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .95rem;
      flex-shrink: 0;
    }

    .tier-out  .sc-icon { background: rgba(220,38,38,.2);  color: var(--tier-out-clr); }
    .tier-crit .sc-icon { background: rgba(234,88,12,.2);  color: var(--tier-crit-clr); }
    .tier-low  .sc-icon { background: rgba(202,138,4,.2);  color: var(--tier-low-clr); }

    .sc-count {
      font-size: 1.75rem;
      font-weight: 800;
      line-height: 1;
    }

    .tier-out  .sc-count { color: var(--tier-out-clr); }
    .tier-crit .sc-count { color: var(--tier-crit-clr); }
    .tier-low  .sc-count { color: var(--tier-low-clr); }

    .sc-label {
      font-size: .78rem;
      color: var(--muted);
      margin-top: 3px;
      text-transform: uppercase;
      letter-spacing: .05em;
    }

    /* ── Section label ────────────────────────────────────── */
    .section-label {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 28px 0 14px;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
    }

    .section-label .sl-bar {
      height: 2px;
      flex: 1;
      border-radius: 2px;
    }

    .sl-out  { color: var(--tier-out-clr);  }
    .sl-crit { color: var(--tier-crit-clr); }
    .sl-low  { color: var(--tier-low-clr);  }

    .sl-out  .sl-bar { background: var(--tier-out-brd);  }
    .sl-crit .sl-bar { background: var(--tier-crit-brd); }
    .sl-low  .sl-bar { background: var(--tier-low-brd);  }

    /* ── Product grid ─────────────────────────────────────── */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 16px;
    }

    /* ── Product card ─────────────────────────────────────── */
    .low-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform .18s, border-color .18s, box-shadow .18s;
      animation: card-in .35s ease both;
    }

    .low-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(0,0,0,.4);
    }

    /* Tier border accent */
    .low-card.tier-out  { border-top: 3px solid #dc2626; }
    .low-card.tier-crit { border-top: 3px solid #ea580c; }
    .low-card.tier-low  { border-top: 3px solid #ca8a04; }

    .low-card:hover.tier-out  { border-color: #dc2626; }
    .low-card:hover.tier-crit { border-color: #ea580c; }
    .low-card:hover.tier-low  { border-color: #ca8a04; }

    @keyframes card-in {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Stagger cards */
    <?php for ($i = 0; $i < 30; $i++): ?>
    .low-card:nth-child(<?= $i+1 ?>) { animation-delay: <?= $i * 0.045 ?>s; }
    <?php endfor; ?>

    /* ── Card image ───────────────────────────────────────── */
    .lc-image-wrap {
      position: relative;
      width: 100%;
      aspect-ratio: 4/3;
      background: var(--panel2);
      overflow: hidden;
    }

    .lc-image-wrap img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
      transition: transform .3s ease;
    }

    .low-card:hover .lc-image-wrap img { transform: scale(1.04); }

    .lc-img-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 2rem;
    }

    /* Stock badge on image */
    .lc-stock-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 800;
      letter-spacing: .03em;
      backdrop-filter: blur(6px);
    }

    .badge-out  { background: rgba(127,0,0,.75);   color: #fca5a5; border: 1px solid rgba(220,38,38,.5); }
    .badge-crit { background: rgba(120,53,15,.75); color: #fdba74; border: 1px solid rgba(234,88,12,.5); }
    .badge-low  { background: rgba(101,69,0,.75);  color: #fde68a; border: 1px solid rgba(202,138,4,.5); }

    /* Alert ribbon for out-of-stock */
    .lc-ribbon {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      padding: 6px 10px;
      background: rgba(127,0,0,.8);
      color: #fca5a5;
      font-size: .72rem;
      font-weight: 600;
      text-align: center;
      letter-spacing: .06em;
      text-transform: uppercase;
      backdrop-filter: blur(4px);
    }

    /* ── Card body ────────────────────────────────────────── */
    .lc-body {
      padding: 14px 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .lc-name {
      font-size: .9rem;
      font-weight: 600;
      color: var(--text);
      line-height: 1.3;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .lc-price {
      font-size: .78rem;
      color: var(--muted);
    }

    /* Stock meter bar */
    .lc-meter {
      margin-top: 6px;
    }

    .lc-meter-label {
      display: flex;
      justify-content: space-between;
      font-size: .72rem;
      color: var(--muted);
      margin-bottom: 5px;
    }

    .lc-meter-label strong { color: var(--text); }

    .lc-bar-track {
      height: 5px;
      background: var(--panel2);
      border-radius: 3px;
      overflow: hidden;
    }

    .lc-bar-fill {
      height: 100%;
      border-radius: 3px;
      transition: width .6s ease;
    }

    .fill-out  { background: #dc2626; width: 2%; }
    .fill-crit { background: linear-gradient(90deg, #dc2626, #ea580c); }
    .fill-low  { background: linear-gradient(90deg, #ea580c, #ca8a04); }

    /* ── Card footer ──────────────────────────────────────── */
    .lc-footer {
      padding: 0 16px 14px;
    }

    .btn-stock-in {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      width: 100%;
      padding: 9px 0;
      border-radius: var(--radius);
      border: 1px solid rgba(34,197,94,.3);
      background: var(--green-soft);
      color: #4ade80;
      font-size: .82rem;
      font-weight: 600;
      text-decoration: none;
      transition: all .18s;
      letter-spacing: .02em;
    }

    .btn-stock-in:hover {
      background: rgba(22,101,52,.45);
      border-color: rgba(34,197,94,.6);
      color: #86efac;
      transform: translateY(-1px);
    }

    /* ── All-clear state ──────────────────────────────────── */
    .all-clear {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 80px 24px;
      text-align: center;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }

    .all-clear-icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: var(--green-soft);
      border: 1px solid rgba(34,197,94,.3);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      color: #4ade80;
      margin-bottom: 18px;
    }

    .all-clear h2 {
      color: var(--text);
      font-size: 1.2rem;
      margin: 0 0 8px;
    }

    .all-clear p {
      color: var(--muted);
      font-size: .9rem;
      margin: 0;
    }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
      .main-content { padding: 20px 16px; }
      .summary-strip { grid-template-columns: 1fr; }
      .product-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
    }

    @media (max-width: 540px) {
      .product-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<?php include '../admin_navbar.php'; ?>
<div class="page-wrapper">
<?php include '../admin_menu.php'; ?>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <div class="title-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        Low Stock Alert
      </h1>
      <p class="page-subtitle">
        <?php if ($total > 0): ?>
          <?= $total ?> product<?= $total !== 1 ? 's' : '' ?> need<?= $total === 1 ? 's' : '' ?> restocking — stock threshold: fewer than 10 units
        <?php else: ?>
          All products are sufficiently stocked
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="ims_adjust_stock_movements.php" class="btn-ims-ghost">
        <i class="fa-solid fa-arrow-down"></i> Stock In
      </a>
    </div>
  </div>

  <?php if ($total > 0): ?>

  <!-- Summary Strip -->
  <div class="summary-strip">
    <div class="summary-card tier-out">
      <div class="sc-icon"><i class="fa-solid fa-ban"></i></div>
      <div>
        <div class="sc-count"><?= count($out_of_stock) ?></div>
        <div class="sc-label">Out of Stock</div>
      </div>
    </div>
    <div class="summary-card tier-crit">
      <div class="sc-icon"><i class="fa-solid fa-fire"></i></div>
      <div>
        <div class="sc-count"><?= count($critical) ?></div>
        <div class="sc-label">Critical (1–3 left)</div>
      </div>
    </div>
    <div class="summary-card tier-low">
      <div class="sc-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
      <div>
        <div class="sc-count"><?= count($low) ?></div>
        <div class="sc-label">Low (4–9 left)</div>
      </div>
    </div>
  </div>

  <?php
  // Helper to render a product card
  function render_card(array $p): void {
    $stock = (int)$p['stock'];

    if ($stock === 0) {
      $tier     = 'tier-out';
      $badgeCls = 'badge-out';
      $badgeTxt = 'Out of Stock';
      $fillCls  = 'fill-out';
      $fillW    = '2%';
    } elseif ($stock <= 3) {
      $tier     = 'tier-crit';
      $badgeCls = 'badge-crit';
      $badgeTxt = $stock . ' left';
      $fillCls  = 'fill-crit';
      $fillW    = round(($stock / 9) * 100) . '%';
    } else {
      $tier     = 'tier-low';
      $badgeCls = 'badge-low';
      $badgeTxt = $stock . ' left';
      $fillCls  = 'fill-low';
      $fillW    = round(($stock / 9) * 100) . '%';
    }

    $name    = htmlspecialchars($p['product_name']);
    $price   = 'Rs ' . number_format((float)$p['price']);
    $imgPath = $p['image'] ? '/jerseyflow-ecommerce/uploads/products/' . ltrim($p['image'], '/') : '';

$img_tag = $imgPath
  ? '<img src="' . htmlspecialchars($imgPath) . '" alt="' . $name . '" 
     onerror="this.outerHTML=`<div class=\'lc-img-placeholder\'><i class=\'fa-solid fa-shirt\'></i></div>`">'
  : '<div class="lc-img-placeholder"><i class="fa-solid fa-shirt"></i></div>';  

    $ribbon = $stock === 0 ? '<div class="lc-ribbon"><i class="fa-solid fa-ban"></i> &nbsp;Out of Stock</div>' : '';

    echo <<<HTML
    <div class="low-card {$tier}">
      <div class="lc-image-wrap">
        {$img_tag}
        <span class="lc-stock-badge {$badgeCls}">{$badgeTxt}</span>
        {$ribbon}
      </div>
      <div class="lc-body">
        <div class="lc-name">{$name}</div>
        <div class="lc-price">{$price}</div>
        <div class="lc-meter">
          <div class="lc-meter-label">
            <span>Stock level</span>
            <strong>{$stock} / 9</strong>
          </div>
          <div class="lc-bar-track">
            <div class="lc-bar-fill {$fillCls}" style="width:{$fillW}"></div>
          </div>
        </div>
      </div>
      <div class="lc-footer">
        <a href="ims_adjust_stock_movements.php?product_id={$p['product_id']}" class="btn-stock-in">
          <i class="fa-solid fa-arrow-down"></i> Stock In
        </a>
      </div>
    </div>
    HTML;
  }
  ?>

  <!-- Out of Stock section -->
  <?php if (!empty($out_of_stock)): ?>
  <div class="section-label sl-out">
    <i class="fa-solid fa-ban"></i> Out of Stock
    <div class="sl-bar"></div>
    <span><?= count($out_of_stock) ?> product<?= count($out_of_stock) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="product-grid">
    <?php foreach ($out_of_stock as $p) render_card($p); ?>
  </div>
  <?php endif; ?>

  <!-- Critical section -->
  <?php if (!empty($critical)): ?>
  <div class="section-label sl-crit">
    <i class="fa-solid fa-fire"></i> Critical — 1 to 3 units
    <div class="sl-bar"></div>
    <span><?= count($critical) ?> product<?= count($critical) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="product-grid">
    <?php foreach ($critical as $p) render_card($p); ?>
  </div>
  <?php endif; ?>

  <!-- Low section -->
  <?php if (!empty($low)): ?>
  <div class="section-label sl-low">
    <i class="fa-solid fa-arrow-trend-down"></i> Low — 4 to 9 units
    <div class="sl-bar"></div>
    <span><?= count($low) ?> product<?= count($low) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="product-grid">
    <?php foreach ($low as $p) render_card($p); ?>
  </div>
  <?php endif; ?>

  <?php else: ?>

  <!-- All clear -->
  <div class="all-clear">
    <div class="all-clear-icon"><i class="fa-solid fa-circle-check"></i></div>
    <h2>All Stocked Up!</h2>
    <p>No products are below the threshold of 10 units. Great job keeping inventory healthy.</p>
  </div>

  <?php endif; ?>

</div><!-- /.main-content -->
</div><!-- /.main-content -->

<?php include '../footer.php'; ?>
<script src="../../script/admin_menu.js"></script>
</body>
</html>