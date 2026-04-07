/**
 * JerseyFlow — Navbar Script
 * File: navbar.js
 *
 * Usage: <script src="../script/navbar.js"></script>
 * Place this at the bottom of navbar.php, replacing the inline <script> block.
 */

(function () {
  'use strict';

  /* ── STICKY SHADOW ─────────────────────────────────────────── */
  const nav = document.getElementById('jfNav');
  if (nav) {
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });
  }

  /* ── SEARCH EXPAND ─────────────────────────────────────────── */
  const searchWrap  = document.getElementById('jfSearchWrap');
  const searchBtn   = document.getElementById('jfSearchBtn');
  const searchInput = document.getElementById('jfSearchInput');

  if (searchBtn && searchWrap && searchInput) {
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

    searchInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && searchInput.value.trim()) {
        window.location.href = 'search.php?q=' + encodeURIComponent(searchInput.value.trim());
      }
    });
  }

  /* ── HAMBURGER ─────────────────────────────────────────────── */
  const hamburger  = document.getElementById('jfHamburger');
  const mobileMenu = document.getElementById('jfMobileMenu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      const open = hamburger.classList.toggle('open');
      mobileMenu.classList.toggle('open', open);
      hamburger.setAttribute('aria-expanded', open);
      mobileMenu.setAttribute('aria-hidden', !open);
    });
  }

  /* ── MOBILE ACCORDION ──────────────────────────────────────── */
  document.querySelectorAll('.mob-link[data-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sub = document.getElementById(btn.dataset.target);
      if (!sub) return;
      const isOpen = sub.classList.toggle('open');
      btn.classList.toggle('open', isOpen);
    });
  });

  /* ── CART COUNT HELPER ─────────────────────────────────────────
   * Call window.JFCart.add(n) from any add-to-cart button.
   * Bumps the displayed count by n (default 1) and plays
   * a pop animation on the cart button.
   * ─────────────────────────────────────────────────────────── */
  const cartCountEl = document.getElementById('jfCartCount');
  const cartBtn     = document.getElementById('jfCartBtn');

  if (cartCountEl && cartBtn) {
    window.JFCart = {
      get count() { return parseInt(cartCountEl.textContent, 10) || 0; },
      add(n = 1) {
        cartCountEl.textContent = this.count + n;
        cartBtn.classList.remove('bump');
        void cartBtn.offsetWidth; // force reflow to replay animation
        cartBtn.classList.add('bump');
        cartBtn.addEventListener('animationend', () => cartBtn.classList.remove('bump'), { once: true });
      }
    };
  }

  /* ── LOGOUT MODAL ──────────────────────────────────────────────
   * jfOpenLogoutModal()  — called by the Logout menu item
   * jfCloseLogoutModal() — called by the Cancel button
   * Also closes on backdrop click or Escape key.
   * ─────────────────────────────────────────────────────────── */
  const logoutModal = document.getElementById('jfLogoutModal');

  window.jfOpenLogoutModal = function () {
    if (!logoutModal) return;
    logoutModal.style.display = 'flex';
    // Focus Cancel for keyboard accessibility
    setTimeout(() => {
      const cancel = logoutModal.querySelector('.jf-logout-cancel');
      if (cancel) cancel.focus();
    }, 50);
  };

  window.jfCloseLogoutModal = function () {
    if (!logoutModal) return;
    logoutModal.style.display = 'none';
  };

  if (logoutModal) {
    // Close on backdrop click
    logoutModal.addEventListener('click', function (e) {
      if (e.target === logoutModal) jfCloseLogoutModal();
    });
  }

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && logoutModal && logoutModal.style.display === 'flex') {
      jfCloseLogoutModal();
    }
  });

})();