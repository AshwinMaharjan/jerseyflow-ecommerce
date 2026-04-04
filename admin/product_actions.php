<?php
/**
 * JerseyFlow Admin — Product Actions
 * File: product_actions.php
 * Handles: delete (single + bulk)
 */

session_start();
require_once 'connect.php';
require_once 'auth_guard.php';
require_once 'user_logger.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$action   = trim($_POST['action']   ?? '');
$redirect = trim($_POST['redirect'] ?? 'all_products.php');

// Whitelist redirect
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $redirect)) {
    $redirect = 'all_products.php';
}

// ── Helper: delete one product ────────────────────────────────
function deleteProduct(mysqli $conn, int $id): bool
{
    // 1. Fetch all image files linked to this product
    $img_stmt = $conn->prepare(
        "SELECT image_path FROM product_images WHERE product_id = ?"
    );
    $img_stmt->bind_param('i', $id);
    $img_stmt->execute();
    $images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $img_stmt->close();

    // Also grab the legacy image column from products table
    $leg_stmt = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
    $leg_stmt->bind_param('i', $id);
    $leg_stmt->execute();
    $legacy = $leg_stmt->get_result()->fetch_assoc();
    $leg_stmt->close();

    // 2. Delete from product_images table
    $del_imgs = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
    $del_imgs->bind_param('i', $id);
    $del_imgs->execute();
    $del_imgs->close();

    // 3. Delete the product row
    $del_prod = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $del_prod->bind_param('i', $id);
    $del_prod->execute();
    $affected = $del_prod->affected_rows;
    $del_prod->close();

    if ($affected === 0) {
        return false; // Product didn't exist
    }

    // 4. Remove physical image files from disk
    $upload_dir = __DIR__ . '/../uploads/products/';

    foreach ($images as $img) {
        $file = $upload_dir . $img['image_path'];
        if (!empty($img['image_path']) && file_exists($file)) {
            @unlink($file);
        }
    }

    // Remove legacy image if it exists and isn't already in product_images
    if (!empty($legacy['image'])) {
        $legacy_file = $upload_dir . $legacy['image'];
        if (file_exists($legacy_file)) {
            @unlink($legacy_file);
        }
    }

    return true;
}

// ════════════════════════════════════════════════════════════════
// SINGLE DELETE
// ════════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $product_id = (int)($_POST['product_id'] ?? 0);

    if ($product_id <= 0) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Invalid product ID.'];
        header("Location: $redirect");
        exit;
    }

    // Grab name before deleting (for the toast message)
    $name_stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $name_stmt->bind_param('i', $product_id);
    $name_stmt->execute();
    $name_row = $name_stmt->get_result()->fetch_assoc();
    $name_stmt->close();

    if (!$name_row) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Product not found.'];
        header("Location: $redirect");
        exit;
    }

    $product_name = $name_row['product_name'];

    if (deleteProduct($conn, $product_id)) {
        // Log the action
        if (function_exists('log_user_action')) {
            log_user_action($conn, "Deleted product: $product_name (ID: $product_id)");
        }
        $_SESSION['toast'] = [
            'type' => 'success',
            'msg'  => "\"$product_name\" has been deleted successfully.",
        ];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => "Failed to delete \"$product_name\"."];
    }

    header("Location: $redirect");
    exit;
}

// ════════════════════════════════════════════════════════════════
// BULK DELETE
// ════════════════════════════════════════════════════════════════
if ($action === 'bulk_delete' || ($action === 'delete' && !empty($_POST['bulk_ids']))) {
    $raw_ids = trim($_POST['bulk_ids'] ?? '');

    if (empty($raw_ids)) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'No products selected.'];
        header("Location: $redirect");
        exit;
    }

    // Sanitise: keep only integers
    $ids = array_filter(
        array_map('intval', explode(',', $raw_ids)),
        fn($id) => $id > 0
    );

    if (empty($ids)) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'No valid product IDs provided.'];
        header("Location: $redirect");
        exit;
    }

    $deleted = 0;
    foreach ($ids as $id) {
        if (deleteProduct($conn, $id)) {
            $deleted++;
        }
    }

    if (function_exists('log_user_action')) {
        log_user_action($conn, "Bulk deleted $deleted product(s). IDs: " . implode(', ', $ids));
    }

    $_SESSION['toast'] = [
        'type' => $deleted > 0 ? 'success' : 'error',
        'msg'  => $deleted > 0
            ? "$deleted product(s) deleted successfully."
            : 'No products were deleted.',
    ];

    header("Location: $redirect");
    exit;
}

// ════════════════════════════════════════════════════════════════
// UNKNOWN ACTION
// ════════════════════════════════════════════════════════════════
$_SESSION['toast'] = ['type' => 'error', 'msg' => 'Unknown action.'];
header("Location: $redirect");
exit;