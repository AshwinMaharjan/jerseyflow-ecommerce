<?php
session_start();
include('connect.php');
require_once 'auth_guard.php';


// ── Filters from GET ───────────────────────────────────────────────────────
$search   = trim($_GET['search']   ?? '');
$club_id  = intval($_GET['club_id']  ?? 0);
$size_id  = intval($_GET['size_id']  ?? 0);
$kit_id   = intval($_GET['kit_id']   ?? 0);
$special_type = $_GET['special_type'] ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$products_per_page = 7;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $products_per_page;

// ── Delete action ──────────────────────────────────────────────────────────
$delete_success = '';
$delete_error   = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    $img_res = mysqli_query($conn, "SELECT image_path FROM product_images WHERE product_id = $del_id");

    $del_stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($del_stmt, 'i', $del_id);

    if (mysqli_stmt_execute($del_stmt)) {
        while ($img_row = mysqli_fetch_assoc($img_res)) {
            $img_file = '../uploads/products/' . $img_row['image_path'];
            if (file_exists($img_file)) unlink($img_file);
        }
        $delete_success = 'Product deleted successfully.';
    } else {
        $delete_error = 'Failed to delete product.';
    }
    mysqli_stmt_close($del_stmt);
}

// ── Fetch filter dropdowns ─────────────────────────────────────────────────
$clubs_result = mysqli_query($conn, "SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
$sizes_result = mysqli_query($conn, "SELECT size_id, size_name FROM sizes ORDER BY sort_order ASC");
$kits_result  = mysqli_query($conn, "SELECT kit_id, kit_name FROM kits ORDER BY sort_order ASC");

// ── Build WHERE clause ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "p.product_name LIKE ?";
    $params[] = '%' . $search . '%';
    $types   .= 's';
}
if ($club_id > 0) {
    $where[]  = "p.club_id = ?";
    $params[] = $club_id;
    $types   .= 'i';
}
if ($size_id > 0) {
    $where[]  = "p.size_id = ?";
    $params[] = $size_id;
    $types   .= 'i';
}
if ($kit_id > 0) {
    $where[]  = "p.kit_id = ?";
    $params[] = $kit_id;
    $types   .= 'i';
}
if ($special_type !== '') {
    $where[]  = "p.special_type = ?";
    $params[] = $special_type;
    $types   .= 's';
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count total matching products ──────────────────────────────────────────
$count_sql = "SELECT COUNT(*) AS total FROM products p $where_sql";

if (count($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}

$total_products = (int) mysqli_fetch_assoc($count_result)['total'];
$total_pages    = max(1, (int) ceil($total_products / $products_per_page));

// Clamp current page
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $products_per_page;
}

// ── Build main query with LIMIT / OFFSET ───────────────────────────────────
$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.price,
        p.stock,
        p.description,
        p.created_at,
        c.club_name,
        s.size_name,
        k.kit_name,
        pi.image_path AS image
    FROM products p
    LEFT JOIN clubs c ON p.club_id = c.club_id
    LEFT JOIN sizes s ON p.size_id = s.size_id
    LEFT JOIN kits  k ON p.kit_id  = k.kit_id
    LEFT JOIN product_images pi
        ON pi.product_id = p.product_id AND pi.is_primary = 1
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$paginated_params   = $params;
$paginated_params[] = $products_per_page;
$paginated_params[] = $offset;
$paginated_types    = $types . 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $paginated_types, ...$paginated_params);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);

// ── Build product data array for view modal (JS) ───────────────────────────
$all_products_data = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $all_products_data[] = $row;
}

// ── Helper: build pagination URL preserving all filters ───────────────────
function paginate_url(int $page, string $search, int $club_id, int $size_id, int $kit_id, string $special_type): string {
    $p = ['page' => $page];
    if ($search !== '')       $p['search']       = $search;
    if ($club_id > 0)         $p['club_id']       = $club_id;
    if ($size_id > 0)         $p['size_id']       = $size_id;
    if ($kit_id  > 0)         $p['kit_id']        = $kit_id;
    if ($special_type !== '') $p['special_type']  = $special_type;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products | JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/all_products.css">
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title"><i class="fa-solid fa-box-open"></i> All Products</h1>
                <p class="page-subtitle">
                    <?php if ($total_products === 0): ?>
                        No products found
                    <?php else: ?>
                        Showing
                        <strong><?= $offset + 1 ?>–<?= min($offset + $products_per_page, $total_products) ?></strong>
                        of <strong><?= $total_products ?></strong>
                        product<?= $total_products !== 1 ? 's' : '' ?>
                        <?= ($search || $club_id || $size_id || $kit_id || $special_type) ? '— filtered results' : '' ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="add_products.php" class="btn-add">
                <i class="fa-solid fa-plus"></i> Add Product
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($delete_success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?= $delete_success ?>
            </div>
        <?php endif; ?>
        <?php if ($delete_error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $delete_error ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" id="filterForm">
                <div class="filters-row">

                    <!-- Search -->
                    <div class="filter-group search-group">
                        <div class="search-input-wrap">
                            <i class="fa-solid fa-magnifying-glass search-icon"></i>
                            <input type="text" name="search" id="searchInput"
                                   placeholder="Search by product name..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <?php if ($search): ?>
                                <button type="button" class="clear-search" onclick="clearSearch()">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Club Filter -->
                    <div class="filter-group">
                        <select name="club_id" onchange="this.form.submit()">
                            <option value="0">All Clubs</option>
                            <?php
                            mysqli_data_seek($clubs_result, 0);
                            while ($club = mysqli_fetch_assoc($clubs_result)):
                                $sel = ($club_id == $club['club_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $club['club_id'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($club['club_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Size Filter -->
                    <div class="filter-group">
                        <select name="size_id" onchange="this.form.submit()">
                            <option value="0">All Sizes</option>
                            <?php
                            mysqli_data_seek($sizes_result, 0);
                            while ($size = mysqli_fetch_assoc($sizes_result)):
                                $sel = ($size_id == $size['size_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $size['size_id'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($size['size_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Kit Filter -->
                    <div class="filter-group">
                        <select name="kit_id" onchange="this.form.submit()">
                            <option value="0">All Kits</option>
                            <?php
                            mysqli_data_seek($kits_result, 0);
                            while ($kit = mysqli_fetch_assoc($kits_result)):
                                $sel = ($kit_id == $kit['kit_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $kit['kit_id'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($kit['kit_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Special Type Filter -->
                    <div class="filter-group">
                        <select name="special_type" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="retro" <?= ($special_type === 'retro') ? 'selected' : '' ?>>
                                Retro Jersey
                            </option>
                            <option value="worldcup_2026" <?= ($special_type === 'worldcup_2026') ? 'selected' : '' ?>>
                                World Cup 2026
                            </option>
                        </select>
                    </div>

                    <!-- Search Button -->
                    <button type="submit" class="btn-filter-search">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>

                    <!-- Clear All -->
                    <?php if ($search || $club_id || $size_id || $kit_id || $special_type): ?>
                        <a href="/jerseyflow-ecommerce/admin/all_products.php" class="btn-clear-filters">
                            <i class="fa-solid fa-filter-circle-xmark"></i> Clear
                        </a>
                    <?php endif; ?>

                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="table-card">

            <?php if ($total_products === 0): ?>

                <!-- ── Empty State ───────────────────────────────────────── -->
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>No products found</h3>
                    <p><?= ($search || $club_id || $size_id || $kit_id || $special_type) ? 'Try adjusting your filters.' : 'Start by adding your first product.' ?></p>
                    <?php if (!$search && !$club_id && !$size_id && !$kit_id && !$special_type): ?>
                        <a href="add_products.php" class="btn-add">
                            <i class="fa-solid fa-plus"></i> Add Product
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <!-- ── Products Table ─────────────────────────────────────── -->
                <div class="table-responsive">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th class="col-img">Image</th>
                                <th class="col-name">Product Name</th>
                                <th class="col-club">Club</th>
                                <th class="col-kit">Kit</th>
                                <th class="col-size">Size</th>
                                <th class="col-price">Price</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-date">Added</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_products_data as $product): ?>
                                <tr>
                                    <!-- Thumbnail -->
                                    <td class="col-img">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                                 alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                 class="product-thumb"
                                                 onerror="this.src='../images/no_image.png'">
                                        <?php else: ?>
                                            <div class="no-image-thumb">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Name + description snippet -->
                                    <td class="col-name">
                                        <span class="product-name"><?= htmlspecialchars($product['product_name']) ?></span>
                                        <?php if (!empty($product['description'])): ?>
                                            <span class="product-desc"><?= htmlspecialchars(mb_strimwidth($product['description'], 0, 60, '…')) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Club -->
                                    <td class="col-club">
                                        <span class="badge badge-club">
                                            <?= htmlspecialchars($product['club_name'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <!-- Kit -->
                                    <td class="col-kit">
                                        <span class="badge badge-kit">
                                            <?= htmlspecialchars($product['kit_name'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <!-- Size -->
                                    <td class="col-size">
                                        <span class="size-pill">
                                            <?= htmlspecialchars($product['size_name'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <!-- Price -->
                                    <td class="col-price">
                                        <span class="price-value">Rs. <?= number_format($product['price'], 2) ?></span>
                                    </td>

                                    <!-- Stock -->
                                    <td class="col-stock">
                                        <?php
                                        $stock = intval($product['stock']);
                                        $stock_class = $stock === 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-ok');
                                        $stock_label = $stock === 0 ? 'Out of Stock' : ($stock <= 5 ? 'Low' : 'In Stock');
                                        ?>
                                        <div class="stock-wrap">
                                            <span class="stock-badge <?= $stock_class ?>"><?= $stock_label ?></span>
                                            <span class="stock-count"><?= $stock ?> pcs</span>
                                        </div>
                                    </td>

                                    <!-- Date -->
                                    <td class="col-date">
                                        <?= date('M d, Y', strtotime($product['created_at'])) ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="col-actions">
                                        <div class="action-btns">
                                            <button type="button"
                                                    class="action-btn btn-view" title="View"
                                                    onclick="openViewModal(<?= $product['product_id'] ?>)">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <a href="edit_product.php?id=<?= $product['product_id'] ?>"
                                               class="action-btn btn-edit" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <button type="button"
                                                    class="action-btn btn-delete"
                                                    title="Delete"
                                                    onclick="confirmDelete(<?= $product['product_id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- ── /Products Table ────────────────────────────────────── -->

                <!-- ── Pagination ─────────────────────────────────────────── -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">

                    <!-- Prev button -->
                    <?php if ($current_page > 1): ?>
                        <a class="page-btn page-nav"
                           href="<?= paginate_url($current_page - 1, $search, $club_id, $size_id, $kit_id, $special_type) ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn page-nav disabled">
                            <i class="fa-solid fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>

                    <!-- Page numbers with smart ellipsis -->
                    <?php
                    $window      = 2;
                    $pages_shown = [];
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if (
                            $i === 1 ||
                            $i === $total_pages ||
                            ($i >= $current_page - $window && $i <= $current_page + $window)
                        ) {
                            $pages_shown[] = $i;
                        }
                    }
                    $prev_shown = null;
                    foreach ($pages_shown as $p):
                        if ($prev_shown !== null && $p - $prev_shown > 1): ?>
                            <span class="page-btn page-ellipsis">…</span>
                        <?php endif;
                        if ($p === $current_page): ?>
                            <span class="page-btn page-active"><?= $p ?></span>
                        <?php else: ?>
                            <a class="page-btn"
                               href="<?= paginate_url($p, $search, $club_id, $size_id, $kit_id, $special_type) ?>">
                                <?= $p ?>
                            </a>
                        <?php endif;
                        $prev_shown = $p;
                    endforeach;
                    ?>

                    <!-- Next button -->
                    <?php if ($current_page < $total_pages): ?>
                        <a class="page-btn page-nav"
                           href="<?= paginate_url($current_page + 1, $search, $club_id, $size_id, $kit_id, $special_type) ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn page-nav disabled">
                            <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>

                </div>
                <?php endif; ?>
                <!-- ── /Pagination ────────────────────────────────────────── -->

            <?php endif; ?>
            <!-- ── END if/else total_products ────────────────────────────── -->

        </div><!-- /table-card -->

    </div><!-- /main-content -->
</div><!-- /page-wrapper -->

<?php include 'footer.php'; ?>

<!-- ── View Product Modal ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box view-modal-box">

        <div class="view-modal-header">
            <h3 class="view-modal-title"><i class="fa-solid fa-box-open"></i> Product Details</h3>
            <button class="view-modal-close" onclick="closeViewModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="view-modal-body">

            <div class="view-modal-image-col">
                <div class="view-modal-image-wrap">
                    <img id="vm-image" src="" alt="Product Image" onerror="this.src='../images/no_image.png'">
                    <div class="view-no-image hidden" id="vm-no-image">
                        <i class="fa-solid fa-image"></i>
                        <span>No Image</span>
                    </div>
                </div>
            </div>

            <div class="view-modal-details-col">
                <h2 class="vm-product-name" id="vm-name">—</h2>
                <div class="vm-price" id="vm-price">—</div>
                <div class="vm-badges" id="vm-badges"></div>
                <div class="vm-meta-grid">
                    <div class="vm-meta-item">
                        <span class="vm-meta-label"><i class="fa-solid fa-warehouse"></i> Stock</span>
                        <span class="vm-meta-value" id="vm-stock">—</span>
                    </div>
                    <div class="vm-meta-item">
                        <span class="vm-meta-label"><i class="fa-solid fa-shield-halved"></i> Club</span>
                        <span class="vm-meta-value" id="vm-club">—</span>
                    </div>
                    <div class="vm-meta-item">
                        <span class="vm-meta-label"><i class="fa-solid fa-shirt"></i> Kit</span>
                        <span class="vm-meta-value" id="vm-kit">—</span>
                    </div>
                    <div class="vm-meta-item">
                        <span class="vm-meta-label"><i class="fa-solid fa-ruler"></i> Size</span>
                        <span class="vm-meta-value" id="vm-size">—</span>
                    </div>
                </div>
                <div class="vm-description-wrap" id="vm-description-wrap">
                    <span class="vm-meta-label"><i class="fa-solid fa-align-left"></i> Description</span>
                    <p class="vm-description" id="vm-description">—</p>
                </div>
            </div>

        </div>

        <div class="view-modal-footer">
            <button class="modal-btn modal-cancel" onclick="closeViewModal()">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </button>
            <a href="#" class="modal-btn btn-modal-edit" id="vm-edit-btn">
                <i class="fa-solid fa-pen-to-square"></i> Edit
            </a>
            <button type="button" class="modal-btn btn-modal-delete" id="vm-delete-btn">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>

    </div>
</div>

<!-- ── Delete Confirmation Modal ──────────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="modal-title">Delete Product?</h3>
        <p class="modal-message">You are about to delete <strong id="deleteProductName"></strong>. This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="modal-btn modal-cancel" onclick="closeModal()">Cancel</button>
            <a href="#" class="modal-btn modal-confirm" id="deleteConfirmBtn">Yes, Delete</a>
        </div>
    </div>
</div>

<script>
    window.PAGE_DATA = {
        search:       <?= json_encode($search) ?>,
        club_id:      <?= json_encode($club_id) ?>,
        size_id:      <?= json_encode($size_id) ?>,
        kit_id:       <?= json_encode($kit_id) ?>,
        current_page: <?= json_encode($current_page) ?>
    };

    window.PRODUCTS = <?= json_encode($all_products_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<script src="/jerseyflow/script/all_products.js"></script>

</body>
</html>