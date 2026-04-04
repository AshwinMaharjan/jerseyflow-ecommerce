<?php
/**
 * JerseyFlow — Featured Products Section
 * File: featured_section.php
 *
 * Displays the 8 latest products added by admin.
 * Excludes special_type = 'worldcup_2026' and 'retro'.
 * Requires: $conn (MySQLi connection object)
 */

// --- DB QUERY ---
$featured_products = [];

$sql = "SELECT 
            p.product_id,
            p.product_name,
            p.price,
            p.description,
            p.stock,
            c.country_name,
            k.kit_name,
            cl.club_name,
            pi.image_path
        FROM products p
        LEFT JOIN countries c ON p.country_id = c.country_id
        LEFT JOIN kits k ON p.kit_id = k.kit_id
        LEFT JOIN clubs cl ON p.club_id = cl.club_id
        LEFT JOIN product_images pi 
            ON p.product_id = pi.product_id 
            AND pi.is_primary = 1
        WHERE p.special_type = 'standard'
           OR p.special_type IS NULL
        ORDER BY p.created_at DESC
        LIMIT 6";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $featured_products[] = $row;
    }
}
?>

<link rel="stylesheet" href="/jerseyflow-ecommerce/style/featured_section.css" />

<?php if (!empty($featured_products)): ?>

<section class="jf-featured" id="featured-products">

  <div class="jf-featured__container">

    <!-- ===== HEADER ===== -->
    <div class="jf-featured__header">

      <div class="jf-featured__header-left">
        <div class="jf-featured__eyebrow">
          <span class="jf-featured__eyebrow-line"></span>
          <span class="jf-featured__eyebrow-text">Latest Drops</span>
        </div>
        <h2 class="jf-featured__heading">
          Featured <span>Products</span>
        </h2>
      </div>

      <div class="jf-featured__header-right">
        <p class="jf-featured__desc">
          Freshly added to the store — the newest jerseys handpicked for every football fan.
        </p>
        <a href="/jerseyflow-ecommerce/jersey.php?type=standard" class="jf-featured__cta">
          View All Products
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

    </div>

    <!-- ===== PRODUCT GRID (4 rows × 2 cols = 8 cards) ===== -->
    <div class="jf-featured__grid">

      <?php foreach ($featured_products as $index => $p):

        // Display label — prefer club over country
        $label = !empty($p['club_name'])
                  ? $p['club_name']
                  : ($p['country_name'] ?? '');

        $img_src = !empty($p['image_path'])
    ? '/jerseyflow-ecommerce/uploads/products/' . htmlspecialchars($p['image_path'])
    : '/jerseyflow-ecommerce/images/products/placeholder.png';

        $is_new = ($index < 2); // Mark top 2 newest as "New"
      ?>

        <a
          href="product.php?id=<?= (int)$p['product_id'] ?>"
          class="jf-feat-card"
          style="--card-delay: <?= $index * 70 ?>ms"
        >
          <!-- IMAGE -->
          <div class="jf-feat-card__img-wrap">
            <img
              src="<?= $img_src ?>"
              alt="<?= htmlspecialchars($p['product_name']) ?>"
              loading="lazy"
              onerror="this.src='/jerseyflow-ecommerce/images/products/placeholder.png'"
            >

            <?php if ($is_new): ?>
              <span class="jf-feat-badge jf-feat-badge--new">New</span>
            <?php endif; ?>

            <?php if ((int)$p['stock'] === 0): ?>
              <span class="jf-feat-badge jf-feat-badge--out">Sold Out</span>
            <?php endif; ?>

            <!-- Hover overlay with view prompt -->
            <div class="jf-feat-card__hover-overlay">
              <span class="jf-feat-card__view-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                View Details
              </span>
            </div>
          </div>

          <!-- INFO -->
          <div class="jf-feat-card__info">
            <?php if (!empty($label)): ?>
              <span class="jf-feat-card__label"><?= htmlspecialchars($label) ?></span>
            <?php endif; ?>
            <h4 class="jf-feat-card__name"><?= htmlspecialchars($p['product_name']) ?></h4>
            <div class="jf-feat-card__foot">
              <span class="jf-feat-card__price">Rs <?= number_format($p['price']) ?></span>
              <?php if (!empty($p['kit_name'])): ?>
                <span class="jf-feat-card__kit"><?= htmlspecialchars($p['kit_name']) ?></span>
              <?php endif; ?>
            </div>
          </div>

        </a>

      <?php endforeach; ?>

    </div><!-- /.jf-featured__grid -->

  </div><!-- /.jf-featured__container -->

</section>

<?php else: ?>

  <section class="jf-featured jf-featured--empty">
    <div class="jf-featured__empty-state">
      <p>No featured products available yet. Check back soon!</p>
    </div>
  </section>

<?php endif; ?>

<script src="/jerseyflow-ecommerce/script/featured_section.js"></script>