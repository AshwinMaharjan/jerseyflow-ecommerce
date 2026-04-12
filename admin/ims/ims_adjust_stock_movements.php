<?php
/**
 * JerseyFlow IMS — Adjust Stock Movements
 * File: admin/ims/ims_adjust_stock_movements.php
 *
 * Handles: Stock IN and Stock OUT on the `products` table (stock column).
 * Accepts both standard page load and AJAX JSON requests.
 */

session_start();
require_once '../connect.php';
require_once '../auth_guard.php';

// ── AJAX: Search products ──────────────────────────────────────────────────
if (!empty($_GET['search_products'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("
        SELECT p.product_id, p.product_name, p.price, p.stock,
               pi.image_path
        FROM products p
        LEFT JOIN product_images pi
               ON pi.product_id = p.product_id AND pi.is_primary = 1
        WHERE p.product_name LIKE ?
        ORDER BY p.product_name
        LIMIT 20
    ");
    $stmt->bind_param('s', $q);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// ── AJAX: Get single product detail ───────────────────────────────────────
if (!empty($_GET['get_product'])) {
    header('Content-Type: application/json');
    $pid = (int)($_GET['product_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT p.product_id, p.product_name, p.price, p.stock,
               pi.image_path
        FROM products p
        LEFT JOIN product_images pi
               ON pi.product_id = p.product_id AND pi.is_primary = 1
        WHERE p.product_id = ?
    ");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode($row ?: null);
    exit;
}

// ── AJAX: Get recent stock movements log ──────────────────────────────────
if (!empty($_GET['recent_movements'])) {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("
        SELECT sl.*, p.product_name,
               pi.image_path
        FROM stock_log sl
        JOIN products p ON p.product_id = sl.product_id
        LEFT JOIN product_images pi
               ON pi.product_id = p.product_id AND pi.is_primary = 1
        ORDER BY sl.created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
    exit;
}

// ── AJAX: Perform stock IN or OUT ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $product_id = (int)($_POST['product_id'] ?? 0);
    $type       = strtoupper(trim($_POST['type'] ?? ''));
    $qty        = (int)($_POST['qty'] ?? 0);
    $note       = trim($_POST['note'] ?? '');

    if (!$product_id) {
        echo json_encode(['ok' => false, 'msg' => 'Please select a product first.']);
        exit;
    }
    if (!in_array($type, ['IN', 'OUT'])) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid movement type.']);
        exit;
    }
    if ($qty <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Quantity must be greater than zero.']);
        exit;
    }

    // Fetch current stock
    $s = $conn->prepare("SELECT stock, product_name FROM products WHERE product_id = ?");
    $s->bind_param('i', $product_id);
    $s->execute();
    $product = $s->get_result()->fetch_assoc();

    if (!$product) {
        echo json_encode(['ok' => false, 'msg' => 'Product not found.']);
        exit;
    }

    $old_stock = (int)$product['stock'];
    $new_stock = $type === 'IN' ? $old_stock + $qty : $old_stock - $qty;

    if ($new_stock < 0) {
        echo json_encode(['ok' => false, 'msg' => "Cannot remove {$qty} units — only {$old_stock} in stock."]);
        exit;
    }

    // Update stock on products table
    $upd = $conn->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
    $upd->bind_param('ii', $new_stock, $product_id);
    $upd->execute();

    // Log the movement — create table if it doesn't exist yet
    $conn->query("
        CREATE TABLE IF NOT EXISTS stock_log (
            log_id       INT AUTO_INCREMENT PRIMARY KEY,
            product_id   INT NOT NULL,
            move_type    ENUM('IN','OUT') NOT NULL,
            quantity     INT NOT NULL,
            stock_before INT NOT NULL,
            stock_after  INT NOT NULL,
            note         TEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $log = $conn->prepare("INSERT INTO stock_log (product_id, move_type, quantity, stock_before, stock_after, note) VALUES (?, ?, ?, ?, ?, ?)");
    $log->bind_param('isiiis', $product_id, $type, $qty, $old_stock, $new_stock, $note);
    $log->execute();

    echo json_encode([
        'ok'        => true,
        'msg'       => "Stock {$type}: {$qty} units for \"{$product['product_name']}\". New stock: {$new_stock}.",
        'new_stock' => $new_stock,
        'old_stock' => $old_stock,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Movements — JerseyFlow</title>
  <link rel="icon" href="../../images/logo_icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../../style/footer.css">
  <link rel="stylesheet" href="../../style/admin_menu.css">
  <link rel="stylesheet" href="../../style/admin_navbar.css">
  <link rel="stylesheet" href="../../style/all_products.css">
  <link rel="stylesheet" href="../../style/ims.css">

  <style>
    /* ── Root tokens ──────────────────────────────────────── */
    :root {
      --bg:        #121212;
      --panel:     #1A1A1A;
      --panel2:    #202020;
      --text:      #EEE5D8;
      --muted:     rgba(238,229,216,.6);
      --border:    rgba(255,255,255,.08);
      --red:       #681010;
      --red-hover: #7d1414;
      --red-soft:  rgba(104,16,16,.2);
      --green:     #166534;
      --hover:     rgba(255,255,255,.06);
      --shadow:    0 4px 18px rgba(0,0,0,.4);
      --nav-h:     64px;
      --radius:    8px;
      --green-soft: rgba(22,101,52,.25);
      --amber:      #b45309;
      --amber-soft: rgba(180,83,9,.2);
    }

    /* ── Layout ───────────────────────────────────────────── */
    .main-content { padding: 28px 32px; }

    .sm-layout {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 24px;
      align-items: start;
      margin-top: 24px;
    }

    /* ── Card base ────────────────────────────────────────── */
    .sm-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }

    .sm-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      background: var(--panel2);
    }

    .sm-card-title {
      font-size: .85rem;
      font-weight: 600;
      color: var(--text);
      text-transform: uppercase;
      letter-spacing: .06em;
    }

    .sm-card-body { padding: 24px; }

    /* ── Page header ──────────────────────────────────────── */
    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
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

    .page-subtitle {
      color: var(--muted);
      font-size: .875rem;
      margin: 4px 0 0;
    }

    /* ── Type toggle ──────────────────────────────────────── */
    .type-toggle {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 24px;
    }

    .type-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 14px 20px;
      border-radius: var(--radius);
      border: 1.5px solid var(--border);
      background: var(--panel2);
      color: var(--muted);
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .18s ease;
    }

    .type-btn:hover { border-color: rgba(255,255,255,.2); color: var(--text); }

    .type-btn.active-in {
      background: var(--green-soft);
      border-color: #16a34a;
      color: #4ade80;
    }

    .type-btn.active-out {
      background: var(--red-soft);
      border-color: #dc2626;
      color: #f87171;
    }

    .type-btn i { font-size: 1rem; }

    /* ── Search ───────────────────────────────────────────── */
    .search-wrap {
      position: relative;
      margin-bottom: 16px;
    }

    .search-wrap > i.search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: .875rem;
      pointer-events: none;
      z-index: 1;
    }

    .search-wrap input {
      width: 100%;
      padding: 11px 14px 11px 38px;
      background: var(--panel2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-size: .9rem;
      outline: none;
      box-sizing: border-box;
      transition: border-color .18s;
    }

    .search-wrap input:focus { border-color: rgba(255,255,255,.25); }
    .search-wrap input::placeholder { color: var(--muted); }

    /* ── Dropdown ─────────────────────────────────────────── */
    .product-dropdown {
      position: absolute;
      top: calc(100% + 6px);
      left: 0; right: 0;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      z-index: 999;
      max-height: 280px;
      overflow-y: auto;
      display: none;
    }

    .product-dropdown.open { display: block; }

    .pd-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      cursor: pointer;
      transition: background .15s;
      border-bottom: 1px solid var(--border);
    }

    .pd-item:last-child { border-bottom: none; }
    .pd-item:hover { background: var(--hover); }

    .pd-thumb {
      width: 40px;
      height: 40px;
      border-radius: 6px;
      object-fit: cover;
      background: var(--panel2);
      flex-shrink: 0;
    }

    .pd-thumb-placeholder {
      width: 40px;
      height: 40px;
      border-radius: 6px;
      background: var(--panel2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--muted);
      font-size: .8rem;
    }

    .pd-name  { font-size: .9rem; color: var(--text); font-weight: 500; }
    .pd-price { font-size: .78rem; color: var(--muted); margin-top: 2px; }

    .pd-stock {
      margin-left: auto;
      font-size: .8rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 20px;
      flex-shrink: 0;
    }

    .pds-ok  { background: var(--green-soft); color: #4ade80; }
    .pds-low { background: var(--amber-soft); color: #fbbf24; }
    .pds-out { background: var(--red-soft);   color: #f87171; }

    .pd-empty { padding: 16px; text-align: center; color: var(--muted); font-size: .875rem; }

    /* ── Selected product card ────────────────────────────── */
    .selected-product-card {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px;
      background: var(--panel2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 20px;
    }

    .selected-product-card.hidden { display: none; }

    .spc-thumb {
      width: 52px;
      height: 52px;
      border-radius: 8px;
      object-fit: cover;
      background: var(--bg);
      flex-shrink: 0;
    }

    .spc-thumb-ph {
      width: 52px;
      height: 52px;
      border-radius: 8px;
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      flex-shrink: 0;
    }

    .spc-info  { flex: 1; min-width: 0; }
    .spc-name  { font-size: .95rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .spc-id    { font-size: .78rem; color: var(--muted); margin-top: 2px; }

    .spc-stock-block { text-align: center; flex-shrink: 0; }
    .spc-stock-val   { display: block; font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1; }
    .spc-stock-lbl   { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

    /* ── Form fields ──────────────────────────────────────── */
    .form-group { margin-bottom: 18px; }

    .form-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-bottom: 7px;
    }

    .sm-input {
      width: 100%;
      padding: 11px 14px;
      background: var(--panel2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-size: .9rem;
      outline: none;
      box-sizing: border-box;
      transition: border-color .18s;
    }

    .sm-input:focus { border-color: rgba(255,255,255,.25); }
    .sm-input::placeholder { color: var(--muted); }

    .sm-textarea {
      width: 100%;
      padding: 11px 14px;
      background: var(--panel2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-size: .9rem;
      outline: none;
      resize: vertical;
      box-sizing: border-box;
      font-family: inherit;
      transition: border-color .18s;
    }

    .sm-textarea:focus { border-color: rgba(255,255,255,.25); }
    .sm-textarea::placeholder { color: var(--muted); }

    /* ── Preview bar ──────────────────────────────────────── */
    .preview-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      background: var(--panel2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 20px;
      font-size: .9rem;
    }

    .preview-bar.hidden { display: none; }
    .preview-label  { color: var(--muted); font-size: .82rem; }
    .preview-before { color: var(--text); font-weight: 600; }
    .preview-arrow  { color: var(--muted); font-size: .8rem; }
    .preview-after  { font-weight: 700; font-size: 1rem; }
    .preview-up     { color: #4ade80; }
    .preview-down   { color: #f87171; }
    .preview-neg    { color: #f87171; }

    /* ── Action buttons ───────────────────────────────────── */
    .form-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 8px;
      padding-top: 18px;
      border-top: 1px solid var(--border);
    }

    .btn-ghost {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 18px;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: transparent;
      color: var(--muted);
      font-size: .875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all .18s;
    }

    .btn-ghost:hover { background: var(--hover); color: var(--text); }

    .btn-primary-in {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 22px;
      border-radius: var(--radius);
      border: none;
      background: var(--green);
      color: #d1fae5;
      font-size: .875rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity .18s;
    }

    .btn-primary-in:hover    { opacity: .85; }
    .btn-primary-in:disabled { opacity: .5; cursor: not-allowed; }

    .btn-primary-out {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 22px;
      border-radius: var(--radius);
      border: none;
      background: var(--red);
      color: #fee2e2;
      font-size: .875rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity .18s;
    }

    .btn-primary-out:hover    { opacity: .85; }
    .btn-primary-out:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Toast ────────────────────────────────────────────── */
    .sm-toast {
      display: none;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      border-radius: var(--radius);
      margin-bottom: 18px;
      font-size: .9rem;
      font-weight: 500;
    }

    .sm-toast-success {
      background: var(--green-soft);
      border: 1px solid #16a34a;
      color: #4ade80;
    }

    .sm-toast-error {
      background: var(--red-soft);
      border: 1px solid #dc2626;
      color: #f87171;
    }

    .sm-toast button {
      margin-left: auto;
      background: none;
      border: none;
      color: inherit;
      cursor: pointer;
      opacity: .7;
      font-size: .9rem;
    }

    /* ── Recent entries ───────────────────────────────────── */
    .recent-list { padding: 0; }

    .rm-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 20px;
      border-bottom: 1px solid var(--border);
      transition: background .15s;
    }

    .rm-item:last-child { border-bottom: none; }
    .rm-item:hover { background: var(--hover); }

    .rm-badge {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .8rem;
      flex-shrink: 0;
    }

    .rm-badge-in  { background: var(--green-soft); color: #4ade80; }
    .rm-badge-out { background: var(--red-soft);   color: #f87171; }

    /* Recent entry thumbnail */
    .rm-thumb {
      width: 34px;
      height: 34px;
      border-radius: 6px;
      object-fit: cover;
      background: var(--panel2);
      flex-shrink: 0;
    }

    .rm-thumb-ph {
      width: 34px;
      height: 34px;
      border-radius: 6px;
      background: var(--panel2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: .72rem;
      flex-shrink: 0;
    }

    .rm-body { flex: 1; min-width: 0; }

    .rm-product {
      font-size: .875rem;
      font-weight: 500;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .rm-note {
      font-size: .75rem;
      color: var(--muted);
      margin-top: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .rm-right { text-align: right; flex-shrink: 0; }

    .rm-qty {
      font-size: .9rem;
      font-weight: 700;
      display: block;
    }

    .qty-in  { color: #4ade80; }
    .qty-out { color: #f87171; }

    .rm-time { font-size: .72rem; color: var(--muted); }

    .rm-empty {
      padding: 32px 20px;
      text-align: center;
      color: var(--muted);
      font-size: .875rem;
    }

    .rm-empty i { font-size: 1.4rem; margin-bottom: 8px; display: block; }

    /* ── Audit link ───────────────────────────────────────── */
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
    }

    .btn-ims-ghost:hover { background: var(--hover); color: var(--text); }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
      .sm-layout { grid-template-columns: 1fr; }
      .main-content { padding: 20px 18px; }
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
          <i class="fa-solid fa-boxes-stacked"></i> Stock Movements
        </h1>
        <p class="page-subtitle">Add or remove stock from your product inventory</p>
      </div>
    </div>

    <!-- Toast -->
    <div id="smToast" class="sm-toast"></div>

    <div class="sm-layout">

      <!-- ── LEFT: Movement Form ───────────────────────────── -->
      <div class="sm-card">
        <div class="sm-card-header">
          <i class="fa-solid fa-pen-to-square" style="color:var(--muted)"></i>
          <span class="sm-card-title">Stock Movement Entry</span>
        </div>
        <div class="sm-card-body">

          <form id="stockForm" autocomplete="off">
            <input type="hidden" id="productId" name="product_id" value="" />
            <input type="hidden" id="typeInput"  name="type"       value="IN" />

            <!-- IN / OUT toggle -->
            <div class="type-toggle">
              <button type="button" class="type-btn active-in" id="btnIn" onclick="setType('IN')">
                <i class="fa-solid fa-arrow-down"></i> Stock In
              </button>
              <button type="button" class="type-btn" id="btnOut" onclick="setType('OUT')">
                <i class="fa-solid fa-arrow-up"></i> Stock Out
              </button>
            </div>

            <!-- Product search -->
            <div class="form-group">
              <label class="form-label">Product <span style="color:#f87171">*</span></label>
              <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input
                  type="text"
                  id="productSearch"
                  placeholder="Search product by name…"
                  autocomplete="off"
                />
                <div id="productDropdown" class="product-dropdown"></div>
              </div>
            </div>

            <!-- Selected product card -->
            <div id="selectedCard" class="selected-product-card hidden">
              <!-- filled by JS -->
            </div>

            <!-- Quantity -->
            <div class="form-group">
              <label class="form-label">Quantity <span style="color:#f87171">*</span></label>
              <input
                type="number"
                id="qtyInput"
                name="qty"
                min="1"
                value="1"
                class="sm-input"
                placeholder="Enter quantity"
              />
            </div>

            <!-- Note -->
            <div class="form-group">
              <label class="form-label">Note <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
              <textarea
                name="note"
                id="noteInput"
                class="sm-textarea"
                rows="3"
                placeholder="e.g. Supplier restock batch #12, damaged goods removed…"
              ></textarea>
            </div>

            <!-- Stock preview -->
            <div id="previewBar" class="preview-bar hidden">
              <span class="preview-label">Stock after save:</span>
              <span id="previewBefore" class="preview-before">—</span>
              <i class="fa-solid fa-arrow-right preview-arrow"></i>
              <span id="previewAfter"  class="preview-after">—</span>
            </div>

            <div class="form-actions">
              <button type="button" class="btn-ghost" onclick="resetForm()">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button>
              <button type="submit" class="btn-primary-in" id="submitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Movement
              </button>
            </div>
          </form>

        </div>
      </div>

      <!-- ── RIGHT: Recent Entries ─────────────────────────── -->
      <div class="sm-card">
        <div class="sm-card-header">
          <i class="fa-solid fa-clock-rotate-left" style="color:var(--muted)"></i>
          <span class="sm-card-title">Recent Entries</span>
        </div>
        <div id="recentList" class="recent-list">
          <div class="rm-empty">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <p>Loading…</p>
          </div>
        </div>
      </div>

    </div>
  </div>
  </div>

<?php include '../footer.php'; ?>

<script>
(function () {

  /* ── Image base path ──────────────────────────────────── */
  const IMG_BASE = '/jerseyflow-ecommerce/uploads/products/';

  /* ── State ────────────────────────────────────────────── */
  let selectedProduct = null;
  let currentType     = 'IN';

  /* ── Type toggle ──────────────────────────────────────── */
  window.setType = function (type) {
    currentType = type;
    document.getElementById('typeInput').value = type;

    const btnIn  = document.getElementById('btnIn');
    const btnOut = document.getElementById('btnOut');
    const submit = document.getElementById('submitBtn');

    btnIn.className  = 'type-btn' + (type === 'IN'  ? ' active-in'  : '');
    btnOut.className = 'type-btn' + (type === 'OUT' ? ' active-out' : '');

    submit.className = type === 'IN' ? 'btn-primary-in' : 'btn-primary-out';
    submit.innerHTML = type === 'IN'
      ? '<i class="fa-solid fa-arrow-down"></i> Add Stock'
      : '<i class="fa-solid fa-arrow-up"></i> Remove Stock';

    updatePreview();
  };

  /* ── Thumb helper — used in dropdown AND selected card ── */
  function makeThumb(imagePath, smallSize = false) {
    const sz  = smallSize ? 40 : 52;
    const cls = smallSize ? 'pd-thumb' : 'spc-thumb';
    const phCls = smallSize ? 'pd-thumb-placeholder' : 'spc-thumb-ph';

    if (imagePath) {
      return `<img
                src="${IMG_BASE}${esc(imagePath)}"
                class="${cls}"
                width="${sz}" height="${sz}"
                onerror="this.outerHTML='<div class=\\'${phCls}\\'><i class=\\'fa-solid fa-shirt\\'></i></div>'"
              >`;
    }
    return `<div class="${phCls}"><i class="fa-solid fa-shirt"></i></div>`;
  }

  /* ── Search ───────────────────────────────────────────── */
  const searchInput = document.getElementById('productSearch');
  const dropdown    = document.getElementById('productDropdown');
  const pidHidden   = document.getElementById('productId');
  let timer;

  searchInput.addEventListener('input', () => {
    clearTimeout(timer);
    const q = searchInput.value.trim();
    if (q.length < 2) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); return; }
    timer = setTimeout(() => {
      fetch(`ims_adjust_stock_movements.php?search_products=1&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(rows => {
          if (!rows.length) {
            dropdown.innerHTML = '<div class="pd-empty">No products found.</div>';
            dropdown.classList.add('open');
            return;
          }
          dropdown.innerHTML = rows.map(p => {
            const stockCls = p.stock == 0 ? 'pds-out' : p.stock <= 5 ? 'pds-low' : 'pds-ok';
            return `
              <div class="pd-item" data-id="${p.product_id}">
                ${makeThumb(p.image_path, true)}
                <div>
                  <div class="pd-name">${esc(p.product_name)}</div>
                  <div class="pd-price">Rs ${parseFloat(p.price).toLocaleString()}</div>
                </div>
                <span class="pd-stock ${stockCls}">${p.stock} pcs</span>
              </div>`;
          }).join('');
          dropdown.classList.add('open');
          dropdown.querySelectorAll('.pd-item').forEach(item => {
            item.addEventListener('click', () => {
              selectProduct(parseInt(item.dataset.id));
              dropdown.classList.remove('open');
            });
          });
        }).catch(() => {});
    }, 280);
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('.search-wrap')) dropdown.classList.remove('open');
  });

  /* ── Select product ───────────────────────────────────── */
  function selectProduct(pid) {
    fetch(`ims_adjust_stock_movements.php?get_product=1&product_id=${pid}`)
      .then(r => r.json())
      .then(data => {
        if (!data) return;
        selectedProduct = data;
        pidHidden.value   = data.product_id;
        searchInput.value = data.product_name;

        document.getElementById('selectedCard').innerHTML = `
          ${makeThumb(data.image_path, false)}
          <div class="spc-info">
            <div class="spc-name">${esc(data.product_name)}</div>
            <div class="spc-id">ID #${data.product_id} &nbsp;·&nbsp; Rs ${parseFloat(data.price).toLocaleString()}</div>
          </div>
          <div class="spc-stock-block">
            <span class="spc-stock-val" id="liveStockVal">${data.stock}</span>
            <span class="spc-stock-lbl">in stock</span>
          </div>`;
        document.getElementById('selectedCard').classList.remove('hidden');
        updatePreview();
      })
      .catch(() => showToast('error', 'Could not load product. Please try again.'));
  }

  /* ── Preview ──────────────────────────────────────────── */
  document.getElementById('qtyInput').addEventListener('input', updatePreview);

  function updatePreview() {
    const bar = document.getElementById('previewBar');
    if (!selectedProduct) { bar.classList.add('hidden'); return; }

    const qty   = parseInt(document.getElementById('qtyInput').value) || 0;
    const curr  = parseInt(selectedProduct.stock);
    const after = currentType === 'IN' ? curr + qty : curr - qty;

    bar.classList.remove('hidden');
    document.getElementById('previewBefore').textContent = curr;
    const afterEl = document.getElementById('previewAfter');
    afterEl.textContent = after;
    afterEl.className   = 'preview-after ' + (after < 0 ? 'preview-neg' : after < curr ? 'preview-down' : 'preview-up');
  }

  /* ── Submit ───────────────────────────────────────────── */
  document.getElementById('stockForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

    const fd = new FormData(this);
    fetch('ims_adjust_stock_movements.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      showToast(data.ok ? 'success' : 'error', data.msg);
      if (data.ok && selectedProduct) {
        selectedProduct.stock = data.new_stock;
        const liveVal = document.getElementById('liveStockVal');
        if (liveVal) liveVal.textContent = data.new_stock;
        document.getElementById('qtyInput').value = 1;
        document.getElementById('noteInput').value = '';
        updatePreview();
        loadRecent();
      }
    })
    .catch(() => showToast('error', 'Network error. Please try again.'))
    .finally(() => {
      btn.disabled = false;
      setType(currentType);
    });
  });

  /* ── Toast ────────────────────────────────────────────── */
  function showToast(type, msg) {
    const t = document.getElementById('smToast');
    t.className = `sm-toast sm-toast-${type}`;
    t.innerHTML = `
      <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
      ${esc(msg)}
      <button onclick="this.parentElement.style.display='none'"><i class="fa-solid fa-xmark"></i></button>`;
    t.style.display = 'flex';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.display = 'none'; }, 6000);
  }

  /* ── Recent entries ───────────────────────────────────── */
  function loadRecent() {
    fetch('ims_adjust_stock_movements.php?recent_movements=1')
      .then(r => r.json())
      .then(rows => {
        const list = document.getElementById('recentList');
        if (!rows.length) {
          list.innerHTML = '<div class="rm-empty"><i class="fa-solid fa-inbox"></i><p>No movements yet.</p></div>';
          return;
        }
        list.innerHTML = rows.map(r => {
          const isIn     = r.move_type === 'IN';
          const diff     = isIn ? `+${r.quantity}` : `-${r.quantity}`;
          const qCls     = isIn ? 'qty-in'      : 'qty-out';
          const bCls     = isIn ? 'rm-badge-in' : 'rm-badge-out';
          const icon     = isIn ? 'fa-arrow-down' : 'fa-arrow-up';
          const time     = new Date(r.created_at).toLocaleString('en-US', {
            month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
          });
          const noteText = r.note ? esc(r.note) : `${r.stock_before} → ${r.stock_after} pcs`;

          // Thumbnail for recent entry row
          const thumbHtml = r.image_path
            ? `<img src="${IMG_BASE}${esc(r.image_path)}" class="rm-thumb"
                    onerror="this.outerHTML='<div class=\\'rm-thumb-ph\\'><i class=\\'fa-solid fa-shirt\\'></i></div>'">`
            : `<div class="rm-thumb-ph"><i class="fa-solid fa-shirt"></i></div>`;

          return `
            <div class="rm-item">
              <div class="rm-badge ${bCls}">
                <i class="fa-solid ${icon}"></i>
              </div>
              ${thumbHtml}
              <div class="rm-body">
                <div class="rm-product">${esc(r.product_name)}</div>
                <div class="rm-note">${noteText}</div>
              </div>
              <div class="rm-right">
                <span class="rm-qty ${qCls}">${diff}</span>
                <span class="rm-time">${time}</span>
              </div>
            </div>`;
        }).join('');
      }).catch(() => {});
  }

  /* ── Reset ────────────────────────────────────────────── */
  window.resetForm = function () {
    selectedProduct = null;
    document.getElementById('productId').value     = '';
    document.getElementById('productSearch').value = '';
    document.getElementById('qtyInput').value      = 1;
    document.getElementById('noteInput').value     = '';
    document.getElementById('selectedCard').classList.add('hidden');
    document.getElementById('previewBar').classList.add('hidden');
    setType('IN');
  };

  /* ── Helpers ──────────────────────────────────────────── */
  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Init ─────────────────────────────────────────────── */
  setType('IN');
  loadRecent();

  // Auto-load product from URL ?product_id=X
  const urlParams    = new URLSearchParams(window.location.search);
  const pidFromUrl   = urlParams.get('product_id');
  if (pidFromUrl) selectProduct(parseInt(pidFromUrl));

})();
</script>

<script src="../../script/admin_menu.js"></script>
</body>
</html>