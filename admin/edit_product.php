<?php
session_start();
include('connect.php');
require_once 'auth_guard.php';


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: all_products.php');
    exit();
}

$product_id = intval($_GET['id']);

// ── Fetch product ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT p.*, c.club_name, s.size_name, k.kit_name
    FROM products p
    LEFT JOIN clubs c ON p.club_id = c.club_id
    LEFT JOIN sizes s ON p.size_id = s.size_id
    LEFT JOIN kits k ON p.kit_id = k.kit_id
    WHERE p.product_id = ?
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: all_products.php');
    exit();
}

// dropdowns
$clubs_result = mysqli_query($conn, "SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
$sizes_result = mysqli_query($conn, "SELECT size_id, size_name FROM sizes ORDER BY sort_order ASC");
$kits_result  = mysqli_query($conn, "SELECT kit_id, kit_name FROM kits ORDER BY sort_order ASC");

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name = trim($_POST['product_name']);
    $price        = floatval($_POST['price']);
    $stock        = intval($_POST['stock']);
    $club_id      = intval($_POST['club_id']);
    $size_id      = intval($_POST['size_id']);
    $kit_id       = intval($_POST['kit_id']);
    $description  = trim($_POST['description']);
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    // ── VALIDATION ─────────────────────────────────────────
    if (empty($product_name) || $price < 500 || $price > 20000 || $stock < 0 || $stock > 500) {
        $error = 'Invalid input values.';
    } elseif (!$club_id || !$size_id || !$kit_id) {
        $error = 'Please fill all required fields.';
    }

    // ── IMAGE HANDLING ─────────────────────────────────────
    $new_image = $product['image'];

    if (empty($error)) {

        $upload_dir   = '../uploads/products/';
        $allowed_ext  = ['jpg','jpeg','png','webp'];
        $allowed_mime = ['image/jpeg','image/png','image/webp'];
        $max_size     = 2 * 1024 * 1024;

        if ($remove_image) {
            if (!empty($product['image'])) {
                @unlink($upload_dir . $product['image']);
            }
            $new_image = '';
        }

        if (!empty($_FILES['image']['name'])) {

            $tmp  = $_FILES['image']['tmp_name'];
            $name = $_FILES['image']['name'];
            $size = $_FILES['image']['size'];

            if (!is_uploaded_file($tmp)) {
                $error = 'Invalid upload.';
            } else {

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmp);
                finfo_close($finfo);

                if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                    $error = 'Invalid image format.';
                } elseif ($size > $max_size) {
                    $error = 'Image too large.';
                } else {

                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $filename = uniqid('product_', true) . '.' . $ext;
                    $dest = $upload_dir . $filename;

                    if (move_uploaded_file($tmp, $dest)) {
                        if (!empty($product['image'])) {
                            @unlink($upload_dir . $product['image']);
                        }
                        $new_image = $filename;
                    } else {
                        $error = 'Upload failed.';
                    }
                }
            }
        }
    }

    // ── UPDATE PRODUCT ─────────────────────────────────────
    if (empty($error)) {

        // get old stock BEFORE update
        $old_stock = (int)$product['stock'];

        $upd = $conn->prepare("
            UPDATE products
            SET product_name=?, price=?, stock=?, club_id=?, size_id=?, kit_id=?, image=?, description=?
            WHERE product_id=?
        ");

        $upd->bind_param(
            'sdiiiissi',
            $product_name,
            $price,
            $stock,
            $club_id,
            $size_id,
            $kit_id,
            $new_image,
            $description,
            $product_id
        );

        if ($upd->execute()) {

            // ── UPDATE VARIANT ─────────────────────────────
            $size_label = 'M';
            $sr = $conn->prepare("SELECT size_name FROM sizes WHERE size_id=?");
            $sr->bind_param('i', $size_id);
            $sr->execute();
            $res = $sr->get_result()->fetch_assoc();
            if ($res) $size_label = $res['size_name'];
            $sr->close();

            $vr = $conn->prepare("SELECT variant_id FROM product_variants WHERE product_id=? LIMIT 1");
            $vr->bind_param('i', $product_id);
            $vr->execute();
            $vrow = $vr->get_result()->fetch_assoc();
            $vr->close();

            if ($vrow) {

                // update variant stock + size
                $vupdate = $conn->prepare("
                    UPDATE product_variants
                    SET size=?, stock=?
                    WHERE variant_id=?
                ");
                $vupdate->bind_param('sii', $size_label, $stock, $vrow['variant_id']);
                $vupdate->execute();
                $vupdate->close();

                // ── STOCK MOVEMENT (AFTER SUCCESS) ─────────
                if ($stock !== $old_stock) {
                    require_once 'ims/ims_helpers.php';

                    $admin_id = $_SESSION['admin_id'] ?? 0;

                    ims_stock_move(
                        $conn,
                        $vrow['variant_id'],
                        $admin_id,
                        'ADJUST',
                        $stock,
                        '',
                        'Stock updated via edit product',
                        ''
                    );
                }
            }

            $success = 'Product updated successfully.';

            // refresh local product
            $product['product_name'] = $product_name;
            $product['price']       = $price;
            $product['stock']       = $stock;
            $product['club_id']     = $club_id;
            $product['size_id']     = $size_id;
            $product['kit_id']      = $kit_id;
            $product['image']       = $new_image;
            $product['description'] = $description;

        } else {
            $error = 'Database update failed.';
        }

        $upd->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/add_products.css">
    <link rel="stylesheet" href="../style/edit_product.css">
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Product
                </h1>
                <p class="page-subtitle">
                    Editing: <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                    &nbsp;·&nbsp; ID #<?= $product_id ?>
                </p>
            </div>
            <a href="all_products.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success" id="alertBox">
                <i class="fa-solid fa-circle-check"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" id="alertBox">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <form method="POST" action="" enctype="multipart/form-data" id="editProductForm">
                <input type="hidden" name="remove_image" id="removeImageFlag" value="0">

                <div class="form-grid">

                    <!-- LEFT COLUMN -->
                    <div class="form-col">

                        <div class="form-section-title">Basic Information</div>

                        <!-- Product Name -->
                        <div class="form-group">
                            <label for="product_name">Product Name <span class="required">*</span></label>
                            <input type="text" id="product_name" name="product_name"
                                   placeholder="e.g. Manchester United Home Jersey"
                                   value="<?= htmlspecialchars($product['product_name']) ?>"
                                   required>
                        </div>

                        <!-- Price & Stock -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="price">Price (Rs.) <span class="required">*</span></label>
                                <div class="input-prefix">
                                    <span class="prefix-icon">Rs.</span>
                                    <input type="number" id="price" name="price"
                                           placeholder="0.00" step="0.01" min="0.01"
                                           value="<?= htmlspecialchars($product['price']) ?>"
                                           required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="stock">Stock Quantity <span class="required">*</span></label>
                                <input type="number" id="stock" name="stock"
                                       placeholder="0" min="0"
                                       value="<?= htmlspecialchars($product['stock']) ?>"
                                       required>
                            </div>
                        </div>

                        <!-- Club & Size -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="club_id">Club <span class="required">*</span></label>
                                <select id="club_id" name="club_id" required>
                                    <option value="">-- Select Club --</option>
                                    <?php
                                    mysqli_data_seek($clubs_result, 0);
                                    while ($club = mysqli_fetch_assoc($clubs_result)):
                                        $sel = ($product['club_id'] == $club['club_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $club['club_id'] ?>" <?= $sel ?>>
                                            <?= htmlspecialchars($club['club_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="size_id">Size <span class="required">*</span></label>
                                <select id="size_id" name="size_id" required>
                                    <option value="">-- Select Size --</option>
                                    <?php
                                    mysqli_data_seek($sizes_result, 0);
                                    while ($size = mysqli_fetch_assoc($sizes_result)):
                                        $sel = ($product['size_id'] == $size['size_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $size['size_id'] ?>" <?= $sel ?>>
                                            <?= htmlspecialchars($size['size_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Kit -->
                        <div class="form-group">
                            <label for="kit_id">Kit <span class="required">*</span></label>
                            <select id="kit_id" name="kit_id" required>
                                <option value="">-- Select Kit --</option>
                                <?php
                                mysqli_data_seek($kits_result, 0);
                                while ($kit = mysqli_fetch_assoc($kits_result)):
                                    $sel = ($product['kit_id'] == $kit['kit_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $kit['kit_id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($kit['kit_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"
                                      rows="5"
                                      placeholder="Write a short product description..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>

                    </div><!-- /form-col left -->

                    <!-- RIGHT COLUMN -->
                    <div class="form-col">

                        <div class="form-section-title">Product Image</div>

                        <div class="form-group">
                            <label>
                                Upload Image
                                <span class="field-hint">(JPG, PNG, WEBP · max 2MB)</span>
                            </label>

                            <div class="image-upload-box" id="imageUploadBox">
                                <?php if (!empty($product['image'])): ?>
                                    <img id="imagePreview"
                                         src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                         alt="Current Image"
                                         class="image-preview">
                                    <div class="upload-placeholder hidden" id="uploadPlaceholder">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <p>Click or drag &amp; drop to upload</p>
                                        <span>Recommended: 800×800px</span>
                                    </div>
                                <?php else: ?>
                                    <img id="imagePreview" src="" alt="Preview" class="image-preview hidden">
                                    <div class="upload-placeholder" id="uploadPlaceholder">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <p>Click or drag &amp; drop to upload</p>
                                        <span>Recommended: 800×800px</span>
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="image" name="image"
                                       accept=".jpg,.jpeg,.png,.webp"
                                       class="image-file-input">
                            </div>

                            <!-- Image action buttons -->
                            <div class="image-actions">
                                <button type="button"
                                        class="btn-remove-image <?= empty($product['image']) ? 'hidden' : '' ?>"
                                        id="removeImage">
                                    <i class="fa-solid fa-xmark"></i> Remove Image
                                </button>
                                <?php if (!empty($product['image'])): ?>
                                    <span class="current-image-note" id="currentImageNote">
                                        <i class="fa-solid fa-circle-info"></i>
                                        Current image will be kept unless you upload a new one or remove it.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Summary Card -->
                        <div class="summary-card">
                            <div class="summary-title">
                                <i class="fa-solid fa-receipt"></i> Product Summary
                            </div>
                            <div class="summary-row">
                                <span>Name</span>
                                <span id="sum-name"><?= htmlspecialchars($product['product_name']) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Price</span>
                                <span id="sum-price">Rs. <?= htmlspecialchars($product['price']) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Stock</span>
                                <span id="sum-stock"><?= htmlspecialchars($product['stock']) ?> pcs</span>
                            </div>
                            <div class="summary-row">
                                <span>Club</span>
                                <span id="sum-club"><?= htmlspecialchars($product['club_name'] ?? '—') ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Size</span>
                                <span id="sum-size"><?= htmlspecialchars($product['size_name'] ?? '—') ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Kit</span>
                                <span id="sum-kit"><?= htmlspecialchars($product['kit_name'] ?? '—') ?></span>
                            </div>
                        </div>

                        <!-- Change indicator -->
                        <div class="changes-indicator hidden" id="changesIndicator">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            You have unsaved changes.
                        </div>

                    </div><!-- /form-col right -->

                </div><!-- /form-grid -->

                <!-- Actions -->
                <div class="form-actions">
                    <a href="all_products.php" class="btn-reset">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </div>

            </form>
        </div><!-- /form-card -->

    </div><!-- /main-content -->
</div><!-- /page-wrapper -->

<?php include 'footer.php'; ?>

<script src="../script/edit_product.js"></script>

</body>
</html>