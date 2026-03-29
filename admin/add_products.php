<?php
session_start();
include('connect.php');

// ── Fetch dropdown data ────────────────────────────────────────────────────
$clubs_result     = mysqli_query($conn, "SELECT club_id, club_name FROM clubs ORDER BY club_name ASC");
$sizes_result     = mysqli_query($conn, "SELECT size_id, size_name FROM sizes ORDER BY sort_order ASC");
$kits_result      = mysqli_query($conn, "SELECT kit_id, kit_name FROM kits ORDER BY sort_order ASC");
$countries_result = mysqli_query($conn, "SELECT country_id, country_name FROM countries ORDER BY country_name ASC");

$success = '';
$error   = '';

// ── Process POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Raw inputs
    $product_name = trim($_POST['product_name'] ?? '');
    $raw_price    = $_POST['price']      ?? '';
    $raw_stock    = $_POST['stock']      ?? '';
    $club_id      = intval($_POST['club_id']     ?? 0);
    $country_id   = intval($_POST['country_id']  ?? 0);
    $size_id      = intval($_POST['size_id']     ?? 0);
    $kit_id       = intval($_POST['kit_id']      ?? 0);
    $description  = trim($_POST['description']   ?? '');
    $image_path   = '';

    // ── Validate: product name ─────────────────────────────────────
    if (empty($product_name) || strlen($product_name) > 255) {
        $error = 'Product name is required and must be under 255 characters.';
    }

    // ── Validate: price (numeric, 500–20,000) ──────────────────────
    elseif (!is_numeric($raw_price) || ($price = floatval($raw_price)) < 500 || $price > 20000) {
        $error = 'Price must be a number between Rs. 500 and Rs. 20,000.';
    }

    // ── Validate: stock (non-negative integer, 0–500) ──────────────
    elseif (
        !preg_match('/^\d+$/', trim($raw_stock)) ||
        ($stock = intval($raw_stock)) < 0 ||
        $stock > 500
    ) {
        $error = 'Stock must be a whole number between 0 and 500.';
    }

    // ── Validate: club OR country (at least one required) ──────────
    elseif (!$club_id && !$country_id) {
        $error = 'Please select at least one — a Club or a Country.';
    }

    // ── Validate: size & kit ───────────────────────────────────────
    elseif (!$size_id) {
        $error = 'Please select a Size.';
    }

    elseif (!$kit_id) {
        $error = 'Please select a Kit.';
    }

    elseif (empty($_FILES['image']['name'])) {
    $error = 'Product image is required.';
}

    else {
        // ── Image upload ───────────────────────────────────────────
        if (!empty($_FILES['image']['name'])) {
            $upload_dir   = '../uploads/products/';
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size     = 2 * 1024 * 1024; // 2 MB

            $file_ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['image']['size'];
            $file_tmp  = $_FILES['image']['tmp_name'];

            // Verify actual MIME type, not just extension
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                $error = 'Invalid image format. Allowed: JPG, PNG, WEBP.';
            } elseif ($file_size > $max_size) {
                $error = 'Image must be under 2 MB.';
            } else {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $new_filename = uniqid('product_', true) . '.' . $file_ext;
                $dest         = $upload_dir . $new_filename;

                if (!move_uploaded_file($file_tmp, $dest)) {
                    $error = 'Failed to upload image. Check folder permissions.';
                } else {
                    $image_path = $new_filename;
                }
            }
        }

        // ── Insert into DB ─────────────────────────────────────────
        if (empty($error)) {
            // NULL-safe: 0 becomes NULL for optional FK columns
            $club_id_val    = $club_id    ?: null;
            $country_id_val = $country_id ?: null;

            $stmt = mysqli_prepare($conn,
                "INSERT INTO products
                    (product_name, price, stock, club_id, country_id, size_id, kit_id, image, description, created_at)
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
                $image_path,
                $description
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = 'Product <strong>' . htmlspecialchars($product_name) . '</strong> added successfully!';
                $_POST   = []; // clear form
            } else {
                $errno = mysqli_errno($conn);
                // MySQL error 3819 = CHECK constraint violated
                if ($errno === 3819) {
                    $error = 'A value was rejected by the database. Ensure price is Rs. 500–20,000 and stock is 0–500.';
                } else {
                    $error = 'A database error occurred. Please try again.';
                    error_log('[add_products] DB error ' . $errno . ': ' . mysqli_error($conn));
                }
            }
            mysqli_stmt_close($stmt);
        }
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

                        </div><!-- /form-row-2 price-stock -->

                        <!-- Club & Country (at least one required) -->
                        <div class="form-row-2">

                            <div class="form-group">
                                <label for="club_id">
                                    Club
                                    <span class="field-hint">(or select a country)</span>
                                </label>
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
                            </div>

                            <div class="form-group">
                                <label for="country_id">
                                    Country
                                    <span class="field-hint">(or select a club)</span>
                                </label>
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
                                <!-- Error shown below country (right cell) -->
                                <span class="field-error" id="club-country-error"></span>
                            </div>

                        </div><!-- /form-row-2 club-country -->

                        <!-- Size & Kit -->
                        <div class="form-row-2">

                            <div class="form-group">
                                <label for="size_id">
                                    Size <span class="required">*</span>
                                </label>
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
                            </div>

                            <div class="form-group">
                                <label for="kit_id">
                                    Kit <span class="required">*</span>
                                </label>
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
                            </div>

                        </div><!-- /form-row-2 size-kit -->

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

                        <!-- Image Upload -->
                        <div class="form-group">
                            <label>
                                Upload Image
                                <span class="field-hint">(JPG, PNG, WEBP · max 2 MB)</span>
                            </label>
                            <div class="image-upload-box" id="imageUploadBox">
                                <img id="imagePreview" src="" alt="Preview"
                                     class="image-preview hidden">
                                <div class="upload-placeholder" id="uploadPlaceholder">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <p>Click or drag &amp; drop to upload</p>
                                    <span>Recommended: 800×800 px</span>
                                </div>
                                <input type="file" id="image" name="image"
                                       accept=".jpg,.jpeg,.png,.webp"
                                       class="image-file-input"
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