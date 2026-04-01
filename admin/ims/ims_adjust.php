<?php
/**
 * JerseyFlow IMS — Adjust Stock
 * File: admin/ims/ims_adjust.php
 *
 * Handles: Stock IN, Stock OUT, and manual Adjustments.
 * Accepts both form POST (standard) and AJAX JSON.
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';
require_once 'ims_helpers.php';



// ── AJAX: search variants ─────────────────────────────────────
if (!empty($_GET['search_variants'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("
        SELECT pv.variant_id, pv.sku, pv.size, pv.color, pv.stock, pv.reorder_level,
               p.product_id, p.product_name
        FROM product_variants pv
        JOIN products p ON p.product_id = pv.product_id
        WHERE (p.product_name LIKE ? OR pv.sku LIKE ? OR pv.color LIKE ?) AND pv.is_active=1
        ORDER BY p.product_name, pv.size LIMIT 20
    ");
    $stmt->bind_param('sss', $q, $q, $q);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// ── AJAX: get variant detail ──────────────────────────────────
if (!empty($_GET['get_variant'])) {
    header('Content-Type: application/json');
    $vid = (int)($_GET['variant_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT pv.*, p.product_name FROM product_variants pv
        JOIN products p ON p.product_id = pv.product_id
        WHERE pv.variant_id = ?
    ");
    $stmt->bind_param('i', $vid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode($row ?: null);
    exit;
}

// ── AJAX: perform stock move ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $type       = strtoupper(trim($_POST['type'] ?? ''));
    $qty        = (int)($_POST['qty'] ?? 0);
    $reference  = trim($_POST['reference'] ?? '');
    $reason     = trim($_POST['reason'] ?? '');
    $note       = trim($_POST['note'] ?? '');

    if (!$variant_id) { echo json_encode(['ok'=>false,'msg'=>'Select a variant first.']); exit; }
    if (!in_array($type, ['IN','OUT','ADJUST','RETURN','DAMAGE'])) { echo json_encode(['ok'=>false,'msg'=>'Invalid type.']); exit; }
    if ($qty <= 0) { echo json_encode(['ok'=>false,'msg'=>'Quantity must be greater than 0.']); exit; }
    if ($type === 'ADJUST' && $reason === '') { echo json_encode(['ok'=>false,'msg'=>'Reason is required for adjustments.']); exit; }

    $result = ims_stock_move($conn, $variant_id, $admin_id, $type, $qty, $reference, $reason, $note);
    echo json_encode($result);
    exit;
}

// Pre-fill from URL
$prefill_variant = (int)($_GET['variant_id'] ?? 0);
$prefill_data = null;
if ($prefill_variant) {
    $s = $conn->prepare("SELECT pv.*, p.product_name FROM product_variants pv JOIN products p ON p.product_id=pv.product_id WHERE pv.variant_id=?");
    $s->bind_param('i', $prefill_variant);
    $s->execute();
    $prefill_data = $s->get_result()->fetch_assoc();
    $s->close();
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Adjust Stock — JerseyFlow IMS</title>
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
      <h1 class="page-title"><i class="fa-solid fa-sliders"></i> Adjust Stock</h1>
      <p class="page-subtitle">Record stock in, out, or manual adjustments</p>
    </div>
    <a href="ims_movements.php" class="btn-ims-ghost"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</a>
  </div>

  <?php if ($toast): ?>
    <div class="ims-toast ims-toast-<?= $toast['type'] ?>" id="pageToast">
      <i class="fa-solid <?= $toast['type']==='success'?'fa-circle-check':'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($toast['msg']) ?>
      <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
  <?php endif; ?>

  <div id="ajaxToast" class="ims-toast" style="display:none;"></div>

  <div class="adjust-layout">

    <!-- Left: Adjust Form -->
    <div class="ims-card adjust-form-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-pen-to-square"></i> Stock Movement Entry</span>
      </div>

      <form id="adjustForm" autocomplete="off">

        <!-- Type Selector -->
        <div class="move-type-tabs">
          <?php
          $types = [
            'IN'     => ['fa-arrow-down',           'Stock In',    'btn-type-in'],
            'OUT'    => ['fa-arrow-up',              'Stock Out',   'btn-type-out'],
            'ADJUST' => ['fa-sliders',               'Adjust',      'btn-type-adjust'],
            'RETURN' => ['fa-rotate-left',           'Return',      'btn-type-return'],
            'DAMAGE' => ['fa-triangle-exclamation',  'Damage',      'btn-type-damage'],
          ];
          foreach ($types as $val => [$icon, $label, $cls]):
          ?>
            <button type="button" class="move-type-btn <?= $cls ?>"
              data-type="<?= $val ?>" onclick="setType('<?= $val ?>')">
              <i class="fa-solid <?= $icon ?>"></i>
              <span><?= $label ?></span>
            </button>
          <?php endforeach; ?>
          <input type="hidden" id="typeInput" name="type" value="IN" />
        </div>

        <!-- Variant Search -->
        <div class="form-group" style="margin-top:24px;">
          <label>Product Variant <span class="required">*</span></label>
          <div class="variant-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="variantSearch" placeholder="Search by product name, SKU, color…" autocomplete="off" />
            <div id="variantDropdown" class="variant-dropdown"></div>
          </div>
          <input type="hidden" id="variantId" name="variant_id" value="<?= $prefill_variant ?>" />
        </div>

        <!-- Selected Variant Card -->
        <div id="variantCard" class="variant-selected-card <?= $prefill_data ? '' : 'hidden' ?>">
          <?php if ($prefill_data): ?>
            <div class="vc-header">
              <div class="vc-info">
                <span class="vc-name"><?= htmlspecialchars($prefill_data['product_name']) ?></span>
                <span class="vc-meta"><?= $prefill_data['size'] ?> · <?= htmlspecialchars($prefill_data['color']) ?></span>
                <code class="vc-sku"><?= htmlspecialchars($prefill_data['sku']) ?></code>
              </div>
              <div class="vc-stock">
                <span class="vc-stock-val" id="currentStockDisplay"><?= $prefill_data['stock'] ?></span>
                <span class="vc-stock-lbl">current stock</span>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Quantity -->
        <div class="form-row-2" style="margin-top:16px;">
          <div class="form-group">
            <label id="qtyLabel">Quantity <span class="required">*</span></label>
            <input type="number" id="qtyInput" name="qty" min="1" value="1" class="ims-input" placeholder="0" />
            <p class="field-hint" id="adjustHint" style="display:none">For ADJUST — enter the new absolute stock value.</p>
          </div>
          <div class="form-group">
            <label>Reference No. <span class="field-hint">(PO / Order ID)</span></label>
            <input type="text" name="reference" class="ims-input" placeholder="e.g. PO-2025-001" />
          </div>
        </div>

        <div class="form-group" id="reasonGroup">
          <label>Reason <span class="required" id="reasonRequired" style="display:none">*</span>
            <span class="field-hint" id="reasonOptional">(optional)</span>
          </label>
          <select name="reason" id="reasonSelect" class="ims-select-full">
            <option value="">— Select a reason —</option>
            <optgroup label="Stock In">
              <option value="Supplier restock">Supplier restock</option>
              <option value="Initial stock entry">Initial stock entry</option>
              <option value="Return from customer">Return from customer</option>
            </optgroup>
            <optgroup label="Stock Out">
              <option value="Order fulfilled">Order fulfilled</option>
              <option value="Damaged / defective">Damaged / defective</option>
              <option value="Sample / promotional">Sample / promotional</option>
            </optgroup>
            <optgroup label="Adjustment">
              <option value="Physical count correction">Physical count correction</option>
              <option value="System correction">System correction</option>
              <option value="Shrinkage / loss">Shrinkage / loss</option>
            </optgroup>
          </select>
        </div>

        <div class="form-group">
          <label>Additional Note</label>
          <textarea name="note" class="ims-textarea" rows="3" placeholder="Any extra details…"></textarea>
        </div>

        <!-- Preview -->
        <div id="previewBox" class="stock-preview hidden">
          <span class="preview-label">Stock will change:</span>
          <span id="previewBefore" class="preview-before">—</span>
          <i class="fa-solid fa-arrow-right"></i>
          <span id="previewAfter"  class="preview-after">—</span>
        </div>

        <div class="form-actions" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
          <button type="button" onclick="resetForm()" class="btn-ims-ghost">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </button>
          <button type="submit" class="btn-ims-primary" id="submitBtn">
            <i class="fa-solid fa-floppy-disk"></i> Save Movement
          </button>
        </div>

      </form>
    </div>

    <!-- Right: Recent Adjustments -->
    <div class="ims-card adjust-recent-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Entries</span>
      </div>
      <div id="recentList" class="recent-movements-list">
        <div class="ims-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></div>
      </div>
    </div>

  </div>
  </div>
  </div>
  </div>
</main>

<?php include '../footer.php'; ?>

<script>
(function () {
  let selectedVariant = null;
  let currentType = 'IN';

  // ── Type selection ─────────────────────────────────────────
  window.setType = function(type) {
    currentType = type;
    document.getElementById('typeInput').value = type;
    document.querySelectorAll('.move-type-btn').forEach(b => b.classList.toggle('active', b.dataset.type === type));

    const isAdjust = type === 'ADJUST';
    document.getElementById('qtyLabel').textContent = isAdjust ? 'New Stock Value *' : 'Quantity *';
    document.getElementById('adjustHint').style.display = isAdjust ? 'block' : 'none';
    document.getElementById('reasonRequired').style.display = isAdjust ? 'inline' : 'none';
    document.getElementById('reasonOptional').style.display = isAdjust ? 'none'   : 'inline';

    updatePreview();
  };
  setType('IN');

  // ── Variant search ─────────────────────────────────────────
  const searchInput  = document.getElementById('variantSearch');
  const dropdown     = document.getElementById('variantDropdown');
  const variantIdHid = document.getElementById('variantId');
  let searchTimer;

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) { dropdown.innerHTML=''; dropdown.classList.remove('open'); return; }
    searchTimer = setTimeout(() => {
      fetch(`ims_adjust.php?search_variants=1&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(rows => {
          if (!rows.length) { dropdown.innerHTML='<div class="vd-empty">No variants found.</div>'; dropdown.classList.add('open'); return; }
          dropdown.innerHTML = rows.map(r => `
            <div class="vd-item" data-id="${r.variant_id}">
              <div class="vd-main">
                <span class="vd-name">${escHtml(r.product_name)}</span>
                <span class="vd-meta">${r.size} · ${escHtml(r.color)}</span>
              </div>
              <div class="vd-right">
                <span class="vd-stock ${r.stock===0?'vds-out':r.stock<=r.reorder_level?'vds-low':'vds-ok'}">${r.stock}</span>
              </div>
            </div>`).join('');
          dropdown.classList.add('open');
          dropdown.querySelectorAll('.vd-item').forEach(item => {
            item.addEventListener('click', () => {
              const vid = parseInt(item.dataset.id);
              selectVariant(vid);
              dropdown.classList.remove('open');
            });
          });
        });
    }, 300);
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('.variant-search-wrap')) dropdown.classList.remove('open');
  });

  function selectVariant(vid) {
    fetch(`ims_adjust.php?get_variant=1&variant_id=${vid}`)
      .then(r => r.json())
      .then(data => {
        if (!data) return;
        selectedVariant = data;
        variantIdHid.value = data.variant_id;
        searchInput.value  = `${data.product_name} — ${data.size} · ${data.color}`;

        const card = document.getElementById('variantCard');
        card.innerHTML = `
          <div class="vc-header">
            <div class="vc-info">
              <span class="vc-name">${escHtml(data.product_name)}</span>
              <span class="vc-meta">${data.size} · ${escHtml(data.color)}</span>
              <code class="vc-sku">${escHtml(data.sku)}</code>
            </div>
            <div class="vc-stock">
              <span class="vc-stock-val" id="currentStockDisplay">${data.stock}</span>
              <span class="vc-stock-lbl">current stock</span>
            </div>
          </div>`;
        card.classList.remove('hidden');
        updatePreview();
      });
  }

  // ── Preview ────────────────────────────────────────────────
  document.getElementById('qtyInput').addEventListener('input', updatePreview);

  function updatePreview() {
    if (!selectedVariant) return;
    const qty   = parseInt(document.getElementById('qtyInput').value) || 0;
    const curr  = parseInt(selectedVariant.stock);
    let after;
    if (currentType === 'ADJUST')                        after = qty;
    else if (currentType==='OUT'||currentType==='DAMAGE') after = curr - qty;
    else                                                  after = curr + qty;

    const box = document.getElementById('previewBox');
    box.classList.remove('hidden');
    document.getElementById('previewBefore').textContent = curr;
    const afterEl = document.getElementById('previewAfter');
    afterEl.textContent = after;
    afterEl.className   = 'preview-after ' + (after < 0 ? 'preview-neg' : after < curr ? 'preview-down' : 'preview-up');
  }

  // ── Submit ─────────────────────────────────────────────────
  document.getElementById('adjustForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

    const fd = new FormData(this);
    fetch('ims_adjust.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      showToast(data.ok ? 'success' : 'error', data.msg);
      if (data.ok) {
        if (selectedVariant) selectedVariant.stock = data.new_stock;
        const disp = document.getElementById('currentStockDisplay');
        if (disp) disp.textContent = data.new_stock;
        loadRecent();
        updatePreview();
      }
    })
    .catch(() => showToast('error','Network error. Please try again.'))
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Movement';
    });
  });

  // ── Toast ──────────────────────────────────────────────────
  function showToast(type, msg) {
    const t = document.getElementById('ajaxToast');
    t.className = `ims-toast ims-toast-${type}`;
    t.innerHTML = `<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i> ${escHtml(msg)}
                   <button onclick="this.parentElement.style.display='none'"><i class="fa-solid fa-xmark"></i></button>`;
    t.style.display = 'flex';
    setTimeout(() => { t.style.display='none'; }, 5000);
  }

  // ── Recent entries ─────────────────────────────────────────
  function loadRecent() {
    fetch('ims_movements.php?ajax=1&page=1')
      .then(r => r.json())
      .then(data => {
        const list = document.getElementById('recentList');
        if (!data.rows.length) { list.innerHTML='<div class="ims-empty"><p>No movements yet.</p></div>'; return; }
        const TYPE_ICON = {IN:'fa-arrow-down',OUT:'fa-arrow-up',ADJUST:'fa-sliders',RETURN:'fa-rotate-left',DAMAGE:'fa-triangle-exclamation'};
        const TYPE_CLS  = {IN:'rm-in',OUT:'rm-out',ADJUST:'rm-adjust',RETURN:'rm-return',DAMAGE:'rm-damage'};
        list.innerHTML = data.rows.slice(0,12).map(r => `
          <div class="rm-item">
            <div class="rm-icon ${TYPE_CLS[r.movement_type]||'rm-adjust'}">
              <i class="fa-solid ${TYPE_ICON[r.movement_type]||'fa-circle'}"></i>
            </div>
            <div class="rm-body">
              <span class="rm-name">${escHtml(r.product_name)}</span>
              <span class="rm-meta">${r.size} · ${escHtml(r.color)}</span>
            </div>
            <div class="rm-right">
              <span class="rm-qty ${r.movement_type==='OUT'||r.movement_type==='DAMAGE'?'qty-neg':'qty-pos'}">
                ${r.movement_type==='OUT'||r.movement_type==='DAMAGE'?'-':'+'}${r.quantity}
              </span>
              <span class="rm-time">${new Date(r.created_at).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
          </div>`).join('');
      });
  }

  window.resetForm = function() {
    document.getElementById('adjustForm').reset();
    selectedVariant = null;
    variantIdHid.value = '';
    searchInput.value  = '';
    document.getElementById('variantCard').classList.add('hidden');
    document.getElementById('previewBox').classList.add('hidden');
    setType('IN');
  };

  function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  // Pre-select from URL
  const preId = <?= $prefill_variant ?: 0 ?>;
  if (preId) { selectedVariant = <?= $prefill_data ? json_encode($prefill_data) : 'null' ?>; updatePreview(); }

  loadRecent();
})();
</script>

<script src="../../script/admin_menu.js"></script>

</body>
</html>