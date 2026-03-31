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
(function () {
  const AUTOPLAY_MS = 5000;
  const TOTAL       = <?= json_encode($total) ?>;

  const track        = document.getElementById('jfTrack');
  const slides       = track.querySelectorAll('.jf-hero__slide');
  const dots         = document.querySelectorAll('.jf-hero__dot');
  const prevBtn      = document.getElementById('jfPrev');
  const nextBtn      = document.getElementById('jfNext');
  const currentLabel = document.getElementById('jfCurrent');
  const progressBar  = document.getElementById('jfProgress');

  let current   = 0;
  let autoTimer = null;
  let progTimer = null;

  /* ── Go to slide ───────────────────────────────────────────── */
  function goTo(index) {
    index = (index + TOTAL) % TOTAL;

    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    dots[current].setAttribute('aria-selected', 'false');

    current = index;

    slides[current].classList.add('active');
    dots[current].classList.add('active');
    dots[current].setAttribute('aria-selected', 'true');

    track.style.transform  = `translateX(-${current * 100}%)`;
    currentLabel.textContent = current + 1;

    resetProgress();
  }

  /* ── Autoplay progress bar ─────────────────────────────────── */
  function resetProgress() {
    progressBar.style.transition = 'none';
    progressBar.style.width      = '0%';
    void progressBar.offsetWidth;
    progressBar.style.transition = `width ${AUTOPLAY_MS}ms linear`;
    progressBar.style.width      = '100%';
  }

  /* ── Autoplay ──────────────────────────────────────────────── */
  function startAutoplay() {
    stopAutoplay();
    autoTimer = setInterval(() => goTo(current + 1), AUTOPLAY_MS);
    resetProgress();
  }

  function stopAutoplay() {
    clearInterval(autoTimer);
    clearTimeout(progTimer);
    const computed = getComputedStyle(progressBar).width;
    progressBar.style.transition = 'none';
    progressBar.style.width      = computed;
  }

  /* ── Button events ─────────────────────────────────────────── */
  prevBtn.addEventListener('click', () => { goTo(current - 1); stopAutoplay(); startAutoplay(); });
  nextBtn.addEventListener('click', () => { goTo(current + 1); stopAutoplay(); startAutoplay(); });

  /* ── Dot events ────────────────────────────────────────────── */
  dots.forEach(dot => {
    dot.addEventListener('click', () => {
      goTo(parseInt(dot.dataset.goto, 10));
      stopAutoplay();
      startAutoplay();
    });
  });

  /* ── Keyboard navigation ───────────────────────────────────── */
  document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft')  { goTo(current - 1); stopAutoplay(); startAutoplay(); }
    if (e.key === 'ArrowRight') { goTo(current + 1); stopAutoplay(); startAutoplay(); }
  });

  /* ── Touch / swipe support ─────────────────────────────────── */
  let touchStartX = 0;
  const hero = document.getElementById('jfHero');

  hero.addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].clientX;
  }, { passive: true });

  hero.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) {
      diff > 0 ? goTo(current + 1) : goTo(current - 1);
      stopAutoplay();
      startAutoplay();
    }
  }, { passive: true });

  /* ── Pause on hover ────────────────────────────────────────── */
  hero.addEventListener('mouseenter', stopAutoplay);
  hero.addEventListener('mouseleave', startAutoplay);

  /* ── Pause when tab is hidden ──────────────────────────────── */
  document.addEventListener('visibilitychange', () => {
    document.hidden ? stopAutoplay() : startAutoplay();
  });

  /* ── Boot ──────────────────────────────────────────────────── */
  goTo(0);
  startAutoplay();

})();
</script>