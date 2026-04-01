<?php
/**
 * JerseyFlow IMS — Stock Movements (Audit Trail)
 * File: admin/ims/ims_movements.php
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

require_once 'ims_helpers.php';

// ── AJAX endpoint ─────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    $search     = trim($_GET['search'] ?? '');
    $type       = $_GET['type']       ?? '';
    $variant_id = (int)($_GET['variant_id'] ?? 0);
    $date_from  = $_GET['date_from']  ?? '';
    $date_to    = $_GET['date_to']    ?? '';
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $per        = 20;
    $offset     = ($page - 1) * $per;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($search !== '') {
        $where[]  = '(p.product_name LIKE ? OR sm.reference_no LIKE ?)';
        $like = "%$search%";
        $params = array_merge($params, [$like, $like]);
        $types .= 'ss';
    }
    if ($type !== '') {
        $where[] = 'sm.movement_type = ?';
        $params[] = $type; $types .= 's';
    }
    if ($variant_id > 0) {
        $where[] = 'sm.variant_id = ?';
        $params[] = $variant_id; $types .= 'i';
    }
    if ($date_from !== '') {
        $where[] = 'DATE(sm.created_at) >= ?';
        $params[] = $date_from; $types .= 's';
    }
    if ($date_to !== '') {
        $where[] = 'DATE(sm.created_at) <= ?';
        $params[] = $date_to; $types .= 's';
    }

    $wsql = 'WHERE ' . implode(' AND ', $where);

    $cstmt = $conn->prepare("SELECT COUNT(*) FROM stock_movements sm JOIN products p ON p.product_id=sm.product_id $wsql");
    if ($types) $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $total = $cstmt->get_result()->fetch_row()[0];
    $cstmt->close();

    $dstmt = $conn->prepare("
        SELECT sm.*, p.product_name, pv.size, pv.color, u.full_name AS admin_name
        FROM stock_movements sm
        JOIN products         p  ON p.product_id  = sm.product_id
        JOIN product_variants pv ON pv.variant_id = sm.variant_id
        LEFT JOIN users       u  ON u.user_id     = sm.admin_id
        $wsql ORDER BY sm.created_at DESC LIMIT ? OFFSET ?
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

// Pre-populate variant filter from query string
$filter_variant = (int)($_GET['variant_id'] ?? 0);
$variant_label  = '';
if ($filter_variant) {
    $r = $conn->prepare("SELECT p.product_name, pv.size, pv.color FROM product_variants pv JOIN products p ON p.product_id=pv.product_id WHERE pv.variant_id=?");
    $r->bind_param('i', $filter_variant);
    $r->execute();
    $vd = $r->get_result()->fetch_assoc();
    $r->close();
    if ($vd) $variant_label = "{$vd['product_name']} — {$vd['size']} · {$vd['color']}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Movements — JerseyFlow IMS</title>
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
      <h1 class="page-title"><i class="fa-solid fa-clock-rotate-left"></i> Stock Movements</h1>
      <p class="page-subtitle">Complete audit trail of every stock change</p>
    </div>
  </div>

  <!-- Filters -->
  <div class="ims-filter-bar ims-filter-bar-extended">
    <div class="ims-search-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search product, reference…" />
    </div>
    <select id="filterType" class="ims-select">
      <option value="">All Types</option>
      <option value="IN">Stock In</option>
      <option value="OUT">Stock Out</option>
      <option value="ADJUST">Adjustment</option>
      <option value="RETURN">Return</option>
      <option value="DAMAGE">Damage</option>
      <option value="TRANSFER">Transfer</option>
    </select>
    <input type="date" id="dateFrom" class="ims-input-date" title="From date" />
    <input type="date" id="dateTo"   class="ims-input-date" title="To date" />
    <input type="hidden" id="variantId" value="<?= $filter_variant ?>" />
    <?php if ($variant_label): ?>
      <span class="variant-filter-tag">
        <?= htmlspecialchars($variant_label) ?>
        <button onclick="document.getElementById('variantId').value=''; fetchMovements();">×</button>
      </span>
    <?php endif; ?>
    <span class="result-count" id="resultCount">Loading…</span>
  </div>

  <div class="ims-card" style="margin-top:0;">
    <div class="ims-table-wrap">
      <table class="ims-table" id="movTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Product</th>
            <th>Variant</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Before → After</th>
            <th>Reference</th>
            <th>Admin</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody id="movBody">
          <tr><td colspan="9" class="ims-td-loading"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
        </tbody>
      </table>
    </div>
    <div class="ims-pagination" id="pagination"></div>
  </div>
  </div>
  </div>

</main>
<?php include '../footer.php'; ?>

<script src="../../script/admin_menu.js"></script>

<script>
(function () {
  let currentPage = 1;
  let debounceTimer;

  const body       = document.getElementById('movBody');
  const pagination = document.getElementById('pagination');
  const countEl    = document.getElementById('resultCount');

  function getFilters() {
    return {
      ajax:       1,
      search:     document.getElementById('searchInput').value.trim(),
      type:       document.getElementById('filterType').value,
      variant_id: document.getElementById('variantId').value,
      date_from:  document.getElementById('dateFrom').value,
      date_to:    document.getElementById('dateTo').value,
      page:       currentPage,
    };
  }

  function buildQuery(obj) {
    return '?' + Object.entries(obj).filter(([,v])=>v!=='').map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
  }

  const TYPE_CFG = {
    IN:       { cls:'ims-badge-in',       icon:'fa-arrow-down',   label:'Stock In'  },
    OUT:      { cls:'ims-badge-out-move', icon:'fa-arrow-up',     label:'Stock Out' },
    ADJUST:   { cls:'ims-badge-adjust',   icon:'fa-sliders',      label:'Adjust'    },
    RETURN:   { cls:'ims-badge-return',   icon:'fa-rotate-left',  label:'Return'    },
    DAMAGE:   { cls:'ims-badge-damage',   icon:'fa-triangle-exclamation', label:'Damage' },
    TRANSFER: { cls:'ims-badge-transfer', icon:'fa-right-left',   label:'Transfer'  },
  };

  function typeBadge(t) {
    const c = TYPE_CFG[t] || { cls:'ims-badge-adjust', icon:'fa-circle', label:t };
    return `<span class="ims-badge ${c.cls}"><i class="fa-solid ${c.icon}"></i> ${c.label}</span>`;
  }

  function renderRows(rows) {
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="9" class="ims-td-empty">No movements found.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(r => {
      const sign  = (r.movement_type==='OUT'||r.movement_type==='DAMAGE') ? '-' : '+';
      const qcls  = (r.movement_type==='OUT'||r.movement_type==='DAMAGE') ? 'qty-neg' : 'qty-pos';
      return `
        <tr>
          <td class="td-muted">#${r.movement_id}</td>
          <td class="td-name">${escHtml(r.product_name)}</td>
          <td><span class="variant-pill">${r.size} · ${escHtml(r.color)}</span></td>
          <td>${typeBadge(r.movement_type)}</td>
          <td class="${qcls}">${sign}${r.quantity}</td>
          <td class="td-arrow">${r.stock_before} <i class="fa-solid fa-arrow-right"></i> ${r.stock_after}</td>
          <td class="td-muted">${escHtml(r.reference_no||'—')}</td>
          <td>${escHtml(r.admin_name||'System')}</td>
          <td class="td-date">${new Date(r.created_at).toLocaleString('en-US',{month:'short',day:'2-digit',hour:'2-digit',minute:'2-digit'})}</td>
        </tr>`;
    }).join('');
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
      btn.addEventListener('click', () => { currentPage=parseInt(btn.dataset.page); fetchMovements(); });
    });
  }

  function fetchMovements() {
    body.innerHTML = '<tr><td colspan="9" class="ims-td-loading"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>';
    fetch('ims_movements.php' + buildQuery(getFilters()))
      .then(r => r.json())
      .then(data => {
        renderRows(data.rows);
        renderPagination(data.total, data.per_page);
        countEl.textContent = data.total + ' records';
      })
      .catch(() => { body.innerHTML='<tr><td colspan="9" class="ims-td-empty">Failed to load.</td></tr>'; });
  }

  window.fetchMovements = fetchMovements;

  document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { currentPage=1; fetchMovements(); }, 350);
  });
  ['filterType','dateFrom','dateTo'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => { currentPage=1; fetchMovements(); });
  });

  function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  fetchMovements();
})();
</script>
</body>
</html>