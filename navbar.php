<?php
/**
 * JerseyFlow — Navbar Component
 * File: navbar.php
 *
 * Usage: <?php include 'navbar.php'; ?>
 *
 * Cart count is read from $_SESSION['cart_count'].
 * Make sure session_start() is called before including this file.
 */

// Cart item count from session (default 0)
$cart_count = isset($_SESSION['cart_count']) ? (int) $_SESSION['cart_count'] : 0;

// Current page for active-link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Font Awesome CDN -->
<link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
<!-- Navbar CSS -->
<link rel="stylesheet" href="style/navbar.css" />

<!-- ══════════════════════════ NAVBAR ══════════════════════════ -->
<nav class="jf-nav" id="jfNav">

  <!-- ── Logo ─────────────────────────────────────────────────── -->
  <a href="homepage.php" class="jf-logo" aria-label="JerseyFlow Home">
    <img src="/jerseyflow-ecommerce/images/logo.png" alt="JerseyFlow Admin" />
  </a>

  <!-- ── Center Links ──────────────────────────────────────────── -->
  <ul class="jf-links">

    <!-- Jersey (dropdown) -->
    <li class="has-drop">
      <a href="jersey.php">
        Jersey
        <i class="fa-solid fa-chevron-down chev"></i>
      </a>
      <div class="jf-dropdown">
        <a href="jersey.php?type=standard">
          <i class="fa-solid fa-shield-halved drop-icon"></i>
          Football Club Jersey
        </a>
        <a href="jersey.php?type=worldcup_2026">
          <i class="fa-solid fa-trophy drop-icon"></i>
          FIFA World Cup 2026 Jersey
        </a>
        <a href="jersey.php?type=retro">
          <i class="fa-solid fa-clock-rotate-left drop-icon"></i>
          Football Retro Jersey
        </a>
        <a href="jersey.php?type=country">
          <i class="fa-solid fa-earth-americas drop-icon"></i>
          Football Country Jersey
        </a>
        <a href="jersey.php?type=limited">
          <i class="fa-solid fa-crown drop-icon"></i>
          Limited Edition Jersey
        </a>
      </div>
    </li>

    <!-- Club (dropdown) -->
    <li class="has-drop">
      <a href="clubs.php">
        Club
        <i class="fa-solid fa-chevron-down chev"></i>
      </a>
      <div class="jf-dropdown">
        <a href="clubs.php?club=real-madrid">
          <i class="fa-solid fa-star drop-icon"></i>
          Real Madrid
        </a>
        <a href="clubs.php?club=barcelona">
          <i class="fa-solid fa-star drop-icon"></i>
          FC Barcelona
        </a>
        <a href="clubs.php?club=manchester-united">
          <i class="fa-solid fa-star drop-icon"></i>
          Manchester United
        </a>
        <a href="clubs.php?club=arsenal">
          <i class="fa-solid fa-star drop-icon"></i>
          Arsenal
        </a>
        <a href="clubs.php?club=liverpool">
          <i class="fa-solid fa-star drop-icon"></i>
          Liverpool FC
        </a>
        <a href="clubs.php?club=ac_milan">
          <i class="fa-solid fa-star drop-icon"></i>
          AC Milan
        </a>
        <a href="clubs.php?club=juventus">
          <i class="fa-solid fa-star drop-icon"></i>
          Juventus
        </a>
      </div>
    </li>
    <!-- About -->
    <li>
      <a href="about.php" class="<?= ($current_page === 'about.php') ? 'active' : '' ?>">
        About
      </a>
    </li>

    <!-- Contact -->
    <li>
      <a href="contact.php" class="<?= ($current_page === 'contact.php') ? 'active' : '' ?>">
        Contact
      </a>
    </li>


  </ul>

  <!-- ── Right Actions ─────────────────────────────────────────── -->
  <div class="jf-actions">

    <!-- Profile Icon -->
    <a href="login.php" class="jf-icon-btn" aria-label="My Account">
      <i class="fa-regular fa-circle-user"></i>
    </a>

    <div class="jf-divider"></div>

    <!-- Cart Button -->
    <a href="cart.php" class="jf-cart-btn" id="jfCartBtn" aria-label="View Cart">
      <i class="fa-solid fa-cart-shopping"></i>
      <span class="cart-label">Cart</span>
      <span class="jf-cart-count" id="jfCartCount">
        <?= $cart_count ?>
      </span>
    </a>

    <div class="jf-divider"></div>

    <!-- Search -->
    <div class="jf-search-wrap" id="jfSearchWrap">
      <button class="jf-search-btn" id="jfSearchBtn" aria-label="Search">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>
      <input
        class="jf-search-input"
        id="jfSearchInput"
        type="text"
        placeholder="Search jerseys…"
        autocomplete="off"
      />
    </div>

    <!-- Hamburger (mobile) -->
    <button class="jf-hamburger" id="jfHamburger" aria-label="Toggle Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>

<!-- ══════════════════════ MOBILE MENU ════════════════════════ -->
<div class="jf-mobile-menu" id="jfMobileMenu" aria-hidden="true">
  <div class="jf-mobile-inner">
    <ul>

      <li>
        <a href="about.php" class="mob-link">About</a>
      </li>

      <li>
        <a href="contact.php" class="mob-link">Contact</a>
      </li>

      <!-- Jersey accordion -->
      <li>
        <button class="mob-link" data-target="mobJersey">
          Jersey <i class="fa-solid fa-chevron-down chev"></i>
        </button>
        <div class="jf-mobile-sub" id="mobJersey">
          <ul>
            <li><a href="jersey.php?type=standard"><i class="fa-solid fa-shield-halved drop-icon"></i> Football Club Jersey</a></li>
            <li><a href="jersey.php?type=worldcup-2026"><i class="fa-solid fa-trophy drop-icon"></i> FIFA World Cup 2026 Jersey</a></li>
            <li><a href="jersey.php?type=retro"><i class="fa-solid fa-clock-rotate-left drop-icon"></i> Football Retro Jersey</a></li>
            <li><a href="jersey.php?type=country"><i class="fa-solid fa-earth-americas drop-icon"></i> Football Country Jersey</a></li>
            <li><a href="jersey.php?type=keeper"><i class="fa-solid fa-hands drop-icon"></i> Keeper Jersey</a></li>
          </ul>
        </div>
      </li>

      <!-- Club accordion -->
      <li>
        <button class="mob-link" data-target="mobClub">
          Club <i class="fa-solid fa-chevron-down chev"></i>
        </button>
        <div class="jf-mobile-sub" id="mobClub">
          <ul>
            <li><a href="clubs.php?club=real-madrid"><i class="fa-solid fa-star drop-icon"></i> Real Madrid</a></li>
            <li><a href="clubs.php?club=barcelona"><i class="fa-solid fa-star drop-icon"></i> FC Barcelona</a></li>
            <li><a href="clubs.php?club=manchester-united"><i class="fa-solid fa-star drop-icon"></i> Manchester United</a></li>
            <li><a href="clubs.php?club=arsenal"><i class="fa-solid fa-star drop-icon"></i> Arsenal</a></li>
            <li><a href="clubs.php?club=liverpool"><i class="fa-solid fa-star drop-icon"></i> Liverpool FC</a></li>
            <li><a href="clubs.php?club=ac_milan"><i class="fa-solid fa-star drop-icon"></i> AC Milan</a></li>
            <li><a href="clubs.php?club=juventus"><i class="fa-solid fa-star drop-icon"></i> Juventus</a></li>
          </ul>
        </div>
      </li>

    </ul>
  </div>
</div>

<!-- ══════════════════════════ JS ══════════════════════════════ -->
<script>
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
</script>