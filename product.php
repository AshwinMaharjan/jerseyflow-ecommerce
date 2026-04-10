<?php
session_start();
require_once 'connect.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) { header('Location: jersey.php'); exit; }

$stmt = $conn->prepare(
    "SELECT
        p.product_id, p.product_name, p.price, p.stock,
        p.description, p.special_type, p.created_at,
        cl.club_name, co.country_name, k.kit_name,
        (SELECT pi.image_path FROM product_images pi
         WHERE pi.product_id = p.product_id AND pi.is_primary = 1
         LIMIT 1) AS primary_image
     FROM products p
     LEFT JOIN clubs     cl ON p.club_id    = cl.club_id
     LEFT JOIN countries co ON p.country_id = co.country_id
     LEFT JOIN kits      k  ON p.kit_id     = k.kit_id
     WHERE p.product_id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) { header('Location: jersey.php'); exit; }

$img_stmt = $conn->prepare(
    "SELECT image_path, is_primary FROM product_images
     WHERE product_id = ? ORDER BY is_primary DESC, created_at ASC"
);
$img_stmt->bind_param('i', $product_id);
$img_stmt->execute();
$all_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$img_stmt->close();
if (empty($all_images) && !empty($product['primary_image'])) {
    $all_images = [['image_path' => $product['primary_image'], 'is_primary' => 1]];
}

// ── Fetch variants from product_variants ──────────────────────
$all_sizes = ['S', 'M', 'L', 'XL', '2XL'];

$var_stmt = $conn->prepare(
    "SELECT variant_id, size, color, stock, price
     FROM product_variants
     WHERE product_id = ? AND is_active = 1
     ORDER BY FIELD(size,'S','M','L','XL','2XL')"
);
$var_stmt->bind_param('i', $product_id);
$var_stmt->execute();
$variants_rows = $var_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$var_stmt->close();

// Map: uppercase size => variant data (first active variant per size)
$variants_by_size = [];
foreach ($variants_rows as $v) {
    $sz = strtoupper(trim($v['size']));
    if (!isset($variants_by_size[$sz])) {
        $variants_by_size[$sz] = $v;
    }
}

$variants_json = json_encode($variants_by_size);

// Related products
$related = [];
if (!empty($product['special_type'])) {
    $rel_stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.image,
                cl.club_name, co.country_name,
                (SELECT pi.image_path FROM product_images pi
                 WHERE pi.product_id = p.product_id AND pi.is_primary = 1
                 LIMIT 1) AS primary_image
         FROM products p
         LEFT JOIN clubs     cl ON p.club_id    = cl.club_id
         LEFT JOIN countries co ON p.country_id = co.country_id
         WHERE p.special_type = ? AND p.product_id != ?
         ORDER BY p.created_at DESC LIMIT 4"
    );
    $rel_stmt->bind_param('si', $product['special_type'], $product_id);
    $rel_stmt->execute();
    $related = $rel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rel_stmt->close();
}

$type_labels = [
    'standard'       => 'Standard',
    'player_edition' => 'Player Edition',
    'limited'        => 'Limited Edition',
    'worldcup_2026'  => 'World Cup 2026',
    'retro'          => 'Retro',
];
$category_label = $type_labels[$product['special_type']] ?? ucfirst($product['special_type'] ?? 'Jersey');
$is_logged_in   = isset($_SESSION['user_id']);
$page_title     = htmlspecialchars($product['product_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $page_title ?> – JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/fonts/barlow-condensed/barlow-condensed.css">
    <link rel="stylesheet" href="style/footer.css">
    <link rel="stylesheet" href="style/product.css">
</head>
<body>
<?php include("homepage/navbar.php"); ?>

<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

<main class="product-page">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php"><i class="fa-solid fa-house"></i></a>
        <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="jersey.php">Jerseys</a>
        <?php if ($product['special_type']): ?>
            <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
            <a href="jersey.php?type=<?= urlencode($product['special_type']) ?>">
                <?= htmlspecialchars($category_label) ?>
            </a>
        <?php endif; ?>
        <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="bc-current"><?= htmlspecialchars($product['product_name']) ?></span>
    </nav>

    <div class="product-layout">

        <!-- Gallery -->
        <div class="gallery-col">
            <div class="gallery-main">
                <?php if (!empty($all_images)): ?>
                    <img src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($all_images[0]['image_path']) ?>"
                         alt="<?= $page_title ?>" id="mainImg" class="main-img"/>
                <?php else: ?>
                    <div class="main-img-placeholder"><i class="fa-solid fa-shirt"></i></div>
                <?php endif; ?>
            </div>
            <?php if (count($all_images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($all_images as $i => $img): ?>
                        <div class="thumb <?= $i === 0 ? 'active' : '' ?>"
                             data-src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($img['image_path']) ?>">
                            <img src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($img['image_path']) ?>"
                                 alt="View <?= $i + 1 ?>" loading="lazy"/>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="detail-col">

            <h1 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h1>

            <p class="product-category">
                <span class="cat-label">Categories:</span>
                <a href="jersey.php?type=<?= urlencode($product['special_type']) ?>" class="cat-link">
                    <?= htmlspecialchars($category_label) ?>
                </a>
                <?php if ($product['club_name']): ?>
                    &nbsp;·&nbsp;
                    <a href="clubs.php?club=<?= urlencode(strtolower(str_replace(' ', '', $product['club_name']))) ?>" class="cat-link">
                        <?= htmlspecialchars($product['club_name']) ?>
                    </a>
                <?php endif; ?>
            </p>

            <div class="price-row">
                <span class="price-main" id="displayPrice">
                    Rs. <?= number_format((float)$product['price'], 2) ?>
                </span>
                <?php if ((int)$product['stock'] > 0 && (int)$product['stock'] <= 5): ?>
                    <span class="stock-warning">Only <?= (int)$product['stock'] ?> left!</span>
                <?php elseif ((int)$product['stock'] === 0): ?>
                    <span class="stock-out">Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Size buttons — availability driven by JS from VARIANTS_BY_SIZE -->
            <div class="option-group">
                <label class="option-label">Size</label>
                <div class="size-btns" id="sizeBtns">
                    <?php foreach ($all_sizes as $sz): ?>
                        <button type="button"
                                class="size-btn unavailable"
                                data-size="<?= $sz ?>"
                                data-available="0"
                                title="Not available in this size">
                            <?= $sz ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <span class="size-error" id="sizeError" style="display:none;">Please select a size.</span>
            </div>

            <div class="price-detail-box">
                <div class="price-detail-header">Price Detail</div>
                <div class="price-detail-row">
                    <span id="detailLabel">Product Price</span>
                    <span id="detailPrice">Rs. <?= number_format((float)$product['price'], 2) ?></span>
                </div>
                <div class="price-detail-row price-total-row" id="totalRow" style="display:none;">
                    <span><strong>Total</strong></span>
                    <span id="totalPrice"></span>
                </div>
            </div>

            <div class="action-row">
                <div class="qty-wrap">
                    <label class="option-label" for="qtySelect">Qty</label>
                    <select class="qty-select" id="qtySelect">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="button" class="btn-cart" id="addToCartBtn"
                        data-id="<?= $product_id ?>"
                        data-price="<?= (float)$product['price'] ?>"
                        data-name="<?= htmlspecialchars(addslashes($product['product_name'])) ?>"
                        data-logged-in="<?= $is_logged_in ? '1' : '0' ?>">
                    <i class="fa-solid fa-cart-shopping"></i>
                    Add to Cart
                </button>
            </div>

            <div class="info-tabs">
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="description">Description</button>
                    <button class="tab-btn" data-tab="delivery">Delivery Time</button>
                    <button class="tab-btn" data-tab="fitcare">Fit &amp; Care</button>
                </div>
                <div class="tab-content active" id="tab-description">
                    <?php if (!empty($product['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    <?php else: ?>
                        <p class="tab-empty">No description available for this product.</p>
                    <?php endif; ?>
                </div>
                <div class="tab-content" id="tab-delivery">
                    <ul class="info-list">
                        <li><i class="fa-solid fa-location-dot"></i><div><strong>Inside Valley</strong> — Orders are usually delivered next day of confirmation.</div></li>
                        <li><i class="fa-solid fa-truck"></i><div><strong>Outside Valley</strong> — Delivered in 1–4 working days after confirmation.</div></li>
                    </ul>
                </div>
                <div class="tab-content" id="tab-fitcare">
                    <ul class="info-list">
                        <li><i class="fa-solid fa-rotate-left"></i><div>Wash garment inside out</div></li>
                        <li><i class="fa-solid fa-droplet-slash"></i><div>Machine wash cold with like colors</div></li>
                        <li><i class="fa-solid fa-ban"></i><div>Do not bleach</div></li>
                        <li><i class="fa-solid fa-wind"></i><div>Tumble dry low</div></li>
                        <li><i class="fa-solid fa-ban"></i><div>Do not iron</div></li>
                        <li><i class="fa-solid fa-ban"></i><div>Do not use softeners</div></li>
                        <li><i class="fa-solid fa-ban"></i><div>Do not dry clean</div></li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <?php if (!empty($related)): ?>
        <section class="related-section">
            <h2 class="related-title">Related Products</h2>
            <div class="related-grid">
                <?php foreach ($related as $r): ?>
                    <?php
                        $r_img  = !empty($r['primary_image']) ? $r['primary_image'] : ($r['image'] ?? null);
                        $r_name = $r['club_name'] ?? $r['country_name'] ?? null;
                    ?>
                    <a href="product.php?id=<?= (int)$r['product_id'] ?>" class="related-card">
                        <div class="related-img">
                            <?php if (!empty($r_img)): ?>
                                <img src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($r_img) ?>"
                                     alt="<?= htmlspecialchars($r['product_name']) ?>" loading="lazy"
                                     onerror="this.parentElement.innerHTML='<div class=\'no-img\'><i class=\'fa-solid fa-shirt\'></i></div>'"/>
                            <?php else: ?>
                                <div class="no-img"><i class="fa-solid fa-shirt"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="related-body">
                            <div class="related-name"><?= htmlspecialchars($r['product_name']) ?></div>
                            <?php if ($r_name): ?>
                                <div class="related-club"><?= htmlspecialchars($r_name) ?></div>
                            <?php endif; ?>
                            <div class="related-price">Rs. <?= number_format((float)$r['price'], 2) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</main>

<?php include("footer.php"); ?>

<script>
    const BASE_PRICE      = <?= (float)$product['price'] ?>;
    const VARIANTS_BY_SIZE = <?= $variants_json ?>; // { "M": { variant_id, size, stock, price }, ... }
</script>
<script src="script/product.js"></script>
</body>
</html>