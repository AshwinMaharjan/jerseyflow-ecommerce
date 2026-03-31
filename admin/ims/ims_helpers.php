<?php
/**
 * JerseyFlow IMS — Shared Helpers
 * File: ims/ims_helpers.php
 *
 * Include this in every IMS page: require_once 'ims_helpers.php';
 */

// ── Stock Movement (PHP wrapper around stored procedure) ──────────────────
function ims_stock_move(
    mysqli $conn,
    int    $variant_id,
    int    $admin_id,
    string $type,
    int    $qty,
    string $reference = '',
    string $reason    = '',
    string $note      = ''
): array {
    $allowed = ['IN','OUT','ADJUST','RETURN','DAMAGE','TRANSFER'];
    if (!in_array($type, $allowed)) return ['ok' => false, 'msg' => 'Invalid movement type.'];
    if ($qty < 0) return ['ok' => false, 'msg' => 'Quantity must be non-negative.'];
    if ($type === 'ADJUST' && trim($reason) === '') return ['ok' => false, 'msg' => 'Reason is required for adjustments.'];

    $stmt = $conn->prepare("CALL safe_stock_movement(?,?,?,?,?,?,?,@res)");
    $stmt->bind_param('iisiiss', $variant_id, $admin_id, $type, $qty, $reference, $reason, $note);
    $stmt->execute();
    $stmt->close();

    $row = $conn->query("SELECT @res AS r")->fetch_assoc();
    $result = $row['r'] ?? 'ERROR:unknown';

    if (str_starts_with($result, 'OK:')) {
        $new_stock = (int)substr($result, 3);
        return ['ok' => true, 'new_stock' => $new_stock, 'msg' => 'Stock updated successfully.'];
    }
    return ['ok' => false, 'msg' => $result];
}

// ── Fetch unread notification count ──────────────────────────────────────
function ims_notif_count(mysqli $conn): int {
    $r = $conn->query("SELECT COUNT(*) FROM inventory_notifications WHERE is_read=0 AND is_dismissed=0");
    return (int)$r->fetch_row()[0];
}

// ── Dismiss a notification ────────────────────────────────────────────────
function ims_dismiss_notif(mysqli $conn, int $notif_id): void {
    $stmt = $conn->prepare("UPDATE inventory_notifications SET is_dismissed=1, is_read=1 WHERE notif_id=?");
    $stmt->bind_param('i', $notif_id);
    $stmt->execute();
    $stmt->close();
}

// ── Generate a SKU ────────────────────────────────────────────────────────
function ims_generate_sku(string $product_name, string $size, string $color): string {
    $pn  = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $product_name));
    $pn  = substr($pn, 0, 6);
    $clr = strtoupper(preg_replace('/[^A-Z]/i', '', $color));
    $clr = substr($clr, 0, 3);
    return $pn . '-' . strtoupper($size) . '-' . $clr . '-' . strtoupper(uniqid('', false));
}

// ── Badge HTML helpers ────────────────────────────────────────────────────
function stock_badge(int $stock, int $reorder): string {
    if ($stock === 0)            return '<span class="ims-badge ims-badge-out">Out of Stock</span>';
    if ($stock <= $reorder)      return '<span class="ims-badge ims-badge-low">Low Stock</span>';
    return '<span class="ims-badge ims-badge-ok">In Stock</span>';
}

function movement_badge(string $type): string {
    $map = [
        'IN'       => ['ims-badge-in',       'fa-arrow-down',    'Stock In'],
        'OUT'      => ['ims-badge-out-move',  'fa-arrow-up',      'Stock Out'],
        'ADJUST'   => ['ims-badge-adjust',    'fa-sliders',       'Adjust'],
        'RETURN'   => ['ims-badge-return',    'fa-rotate-left',   'Return'],
        'DAMAGE'   => ['ims-badge-damage',    'fa-triangle-exclamation', 'Damage'],
        'TRANSFER' => ['ims-badge-transfer',  'fa-right-left',    'Transfer'],
    ];
    [$cls, $icon, $label] = $map[$type] ?? ['ims-badge-adjust', 'fa-circle', $type];
    return "<span class=\"ims-badge $cls\"><i class=\"fa-solid $icon\"></i> $label</span>";
}

// ── Dashboard stats query ─────────────────────────────────────────────────
function ims_dashboard_stats(mysqli $conn): array {
    $q = $conn->query("
        SELECT
            COUNT(*)                                    AS total_variants,
            SUM(stock)                                  AS total_stock,
            SUM(stock = 0)                              AS out_of_stock,
            SUM(stock > 0 AND stock <= reorder_level)   AS low_stock,
            SUM(stock > reorder_level)                  AS healthy
        FROM product_variants WHERE is_active = 1
    ");
    return $q->fetch_assoc();
}

// ── Fast movers (top 5 by OUT movements in last 30 days) ──────────────────
function ims_fast_movers(mysqli $conn, int $limit = 5): array {
    $stmt = $conn->prepare("
        SELECT p.product_name, pv.size, pv.color, SUM(sm.quantity) AS units_sold
        FROM stock_movements sm
        JOIN product_variants pv ON pv.variant_id = sm.variant_id
        JOIN products p ON p.product_id = sm.product_id
        WHERE sm.movement_type = 'OUT'
          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY sm.variant_id
        ORDER BY units_sold DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ── Dead stock (no OUT in 90 days, stock > 0) ─────────────────────────────
function ims_dead_stock(mysqli $conn, int $days = 90): array {
    $stmt = $conn->prepare("
        SELECT p.product_name, pv.variant_id, pv.size, pv.color, pv.stock
        FROM product_variants pv
        JOIN products p ON p.product_id = pv.product_id
        WHERE pv.stock > 0 AND pv.is_active = 1
          AND pv.variant_id NOT IN (
              SELECT DISTINCT variant_id FROM stock_movements
              WHERE movement_type = 'OUT'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          )
        ORDER BY pv.stock DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}