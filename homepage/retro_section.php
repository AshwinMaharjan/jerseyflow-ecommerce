<?php
/**
 * JerseyFlow — Retro Jersey Section
 * File: retro_section.php
 *
 * Fetches products from DB where special_type = 'retro'
 * Requires: $conn (MySQLi connection object)
 */

// --- DB QUERY ---
$retro_products = [];

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
        WHERE p.special_type = 'retro'
        ORDER BY p.created_at DESC
        LIMIT 4";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $retro_products[] = $row;
    }
}
?>

<link rel="stylesheet" href="/jerseyflow-ecommerce/style/retro_section.css" />

<?php if (!empty($retro_products)): ?>

<section class="jf-retro" id="retro-jerseys">

  <!-- Grain texture overlay -->
  <div class="jf-retro__grain" aria-hidden="true"></div>

  <div class="jf-retro__container">

    <!-- ===== HEADER ===== -->
    <div class="jf-retro__header">

      <div class="jf-retro__header-left">
        <div class="jf-retro__stamp">
          <span>Classic</span>
          <span>Era</span>
        </div>

        <div class="jf-retro__title-wrap">
          <p class="jf-retro__sub">&#9670; Vintage Collection &#9670;</p>
          <h2 class="jf-retro__heading">
            Retro <em>Jerseys</em>
          </h2>
          <p class="jf-retro__desc">
            Legendary kits from football's golden eras — restored, reimagined,
            and ready to wear. Each piece carries the soul of the beautiful game.
          </p>
        </div>
      </div>

      <div class="jf-retro__header-right">
        <!-- FILTERS -->
        <div class="jf-retro__filters">
          <button class="jf-retro-filter active" data-filter="all">All</button>
          <button class="jf-retro-filter" data-filter="club">Club</button>
          <button class="jf-retro-filter" data-filter="national">National</button>
          <button class="jf-retro-filter" data-filter="home">Home</button>
          <button class="jf-retro-filter" data-filter="away">Away</button>
        </div>

        <a href="retro.php" class="jf-retro__cta">
          Browse All Retro Kits
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

    </div>

    <!-- ===== PRODUCT GRID ===== -->
    <div class="jf-retro__grid" id="retro-grid">

      <?php foreach ($retro_products as $index => $p):

        // Derive kit type for filtering
        $kit_raw  = strtolower($p['kit_name'] ?? '');
        $kit_type = 'home';
        if (str_contains($kit_raw, 'away'))  $kit_type = 'away';
        if (str_contains($kit_raw, 'third')) $kit_type = 'third';

        // National vs Club for filter
        $has_country = !empty($p['country_name']);
        $has_club    = !empty($p['club_name']);
        $category    = $has_club ? 'club' : 'national';

        // Display label — prefer club, fallback country
        $label = $has_club ? $p['club_name'] : ($p['country_name'] ?? '');

        $img_src = !empty($p['image_path'])
    ? '/jerseyflow-ecommerce/uploads/products/' . htmlspecialchars($p['image_path'])
    : '/jerseyflow-ecommerce/uploads/placeholder.png';

        $is_latest = ($index === 0);
      ?>

        <div
          class="jf-retro-card"
          data-type="<?= $kit_type ?>"
          data-category="<?= $category ?>"
          data-id="<?= (int)$p['product_id'] ?>"
          style="--card-delay: <?= $index * 90 ?>ms"
        >
          <!-- IMAGE AREA -->
          <div class="jf-retro-card__img-wrap">

            <!-- Decorative era label -->
            <div class="jf-retro-card__era" aria-hidden="true">
              <?= htmlspecialchars($label) ?>
            </div>

            <img
              src="<?= $img_src ?>"
              alt="<?= htmlspecialchars($p['product_name']) ?>"
              loading="lazy"
              onerror="this.src='/jerseyflow-ecommerce/images/placeholder.png'"
            >

            <?php if ($is_latest): ?>
              <span class="jf-retro-badge jf-retro-badge--new">Just In</span>
            <?php endif; ?>

            <?php if ((int)$p['stock'] === 0): ?>
              <span class="jf-retro-badge jf-retro-badge--out">Sold Out</span>
            <?php endif; ?>

            <!-- Quick View -->
            <button
              class="jf-retro-quickview"
              data-id="<?= (int)$p['product_id'] ?>"
              data-name="<?= htmlspecialchars($p['product_name']) ?>"
              data-price="<?= number_format($p['price']) ?>"
              data-label="<?= htmlspecialchars($label) ?>"
              data-kit="<?= htmlspecialchars($p['kit_name'] ?? '') ?>"
              data-img="<?= $img_src ?>"
              data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
              data-stock="<?= (int)$p['stock'] ?>"
              aria-label="Quick view <?= htmlspecialchars($p['product_name']) ?>"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              Quick View
            </button>

          </div>

          <!-- CARD INFO -->
          <div class="jf-retro-card__info">
            <span class="jf-retro-card__label"><?= htmlspecialchars($label) ?></span>
            <h4 class="jf-retro-card__name"><?= htmlspecialchars($p['product_name']) ?></h4>
            <div class="jf-retro-card__foot">
              <span class="jf-retro-card__price">Rs <?= number_format($p['price']) ?></span>
              <?php if (!empty($p['kit_name'])): ?>
                <span class="jf-retro-card__kit"><?= htmlspecialchars($p['kit_name']) ?></span>
              <?php endif; ?>
            </div>
          </div>

        </div>

      <?php endforeach; ?>

    </div><!-- /.jf-retro__grid -->

  </div><!-- /.jf-retro__container -->

</section>

<!-- ===== RETRO QUICK VIEW MODAL ===== -->
<div class="jf-retro-modal-overlay" id="jf-retro-overlay" role="dialog" aria-modal="true" aria-label="Retro Jersey Quick View">
  <div class="jf-retro-modal">

    <button class="jf-retro-modal__close" id="jf-retro-close" aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6 6 18M6 6l12 12"/>
      </svg>
    </button>

    <!-- Corner decorations -->
    <span class="jf-retro-modal__corner tl" aria-hidden="true"></span>
    <span class="jf-retro-modal__corner tr" aria-hidden="true"></span>
    <span class="jf-retro-modal__corner bl" aria-hidden="true"></span>
    <span class="jf-retro-modal__corner br" aria-hidden="true"></span>

    <div class="jf-retro-modal__inner">

      <div class="jf-retro-modal__img-side">
        <img src="" alt="" id="retro-modal-img">
      </div>

      <div class="jf-retro-modal__info-side">
        <p class="jf-retro-modal__eyebrow">&#9670; Vintage Collection &#9670;</p>
        <p class="jf-retro-modal__label"  id="retro-modal-label"></p>
        <h3 class="jf-retro-modal__name"  id="retro-modal-name"></h3>
        <p class="jf-retro-modal__kit"    id="retro-modal-kit"></p>

        <div class="jf-retro-modal__divider"></div>

        <div class="jf-retro-modal__price-row">
          <span class="jf-retro-modal__price" id="retro-modal-price"></span>
          <span class="jf-retro-modal__stock" id="retro-modal-stock"></span>
        </div>

        <p class="jf-retro-modal__desc" id="retro-modal-desc"></p>

        <a href="#" class="jf-retro-modal__cta" id="retro-modal-cta">
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

  <section class="jf-retro jf-retro--empty">
    <div class="jf-retro__empty">
      <p>👕 Retro jerseys coming soon — classics are being restored!</p>
    </div>
  </section>

<?php endif; ?>

<script src="/jerseyflow-ecommerce/script/retro_section.js"></script>