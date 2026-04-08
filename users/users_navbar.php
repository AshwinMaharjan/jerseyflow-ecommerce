<?php
/**
 * JerseyFlow — Navbar Component
 * File: navbar.php
 *
 * Requirements:
 *  - session_start() must be called before including this file
 *  - $conn (MySQLi connection) must be available
 */

// ─────────────────────────────────────────────
// CART COUNT
// ─────────────────────────────────────────────
$cart_count = 0;

if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($cart_count);
    $stmt->fetch();
    $stmt->close();
}

// ─────────────────────────────────────────────
// CURRENT PAGE
// ─────────────────────────────────────────────
$current_page = basename($_SERVER['PHP_SELF']);

// ─────────────────────────────────────────────
// DEFAULT VALUES
// ─────────────────────────────────────────────
$user_name     = 'Guest';
$user_email    = '';
$profile_image = null;
$user_role     = null;

// ROLE-BASED URLS (DEFAULT = USER)
$account_url = "/jerseyflow-ecommerce/users/users_homepage.php";
$profile_url = "/jerseyflow-ecommerce/users/users_profile.php";
$change_pw_url = "/jerseyflow-ecommerce/users/change_password.php";

// ─────────────────────────────────────────────
// USER DATA
// ─────────────────────────────────────────────
if (isset($_SESSION['user_id'])) {

    $uid = (int) $_SESSION['user_id'];

    // ROLE (session first, DB fallback)
    if (isset($_SESSION['role'])) {
        $user_role = $_SESSION['role'];
    } else {
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->bind_result($user_role);
        $stmt->fetch();
        $stmt->close();

        $_SESSION['role'] = $user_role;
    }

    // FETCH USER INFO
    $stmt = $conn->prepare("SELECT full_name, email, profile_image FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($user_name, $user_email, $profile_image);
    $stmt->fetch();
    $stmt->close();

    // ─────────────────────────────────────────────
    // ROLE BASED ROUTING (ALL LINKS)
    // ─────────────────────────────────────────────
    if ($user_role === 'admin') {

        $account_url   = "/jerseyflow-ecommerce/admin/admin_homepage.php";
        $profile_url   = "/jerseyflow-ecommerce/admin/admin_profile.php";
        $change_pw_url = "/jerseyflow-ecommerce/admin/admin_change_pw.php";

    } else {

        $account_url   = "/jerseyflow-ecommerce/users/users_homepage.php";
        $profile_url   = "/jerseyflow-ecommerce/users/users_profile.php";
        $change_pw_url = "/jerseyflow-ecommerce/users/change_password.php";
    }
}

// ─────────────────────────────────────────────
// INITIALS
// ─────────────────────────────────────────────
$name_parts = explode(' ', trim($user_name));
$initials = strtoupper(
    substr($name_parts[0] ?? '', 0, 1) .
    substr($name_parts[1] ?? '', 0, 1)
);

// ─────────────────────────────────────────────
// PROFILE IMAGE
// ─────────────────────────────────────────────
$avatar_src = null;

if (!empty($profile_image)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/jerseyflow-ecommerce/uploads/' . $profile_image;

    if (file_exists($file_path)) {
        $avatar_src = '/jerseyflow-ecommerce/uploads/' . htmlspecialchars($profile_image);
    }
}
?>

<!-- Font Awesome CDN -->
<link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
<!-- Navbar CSS -->
<link rel="stylesheet" href="../style/navbar.css" />
<link rel="stylesheet" href="../style/users_navbar.css" />

<!-- ══════════════════════════ NAVBAR ══════════════════════════ -->
<nav class="jf-nav" id="jfNav">

  <!-- ── Logo ─────────────────────────────────────────────────── -->
  <a href="../homepage.php" class="jf-logo" aria-label="JerseyFlow Home">
    <img src="/jerseyflow-ecommerce/images/logo.png" alt="JerseyFlow Admin" />
  </a>

  <!-- ── Center Links ──────────────────────────────────────────── -->
  <ul class="jf-links">

    <!-- Jersey (dropdown) -->
    <li class="has-drop">
      <a href="/jerseyflow-ecommerce/jersey.php">
        Jersey
        <i class="fa-solid fa-chevron-down chev"></i>
      </a>
      <div class="jf-dropdown">
        <a href="/jerseyflow-ecommerce/jersey.php?type=standard">
          <i class="fa-solid fa-shield-halved drop-icon"></i>
          Football Club Jersey
        </a>
        <a href="/jerseyflow-ecommerce/jersey.php?type=worldcup_2026">
          <i class="fa-solid fa-trophy drop-icon"></i>
          FIFA World Cup 2026 Jersey
        </a>
        <a href="/jerseyflow-ecommerce/jersey.php?type=retro">
          <i class="fa-solid fa-clock-rotate-left drop-icon"></i>
          Football Retro Jersey
        </a>
        <a href="/jerseyflow-ecommerce/jersey.php?type=country">
          <i class="fa-solid fa-earth-americas drop-icon"></i>
          Football Country Jersey
        </a>
        <a href="/jerseyflow-ecommerce/jersey.php?type=limited">
          <i class="fa-solid fa-crown drop-icon"></i>
          Limited Edition Jersey
        </a>
      </div>
    </li>

    <!-- Club (dropdown) -->
    <li class="has-drop">
      <a href="/jerseyflow-ecommerce/clubs.php">
        Club
        <i class="fa-solid fa-chevron-down chev"></i>
      </a>
      <div class="jf-dropdown">
        <a href="/jerseyflow-ecommerce/clubs.php?club=realmadrid">
          <i class="fa-solid fa-star drop-icon"></i>
          Real Madrid
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=fcbarcelona">
          <i class="fa-solid fa-star drop-icon"></i>
          FC Barcelona
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=manchesterunited">
          <i class="fa-solid fa-star drop-icon"></i>
          Manchester United
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=arsenalfc">
          <i class="fa-solid fa-star drop-icon"></i>
          Arsenal
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=liverpoolfc">
          <i class="fa-solid fa-star drop-icon"></i>
          Liverpool FC
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=acmilan">
          <i class="fa-solid fa-star drop-icon"></i>
          AC Milan
        </a>
        <a href="/jerseyflow-ecommerce/clubs.php?club=juventus">
          <i class="fa-solid fa-star drop-icon"></i>
          Juventus
        </a>
      </div>
    </li>

    <!-- About -->
    <li>
      <a href="/jerseyflow-ecommerce/about_section.php" class="<?= ($current_page === 'about_section.php') ? 'active' : '' ?>">
        About
      </a>
    </li>

    <!-- Contact -->
    <li>
      <a href="/jerseyflow-ecommerce/contact.php" class="<?= ($current_page === 'contact.php') ? 'active' : '' ?>">
        Contact
      </a>
    </li>

  </ul>

  <!-- ── Right Actions ─────────────────────────────────────────── -->
  <div class="jf-actions">

    <!-- ── Profile Dropdown ───────────────────────────────────── -->
    <div class="jf-profile-wrap">

      <!-- Trigger: profile photo or initials fallback -->
      <button class="jf-icon-btn jf-profile-trigger" aria-label="My Account" aria-haspopup="true">
        <?php if ($avatar_src): ?>
          <img
            src="<?= $avatar_src ?>"
            alt="<?= htmlspecialchars($user_name) ?>"
            class="jf-avatar-img"
          />
        <?php else: ?>
          <span class="jf-avatar"><?= htmlspecialchars($initials) ?></span>
        <?php endif; ?>
      </button>

      <!-- Dropdown menu -->
      <div class="jf-profile-dropdown">

        <!-- User info header -->
        <div class="jf-profile-head">
          <?php if ($avatar_src): ?>
            <img
              src="<?= $avatar_src ?>"
              alt="<?= htmlspecialchars($user_name) ?>"
              class="jf-avatar-img jf-avatar-img--lg"
            />
          <?php else: ?>
            <div class="jf-avatar jf-avatar--lg"><?= htmlspecialchars($initials) ?></div>
          <?php endif; ?>
          <div class="jf-profile-info">
            <span class="jf-profile-name"><?= htmlspecialchars($user_name) ?></span>
            <?php if ($user_email): ?>
              <span class="jf-profile-email"><?= htmlspecialchars($user_email) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <hr class="jf-profile-divider" />

        <!-- Menu links -->
        <a href="<?= $account_url ?>" class="jf-profile-item">
          <i class="fa-regular fa-house"></i>
          My Account
        </a>
        <a href="<?= $profile_url ?>" class="jf-profile-item">
          <i class="fa-regular fa-circle-user"></i>
          My Profile
        </a>
        <a href="<?= $change_pw_url ?>" class="jf-profile-item">
          <i class="fa-solid fa-lock"></i>
          Change Password
        </a>

        <hr class="jf-profile-divider" />

        <a href="#" class="jf-profile-item jf-profile-logout"
           onclick="jfOpenLogoutModal(); return false;">
          <i class="fa-solid fa-right-from-bracket"></i>
          Logout
        </a>

      </div>
    </div>
    <!-- ── End Profile Dropdown ───────────────────────────────── -->

    <div class="jf-divider"></div>

    <!-- Cart Button -->
    <a href="/jerseyflow-ecommerce/users/cart.php" class="jf-cart-btn" id="jfCartBtn" aria-label="View Cart">
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
        <a href="about_section.php" class="mob-link">About</a>
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
            <li><a href="clubs.php?club=realmadrid"><i class="fa-solid fa-star drop-icon"></i> Real Madrid</a></li>
            <li><a href="clubs.php?club=fcbarcelona"><i class="fa-solid fa-star drop-icon"></i> FC Barcelona</a></li>
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

<!-- ══════════════════════ LOGOUT MODAL ═══════════════════════ -->
<div id="jfLogoutModal" role="dialog" aria-modal="true" aria-labelledby="jfLogoutTitle">
  <div class="jf-logout-box">
    <div class="jf-logout-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
    <h2 id="jfLogoutTitle">Confirm Logout</h2>
    <p>Are you sure you want to log out of your account?</p>
    <div class="jf-logout-actions">
      <button class="jf-logout-cancel" onclick="jfCloseLogoutModal()">Cancel</button>
      <a href="/jerseyflow-ecommerce/logout.php" class="jf-logout-confirm">Logout</a>
    </div>
  </div>
</div>

<!-- ══════════════════════════ JS ══════════════════════════════ -->
<script src="../script/users_navbar.js"></script>
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
  const hamburger  = document.getElementById('jfHamburger');
  const mobileMenu = document.getElementById('jfMobileMenu');

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

  /* Cart count helper */
  const cartCountEl = document.getElementById('jfCartCount');
  const cartBtn     = document.getElementById('jfCartBtn');

  window.JFCart = {
    get count () { return parseInt(cartCountEl.textContent, 10) || 0; },
    add (n = 1) {
      cartCountEl.textContent = this.count + n;
      cartBtn.classList.remove('bump');
      void cartBtn.offsetWidth;
      cartBtn.classList.add('bump');
      cartBtn.addEventListener('animationend', () => cartBtn.classList.remove('bump'), { once: true });
    }
  };

  /* ── Logout modal helpers ─────────────────────────────────── */
  const logoutModal = document.getElementById('jfLogoutModal');

  window.jfOpenLogoutModal = function () {
    logoutModal.style.display = 'flex';
    setTimeout(() => {
      const cancel = logoutModal.querySelector('.jf-logout-cancel');
      if (cancel) cancel.focus();
    }, 50);
  };

  window.jfCloseLogoutModal = function () {
    logoutModal.style.display = 'none';
  };

  // Close on backdrop click
  logoutModal.addEventListener('click', function (e) {
    if (e.target === logoutModal) jfCloseLogoutModal();
  });

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
      jfCloseLogoutModal();
    }
  });

})();
</script>