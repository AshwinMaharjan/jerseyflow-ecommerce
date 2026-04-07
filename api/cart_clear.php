<?php
/**
 * api/cart_clear.php — JerseyFlow
 * Removes ALL items from the logged-in user's cart.
 *
 * Expects JSON body: {} (empty object is fine)
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

$user_id = (int)$_SESSION['user_id'];

// ── Delete all cart rows for this user ─────────────────────────────────────
$stmt = $conn->prepare(
    "DELETE FROM cart WHERE user_id = ?"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->close();

// affected_rows may be 0 if cart was already empty — still a success
echo json_encode(['success' => true, 'message' => 'Cart cleared.']);