(function () {
  /* Sticky shadow */
  const nav = document.getElementById('jfNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  }, { passive: true });

  /* Search expand */
  const searchWrap  = document.getElementById('jfSearchWrap');
  const searchBtn   = document.getElementById('jfSearchBtn');
  const searchInput = document.getElementById('jfSearchInput');

  searchBtn.addEventListener('click', () => {
    const isOpen = searchWrap.classList.toggle('open');
    if (isOpen) searchInput.focus();
    else searchInput.value = '';
  });
  document.addEventListener('click', e => {
    if (!searchWrap.contains(e.target)) {
      searchWrap.classList.remove('open');
      searchInput.value = '';
    }
  });

  /* Search submit on Enter */
  searchInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && searchInput.value.trim()) {
      window.location.href = 'search.php?q=' + encodeURIComponent(searchInput.value.trim());
    }
  });

  /* Hamburger */
  const hamburger   = document.getElementById('jfHamburger');
  const mobileMenu  = document.getElementById('jfMobileMenu');

  hamburger.addEventListener('click', () => {
    const open = hamburger.classList.toggle('open');
    mobileMenu.classList.toggle('open', open);
    hamburger.setAttribute('aria-expanded', open);
    mobileMenu.setAttribute('aria-hidden', !open);
  });

  /* Mobile accordion */
  document.querySelectorAll('.mob-link[data-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sub = document.getElementById(btn.dataset.target);
      const isOpen = sub.classList.toggle('open');
      btn.classList.toggle('open', isOpen);
    });
  });

  /* ── Cart count helper ──────────────────────────────────────
   * Call  window.JFCart.add(n)  from any add-to-cart button.
   * It bumps the displayed count by n (default 1) without a
   * page reload, then triggers the pop animation.
   * ─────────────────────────────────────────────────────────── */
  const cartCountEl = document.getElementById('jfCartCount');
  const cartBtn     = document.getElementById('jfCartBtn');

  window.JFCart = {
    get count () { return parseInt(cartCountEl.textContent, 10) || 0; },
    add (n = 1) {
      cartCountEl.textContent = this.count + n;
      cartBtn.classList.remove('bump');
      void cartBtn.offsetWidth; // force reflow for animation replay
      cartBtn.classList.add('bump');
      cartBtn.addEventListener('animationend', () => cartBtn.classList.remove('bump'), { once: true });
    }
  };
})();