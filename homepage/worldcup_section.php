<?php
/**
 * JerseyFlow — FIFA World Cup 2026 Section
 * File: worldcup_section.php
 *
 * Fetches products from DB where special_type = 'worldcup_2026'
 * Requires: $conn (MySQLi connection object)
 */

// --- DB QUERY ---

$wc_products = [];

$sql = "SELECT 
            p.product_id,
            p.product_name,
            p.price,
            p.description,
            p.stock,
            c.country_name,
            k.kit_name,
            pi.image_path
        FROM products p
        LEFT JOIN countries c ON p.country_id = c.country_id
        LEFT JOIN kits k ON p.kit_id = k.kit_id
        LEFT JOIN product_images pi 
            ON p.product_id = pi.product_id 
            AND pi.is_primary = 1
        WHERE p.special_type = 'worldcup_2026'
        ORDER BY p.created_at DESC
        LIMIT 8";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $wc_products[] = $row;
    }
}
?>

<link rel="stylesheet" href="/jerseyflow-ecommerce/style/worldcup_section.css" />

<?php if (!empty($wc_products)): ?>

<section class="jf-worldcup" id="worldcup-2026">

  <div class="jf-worldcup__container">

    <!-- ===== LEFT PANEL ===== -->
    <div class="jf-worldcup__left">

      <div class="jf-worldcup__eyebrow">
        <span class="jf-eyebrow__line"></span>
        <span class="jf-eyebrow__text">FIFA 2026</span>
      </div>

      <h2 class="jf-worldcup__heading">
        World Cup<br><span>Collection</span>
      </h2>

      <p class="jf-worldcup__desc">
        The official FIFA World Cup 2026 jerseys — lightweight, breathable
        fabric built for match day and beyond. Represent your nation in style.
      </p>

      <a href="/jerseyflow-ecommerce/jersey.php?type=worldcup_2026" class="jf-worldcup__cta">
        <span>View All Kits</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>

      <!-- FILTER BUTTONS -->
      <div class="jf-worldcup__filters">
        <button class="jf-filter-btn active" data-filter="all">All</button>
        <button class="jf-filter-btn" data-filter="home">Home</button>
        <button class="jf-filter-btn" data-filter="away">Away</button>
        <button class="jf-filter-btn" data-filter="third">Third</button>
      </div>

      <!-- TROPHY DECORATION -->
      <div class="jf-worldcup__deco" aria-hidden="true">
        <svg viewBox="0 0 120 160" xmlns="http://www.w3.org/2000/svg">
          <text y="120" font-size="110" opacity="0.04" fill="#fff">🏆</text>
        </svg>
      </div>

    </div>

    <!-- ===== RIGHT GRID ===== -->
    <div class="jf-worldcup__grid" id="wc-grid">

      <?php foreach ($wc_products as $index => $p):
        // Derive kit type for filter from kit_name (home / away / third)
        $kit_raw   = strtolower($p['kit_name'] ?? '');
        $kit_type  = 'home'; // default
        if (str_contains($kit_raw, 'away'))  $kit_type = 'away';
        if (str_contains($kit_raw, 'third')) $kit_type = 'third';

        $img_src = !empty($p['image_path'])
    ? '/jerseyflow-ecommerce/uploads/products/' . htmlspecialchars($p['image_path'])
    : '/jerseyflow-ecommerce/uploads/placeholder.png';

        $is_new    = $index === 0; // Mark the latest product as "New"
      ?>

        <div
          class="jf-product-card"
          data-type="<?= $kit_type ?>"
          data-id="<?= (int)$p['product_id'] ?>"
          style="--card-delay: <?= $index * 80 ?>ms"
        >
          <!-- IMAGE AREA -->
          <div class="jf-product-card__img">
            <img
              src="<?= $img_src ?>"
              alt="<?= htmlspecialchars($p['product_name']) ?>"
              loading="lazy"
              onerror="this.src='/jerseyflow-ecommerce/images/placeholder.png'"
            >

            <?php if ($is_new): ?>
              <span class="jf-badge jf-badge--new">New</span>
            <?php endif; ?>

            <?php if ((int)$p['stock'] === 0): ?>
              <span class="jf-badge jf-badge--out">Out of Stock</span>
            <?php endif; ?>

            <!-- QUICK VIEW TRIGGER -->
            <button
              class="jf-quickview-btn"
              data-id="<?= (int)$p['product_id'] ?>"
              data-name="<?= htmlspecialchars($p['product_name']) ?>"
              data-price="<?= number_format($p['price']) ?>"
              data-country="<?= htmlspecialchars($p['country_name'] ?? '') ?>"
              data-kit="<?= htmlspecialchars($p['kit_name'] ?? '') ?>"
              data-img="<?= $img_src ?>"
              data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
              data-stock="<?= (int)$p['stock'] ?>"
              aria-label="Quick view <?= htmlspecialchars($p['product_name']) ?>"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              Quick View
            </button>
          </div>

          <!-- CARD INFO -->
          <div class="jf-product-card__info">
            <span class="jf-product-card__country">
              <?= htmlspecialchars($p['country_name'] ?? '') ?>
            </span>
            <h4 class="jf-product-card__name">
              <?= htmlspecialchars($p['product_name']) ?>
            </h4>
            <div class="jf-price">
              <span class="jf-price__tag">Rs <?= number_format($p['price']) ?></span>
              <span class="jf-kit-badge"><?= htmlspecialchars($p['kit_name'] ?? '') ?></span>
            </div>
          </div>

        </div>

      <?php endforeach; ?>

    </div><!-- /.jf-worldcup__grid -->

  </div><!-- /.jf-worldcup__container -->

</section>

<!-- ===== QUICK VIEW MODAL ===== -->
<div class="jf-modal-overlay" id="jf-modal-overlay" role="dialog" aria-modal="true" aria-label="Product Quick View">
  <div class="jf-modal">

    <button class="jf-modal__close" id="jf-modal-close" aria-label="Close modal">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6 6 18M6 6l12 12"/>
      </svg>
    </button>

    <div class="jf-modal__inner">

      <div class="jf-modal__img-wrap">
        <img src="" alt="" id="modal-img">
      </div>

      <div class="jf-modal__details">
        <p class="jf-modal__country" id="modal-country"></p>
        <h3 class="jf-modal__name"  id="modal-name"></h3>
        <p class="jf-modal__kit"    id="modal-kit"></p>

        <div class="jf-modal__price-row">
          <span class="jf-modal__price" id="modal-price"></span>
          <span class="jf-modal__stock" id="modal-stock"></span>
        </div>

        <p class="jf-modal__desc" id="modal-desc"></p>

        <a href="#" class="jf-modal__cta" id="modal-cta">
          View Full Details →
        </a>
      </div>

    </div>
  </div>
</div>

<?php else: ?>

  <!-- Empty state if no WC 2026 products added yet -->
  <section class="jf-worldcup jf-worldcup--empty">
    <div class="jf-worldcup__container jf-worldcup__empty-state">
      <p>🏆 World Cup 2026 jerseys coming soon. Stay tuned!</p>
    </div>
  </section>

<?php endif; ?>

<script src="/jerseyflow-ecommerce/script/worldcup_section.js"></script>