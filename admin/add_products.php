<?php
session_start();
include('connect.php');
require_once 'auth_guard.php';


// ── Fetch dropdown data ─────────────────────────────────────────
$clubs_result     = mysqli_query($conn, "SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
$sizes_result     = mysqli_query($conn, "SELECT size_id, size_name FROM sizes ORDER BY sort_order ASC");
$kits_result      = mysqli_query($conn, "SELECT kit_id, kit_name FROM kits ORDER BY sort_order ASC");
$countries_result = mysqli_query($conn, "SELECT country_id, country_name FROM countries ORDER BY country_name ASC");

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name = trim($_POST['product_name'] ?? '');
    $raw_price    = $_POST['price'] ?? '';
    $raw_stock    = $_POST['stock'] ?? '';
    $club_id      = intval($_POST['club_id'] ?? 0);
    $country_id   = intval($_POST['country_id'] ?? 0);
    $size_id      = intval($_POST['size_id'] ?? 0);
    $kit_id       = intval($_POST['kit_id'] ?? 0);
    $description  = trim($_POST['description'] ?? '');
    $special_type = $_POST['special_type'] ?? null;

    // ── EXPLICITLY ASSIGN $price and $stock BEFORE validation ────
    // This fixes the scoping bug where $price/$stock were only assigned
    // inside the elseif condition (when validation FAILED), meaning they
    // were undefined when validation PASSED.
    $price = is_numeric($raw_price) ? floatval($raw_price) : 0;
    $stock = preg_match('/^\d+$/', trim($raw_stock)) ? intval($raw_stock) : -1;

    // ── VALIDATION ───────────────────────────────────────────────

    if (empty($product_name) || strlen($product_name) > 255) {
        $error = 'Product name is required and must be under 255 characters.';
    }

    elseif ($price < 500 || $price > 20000) {
        $error = 'Price must be between Rs. 500 and Rs. 20,000.';
    }

    elseif ($stock < 0 || $stock > 500) {
        $error = 'Stock must be a whole number between 0 and 500.';
    }

    elseif (!$club_id && !$country_id) {
        $error = 'Select at least one: Club or Country.';
    }

    elseif ($club_id && $country_id) {
        $error = 'Select either Club OR Country, not both.';
    }

    elseif (!$size_id) {
        $error = 'Select a size.';
    }

    elseif (!$kit_id) {
        $error = 'Select a kit.';
    }


    // ── IMAGE UPLOAD ─────────────────────────────────────────────
    $uploaded_images = [];

    if (empty($error)) {

        $file_count = count($_FILES['images']['name']);

        if ($file_count < 1) {
            $error = 'At least one product image is required.';
        }
        elseif ($file_count > 4) {
            $error = 'You can upload maximum 4 images only.';
        } else {

            $upload_dir   = '../uploads/products/';
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size     = 5 * 1024 * 1024;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['images']['name'] as $index => $fname) {

                if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
                    $error = 'Upload error on file: ' . htmlspecialchars($fname);
                    break;
                }

                $file_tmp  = $_FILES['images']['tmp_name'][$index];
                $file_size = $_FILES['images']['size'][$index];

                if (!is_uploaded_file($file_tmp)) {
                    $error = 'Invalid file upload on image ' . ($index + 1) . '.';
                    break;
                }

                $file_ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);

                if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                    $error = 'Invalid image format for file: ' . htmlspecialchars($fname);
                    break;
                }

                if ($file_size > $max_size) {
                    $error = 'Image "' . htmlspecialchars($fname) . '" exceeds 5MB limit.';
                    break;
                }

                $new_filename = uniqid('product_', true) . '.' . $file_ext;
                $dest = $upload_dir . $new_filename;

                if (!move_uploaded_file($file_tmp, $dest)) {
                    $error = 'Upload failed for: ' . htmlspecialchars($fname);
                    break;
                }

                $uploaded_images[] = [
                    'path'       => $new_filename,
                    'is_primary' => ($index === 0) ? 1 : 0
                ];
            }
        }
    }

    // ── INSERT PRODUCT ───────────────────────────────────────────
    if (empty($error)) {

        $club_id_val    = $club_id ?: null;
        $country_id_val = $country_id ?: null;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO products
            (product_name, price, stock, club_id, country_id, size_id, kit_id, special_type, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        mysqli_stmt_bind_param($stmt, 'sdiiiiiss',
            $product_name,
            $price,
            $stock,
            $club_id_val,
            $country_id_val,
            $size_id,
            $kit_id,
            $special_type,
            $description
        );

        if (mysqli_stmt_execute($stmt)) {

            $new_product_id = mysqli_insert_id($conn);

            // ── INSERT INTO product_images ────────────────────────
            $img_stmt = $conn->prepare(
                "INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)"
            );
            foreach ($uploaded_images as $img) {
                $img_stmt->bind_param('isi', $new_product_id, $img['path'], $img['is_primary']);
                $img_stmt->execute();
            }
            $img_stmt->close();

            // ── GET SIZE LABEL ────────────────────────────────────
            $size_label = 'M';
            $sr = $conn->prepare("SELECT size_name FROM sizes WHERE size_id = ?");
            $sr->bind_param('i', $size_id);
            $sr->execute();
            $res = $sr->get_result()->fetch_assoc();
            if ($res) $size_label = $res['size_name'];
            $sr->close();

            // ── GENERATE SKU ──────────────────────────────────────
            $sku = 'JF-' . str_pad($new_product_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper($size_label) . '-DEF';

            // ── CREATE VARIANT (price NOW correctly stored) ───────
            $vstmt = $conn->prepare("
                INSERT INTO product_variants
                    (product_id, size, color, sku, stock, reorder_level, reorder_qty, price)
                VALUES (?, ?, 'Default', ?, ?, 5, 20, ?)
            ");

            // $price is guaranteed to be set correctly here
            $vstmt->bind_param(
                'issid',
                $new_product_id,
                $size_label,
                $sku,
                $stock,
                $price          // ← now always the validated floatval from $raw_price
            );

            if (!$vstmt->execute()) {
                die("Variant insert failed: " . $vstmt->error);
            }

            $variant_id = $conn->insert_id;
            $vstmt->close();

            // ── STOCK MOVEMENT ────────────────────────────────────
            if ($stock > 0 && $variant_id) {
                require_once 'ims/ims_helpers.php';
                $admin_id = $_SESSION['admin_id'] ?? 0;

                ims_stock_move(
                    $conn,
                    $variant_id,
                    $admin_id,
                    'IN',
                    $stock,
                    '',
                    'Initial stock on product creation',
                    ''
                );
            }

            $success = 'Product <strong>' . htmlspecialchars($product_name) . '</strong> added successfully!';
            $_POST = [];

        } else {
            $error = 'Database error.';
            error_log(mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/add_products.css">
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
                    <i class="fa-solid fa-plus"></i> Add New Product
                </h1>
                <p class="page-subtitle">Fill in the details below to add a product to the catalog.</p>
            </div>
            <a href="all_products.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <form method="POST" action="" enctype="multipart/form-data" id="addProductForm" novalidate>

                <div class="form-grid">

                    <!-- ── LEFT COLUMN ──────────────────────────────────── -->
                    <div class="form-col">

                        <div class="form-section-title">Basic Information</div>

                        <!-- Product Name -->
                        <div class="form-group">
                            <label for="product_name">
                                Product Name <span class="required">*</span>
                            </label>
                            <input type="text" id="product_name" name="product_name"
                                   placeholder="e.g. Manchester United Home Jersey"
                                   maxlength="255"
                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                                   required>
                        </div>

                        <!-- Price & Stock -->
                        <div class="form-row-2">

                            <div class="form-group">
                                <label for="price">
                                    Price (Rs.) <span class="required">*</span>
                                </label>
                                <div class="input-prefix">
                                    <span class="prefix-icon">Rs.</span>
                                    <input type="number" id="price" name="price"
                                           placeholder="500 – 20,000"
                                           step="0.01" min="500" max="20000"
                                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                           required>
                                </div>
                                <span class="field-error" id="price-error"></span>
                            </div>

                            <div class="form-group">
                                <label for="stock">
                                    Stock Quantity <span class="required">*</span>
                                </label>
                                <input type="number" id="stock" name="stock"
                                       placeholder="0 – 500"
                                       min="0" max="500" step="1"
                                       value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>"
                                       required>
                                <span class="field-error" id="stock-error"></span>
                            </div>

                        </div>

                        <!-- Club & Country -->
                        <div class="form-row-2">

                            <div class="form-group">
                                <label for="club_id">
                                    Club
                                    <span class="field-hint">(or select a country)</span>
                                </label>
                                <div class="select-wrapper">
                                    <select id="club_id" name="club_id">
                                        <option value="">-- Select Club --</option>
                                        <?php
                                        mysqli_data_seek($clubs_result, 0);
                                        while ($club = mysqli_fetch_assoc($clubs_result)):
                                            $sel = (isset($_POST['club_id']) && $_POST['club_id'] == $club['club_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $club['club_id'] ?>" <?= $sel ?>>
                                                <?= htmlspecialchars($club['club_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="country_id">
                                    Country
                                    <span class="field-hint">(or select a club)</span>
                                </label>
                                <div class="select-wrapper">
                                    <select id="country_id" name="country_id">
                                        <option value="">-- Select Country --</option>
                                        <?php
                                        mysqli_data_seek($countries_result, 0);
                                        while ($country = mysqli_fetch_assoc($countries_result)):
                                            $sel = (isset($_POST['country_id']) && $_POST['country_id'] == $country['country_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $country['country_id'] ?>" <?= $sel ?>>
                                                <?= htmlspecialchars($country['country_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                    <span class="field-error" id="club-country-error"></span>
                                </div>
                            </div>

                        </div>

                        <!-- Size & Kit -->
                        <div class="form-row-2">

                            <div class="form-group">
                                <label for="size_id">
                                    Size <span class="required">*</span>
                                </label>
                                <div class="select-wrapper">
                                    <select id="size_id" name="size_id" required>
                                        <option value="">-- Select Size --</option>
                                        <?php
                                        mysqli_data_seek($sizes_result, 0);
                                        while ($size = mysqli_fetch_assoc($sizes_result)):
                                            $sel = (isset($_POST['size_id']) && $_POST['size_id'] == $size['size_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $size['size_id'] ?>" <?= $sel ?>>
                                                <?= htmlspecialchars($size['size_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="kit_id">
                                    Kit <span class="required">*</span>
                                </label>
                                <div class="select-wrapper">
                                    <select id="kit_id" name="kit_id" required>
                                        <option value="">-- Select Kit --</option>
                                        <?php
                                        mysqli_data_seek($kits_result, 0);
                                        while ($kit = mysqli_fetch_assoc($kits_result)):
                                            $sel = (isset($_POST['kit_id']) && $_POST['kit_id'] == $kit['kit_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $kit['kit_id'] ?>" <?= $sel ?>>
                                                <?= htmlspecialchars($kit['kit_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>

                        </div>

                        <!-- Special Category -->
                        <div class="form-group">
                            <label for="special_type">
                                Special Category
                                <span class="field-hint">(optional)</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="special_type" name="special_type">
                                    <option value="">-- None --</option>
                                    <option value="standard"
                                        <?= (isset($_POST['special_type']) && $_POST['special_type'] === 'standard') ? 'selected' : '' ?>>
                                        Standard Jersey
                                    </option>
                                    <option value="player_edition"
                                        <?= (isset($_POST['special_type']) && $_POST['special_type'] === 'player_edition') ? 'selected' : '' ?>>
                                        Player Edition Jersey
                                    </option>
                                    <option value="limited"
                                        <?= (isset($_POST['special_type']) && $_POST['special_type'] === 'limited') ? 'selected' : '' ?>>
                                        Limited Jersey
                                    </option>
                                    <option value="retro"
                                        <?= (isset($_POST['special_type']) && $_POST['special_type'] === 'retro') ? 'selected' : '' ?>>
                                        Retro Jersey
                                    </option>
                                    <option value="worldcup_2026"
                                        <?= (isset($_POST['special_type']) && $_POST['special_type'] === 'worldcup_2026') ? 'selected' : '' ?>>
                                        World Cup 2026 Jersey
                                    </option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-icon"></i>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"
                                      rows="5"
                                      placeholder="Write a short product description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                    </div><!-- /form-col left -->

                    <!-- ── RIGHT COLUMN ─────────────────────────────────── -->
                    <div class="form-col">

                        <div class="form-section-title">Product Image</div>

                        <div class="form-group">
                            <label>
                                Upload Image
                                <span class="field-hint">(JPG, PNG, WEBP · max 5 MB each · first image = primary)</span>
                            </label>
                            <div class="image-upload-box" id="imageUploadBox">
                                <img id="imagePreview" src="" alt="Preview"
                                     class="image-preview hidden">
                                <div class="upload-placeholder" id="uploadPlaceholder">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <p>Click or drag &amp; drop to upload</p>
                                    <span>Recommended: 800×800 px</span>
                                </div>
                                <input type="file" id="images" name="images[]"
                                       accept=".jpg,.jpeg,.png,.webp"
                                       class="image-file-input"
                                       multiple
                                       required>
                            </div>
                            <button type="button" class="btn-remove-image hidden" id="removeImage">
                                <i class="fa-solid fa-xmark"></i> Remove Image
                            </button>
                        </div>

                        <!-- Summary Card -->
                        <div class="summary-card">
                            <div class="summary-title">
                                <i class="fa-solid fa-receipt"></i> Product Summary
                            </div>
                            <div class="summary-row">
                                <span>Name</span>
                                <span id="sum-name">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Price</span>
                                <span id="sum-price">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Stock</span>
                                <span id="sum-stock">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Club</span>
                                <span id="sum-club">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Country</span>
                                <span id="sum-country">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Size</span>
                                <span id="sum-size">—</span>
                            </div>
                            <div class="summary-row">
                                <span>Kit</span>
                                <span id="sum-kit">—</span>
                            </div>
                        </div>

                    </div><!-- /form-col right -->

                </div><!-- /form-grid -->

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn-reset" id="resetBtn">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i> Save Product
                    </button>
                </div>

            </form>
        </div><!-- /form-card -->

    </div><!-- /main-content -->
</div><!-- /page-wrapper -->

<?php include 'footer.php'; ?>

<script src="../script/add_products.js"></script>

</body>
</html>