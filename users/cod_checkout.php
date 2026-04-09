<?php
session_start();
require_once 'connect.php';

/* ══════════════════════════════════════════════════════
   SECURITY — must be logged in
   ══════════════════════════════════════════════════════ */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$error_message = '';
$order_id = null;

/* ══════════════════════════════════════════════════════
   1. VALIDATE POST DATA (FOR COD)
   ══════════════════════════════════════════════════════ */
$selected_ids = [];
$address_id   = (int)($_POST['address_id'] ?? 0);
$total_amount = (float)($_POST['total_amount'] ?? 0);

if (!empty($_POST['selected_cart_ids'])) {
    foreach (explode(',', $_POST['selected_cart_ids']) as $id) {
        $id = (int) trim($id);
        if ($id > 0) {
            $selected_ids[] = $id;
        }
    }
}

if (empty($selected_ids) || $address_id <= 0) {
    $error_message = "Invalid or missing order data.";
}

/* ══════════════════════════════════════════════════════
   2. CREATE ORDER IN DATABASE (transaction-safe)
   ══════════════════════════════════════════════════════ */
if (empty($error_message)) {

    $conn->begin_transaction();

    try {

        /* ── 2a. Fetch cart items ── */
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $types        = 'i' . str_repeat('i', count($selected_ids));
        $params       = array_merge([$user_id], $selected_ids);

        $cart_sql = "
            SELECT c.cart_id, c.product_id, c.variant_id, c.quantity, p.price
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?
              AND c.cart_id IN ($placeholders)
        ";

        $cart_stmt = $conn->prepare($cart_sql);
        $cart_stmt->bind_param($types, ...$params);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        $cart_rows = [];
        $db_total  = 0.00;
        while ($row = $cart_result->fetch_assoc()) {
            $row['subtotal'] = (float)$row['price'] * (int)$row['quantity'];
            $db_total       += $row['subtotal'];
            $cart_rows[]     = $row;
        }
        $cart_stmt->close();

        if (empty($cart_rows)) {
            throw new Exception("Cart items not found. They may have already been ordered.");
        }

        /* ── 2b. Get COD payment method ID ── */
        $method_name = 'cod';
        $stmt = $conn->prepare("SELECT method_id FROM payment_methods WHERE method_name = ? LIMIT 1");
        $stmt->bind_param("s", $method_name);
        $stmt->execute();
        $stmt->bind_result($method_id);
        $stmt->fetch();
        $stmt->close();

        if (!$method_id) {
            throw new Exception("Cash on Delivery payment method not found in database.");
        }

        /* ── 2c. Insert into orders table ── */
        $order_stmt = $conn->prepare("
            INSERT INTO orders
                (user_id, address_id, total_amount,
                 method_id, payment_status,
                 order_status, created_at)
            VALUES
                (?, ?, ?,
                 ?, 'pending',
                 'pending', NOW())
        ");

        $order_stmt->bind_param(
            'iidi',
            $user_id,
            $address_id,
            $db_total,
            $method_id
        );

        $order_stmt->execute();
        $order_id = $conn->insert_id;
        $order_stmt->close();

        if (!$order_id) {
            throw new Exception("Failed to create order record.");
        }

        /* ── 2d. Insert into payments table ── */
        $payment_stmt = $conn->prepare("
            INSERT INTO payments
                (order_id, amount, payment_status, gateway, transaction_id,
                 failure_reason, paid_at, created_at)
            VALUES
                (?, ?, 'pending', 'cod', NULL,
                 NULL, NULL, NOW())
        ");
        $payment_stmt->bind_param('id', $order_id, $db_total);
        $payment_stmt->execute();
        $payment_stmt->close();

        /* ── 2e. Insert each cart item into order_items ── */
        $item_stmt = $conn->prepare("
            INSERT INTO order_items
                (order_id, product_id, variant_id, quantity, unit_price, subtotal)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        foreach ($cart_rows as $item) {
            $variant_id = $item['variant_id'] ?? null;
            $item_stmt->bind_param(
                'iiiddd',
                $order_id,
                $item['product_id'],
                $variant_id,
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            );
            $item_stmt->execute();
        }
        $item_stmt->close();

        /* ── 2f. Deduct stock ── */
        $stock_stmt = $conn->prepare("
            UPDATE products
            SET stock = stock - ?
            WHERE product_id = ?
              AND stock >= ?
        ");

        foreach ($cart_rows as $item) {
            $stock_stmt->bind_param('iii', $item['quantity'], $item['product_id'], $item['quantity']);
            $stock_stmt->execute();

            if ($stock_stmt->affected_rows === 0) {
                throw new Exception(
                    "Insufficient stock for product ID {$item['product_id']}."
                );
            }
        }
        $stock_stmt->close();

        /* ── 2g. Remove purchased items from cart ── */
        $del_placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $del_types        = 'i' . str_repeat('i', count($selected_ids));
        $del_params       = array_merge([$user_id], $selected_ids);

        $del_stmt = $conn->prepare("
            DELETE FROM cart
            WHERE user_id = ?
              AND cart_id IN ($del_placeholders)
        ");
        $del_stmt->bind_param($del_types, ...$del_params);
        $del_stmt->execute();
        $del_stmt->close();

        /* ── 2h. Commit ── */
        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Order processing failed: " . $e->getMessage();
    }
}

/* ══════════════════════════════════════════════════════
   3. REDIRECT
   ══════════════════════════════════════════════════════ */
if (!empty($error_message)) {
    $_SESSION['cod_error'] = $error_message;
    header('Location: checkout.php?error=cod');
    exit();
}

header("Location: invoice.php?order_id={$order_id}");
exit();