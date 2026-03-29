(function () {
  const AUTOPLAY_MS   = 5000;   // time per slide (ms)
  const TOTAL = window.HERO_DATA.total;

  const track         = document.getElementById('jfTrack');
  const slides        = track.querySelectorAll('.jf-hero__slide');
  const dots          = document.querySelectorAll('.jf-hero__dot');
  const prevBtn       = document.getElementById('jfPrev');
  const nextBtn       = document.getElementById('jfNext');
  const currentLabel  = document.getElementById('jfCurrent');
  const progressBar   = document.getElementById('jfProgress');

  let current   = 0;
  let autoTimer = null;
  let progTimer = null;

  /* ── Go to slide ───────────────────────────────────────────── */
  function goTo(index) {
    // wrap around
    index = (index + TOTAL) % TOTAL;

    // remove active from old slide
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    dots[current].setAttribute('aria-selected', 'false');

    current = index;

    // set active on new slide
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    dots[current].setAttribute('aria-selected', 'true');

    // move track
    track.style.transform = `translateX(-${current * 100}%)`;

    // update counter
    currentLabel.textContent = current + 1;

    // reset progress bar
    resetProgress();
  }

  /* ── Autoplay progress bar ─────────────────────────────────── */
  function resetProgress() {
    // clear old animation
    progressBar.style.transition = 'none';
    progressBar.style.width      = '0%';

    // force reflow so transition resets properly
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
    // freeze progress bar width
    const computed = getComputedStyle(progressBar).width;
    progressBar.style.transition = 'none';
    progressBar.style.width      = computed;
  }

  /* ── Button events ─────────────────────────────────────────── */
  prevBtn.addEventListener('click', () => {
    goTo(current - 1);
    stopAutoplay();
    startAutoplay();
  });

  nextBtn.addEventListener('click', () => {
    goTo(current + 1);
    stopAutoplay();
    startAutoplay();
  });

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