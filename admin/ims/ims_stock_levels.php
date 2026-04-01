<?php
/**
 * JerseyFlow IMS — Stock Levels
 * File: admin/ims/ims_stock_levels.php
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

require_once 'ims_helpers.php';

// ── AJAX endpoint ─────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    $search  = trim($_GET['search'] ?? '');
    $status  = $_GET['status'] ?? '';
    $size    = $_GET['size']   ?? '';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $per     = 15;
    $offset  = ($page - 1) * $per;

    $where  = ['pv.is_active = 1'];
    $params = [];
    $types  = '';

    if ($search !== '') {
        $where[]  = '(p.product_name LIKE ? OR pv.sku LIKE ? OR pv.color LIKE ?)';
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like]);
        $types .= 'sss';
    }
    if ($size !== '')  { $where[] = 'pv.size = ?'; $params[] = $size; $types .= 's'; }

    if ($status === 'out')     { $where[] = 'pv.stock = 0'; }
    elseif ($status === 'low') { $where[] = 'pv.stock > 0 AND pv.stock <= pv.reorder_level'; }
    elseif ($status === 'ok')  { $where[] = 'pv.stock > pv.reorder_level'; }

    $wsql = 'WHERE ' . implode(' AND ', $where);

    // Count
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM product_variants pv JOIN products p ON p.product_id=pv.product_id $wsql");
    if ($types) $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $total = $cstmt->get_result()->fetch_row()[0];
    $cstmt->close();

    // Data
    $dstmt = $conn->prepare("
        SELECT pv.variant_id, pv.sku, pv.size, pv.color, pv.stock, pv.reorder_level, pv.reorder_qty, pv.cost_price,
               p.product_id, p.product_name, p.image
        FROM product_variants pv
        JOIN products p ON p.product_id = pv.product_id
        $wsql ORDER BY pv.stock ASC, p.product_name ASC LIMIT ? OFFSET ?
    ");
    $fp = array_merge($params, [$per, $offset]);
    $ft = $types . 'ii';
    $dstmt->bind_param($ft, ...$fp);
    $dstmt->execute();
    $rows = $dstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dstmt->close();

    echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => $per]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Levels — JerseyFlow IMS</title>
      <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../style/footer.css">
    <link rel="stylesheet" href="../../style/admin_menu.css">
    <link rel="stylesheet" href="../../style/all_products.css">
    <link rel="stylesheet" href="../../style/ims.css">
    <link rel="stylesheet" href="../../style/admin_navbar.css">

</head>
<body>


<?php include '../admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include '../admin_menu.php'; ?>

    <div class="main-content">

    <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-layer-group"></i> Stock Levels</h1>
      <p class="page-subtitle">Live stock across all product variants</p>
    </div>
    <div class="header-actions">
      <a href="ims_adjust.php" class="btn-ims-primary"><i class="fa-solid fa-sliders"></i> Adjust Stock</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="ims-filter-bar">
    <div class="ims-search-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search product, SKU, color…" />
      <button id="searchClear" class="search-clear-btn" style="display:none"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select id="filterStatus" class="ims-select">
      <option value="">All Status</option>
      <option value="ok">In Stock</option>
      <option value="low">Low Stock</option>
      <option value="out">Out of Stock</option>
    </select>
    <select id="filterSize" class="ims-select">
      <option value="">All Sizes</option>
      <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $s): ?>
        <option value="<?= $s ?>"><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <span class="result-count" id="resultCount">Loading…</span>
  </div>

  <!-- Table Card -->
  <div class="ims-card" style="margin-top:0;">
    <div class="ims-table-wrap">
      <table class="ims-table" id="stockTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Size</th>
            <th>Color</th>
            <th>Stock</th>
            <th>Reorder Level</th>
            <th>Status</th>
            <th>Cost</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="stockBody">
          <tr><td colspan="9" class="ims-td-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>
        </tbody>
      </table>
    </div>
    <div class="ims-pagination" id="pagination"></div>
  </div>
  </div>
  </div>
  </div>

</main>
<?php include '../footer.php'; ?>


<script src="../../script/admin_navbar.js"></script>
<script src="../../script/admin_menu.js"></script>

<script>
(function () {
  let currentPage = 1;
  let debounceTimer;

  const body        = document.getElementById('stockBody');
  const pagination  = document.getElementById('pagination');
  const countEl     = document.getElementById('resultCount');
  const searchInput = document.getElementById('searchInput');
  const clearBtn    = document.getElementById('searchClear');
  const statusSel   = document.getElementById('filterStatus');
  const sizeSel     = document.getElementById('filterSize');

  function getFilters() {
    return {
      search: searchInput.value.trim(),
      status: statusSel.value,
      size:   sizeSel.value,
      page:   currentPage,
      ajax:   1,
    };
  }

  function buildQuery(obj) {
    return '?' + Object.entries(obj).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&');
  }

  function statusBadge(stock, reorder) {
    if (stock == 0)                 return '<span class="ims-badge ims-badge-out">Out of Stock</span>';
    if (stock <= reorder)           return '<span class="ims-badge ims-badge-low">Low Stock</span>';
    return '<span class="ims-badge ims-badge-ok">In Stock</span>';
  }

  function renderRows(rows) {
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="9" class="ims-td-empty">No variants match your filters.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(r => `
      <tr>
        <td class="td-name">${escHtml(r.product_name)}</td>
        <td><code class="sku-code">${escHtml(r.sku)}</code></td>
        <td><span class="size-pill">${r.size}</span></td>
        <td>${escHtml(r.color)}</td>
        <td class="td-stock ${r.stock == 0 ? 'stock-zero' : r.stock <= r.reorder_level ? 'stock-low' : ''}">${r.stock}</td>
        <td class="td-muted">${r.reorder_level}</td>
        <td>${statusBadge(r.stock, r.reorder_level)}</td>
        <td class="td-muted">${r.cost_price ? 'Rs.' + parseFloat(r.cost_price).toFixed(2) : '—'}</td>
        <td>
          <div class="ims-row-actions">
            <a href="ims_adjust.php?variant_id=${r.variant_id}" class="btn-ims-xs btn-ims-xs-primary" title="Adjust">
              <i class="fa-solid fa-sliders"></i>
            </a>
            <a href="ims_movements.php?variant_id=${r.variant_id}" class="btn-ims-xs btn-ims-xs-ghost" title="History">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </a>
          </div>
        </td>
      </tr>
    `).join('');
  }

  function renderPagination(total, perPage) {
    const totalPages = Math.ceil(total / perPage);
    if (totalPages <= 1) { pagination.innerHTML = ''; return; }

    let html = '';
    if (currentPage > 1) html += `<button class="page-btn" data-page="${currentPage-1}"><i class="fa-solid fa-angle-left"></i></button>`;

    for (let p = Math.max(1, currentPage-2); p <= Math.min(totalPages, currentPage+2); p++) {
      html += `<button class="page-btn ${p===currentPage?'active':''}" data-page="${p}">${p}</button>`;
    }
    if (currentPage < totalPages) html += `<button class="page-btn" data-page="${currentPage+1}"><i class="fa-solid fa-angle-right"></i></button>`;

    pagination.innerHTML = html;
    pagination.querySelectorAll('.page-btn').forEach(btn => {
      btn.addEventListener('click', () => { currentPage = parseInt(btn.dataset.page); fetchData(); });
    });
  }

  function fetchData() {
    body.innerHTML = '<tr><td colspan="9" class="ims-td-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>';
    fetch('ims_stock_levels.php' + buildQuery(getFilters()))
      .then(r => r.json())
      .then(data => {
        renderRows(data.rows);
        renderPagination(data.total, data.per_page);
        countEl.textContent = data.total + ' variants';
      })
      .catch(() => { body.innerHTML = '<tr><td colspan="9" class="ims-td-empty">Failed to load. Try again.</td></tr>'; });
  }

  searchInput.addEventListener('input', () => {
    clearBtn.style.display = searchInput.value ? 'block' : 'none';
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { currentPage = 1; fetchData(); }, 350);
  });

  clearBtn.addEventListener('click', () => {
    searchInput.value = ''; clearBtn.style.display = 'none'; currentPage = 1; fetchData();
  });

  statusSel.addEventListener('change', () => { currentPage = 1; fetchData(); });
  sizeSel.addEventListener('change',   () => { currentPage = 1; fetchData(); });

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  fetchData();
})();
</script>
</body>
</html>