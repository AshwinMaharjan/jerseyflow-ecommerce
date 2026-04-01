<?php
/**
 * JerseyFlow IMS — Reorder Management
 * File: admin/ims/ims_reorder.php
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

require_once 'ims_helpers.php';

// ── AJAX: create reorder request ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $variant_id   = (int)($_POST['variant_id'] ?? 0);
        $qty          = (int)($_POST['qty'] ?? 0);
        $supplier_note= trim($_POST['supplier_note'] ?? '');

        if (!$variant_id || $qty <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid variant or quantity.']); exit;
        }

        // Get product_id
        $r = $conn->prepare("SELECT product_id FROM product_variants WHERE variant_id=?");
        $r->bind_param('i', $variant_id);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();

        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Variant not found.']); exit; }

        $stmt = $conn->prepare("INSERT INTO reorder_requests (variant_id, product_id, admin_id, qty_requested, supplier_note) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iiiss', $variant_id, $row['product_id'], $admin_id, $qty, $supplier_note);
        $stmt->execute();
        $stmt->close();

        // Create notification
        $conn->query("INSERT INTO inventory_notifications (variant_id, product_id, type, message) VALUES ($variant_id, {$row['product_id']}, 'REORDER', 'Reorder request created for variant #$variant_id ($qty units).')");

        echo json_encode(['ok'=>true,'msg'=>'Reorder request created.']);
        exit;
    }

    if ($action === 'update_status') {
        $reorder_id = (int)($_POST['reorder_id'] ?? 0);
        $status     = $_POST['status'] ?? '';
        $allowed    = ['PENDING','ORDERED','RECEIVED','CANCELLED'];
        if (!$reorder_id || !in_array($status, $allowed)) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid.']); exit;
        }

        // If RECEIVED, trigger stock IN
        if ($status === 'RECEIVED') {
            $rq = $conn->prepare("SELECT variant_id, qty_requested FROM reorder_requests WHERE reorder_id=?");
            $rq->bind_param('i', $reorder_id);
            $rq->execute();
            $rqrow = $rq->get_result()->fetch_assoc();
            $rq->close();

            if ($rqrow) {
                $result = ims_stock_move($conn, $rqrow['variant_id'], $admin_id, 'IN', $rqrow['qty_requested'], "RO-$reorder_id", '', 'Reorder received');
                if (!$result['ok']) { echo json_encode($result); exit; }
            }
        }

        $stmt = $conn->prepare("UPDATE reorder_requests SET status=?, updated_at=NOW() WHERE reorder_id=?");
        $stmt->bind_param('si', $status, $reorder_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok'=>true,'msg'=>"Status updated to $status."]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
    exit;
}

// Pre-select variant
$prefill_vid = (int)($_GET['variant_id'] ?? 0);
$prefill_v   = null;
if ($prefill_vid) {
    $s = $conn->prepare("SELECT pv.*, p.product_name FROM product_variants pv JOIN products p ON p.product_id=pv.product_id WHERE pv.variant_id=?");
    $s->bind_param('i', $prefill_vid);
    $s->execute();
    $prefill_v = $s->get_result()->fetch_assoc();
    $s->close();
}

// Reorder requests
$reorders = $conn->query("
    SELECT rr.*, p.product_name, pv.size, pv.color, pv.stock, pv.reorder_level, u.full_name AS admin_name
    FROM reorder_requests rr
    JOIN product_variants pv ON pv.variant_id = rr.variant_id
    JOIN products         p  ON p.product_id  = rr.product_id
    LEFT JOIN users       u  ON u.user_id     = rr.admin_id
    ORDER BY rr.created_at DESC LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Suggest reorder candidates
$suggestions = $conn->query("
    SELECT pv.variant_id, pv.size, pv.color, pv.stock, pv.reorder_level, pv.reorder_qty, p.product_name
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE pv.stock <= pv.reorder_level AND pv.is_active=1
      AND pv.variant_id NOT IN (SELECT variant_id FROM reorder_requests WHERE status IN ('PENDING','ORDERED'))
    ORDER BY pv.stock ASC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reorder Management — JerseyFlow IMS</title>
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
      <h1 class="page-title"><i class="fa-solid fa-cart-plus"></i> Reorder Management</h1>
      <p class="page-subtitle">Track and manage restocking requests</p>
    </div>
  </div>

  <div id="ajaxToast" class="ims-toast" style="display:none;"></div>

  <div class="reorder-layout">

    <!-- Create Request Form -->
    <div class="ims-card reorder-form-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-plus-circle"></i> New Reorder Request</span>
      </div>

      <div class="form-group">
        <label>Variant</label>
        <input type="text" id="reorderSearch" class="ims-input" placeholder="Search product / variant…" />
        <input type="hidden" id="reorderVid" />
        <div id="reorderDropdown" class="variant-dropdown"></div>
      </div>

      <!-- Suggestions -->
      <?php if ($suggestions): ?>
        <p class="suggest-label"><i class="fa-solid fa-lightbulb"></i> Suggested (low / out of stock)</p>
        <div class="suggest-chips">
          <?php foreach ($suggestions as $sg): ?>
            <button class="suggest-chip" data-vid="<?= $sg['variant_id'] ?>"
              data-name="<?= htmlspecialchars($sg['product_name']) ?>"
              data-size="<?= $sg['size'] ?>" data-color="<?= htmlspecialchars($sg['color']) ?>"
              data-qty="<?= $sg['reorder_qty'] ?>" data-stock="<?= $sg['stock'] ?>">
              <?= htmlspecialchars($sg['product_name']) ?> · <?= $sg['size'] ?>
              <span class="chip-stock <?= $sg['stock']==0?'chip-out':'chip-low' ?>"><?= $sg['stock'] ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div id="reorderSelectedCard" class="variant-selected-card hidden"></div>

      <div class="form-row-2" style="margin-top:16px;">
        <div class="form-group">
          <label>Quantity to Order <span class="required">*</span></label>
          <input type="number" id="reorderQty" class="ims-input" min="1" value="" placeholder="0" />
        </div>
      </div>

      <div class="form-group">
        <label>Supplier Note</label>
        <textarea id="reorderNote" class="ims-textarea" rows="2" placeholder="Add note for supplier…"></textarea>
      </div>

      <button class="btn-ims-primary" style="width:100%;" onclick="submitReorder()">
        <i class="fa-solid fa-paper-plane"></i> Submit Request
      </button>
    </div>

    <!-- Reorder Table -->
    <div class="ims-card reorder-table-card">
      <div class="ims-card-header">
        <span class="ims-card-title"><i class="fa-solid fa-list-check"></i> Reorder Requests</span>
      </div>
      <div class="ims-table-wrap">
        <table class="ims-table" id="reorderTable">
          <thead>
            <tr>
              <th>Product</th>
              <th>Variant</th>
              <th>Qty</th>
              <th>Stock Now</th>
              <th>Status</th>
              <th>Requested By</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reorders)): ?>
              <tr><td colspan="8" class="ims-td-empty">No reorder requests yet.</td></tr>
            <?php else: ?>
              <?php foreach ($reorders as $ro): ?>
                <?php
                $status_cls = [
                  'PENDING'   => 'ims-badge-adjust',
                  'ORDERED'   => 'ims-badge-in',
                  'RECEIVED'  => 'ims-badge-ok',
                  'CANCELLED' => 'ims-badge-out',
                ][$ro['status']] ?? 'ims-badge-adjust';
                ?>
                <tr id="row-<?= $ro['reorder_id'] ?>">
                  <td class="td-name"><?= htmlspecialchars($ro['product_name']) ?></td>
                  <td><span class="variant-pill"><?= $ro['size'] ?> · <?= htmlspecialchars($ro['color']) ?></span></td>
                  <td class="td-qty"><?= $ro['qty_requested'] ?></td>
                  <td class="<?= $ro['stock']==0?'stock-zero':($ro['stock']<=$ro['reorder_level']?'stock-low':'') ?>"><?= $ro['stock'] ?></td>
                  <td><span class="ims-badge <?= $status_cls ?>"><?= $ro['status'] ?></span></td>
                  <td><?= htmlspecialchars($ro['admin_name'] ?? 'Admin') ?></td>
                  <td class="td-date"><?= date('M d, Y', strtotime($ro['created_at'])) ?></td>
                  <td>
                    <?php if ($ro['status'] === 'PENDING'): ?>
                      <button class="btn-ims-xs btn-ims-xs-primary" onclick="updateStatus(<?= $ro['reorder_id'] ?>,'ORDERED')">Mark Ordered</button>
                      <button class="btn-ims-xs btn-ims-xs-danger" onclick="updateStatus(<?= $ro['reorder_id'] ?>,'CANCELLED')">Cancel</button>
                    <?php elseif ($ro['status'] === 'ORDERED'): ?>
                      <button class="btn-ims-xs btn-ims-xs-success" onclick="updateStatus(<?= $ro['reorder_id'] ?>,'RECEIVED')">
                        <i class="fa-solid fa-check"></i> Mark Received
                      </button>
                    <?php else: ?>
                      <span class="td-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

</div>
</div>
</div>
<?php include '../footer.php'; ?>
<script src="../../script/admin_menu.js"></script>


<script>
(function(){
  // Variant search for reorder form
  const rsearch   = document.getElementById('reorderSearch');
  const rdropdown = document.getElementById('reorderDropdown');
  const rvidInput = document.getElementById('reorderVid');
  let rTimer;

  rsearch.addEventListener('input', () => {
    clearTimeout(rTimer);
    const q = rsearch.value.trim();
    if (q.length < 2) { rdropdown.innerHTML=''; rdropdown.classList.remove('open'); return; }
    rTimer = setTimeout(() => {
      fetch(`ims_adjust.php?search_variants=1&q=${encodeURIComponent(q)}`)
        .then(r=>r.json()).then(rows => {
          if (!rows.length) { rdropdown.innerHTML='<div class="vd-empty">Not found.</div>'; rdropdown.classList.add('open'); return; }
          rdropdown.innerHTML = rows.map(r=>`
            <div class="vd-item" data-id="${r.variant_id}" data-name="${escHtml(r.product_name)}" data-size="${r.size}" data-color="${escHtml(r.color)}" data-qty="${r.reorder_qty}" data-stock="${r.stock}">
              <div class="vd-main"><span class="vd-name">${escHtml(r.product_name)}</span><span class="vd-meta">${r.size} · ${escHtml(r.color)}</span></div>
              <div class="vd-right"><span class="vd-stock ${r.stock===0?'vds-out':r.stock<=r.reorder_level?'vds-low':'vds-ok'}">${r.stock}</span></div>
            </div>`).join('');
          rdropdown.classList.add('open');
          rdropdown.querySelectorAll('.vd-item').forEach(item => item.addEventListener('click', ()=>selectReorderVariant(item)));
        });
    }, 300);
  });

  document.addEventListener('click', e => { if(!e.target.closest('#reorderSearch')&&!e.target.closest('#reorderDropdown')) rdropdown.classList.remove('open'); });

  function selectReorderVariant(item) {
    rvidInput.value = item.dataset.id;
    rsearch.value   = `${item.dataset.name} — ${item.dataset.size} · ${item.dataset.color}`;
    rdropdown.classList.remove('open');
    document.getElementById('reorderQty').value = item.dataset.qty || '';
    showReorderCard(item.dataset.name, item.dataset.size, item.dataset.color, item.dataset.stock);
  }

  function showReorderCard(name, size, color, stock) {
    const card = document.getElementById('reorderSelectedCard');
    card.innerHTML = `<div class="vc-header"><div class="vc-info"><span class="vc-name">${escHtml(name)}</span><span class="vc-meta">${size} · ${escHtml(color)}</span></div>
      <div class="vc-stock"><span class="vc-stock-val">${stock}</span><span class="vc-stock-lbl">in stock</span></div></div>`;
    card.classList.remove('hidden');
  }

  // Suggestion chips
  document.querySelectorAll('.suggest-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      rvidInput.value = chip.dataset.vid;
      rsearch.value = `${chip.dataset.name} — ${chip.dataset.size} · ${chip.dataset.color}`;
      document.getElementById('reorderQty').value = chip.dataset.qty || '';
      showReorderCard(chip.dataset.name, chip.dataset.size, chip.dataset.color, chip.dataset.stock);
    });
  });

  window.submitReorder = function() {
    const vid = rvidInput.value;
    const qty = parseInt(document.getElementById('reorderQty').value);
    if (!vid) { showToast('error','Please select a variant.'); return; }
    if (!qty || qty <= 0) { showToast('error','Enter a valid quantity.'); return; }

    const fd = new FormData();
    fd.append('action','create');
    fd.append('variant_id', vid);
    fd.append('qty', qty);
    fd.append('supplier_note', document.getElementById('reorderNote').value);

    fetch('ims_reorder.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r=>r.json()).then(d => {
        showToast(d.ok?'success':'error', d.msg);
        if (d.ok) setTimeout(()=>location.reload(), 1200);
      });
  };

  window.updateStatus = function(id, status) {
    const fd = new FormData();
    fd.append('action','update_status');
    fd.append('reorder_id', id);
    fd.append('status', status);
    fetch('ims_reorder.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r=>r.json()).then(d => {
        showToast(d.ok?'success':'error', d.msg);
        if (d.ok) setTimeout(()=>location.reload(), 1200);
      });
  };

  function showToast(type, msg) {
    const t = document.getElementById('ajaxToast');
    t.className = `ims-toast ims-toast-${type}`;
    t.innerHTML = `<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i> ${escHtml(msg)} <button onclick="this.parentElement.style.display='none'"><i class="fa-solid fa-xmark"></i></button>`;
    t.style.display='flex';
    setTimeout(()=>{ t.style.display='none'; }, 5000);
  }

  function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  // Pre-select from URL
  <?php if ($prefill_v): ?>
  document.getElementById('reorderVid').value = <?= $prefill_vid ?>;
  document.getElementById('reorderSearch').value = "<?= htmlspecialchars(addslashes($prefill_v['product_name'])) ?> — <?= $prefill_v['size'] ?> · <?= htmlspecialchars(addslashes($prefill_v['color'])) ?>";
  document.getElementById('reorderQty').value = <?= $prefill_v['reorder_qty'] ?: 10 ?>;
  showReorderCard("<?= htmlspecialchars(addslashes($prefill_v['product_name'])) ?>","<?= $prefill_v['size'] ?>","<?= htmlspecialchars(addslashes($prefill_v['color'])) ?>",<?= $prefill_v['stock'] ?>);
  <?php endif; ?>
})();
</script>
</body>
</html>