<?php
/**
 * JerseyFlow Admin — User Actions Handler
 * File: user_actions.php
 *
 * Handles: block, activate, delete (soft), bulk actions.
 * Returns JSON for AJAX or redirects for standard POST.
 */

session_start();
require_once 'connect.php';
require_once 'user_logger.php';

// Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    respond(false, 'Unauthorized.', 403);
}

$is_ajax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action    = trim($_POST['action'] ?? '');
$user_id   = (int)($_POST['user_id'] ?? 0);
$bulk_ids  = $_POST['bulk_ids'] ?? '';           // comma-separated
$redirect  = $_POST['redirect'] ?? 'users.php';
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

// ── Validate redirect (whitelist) ─────────────────────────────
$allowed_redirects = ['users.php', 'view_user.php'];
$redirect_base = basename(parse_url($redirect, PHP_URL_PATH));
if (!in_array($redirect_base, $allowed_redirects)) $redirect = 'users.php';

// ── Sanitize bulk IDs ─────────────────────────────────────────
$bulk_array = [];
if ($bulk_ids !== '') {
    foreach (explode(',', $bulk_ids) as $bid) {
        $bid = (int)trim($bid);
        if ($bid > 0) $bulk_array[] = $bid;
    }
}

// ── Prevent admin from acting on themselves ───────────────────
$self_id = (int)$_SESSION['admin_id'];

function is_self_targeted(int $self, array $ids): bool {
    return in_array($self, $ids);
}

// ── Route ─────────────────────────────────────────────────────
switch ($action) {

    // ── Single: Block ─────────────────────────────────────────
    case 'block':
        if (!$user_id) fail('No user specified.');
        if ($user_id === $self_id) fail('You cannot block your own account.');

        $stmt = $conn->prepare("UPDATE users SET status='blocked', updated_at=NOW() WHERE user_id=? AND is_deleted=0");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity($conn, $user_id, 'Blocked', 'Admin blocked this account.', $ip);
            ok('User has been blocked.');
        }
        fail('Could not block user or user not found.');
        break;

    // ── Single: Activate ──────────────────────────────────────
    case 'activate':
        if (!$user_id) fail('No user specified.');
        if ($user_id === $self_id) fail('Your account is already active.');

        $stmt = $conn->prepare("UPDATE users SET status='active', updated_at=NOW() WHERE user_id=? AND is_deleted=0");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity($conn, $user_id, 'Activated', 'Admin activated this account.', $ip);
            ok('User has been activated.');
        }
        fail('Could not activate user or user not found.');
        break;

    // ── Single: Soft Delete ───────────────────────────────────
    case 'delete':
        if (!$user_id) fail('No user specified.');
        if ($user_id === $self_id) fail('You cannot delete your own account.');

        $stmt = $conn->prepare("UPDATE users SET is_deleted=1, updated_at=NOW() WHERE user_id=? AND is_deleted=0");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity($conn, $user_id, 'Deleted', 'Admin soft-deleted this account.', $ip);
            ok('User has been deleted.');
        }
        fail('Could not delete user or user not found.');
        break;

    // ── Bulk: Block ───────────────────────────────────────────
    case 'bulk_block':
        if (empty($bulk_array)) fail('No users selected.');
        $ids = array_diff($bulk_array, [$self_id]);
        if (empty($ids)) fail('Cannot block your own account.');

        [$placeholders, $bound] = build_in($ids);
        $stmt = $conn->prepare("UPDATE users SET status='blocked', updated_at=NOW() WHERE user_id IN ($placeholders) AND is_deleted=0");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$bound);
        $stmt->execute();
        $count = $stmt->affected_rows;
        foreach ($ids as $bid) log_activity($conn, $bid, 'Blocked', 'Admin bulk-blocked this account.', $ip);
        ok("$count user(s) blocked.");
        break;

    // ── Bulk: Activate ────────────────────────────────────────
    case 'bulk_activate':
        if (empty($bulk_array)) fail('No users selected.');
        $ids = array_diff($bulk_array, [$self_id]);

        [$placeholders, $bound] = build_in($ids);
        $stmt = $conn->prepare("UPDATE users SET status='active', updated_at=NOW() WHERE user_id IN ($placeholders) AND is_deleted=0");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$bound);
        $stmt->execute();
        $count = $stmt->affected_rows;
        foreach ($ids as $bid) log_activity($conn, $bid, 'Activated', 'Admin bulk-activated this account.', $ip);
        ok("$count user(s) activated.");
        break;

    // ── Bulk: Delete ──────────────────────────────────────────
    case 'bulk_delete':
        if (empty($bulk_array)) fail('No users selected.');
        $ids = array_diff($bulk_array, [$self_id]);
        if (empty($ids)) fail('Cannot delete your own account.');

        [$placeholders, $bound] = build_in($ids);
        $stmt = $conn->prepare("UPDATE users SET is_deleted=1, updated_at=NOW() WHERE user_id IN ($placeholders) AND is_deleted=0");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$bound);
        $stmt->execute();
        $count = $stmt->affected_rows;
        foreach ($ids as $bid) log_activity($conn, $bid, 'Deleted', 'Admin bulk-deleted this account.', $ip);
        ok("$count user(s) deleted.");
        break;

    default:
        fail('Unknown action.');
}

// ── Helpers ───────────────────────────────────────────────────
function build_in(array $ids): array {
    return [implode(',', array_fill(0, count($ids), '?')), $ids];
}

function ok(string $msg) {
    global $is_ajax, $redirect;
    if ($is_ajax) { echo json_encode(['success' => true, 'msg' => $msg]); exit; }
    $_SESSION['toast'] = ['type' => 'success', 'msg' => $msg];
    header("Location: $redirect");
    exit;
}

function fail(string $msg, int $code = 400) {
    global $is_ajax, $redirect;
    if ($is_ajax) { http_response_code($code); echo json_encode(['success' => false, 'msg' => $msg]); exit; }
    $_SESSION['toast'] = ['type' => 'error', 'msg' => $msg];
    header("Location: $redirect");
    exit;
}

function respond(bool $ok, string $msg, int $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'msg' => $msg]);
    exit;
}