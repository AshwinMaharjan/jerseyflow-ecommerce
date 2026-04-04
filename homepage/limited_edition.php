<?php
/**
 * JerseyFlow — Limited Edition Section
 * File: limited_edition.php
 *
 * Fetches products from DB where special_type = 'limited'
 * Requires: $conn (MySQLi connection object)
 */

// --- DB QUERY ---
$limited_products = [];

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
        WHERE p.special_type = 'limited'
        ORDER BY p.created_at DESC
        LIMIT 8";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $limited_products[] = $row;
    }
}
?>

<link rel="stylesheet" href="/jerseyflow-ecommerce/style/limited_edition.css" />

<?php if (!empty($limited_products)): ?>

<section class="jf-ltd" id="limited-edition">

  <!-- Animated diagonal stripe overlay -->
  <div class="jf-ltd__stripes" aria-hidden="true"></div>
  <!-- Radial glow spots -->
  <div class="jf-ltd__glow jf-ltd__glow--a" aria-hidden="true"></div>
  <div class="jf-ltd__glow jf-ltd__glow--b" aria-hidden="true"></div>

  <div class="jf-ltd__container">

    <!-- ===== HEADER ===== -->
    <div class="jf-ltd__header">

      <!-- LEFT: badge + titles -->
      <div class="jf-ltd__header-left">

        <!-- Exclusive drop badge -->
        <div class="jf-ltd__drop-badge" aria-hidden="true">
          <span class="jf-ltd__drop-icon">◈</span>
          <span>Exclusive Drop</span>
        </div>

        <div class="jf-ltd__title-block">
          <p class="jf-ltd__sub">&#10022; Limited Edition &#10022;</p>
          <h2 class="jf-ltd__heading">
            <span class="jf-ltd__heading-line1">Limited</span>
            <em class="jf-ltd__heading-line2">Edition</em>
          </h2>
          <p class="jf-ltd__desc">
            Rare, numbered, and never restocked — our Limited Edition jerseys are collector-grade pieces released in strictly controlled quantities. Once they're gone, they're gone. Own a piece of football history before it disappears forever.
          </p>
        </div>

      </div>

      <!-- RIGHT: counter + filters + CTA -->
      <div class="jf-ltd__header-right">

        <!-- Live stock counter pill -->
        <div class="jf-ltd__stock-counter">
          <span class="jf-ltd__counter-dot" aria-hidden="true"></span>
          <span id="ltd-stock-text">
            <?= count($limited_products) ?> Drop<?= count($limited_products) !== 1 ? 's' : '' ?> Available
          </span>
        </div>

        <!-- Filters -->
        <div class="jf-ltd__filters">
          <button class="jf-ltd-filter active" data-filter="all">All</button>
          <button class="jf-ltd-filter" data-filter="club">Club</button>
          <button class="jf-ltd-filter" data-filter="national">National</button>
          <button class="jf-ltd-filter" data-filter="home">Home</button>
          <button class="jf-ltd-filter" data-filter="away">Away</button>
        </div>

        <a href="/jerseyflow-ecommerce/jersey.php?type=limited" class="jf-ltd__cta">
          Shop All Limited Drops
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>

      </div>

    </div><!-- /.jf-ltd__header -->

    <!-- ===== PRODUCT GRID (3×3) ===== -->
    <div class="jf-ltd__grid" id="ltd-grid">

      <?php foreach ($limited_products as $index => $p):

        // Kit type for filtering
        $kit_raw  = strtolower($p['kit_name'] ?? '');
        $kit_type = 'home';
        if (str_contains($kit_raw, 'away'))  $kit_type = 'away';
        if (str_contains($kit_raw, 'third')) $kit_type = 'third';

        // Category
        $has_country = !empty($p['country_name']);
        $has_club    = !empty($p['club_name']);
        $category    = $has_club ? 'club' : 'national';

        // Display label
        $label = $has_club ? $p['club_name'] : ($p['country_name'] ?? '');

        $img_src = !empty($p['image_path'])
            ? '/jerseyflow-ecommerce/uploads/products/' . htmlspecialchars($p['image_path'])
            : '/jerseyflow-ecommerce/uploads/placeholder.png';

        // Edition number (1-based, padded)
        $edition_num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);

        // Low stock warning (<=5)
        $low_stock = ((int)$p['stock'] > 0 && (int)$p['stock'] <= 5);
      ?>

      <div
        class="jf-ltd-card"
        data-type="<?= $kit_type ?>"
        data-category="<?= $category ?>"
        data-id="<?= (int)$p['product_id'] ?>"
        style="--card-delay: <?= $index * 75 ?>ms; --card-index: <?= $index ?>;"
      >

        <!-- Edition number watermark -->
        <div class="jf-ltd-card__edition-num" aria-hidden="true"><?= $edition_num ?></div>

        <!-- IMAGE AREA -->
        <div class="jf-ltd-card__img-wrap">

          <!-- Corner accents -->
          <span class="jf-ltd-card__corner jf-ltd-card__corner--tl" aria-hidden="true"></span>
          <span class="jf-ltd-card__corner jf-ltd-card__corner--tr" aria-hidden="true"></span>

          <img
            src="<?= $img_src ?>"
            alt="<?= htmlspecialchars($p['product_name']) ?>"
            loading="lazy"
            onerror="this.src='/jerseyflow-ecommerce/images/placeholder.png'"
          >

          <!-- Badges -->
          <?php if ($index === 0): ?>
            <span class="jf-ltd-badge jf-ltd-badge--new">New Drop</span>
          <?php endif; ?>

          <?php if ($low_stock): ?>
            <span class="jf-ltd-badge jf-ltd-badge--low">Only <?= (int)$p['stock'] ?> Left</span>
          <?php endif; ?>

          <?php if ((int)$p['stock'] === 0): ?>
            <span class="jf-ltd-badge jf-ltd-badge--out">Sold Out</span>
          <?php endif; ?>

          <!-- Quick View -->
          <button
            class="jf-ltd-quickview"
            data-id="<?= (int)$p['product_id'] ?>"
            data-name="<?= htmlspecialchars($p['product_name']) ?>"
            data-price="<?= number_format($p['price']) ?>"
            data-label="<?= htmlspecialchars($label) ?>"
            data-kit="<?= htmlspecialchars($p['kit_name'] ?? '') ?>"
            data-img="<?= $img_src ?>"
            data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
            data-stock="<?= (int)$p['stock'] ?>"
            data-edition="<?= $edition_num ?>"
            aria-label="Quick view <?= htmlspecialchars($p['product_name']) ?>"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            Quick View
          </button>

        </div><!-- /.jf-ltd-card__img-wrap -->

        <!-- CARD INFO -->
        <div class="jf-ltd-card__info">
          <div class="jf-ltd-card__meta">
            <span class="jf-ltd-card__label"><?= htmlspecialchars($label) ?></span>
            <?php if (!empty($p['kit_name'])): ?>
              <span class="jf-ltd-card__kit"><?= htmlspecialchars($p['kit_name']) ?></span>
            <?php endif; ?>
          </div>
          <h4 class="jf-ltd-card__name"><?= htmlspecialchars($p['product_name']) ?></h4>
          <div class="jf-ltd-card__foot">
            <span class="jf-ltd-card__price">Rs <?= number_format($p['price']) ?></span>
            <span class="jf-ltd-card__stock-pill <?= (int)$p['stock'] === 0 ? 'out' : ((int)$p['stock'] <= 5 ? 'low' : 'in') ?>">
              <?php if ((int)$p['stock'] === 0): ?>
                Sold Out
              <?php elseif ((int)$p['stock'] <= 5): ?>
                <?= (int)$p['stock'] ?> Left
              <?php else: ?>
                In Stock
              <?php endif; ?>
            </span>
          </div>
        </div>

      </div><!-- /.jf-ltd-card -->

      <?php endforeach; ?>

    </div><!-- /.jf-ltd__grid -->

  </div><!-- /.jf-ltd__container -->

</section>

<!-- ===== LIMITED EDITION QUICK VIEW MODAL ===== -->
<div class="jf-ltd-modal-overlay" id="jf-ltd-overlay" role="dialog" aria-modal="true" aria-label="Limited Edition Quick View">
  <div class="jf-ltd-modal">

    <!-- Close button -->
    <button class="jf-ltd-modal__close" id="jf-ltd-close" aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6 6 18M6 6l12 12"/>
      </svg>
    </button>

    <!-- Premium corner accents -->
    <span class="jf-ltd-modal__corner tl" aria-hidden="true"></span>
    <span class="jf-ltd-modal__corner tr" aria-hidden="true"></span>
    <span class="jf-ltd-modal__corner bl" aria-hidden="true"></span>
    <span class="jf-ltd-modal__corner br" aria-hidden="true"></span>

    <div class="jf-ltd-modal__inner">

      <!-- Image side -->
      <div class="jf-ltd-modal__img-side">
        <div class="jf-ltd-modal__edition-watermark" id="ltd-modal-edition"></div>
        <img src="" alt="" id="ltd-modal-img">
      </div>

      <!-- Info side -->
      <div class="jf-ltd-modal__info-side">
        <p class="jf-ltd-modal__eyebrow">&#10022; Limited Edition &#10022;</p>
        <p class="jf-ltd-modal__label" id="ltd-modal-label"></p>
        <h3 class="jf-ltd-modal__name"  id="ltd-modal-name"></h3>
        <p class="jf-ltd-modal__kit"    id="ltd-modal-kit"></p>

        <div class="jf-ltd-modal__divider">
          <span class="jf-ltd-modal__divider-gem" aria-hidden="true">◈</span>
        </div>

        <div class="jf-ltd-modal__price-row">
          <span class="jf-ltd-modal__price" id="ltd-modal-price"></span>
          <span class="jf-ltd-modal__stock" id="ltd-modal-stock"></span>
        </div>

        <p class="jf-ltd-modal__desc" id="ltd-modal-desc"></p>

        <a href="#" class="jf-ltd-modal__cta" id="ltd-modal-cta">
          View Full Details
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

    </div>
  </div>
</div>

<?php else: ?>

  <section class="jf-ltd jf-ltd--empty">
    <div class="jf-ltd__empty">
      <span class="jf-ltd__empty-icon">◈</span>
      <p>Limited Edition drops coming soon — stay tuned for exclusive releases!</p>
    </div>
  </section>

<?php endif; ?>

<script src="/jerseyflow-ecommerce/script/limited_edition.js"></script>