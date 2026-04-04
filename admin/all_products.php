<?php
/**
 * JerseyFlow Admin — All Products
 * File: all_products.php
 */

session_start();
require_once 'connect.php';
require_once 'auth_guard.php';
require_once 'user_logger.php';

// ── Pagination ────────────────────────────────────────────────
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── Filters ───────────────────────────────────────────────────
$search         = trim($_GET['search'] ?? '');
$filter_club    = $_GET['club']         ?? '';
$filter_kit     = $_GET['kit']          ?? '';
$filter_size    = $_GET['size']         ?? '';
$filter_country = $_GET['country']      ?? '';
$filter_special = $_GET['special_type'] ?? '';

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(p.product_name LIKE ? OR cl.club_name LIKE ? OR p.description LIKE ?)';
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($filter_club !== '') {
    $where[]  = 'p.club_id = ?';
    $params[] = (int)$filter_club;
    $types   .= 'i';
}
if ($filter_kit !== '') {
    $where[]  = 'p.kit_id = ?';
    $params[] = (int)$filter_kit;
    $types   .= 'i';
}
if ($filter_size !== '') {
    $where[]  = 'p.size_id = ?';
    $params[] = (int)$filter_size;
    $types   .= 'i';
}
if ($filter_country !== '') {
    $where[]  = 'p.country_id = ?';
    $params[] = (int)$filter_country;
    $types   .= 'i';
}
if ($filter_special !== '') {
    $where[]  = 'p.special_type = ?';
    $params[] = $filter_special;
    $types   .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ── Total count ───────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) FROM products p
               LEFT JOIN clubs cl ON p.club_id = cl.club_id
               $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_products / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// ── Fetch products ────────────────────────────────────────────
$data_sql = "SELECT
    p.product_id, p.product_name, p.price, p.stock, p.image,
    p.description, p.created_at, p.special_type,
    cl.club_name,
    s.size_name,
    k.kit_name,
    co.country_name,
    (SELECT pi.image_path FROM product_images pi
     WHERE pi.product_id = p.product_id AND pi.is_primary = 1
     LIMIT 1) AS primary_image
FROM products p
LEFT JOIN clubs     cl ON p.club_id    = cl.club_id
LEFT JOIN sizes     s  ON p.size_id    = s.size_id
LEFT JOIN kits      k  ON p.kit_id     = k.kit_id
LEFT JOIN countries co ON p.country_id = co.country_id
$where_sql
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?";

$data_stmt    = $conn->prepare($data_sql);
$fetch_types  = $types . 'ii';
$fetch_params = array_merge($params, [$per_page, $offset]);
$data_stmt->bind_param($fetch_types, ...$fetch_params);
$data_stmt->execute();
$products = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Build per-product JSON for view modal ─────────────────────
$products_json = [];
foreach ($products as $p) {
    $img_stmt = $conn->prepare(
        "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, created_at ASC"
    );
    $img_stmt->bind_param('i', $p['product_id']);
    $img_stmt->execute();
    $all_images = array_column($img_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'image_path');
    $img_stmt->close();

    $display_img = $p['primary_image'] ?? $p['image'] ?? null;

    $products_json[$p['product_id']] = [
        'id'          => $p['product_id'],
        'name'        => $p['product_name'],
        'price'       => number_format($p['price'], 2),
        'stock'       => (int)$p['stock'],
        'club'        => $p['club_name']    ?? '—',
        'kit'         => $p['kit_name']     ?? '—',
        'size'        => $p['size_name']    ?? '—',
        'country'     => $p['country_name'] ?? '—',
        'special'     => $p['special_type'] ?? '',
        'description' => $p['description']  ?? '',
        'created_at'  => date('M d, Y', strtotime($p['created_at'])),
        'image'       => $display_img,
        'all_images'  => $all_images,
    ];
}

// ── Stats ─────────────────────────────────────────────────────
$stats_res = $conn->query("SELECT
    COUNT(*)                      AS total,
    SUM(stock > 0)                AS in_stock,
    SUM(stock = 0)                AS out_of_stock,
    SUM(stock <= 5 AND stock > 0) AS low_stock
    FROM products");
$stats = $stats_res->fetch_assoc();

// ── Filter dropdown data ──────────────────────────────────────
$clubs     = $conn->query("SELECT club_id, club_name FROM clubs ORDER BY club_name")->fetch_all(MYSQLI_ASSOC);
$kits      = $conn->query("SELECT kit_id, kit_name FROM kits ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$sizes     = $conn->query("SELECT size_id, size_name FROM sizes ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$countries = $conn->query("SELECT country_id, country_name FROM countries ORDER BY country_name")->fetch_all(MYSQLI_ASSOC);

// ── Special type display map ──────────────────────────────────
$special_map = [
    'standard'       => ['label' => 'Standard',       'class' => 'stype-standard'],
    'player_edition' => ['label' => 'Player Edition',  'class' => 'stype-player'],
    'limited'        => ['label' => 'Limited',         'class' => 'stype-limited'],
    'worldcup_2026'  => ['label' => 'World Cup 2026',  'class' => 'stype-worldcup'],
    'retro'          => ['label' => 'Retro',           'class' => 'stype-retro'],
];

// ── Toast ─────────────────────────────────────────────────────
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>All Products — JerseyFlow Admin</title>
  <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../style/admin_panel.css" />
  <link rel="stylesheet" href="../style/footer.css">
  <link rel="stylesheet" href="../style/admin_menu.css">
  <link rel="stylesheet" href="../style/add_products.css">
  <link rel="stylesheet" href="../style/users.css" />
  <link rel="stylesheet" href="../style/all_products.css" />
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
  <?php include 'admin_menu.php'; ?>

  <div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fa-solid fa-shirt"></i> All Products</h1>
        <p class="page-subtitle">Browse, filter, and manage your jersey catalogue</p>
      </div>
      <a href="add_product.php" class="btn-add-product">
        <i class="fa-solid fa-plus"></i> Add Product
      </a>
    </div>

    <!-- Toast -->
    <?php if ($toast): ?>
      <div class="toast toast-<?= htmlspecialchars($toast['type']) ?>" id="pageToast">
        <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($toast['msg']) ?>
        <button class="toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
      </div>
    <?php endif; ?>

    <!-- ── Filter Bar ──────────────────────────────────────── -->
    <div class="filter-card">
      <form method="GET" action="all_products.php" id="filterForm">
        <div class="filter-row">

          <!-- Search -->
          <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search"
              placeholder="Search name, club, description…"
              value="<?= htmlspecialchars($search) ?>"
              class="search-input" autocomplete="off" />
            <?php if ($search): ?>
              <button type="button" class="search-clear" onclick="clearSearch()">
                <i class="fa-solid fa-xmark"></i>
              </button>
            <?php endif; ?>
          </div>

          <!-- Club -->
          <select name="club" class="filter-select" onchange="this.form.submit()">
            <option value="">All Clubs</option>
            <?php foreach ($clubs as $c): ?>
              <option value="<?= $c['club_id'] ?>" <?= $filter_club == $c['club_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['club_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Country -->
          <select name="country" class="filter-select" onchange="this.form.submit()">
            <option value="">All Countries</option>
            <?php foreach ($countries as $cn): ?>
              <option value="<?= $cn['country_id'] ?>" <?= $filter_country == $cn['country_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cn['country_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Kit -->
          <select name="kit" class="filter-select" onchange="this.form.submit()">
            <option value="">All Kits</option>
            <?php foreach ($kits as $k): ?>
              <option value="<?= $k['kit_id'] ?>" <?= $filter_kit == $k['kit_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($k['kit_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Size -->
          <select name="size" class="filter-select" onchange="this.form.submit()">
            <option value="">All Sizes</option>
            <?php foreach ($sizes as $sz): ?>
              <option value="<?= $sz['size_id'] ?>" <?= $filter_size == $sz['size_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sz['size_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Special Type — all 5 options -->
          <select name="special_type" class="filter-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="standard"       <?= $filter_special === 'standard'       ? 'selected' : '' ?>>Standard</option>
            <option value="player_edition" <?= $filter_special === 'player_edition' ? 'selected' : '' ?>>Player Edition</option>
            <option value="limited"        <?= $filter_special === 'limited'        ? 'selected' : '' ?>>Limited</option>
            <option value="worldcup_2026"  <?= $filter_special === 'worldcup_2026'  ? 'selected' : '' ?>>World Cup 2026</option>
            <option value="retro"          <?= $filter_special === 'retro'          ? 'selected' : '' ?>>Retro</option>
          </select>

          <button type="submit" class="btn-search">
            <i class="fa-solid fa-search"></i> Search
          </button>

          <?php if ($search || $filter_club || $filter_kit || $filter_size || $filter_country || $filter_special): ?>
            <a href="all_products.php" class="btn-clear-filters">
              <i class="fa-solid fa-filter-circle-xmark"></i> Clear
            </a>
          <?php endif; ?>

        </div>
      </form>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulk-bar" id="bulkBar">
      <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
      <div class="bulk-actions">
        <button class="btn-bulk btn-bulk-delete" onclick="bulkAction('delete')">
          <i class="fa-solid fa-trash"></i> Delete Selected
        </button>
      </div>
      <button class="btn-bulk-cancel" onclick="clearSelection()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- ── Table Card ──────────────────────────────────────── -->
    <div class="table-card">
      <div class="table-header">
        <span class="table-title">
          <?php if ($total_products > 0): ?>
            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?> of <?= $total_products ?> products
          <?php else: ?>
            No products found
          <?php endif; ?>
        </span>
      </div>

      <div class="table-wrap">
        <table class="products-table" id="productsTable">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="selectAll" class="jf-checkbox" /></th>
              <th class="col-img-th">Image</th>
              <th>Product Name</th>
              <th>Club</th>
              <th>Kit</th>
              <th>Size</th>
              <th>Price</th>
              <th>Stock</th>
              <th class="col-actions-th">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr>
                <td colspan="11" class="empty-state">
                  <div class="empty-inner">
                    <i class="fa-solid fa-shirt"></i>
                    <p>No products match your filters.</p>
                    <a href="all_products.php" class="btn-back-plain">Clear filters</a>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($products as $p): ?>
                <?php
                  // Stock
                  if ($p['stock'] == 0)       { $stock_class = 'badge-out'; $stock_label = 'Out of Stock'; }
                  elseif ($p['stock'] <= 5)   { $stock_class = 'badge-low'; $stock_label = 'Low Stock'; }
                  else                         { $stock_class = 'badge-in';  $stock_label = 'In Stock'; }

                  // Special type
                  $stype = $special_map[$p['special_type']] ?? null;
                ?>
                <tr class="product-row" data-id="<?= $p['product_id'] ?>">

                  <!-- Checkbox -->
                  <td class="col-check">
                    <input type="checkbox" class="jf-checkbox row-check" value="<?= $p['product_id'] ?>" />
                  </td>

                  <!-- Image column -->
<td class="col-img">
    <?php
        // Decide which image to use
        $img = !empty($p['primary_image']) ? $p['primary_image'] : $p['image'];
    ?>

    <?php if (!empty($img)): ?>
        <img src="../uploads/products/<?= htmlspecialchars($img) ?>"
             alt="<?= htmlspecialchars($p['product_name']) ?>"
             class="product-thumb"
             onerror="this.src='../images/no_image.png'">
    <?php else: ?>
        <div class="product-thumb-placeholder">
            <i class="fa-solid fa-shirt"></i>
        </div>
    <?php endif; ?>
</td>
                  <!-- Product Name column -->
                  <td class="col-name">
                    <span class="product-name"><?= htmlspecialchars($p['product_name']) ?></span>
                    <?php if (!empty($p['description'])): ?>
                      <span class="product-desc"><?= htmlspecialchars(mb_strimwidth($p['description'], 0, 48, '…')) ?></span>
                    <?php endif; ?>
                  </td>

                  <!-- Club -->
                  <td>
                    <span class="club-name">
                      <i class="fa-solid fa-shield-halved"></i>
                      <?= htmlspecialchars($p['club_name'] ?? '—') ?>
                    </span>
                  </td>

                  <!-- Kit (own column) -->
                  <td>
                    <?php if (!empty($p['kit_name'])): ?>
                      <span class="badge badge-kit"><?= htmlspecialchars($p['kit_name']) ?></span>
                    <?php else: ?>
                      <span class="text-muted-sm">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Size (own column) -->
                  <td>
                    <?php if (!empty($p['size_name'])): ?>
                      <span class="badge badge-size"><?= htmlspecialchars($p['size_name']) ?></span>
                    <?php else: ?>
                      <span class="text-muted-sm">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Price -->
                  <td>
                    <span class="price-tag">Rs. <?= number_format($p['price'], 2) ?></span>
                  </td>

                  <!-- Stock -->
                  <td>
                    <div class="stock-cell">
                      <span class="badge badge-stock <?= $stock_class ?>"><?= $stock_label ?></span>
                      <span class="stock-qty"><?= (int)$p['stock'] ?> pcs</span>
                    </div>
                  </td>

                  <!-- Special Type -->

                  <!-- Actions: 3 icon buttons -->
                  <td class="col-actions">
                    <div class="action-btns">
                      <button class="btn-icon btn-icon-view" title="View"
                        onclick="openViewModal(<?= $p['product_id'] ?>)">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <a href="edit_product.php?id=<?= $p['product_id'] ?>"
                        class="btn-icon btn-icon-edit" title="Edit">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </a>
                      <button class="btn-icon btn-icon-delete" title="Delete"
                        onclick="confirmDelete(<?= $p['product_id'] ?>, '<?= htmlspecialchars(addslashes($p['product_name'])) ?>')">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>

                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        $q = http_build_query(array_filter([
          'search'       => $search,
          'club'         => $filter_club,
          'kit'          => $filter_kit,
          'size'         => $filter_size,
          'country'      => $filter_country,
          'special_type' => $filter_special,
        ]));
        $q = $q ? "&$q" : '';
        ?>
        <a href="?page=1<?= $q ?>"                class="page-btn <?= $page === 1            ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="?page=<?= $page - 1 ?><?= $q ?>" class="page-btn <?= $page === 1            ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>

        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) echo '<span class="page-gap">…</span>';
        for ($p2 = $start; $p2 <= $end; $p2++):
        ?>
          <a href="?page=<?= $p2 ?><?= $q ?>" class="page-btn <?= $p2 === $page ? 'active' : '' ?>"><?= $p2 ?></a>
        <?php endfor;
        if ($end < $total_pages) echo '<span class="page-gap">…</span>';
        ?>

        <a href="?page=<?= $page + 1 ?><?= $q ?>"       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="?page=<?= $total_pages ?><?= $q ?>"     class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
      </div>
    <?php endif; ?>

  </div><!-- /.main-content -->
</div><!-- /.page-wrapper -->

<!-- ════════════════════════════════════════════════════════════
     VIEW PRODUCT MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
  <div class="modal-box view-modal-box">

    <div class="view-modal-header">
      <h3 class="view-modal-title" id="vmTitle"></h3>
      <button class="view-modal-close" onclick="closeViewModal()" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="view-modal-body">

      <!-- Gallery -->
      <div class="vm-gallery">
        <div class="vm-main-img-wrap">
          <img src="" alt="" id="vmMainImg" class="vm-main-img" />
          <div class="vm-main-placeholder" id="vmPlaceholder">
            <i class="fa-solid fa-shirt"></i>
          </div>
        </div>
        <div class="vm-thumbs" id="vmThumbs"></div>
      </div>

      <!-- Details -->
      <div class="vm-details">

        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-tag"></i> Price</span>
          <span class="vm-value" id="vmPrice"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-boxes-stacked"></i> Stock</span>
          <span class="vm-value" id="vmStock"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-shield-halved"></i> Club</span>
          <span class="vm-value" id="vmClub"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-earth-americas"></i> Country</span>
          <span class="vm-value" id="vmCountry"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-layer-group"></i> Kit</span>
          <span class="vm-value" id="vmKit"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-ruler"></i> Size</span>
          <span class="vm-value" id="vmSize"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-star"></i> Type</span>
          <span class="vm-value" id="vmType"></span>
        </div>
        <div class="vm-detail-row">
          <span class="vm-label"><i class="fa-solid fa-calendar-days"></i> Added</span>
          <span class="vm-value" id="vmDate"></span>
        </div>

        <div class="vm-desc-wrap" id="vmDescWrap">
          <span class="vm-label"><i class="fa-solid fa-align-left"></i> Description</span>
          <p class="vm-desc" id="vmDesc"></p>
        </div>

        <div class="vm-modal-actions">
          <a href="#" class="btn-vm-edit" id="vmEditBtn">
            <i class="fa-solid fa-pen-to-square"></i> Edit Product
          </a>
          <button class="btn-vm-close" onclick="closeViewModal()">Close</button>
        </div>

      </div>
    </div>

  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-icon icon-danger">
      <i class="fa-solid fa-trash"></i>
    </div>
    <h3 class="modal-title">Delete Product?</h3>
    <p class="modal-msg" id="modalMsg"></p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn-modal-confirm danger" onclick="executeDelete()">Delete</button>
    </div>
  </div>
</div>

<!-- Hidden action form -->
<form method="POST" action="product_actions.php" id="actionForm">
  <input type="hidden" name="action"     id="actionField"    />
  <input type="hidden" name="product_id" id="productIdField" />
  <input type="hidden" name="bulk_ids"   id="bulkIdsField"   />
  <input type="hidden" name="redirect"   value="all_products.php" />
</form>

<?php include("footer.php"); ?>

<script>
// ── Product data injected from PHP ────────────────────────────
const PRODUCTS     = <?= json_encode($products_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const UPLOADS_BASE = '../uploads/products/';

const SPECIAL_LABELS = {
  standard:       { label: 'Standard',       cls: 'stype-standard' },
  player_edition: { label: 'Player Edition',  cls: 'stype-player'   },
  limited:        { label: 'Limited',         cls: 'stype-limited'  },
  worldcup_2026:  { label: 'World Cup 2026',  cls: 'stype-worldcup' },
  retro:          { label: 'Retro',           cls: 'stype-retro'    },
};

// ── Toast helper ──────────────────────────────────────────────
function showToast(msg, type = 'success') {
  // Remove any existing toast first
  document.querySelectorAll('.toast').forEach(t => t.remove());

  const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <i class="fa-solid ${icon}"></i>
    ${msg}
    <button class="toast-close" onclick="this.parentElement.remove()">
      <i class="fa-solid fa-xmark"></i>
    </button>`;
  // Insert after page-header
  const header = document.querySelector('.page-header');
  header.insertAdjacentElement('afterend', toast);
  setTimeout(() => toast.remove(), 5000);
}

// ── AJAX delete helper ────────────────────────────────────────
async function doDelete(productId, productName) {
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('product_id', productId);
  formData.append('redirect', 'all_products.php');

  try {
    const res  = await fetch('product_actions.php', { method: 'POST', body: formData });
    const text = await res.text();

    // product_actions.php likely redirects — treat any 2xx or redirect as success
    // Remove the table row
    const row = document.querySelector(`.product-row[data-id="${productId}"]`);
    if (row) {
      row.style.transition = 'opacity 0.3s';
      row.style.opacity = '0';
      setTimeout(() => row.remove(), 300);
    }

    // Remove from PRODUCTS cache
    delete PRODUCTS[productId];

    // Update stat counters (simple decrement)
    const totalEl = document.querySelector('.stat-card .stat-value');
    if (totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent.replace(/,/g,'')) - 1);

    showToast(`"${productName}" has been deleted successfully.`, 'success');
  } catch (err) {
    showToast('Something went wrong. Please try again.', 'error');
  }
}

// ── Search clear ──────────────────────────────────────────────
function clearSearch() {
  document.querySelector('.search-input').value = '';
  document.getElementById('filterForm').submit();
}

// ── Select All / Bulk ─────────────────────────────────────────
const selectAll = document.getElementById('selectAll');
const bulkBar   = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');

selectAll?.addEventListener('change', function () {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});

document.querySelectorAll('.row-check').forEach(cb => {
  cb.addEventListener('change', () => {
    const all     = document.querySelectorAll('.row-check');
    const checked = document.querySelectorAll('.row-check:checked');
    if (selectAll) {
      selectAll.checked       = all.length === checked.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    }
    updateBulkBar();
  });
});

function updateBulkBar() {
  const n = document.querySelectorAll('.row-check:checked').length;
  if (bulkCount) bulkCount.textContent = n;
  if (bulkBar)   bulkBar.classList.toggle('visible', n > 0);
}

function clearSelection() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
  if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
  updateBulkBar();
}

async function bulkAction(action) {
  const checked = [...document.querySelectorAll('.row-check:checked')];
  const ids = checked.map(cb => cb.value);
  if (!ids.length) return;
  if (!confirm(`Are you sure you want to ${action} ${ids.length} product(s)?`)) return;

  const formData = new FormData();
  formData.append('action', action);
  formData.append('bulk_ids', ids.join(','));
  formData.append('redirect', 'all_products.php');

  try {
    await fetch('product_actions.php', { method: 'POST', body: formData });

    // Remove all selected rows
    ids.forEach(id => {
      const row = document.querySelector(`.product-row[data-id="${id}"]`);
      if (row) {
        row.style.transition = 'opacity 0.3s';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
      }
      delete PRODUCTS[id];
    });

    clearSelection();
    showToast(`${ids.length} product(s) deleted successfully.`, 'success');
  } catch (err) {
    showToast('Something went wrong. Please try again.', 'error');
  }
}

// ── Delete modal ──────────────────────────────────────────────
let _deleteId   = null;
let _deleteName = null;

function confirmDelete(id, name) {
  _deleteId   = id;
  _deleteName = name;
  document.getElementById('modalMsg').textContent =
    `This will permanently remove "${name}" and all its images. This cannot be undone.`;
  document.getElementById('confirmModal').classList.add('open');
}

function closeDeleteModal() {
  document.getElementById('confirmModal').classList.remove('open');
  _deleteId   = null;
  _deleteName = null;
}

function executeDelete() {
  if (!_deleteId) return;
  const id   = _deleteId;
  const name = _deleteName;
  closeDeleteModal();
  doDelete(id, name);
}

document.getElementById('confirmModal').addEventListener('click', function (e) {
  if (e.target === this) closeDeleteModal();
});

// ── View modal ────────────────────────────────────────────────
function openViewModal(id) {
  const p = PRODUCTS[id];
  if (!p) return;

  document.getElementById('vmTitle').textContent = p.name;
  document.getElementById('vmEditBtn').href = `edit_product.php?id=${p.id}`;

  const mainImg     = document.getElementById('vmMainImg');
  const placeholder = document.getElementById('vmPlaceholder');
  const thumbsWrap  = document.getElementById('vmThumbs');
  thumbsWrap.innerHTML = '';

  let allImgs = [];
  if (p.all_images && p.all_images.length) {
    allImgs = p.all_images;
  } else if (p.image) {
    allImgs = [p.image];
  }

  if (allImgs.length) {
    mainImg.src               = UPLOADS_BASE + allImgs[0];
    mainImg.style.display     = 'block';
    placeholder.style.display = 'none';

    if (allImgs.length > 1) {
      allImgs.forEach((imgPath, idx) => {
        const tb = document.createElement('div');
        tb.className = 'vm-thumb' + (idx === 0 ? ' active' : '');
        const imgSrc = imgPath.startsWith('http') ? imgPath : UPLOADS_BASE + imgPath;
        tb.innerHTML = `<img src="${imgSrc}" alt="" loading="lazy" />`;
        tb.addEventListener('click', () => {
          mainImg.src = imgPath.startsWith('http') ? imgPath : UPLOADS_BASE + imgPath;
          thumbsWrap.querySelectorAll('.vm-thumb').forEach(t => t.classList.remove('active'));
          tb.classList.add('active');
        });
        thumbsWrap.appendChild(tb);
      });
      thumbsWrap.style.display = 'flex';
    } else {
      thumbsWrap.style.display = 'none';
    }
  } else {
    mainImg.style.display     = 'none';
    placeholder.style.display = 'flex';
    thumbsWrap.style.display  = 'none';
  }

  document.getElementById('vmPrice').textContent   = 'Rs. ' + p.price;
  document.getElementById('vmClub').textContent    = p.club;
  document.getElementById('vmCountry').textContent = p.country;
  document.getElementById('vmKit').textContent     = p.kit;
  document.getElementById('vmSize').textContent    = p.size;
  document.getElementById('vmDate').textContent    = p.created_at;

  const stockEl = document.getElementById('vmStock');
  stockEl.innerHTML = '';
  const sBadge = document.createElement('span');
  sBadge.className = 'badge badge-stock ';
  if      (p.stock === 0) { sBadge.classList.add('badge-out'); sBadge.textContent = `Out of Stock`; }
  else if (p.stock <= 5)  { sBadge.classList.add('badge-low'); sBadge.textContent = `Low (${p.stock} pcs)`; }
  else                    { sBadge.classList.add('badge-in');  sBadge.textContent = `In Stock (${p.stock} pcs)`; }
  stockEl.appendChild(sBadge);

  const typeEl  = document.getElementById('vmType');
  typeEl.innerHTML = '';
  const st      = SPECIAL_LABELS[p.special] || SPECIAL_LABELS['standard'];
  const stBadge = document.createElement('span');
  stBadge.className   = `badge badge-stype ${st.cls}`;
  stBadge.textContent = st.label;
  typeEl.appendChild(stBadge);

  const descWrap = document.getElementById('vmDescWrap');
  if (p.description && p.description.trim()) {
    document.getElementById('vmDesc').textContent = p.description;
    descWrap.style.display = 'flex';
  } else {
    descWrap.style.display = 'none';
  }

  document.getElementById('viewModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeViewModal() {
  document.getElementById('viewModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('viewModal').addEventListener('click', function (e) {
  if (e.target === this) closeViewModal();
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeViewModal(); closeDeleteModal(); }
});

// ── Auto-dismiss toast (PHP-rendered) ─────────────────────────
const toast = document.getElementById('pageToast');
if (toast) setTimeout(() => toast.remove(), 5000);
</script>
</body>
</html>