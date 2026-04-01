<?php
// admin/auth_guard.php

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role'])    ||
    $_SESSION['role'] !== 'admin'
) {
    session_unset();
    session_destroy();
    header('Location: /jerseyflow-ecommerce/login.php?reason=unauthorized');
    exit;
}

// Extra safety: block soft-deleted or inactive admin accounts
// by re-checking the DB on every request
if (!isset($conn)) include(__DIR__ . '/connect.php');

$guard_id   = (int) $_SESSION['user_id'];
$guard_stmt = $conn->prepare(
    "SELECT role, status, is_deleted FROM users WHERE user_id = ? LIMIT 1"
);
$guard_stmt->bind_param('i', $guard_id);
$guard_stmt->execute();
$guard_row = $guard_stmt->get_result()->fetch_assoc();
$guard_stmt->close();

if (
    !$guard_row                          ||   // user doesn't exist
    $guard_row['role']       !== 'admin' ||   // no longer admin
    $guard_row['status']     !== 'active'||   // account deactivated
    $guard_row['is_deleted']  == 1            // soft deleted
) {
    session_unset();
    session_destroy();
    header('Location: /jerseyflow-ecommerce/login.php?reason=unauthorized');
    exit;
}