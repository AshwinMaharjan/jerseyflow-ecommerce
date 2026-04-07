<?php
/**
 * cart.php — JerseyFlow
 * Shopping cart page with selective checkout
 */

session_start();
require_once 'connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=cart.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT
        c.cart_id,
        c.quantity,
        c.size,
        p.product_id,
        p.product_name,
        p.price,
        p.stock,
        p.special_type,
        cl.club_name,
        co.country_name,
        (SELECT pi.image_path FROM product_images pi
         WHERE pi.product_id = p.product_id AND pi.is_primary = 1
         LIMIT 1) AS primary_image,
        p.image AS fallback_image
     FROM cart c
     JOIN    products  p  ON p.product_id  = c.product_id
     LEFT JOIN clubs   cl ON cl.club_id    = p.club_id
     LEFT JOIN countries co ON co.country_id = p.country_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtotal = 0.0;
foreach ($cart_items as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

$type_labels = [
    'standard'       => 'Standard',
    'player_edition' => 'Player Edition',
    'limited'        => 'Limited Edition',
    'worldcup_2026'  => 'World Cup 2026',
    'retro'          => 'Retro',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Cart – JerseyFlow</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/fonts/barlow-condensed/barlow-condensed.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/cart.css">
    <link rel="stylesheet" href="../style/navbar.css">
</head>
<body>

    <?php include("users_navbar.php"); ?>
    <div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

<main class="cart-page">

    <div class="cart-header">
        <h1 class="cart-title">
            <i class="fa-solid fa-cart-shopping"></i>
            My Cart
        </h1>
        <?php if (!empty($cart_items)): ?>
            <button class="btn-clear-cart" id="clearCartBtn" title="Remove all items">
                <i class="fa-solid fa-trash"></i> Clear Cart
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($cart_items)): ?>
    <div class="cart-empty">
        <i class="fa-solid fa-cart-shopping empty-icon"></i>
        <h2>Your cart is empty</h2>
        <p>Looks like you haven't added any jerseys yet.</p>
        <a href="jersey.php" class="btn-shop">Browse Jerseys</a>
    </div>

    <?php else: ?>
    <div class="cart-layout">

        <!-- Cart items list -->
        <div>
            <!-- Select All bar -->
            <div class="select-all-bar">
                <label class="select-all-label">
                    <input type="checkbox" id="selectAllCheckbox" checked>
                    <span class="custom-checkbox"></span>
                    <span>Select All</span>
                </label>
                <span class="selected-count-label" id="selectedCountLabel">
                    <?= count($cart_items) ?> of <?= count($cart_items) ?> selected
                </span>
            </div>

            <div class="cart-items" id="cartItems">
                <?php foreach ($cart_items as $item): ?>
                    <?php
                        $img        = !empty($item['primary_image']) ? $item['primary_image'] : ($item['fallback_image'] ?? null);
                        $line_total = (float)$item['price'] * (int)$item['quantity'];
                        $affiliation = $item['club_name'] ?? $item['country_name'] ?? null;
                        $type_label  = $type_labels[$item['special_type']] ?? null;
                    ?>
                    <div class="cart-item"
                         id="cartItem-<?= $item['cart_id'] ?>"
                         data-cart-id="<?= $item['cart_id'] ?>"
                         data-price="<?= (float)$item['price'] ?>"
                         data-quantity="<?= (int)$item['quantity'] ?>">

                        <!-- Checkbox -->
                        <label class="item-checkbox-wrap">
                            <input
                                type="checkbox"
                                class="item-checkbox"
                                data-cart-id="<?= $item['cart_id'] ?>"
                                data-price="<?= (float)$item['price'] ?>"
                                checked
                            />
                            <span class="custom-checkbox"></span>
                        </label>

                        <!-- Image -->
                        <a href="product.php?id=<?= (int)$item['product_id'] ?>" class="item-img-wrap">
                            <?php if (!empty($img)): ?>
                                <img
                                    src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($img) ?>"
                                    alt="<?= htmlspecialchars($item['product_name']) ?>"
                                    loading="lazy"
                                    onerror="this.src='/jerseyflow-ecommerce/images/no_image.png'"
                                />
                            <?php else: ?>
                                <div class="item-no-img"><i class="fa-solid fa-shirt"></i></div>
                            <?php endif; ?>
                        </a>

                        <!-- Details -->
                        <div class="item-details">
                            <div class="item-top">
                                <div>
                                    <a href="product.php?id=<?= (int)$item['product_id'] ?>" class="item-name">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </a>
                                    <?php if ($affiliation): ?>
                                        <div class="item-club"><?= htmlspecialchars($affiliation) ?></div>
                                    <?php endif; ?>
                                    <div class="item-meta-row">
                                        <span class="item-badge item-size">
                                            <i class="fa-solid fa-ruler"></i> <?= htmlspecialchars($item['size']) ?>
                                        </span>
                                        <?php if ($type_label): ?>
                                            <span class="item-badge item-type"><?= htmlspecialchars($type_label) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="btn-remove" title="Remove item" data-cart-id="<?= $item['cart_id'] ?>">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <div class="item-bottom">
                                <div class="item-unit-price">
                                    Rs. <?= number_format((float)$item['price'], 2) ?> each
                                </div>

                                <div class="item-qty-wrap">
                                    <button class="qty-btn qty-dec" data-cart-id="<?= $item['cart_id'] ?>">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="qty-display" id="qty-<?= $item['cart_id'] ?>">
                                        <?= (int)$item['quantity'] ?>
                                    </span>
                                    <button class="qty-btn qty-inc" data-cart-id="<?= $item['cart_id'] ?>" data-stock="<?= (int)$item['stock'] ?>">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>

                                <div class="item-line-total" id="lineTotal-<?= $item['cart_id'] ?>">
                                    Rs. <?= number_format($line_total, 2) ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- /.cart-items wrapper -->

        <!-- Order summary sidebar -->
        <aside class="cart-summary">
            <h2 class="summary-title">Order Summary</h2>

            <div class="summary-rows">
                <div class="summary-row">
                    <span id="summaryItemLabel">
                        Subtotal (<?= count($cart_items) ?> <?= count($cart_items) === 1 ? 'item' : 'items' ?>)
                    </span>
                    <span id="summarySubtotal">Rs. <?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Delivery</span>
                    <span class="delivery-free">Free</span>
                </div>
            </div>

            <div class="summary-total">
                <span>Total</span>
                <span id="summaryTotal">Rs. <?= number_format($subtotal, 2) ?></span>
            </div>

            <!-- Hidden form posts selected cart IDs -->
            <form id="checkoutForm" action="checkout.php" method="POST">
                <input type="hidden" name="selected_cart_ids" id="selectedCartIdsInput" value="">
                <button type="submit" class="btn-checkout" id="checkoutBtn">
                    <i class="fa-solid fa-lock"></i> Proceed to Checkout
                </button>
            </form>

            <a href="/jerseyflow-ecommerce/jersey.php" class="btn-continue">
                <i class="fa-solid fa-arrow-left"></i> Continue Shopping
            </a>

            <!-- No selection warning -->
            <p class="no-selection-msg" id="noSelectionMsg" style="display:none;">
                <i class="fa-solid fa-circle-exclamation"></i>
                Please select at least one item.
            </p>
        </aside>

    </div>
    <?php endif; ?>

</main>

<?php include("../footer.php"); ?>
<script src="../script/cart.js"></script>
</body>
</html>