<?php
/**
 * JerseyFlow — Hero Banner Slider
 * File: hero_section.php
 *
 * Usage in homepage.php:
 *   <?php include("hero_section.php"); ?>
 *
 * Add or remove banners by editing the $banners array below.
 */

$banners = [
    [
        'src' => 'images/banner/banner1.png',
        'alt' => 'JerseyFlow Banner 1',
    ],
    [
        'src' => 'images/banner/banner2.png',
        'alt' => 'JerseyFlow Banner 2',
    ],
    [
        'src' => 'images/banner/banner3.png',
        'alt' => 'JerseyFlow Banner 3',
    ],
    [
        'src' => 'images/banner/banner4.png',
        'alt' => 'JerseyFlow Banner 4',
    ],
];

$total = count($banners);
?>

<!-- Hero CSS -->
<link rel="stylesheet" href="style/hero_section.css" />
<!-- Barlow font (skip if already loaded via navbar.css) -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet" />

<!-- ══════════════════════════ HERO ════════════════════════════ -->
<section class="jf-hero" id="jfHero" aria-label="Featured Banners">

  <!-- Slide track -->
  <div class="jf-hero__track" id="jfTrack">
    <?php foreach ($banners as $i => $banner): ?>
      <div class="jf-hero__slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <img
          src="<?= htmlspecialchars($banner['src']) ?>"
          alt="<?= htmlspecialchars($banner['alt']) ?>"
          <?= $i === 0 ? '' : 'loading="lazy"' ?>
          draggable="false"
        />
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Prev arrow -->
  <button class="jf-hero__arrow prev" id="jfPrev" aria-label="Previous slide">
    <i class="fa-solid fa-chevron-left"></i>
  </button>

  <!-- Next arrow -->
  <button class="jf-hero__arrow next" id="jfNext" aria-label="Next slide">
    <i class="fa-solid fa-chevron-right"></i>
  </button>

  <!-- Dot indicators -->
  <div class="jf-hero__dots" role="tablist" aria-label="Slide navigation">
    <?php for ($i = 0; $i < $total; $i++): ?>
      <button
        class="jf-hero__dot <?= $i === 0 ? 'active' : '' ?>"
        data-goto="<?= $i ?>"
        role="tab"
        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
        aria-label="Go to slide <?= $i + 1 ?>"
      ></button>
    <?php endfor; ?>
  </div>

  <!-- Counter e.g. 1 / 4 -->
  <div class="jf-hero__counter">
    <span id="jfCurrent">1</span> / <?= $total ?>
  </div>

  <!-- Auto-play progress bar -->
  <div class="jf-hero__progress" id="jfProgress"></div>

</section>

<!-- ══════════════════════════ JS ══════════════════════════════ -->
<script>
  window.HERO_DATA = {
    total: <?= json_encode($total) ?>
  };
</script>

<script src="../script/hero_section.js"></script>