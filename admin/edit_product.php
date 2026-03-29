<?php
session_start();
include('connect.php');

// ── Guard: require a valid product ID ─────────────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: all_products.php');
    exit();
}

$product_id = intval($_GET['id']);

// ── Fetch existing product ─────────────────────────────────────────────────
$fetch_stmt = mysqli_prepare($conn,
    "SELECT p.*, c.club_name, s.size_name, k.kit_name
     FROM products p
     LEFT JOIN clubs c ON p.club_id = c.club_id
     LEFT JOIN sizes s ON p.size_id = s.size_id
     LEFT JOIN kits  k ON p.kit_id  = k.kit_id
     WHERE p.product_id = ?"
);
mysqli_stmt_bind_param($fetch_stmt, 'i', $product_id);
mysqli_stmt_execute($fetch_stmt);
$fetch_result = mysqli_stmt_get_result($fetch_stmt);
$product = mysqli_fetch_assoc($fetch_result);
mysqli_stmt_close($fetch_stmt);

if (!$product) {
    header('Location: all_products.php');
    exit();
}

// ── Fetch dropdowns ────────────────────────────────────────────────────────
$clubs_result = mysqli_query($conn, "SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
$sizes_result = mysqli_query($conn, "SELECT size_id, size_name FROM sizes ORDER BY sort_order ASC");
$kits_result  = mysqli_query($conn, "SELECT kit_id, kit_name FROM kits ORDER BY sort_order ASC");

$success = '';
$error   = '';

// ── Handle POST (update) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name = trim(mysqli_real_escape_string($conn, $_POST['product_name']));
    $price        = floatval($_POST['price']);
    $stock        = intval($_POST['stock']);
    $club_id      = intval($_POST['club_id']);
    $size_id      = intval($_POST['size_id']);
    $kit_id       = intval($_POST['kit_id']);
    $description  = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    // ── Validation ────────────────────────────────────────────────
    if (empty($product_name) || $price <= 0 || $stock < 0 || !$club_id || !$size_id || !$kit_id) {
        $error = 'Please fill in all required fields with valid values.';
    } else {

        $new_image = $product['image']; // keep existing by default

        // ── Remove image explicitly requested ──────────────────────
        if ($remove_image) {
            if (!empty($product['image'])) {
                $old_file = '../uploads/products/' . $product['image'];
                if (file_exists($old_file)) unlink($old_file);
            }
            $new_image = '';
        }

        // ── New image uploaded ─────────────────────────────────────
        if (!empty($_FILES['image']['name'])) {
            $upload_dir  = '../uploads/products/';
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size    = 2 * 1024 * 1024;

            $file_ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['image']['size'];
            $file_tmp  = $_FILES['image']['tmp_name'];

            if (!in_array($file_ext, $allowed_ext)) {
                $error = 'Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.';
            } elseif ($file_size > $max_size) {
                $error = 'Image size must be under 2MB.';
            } else {
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $new_filename = uniqid('product_', true) . '.' . $file_ext;
                $dest         = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $dest)) {
                    // Delete old image file
                    if (!empty($product['image'])) {
                        $old_file = $upload_dir . $product['image'];
                        if (file_exists($old_file)) unlink($old_file);
                    }
                    $new_image = $new_filename;
                } else {
                    $error = 'Failed to upload image. Check folder permissions.';
                }
            }
        }

        // ── UPDATE ────────────────────────────────────────────────
        if (empty($error)) {
            $upd_stmt = mysqli_prepare($conn,
                "UPDATE products
                 SET product_name = ?, price = ?, stock = ?,
                     club_id = ?, size_id = ?, kit_id = ?,
                     image = ?, description = ?
                 WHERE product_id = ?"
            );
            mysqli_stmt_bind_param($upd_stmt, 'sdiiiissi',
                $product_name, $price, $stock,
                $club_id, $size_id, $kit_id,
                $new_image, $description,
                $product_id
            );

            if (mysqli_stmt_execute($upd_stmt)) {
                $success = 'Product <strong>' . htmlspecialchars($product_name) . '</strong> updated successfully!';
                // Refresh $product so form shows updated values
                $product['product_name'] = $product_name;
                $product['price']        = $price;
                $product['stock']        = $stock;
                $product['club_id']      = $club_id;
                $product['size_id']      = $size_id;
                $product['kit_id']       = $kit_id;
                $product['image']        = $new_image;
                $product['description']  = $description;
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upd_stmt);
        }
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