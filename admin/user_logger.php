<?php
/**
 * JerseyFlow — User Activity Logger
 * File: user_logger.php
 *
 * Requires table:
 *   CREATE TABLE user_logs (
 *     log_id      INT AUTO_INCREMENT PRIMARY KEY,
 *     user_id     INT NOT NULL,
 *     action      VARCHAR(50) NOT NULL,
 *     description TEXT,
 *     ip_address  VARCHAR(45),
 *     created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
 *     INDEX idx_user (user_id)
 *   );
 */

function log_activity(
    mysqli $conn,
    int    $user_id,
    string $action,
    string $description = '',
    string $ip = ''
): void {
    $stmt = $conn->prepare(
        "INSERT INTO user_logs (user_id, action, description, ip_address, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('isss', $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}