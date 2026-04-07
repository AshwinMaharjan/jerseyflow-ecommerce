<?php
/**
 * api/cart_remove.php — JerseyFlow
 * Removes a single item from the logged-in user's cart.
 *
 * Expects JSON body: { "cart_id": int }
 * Returns JSON:      { "success": bool, "message": string }
 */

session_start();
require_once '../connect.php';

header('Content-Type: application/json');

// ── Auth guard ─────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// ── Only accept AJAX POST ──────────────────────────────────────────────────
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// ── Parse JSON body ────────────────────────────────────────────────────────
$data    = json_decode(file_get_contents('php://input'), true);
$cart_id = isset($data['cart_id']) ? (int)$data['cart_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

// ── Basic validation ───────────────────────────────────────────────────────
if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
    exit;
}

// ── Delete (user_id in WHERE prevents deleting another user's item) ────────
$stmt = $conn->prepare(
    "DELETE FROM cart WHERE cart_id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $cart_id, $user_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode(['success' => true, 'message' => 'Item removed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
}