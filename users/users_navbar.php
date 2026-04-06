<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>JerseyFlow — Customer Navbar</title>
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/fonts/barlow-condensed/barlow-condensed.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/cart.css">
    <link rel="stylesheet" href="../style/user_navbar.css">

</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     CUSTOMER NAVBAR
══════════════════════════════════════════════════════════ -->
<nav class="users-nav" id="usersNav">

  <!-- ── LEFT: LOGO ─────────────────────────────────────── -->
  <div class="nav-logo">
    <a href="/jerseyflow-ecommerce/index.php">
      <div class="logo-icon"><i class="fa-solid fa-shirt"></i></div>
      <span>JerseyFlow</span>
    </a>
  </div>

  <!-- ── CENTER: SEARCH ─────────────────────────────────── -->
  <div class="nav-search-wrap">
    <div class="nav-search">
      <input
        type="text"
        id="searchInput"
        placeholder="Search jerseys, teams, players..."
        autocomplete="off"
      />
      <button aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>

    <!-- Search suggestions -->
    <div class="search-suggestions" id="searchSuggestions">
      <div class="suggestion-label">Popular searches</div>
      <div class="suggestion-item"><i class="fa-solid fa-fire"></i> Real Madrid 2024/25 Kit</div>
      <div class="suggestion-item"><i class="fa-solid fa-fire"></i> Manchester City Home Jersey</div>
      <div class="suggestion-item"><i class="fa-solid fa-clock-rotate-left"></i> Barcelona Away Kit</div>
      <div class="suggestion-item"><i class="fa-solid fa-clock-rotate-left"></i> PSG 2025 Third Kit</div>
      <div class="suggestion-item"><i class="fa-solid fa-magnifying-glass"></i> Argentina World Cup Jersey</div>
    </div>
  </div>

  <!-- ── RIGHT: ACTIONS ─────────────────────────────────── -->
  <div class="nav-actions">

    <!-- 🤍 Wishlist -->
    <button class="nav-icon" onclick="window.location='/jerseyflow-ecommerce/wishlist.php'" aria-label="Wishlist">
      <i class="fa-regular fa-heart"></i>
      <span class="badge" id="wishlistCount">3</span>
    </button>

    <!-- 🛒 Cart -->
    <div class="dropdown" id="cartDropdown">
      <button class="nav-icon" aria-label="Cart" onclick="toggleDropdown('cartDropdown')">
        <i class="fa-solid fa-bag-shopping"></i>
        <span class="badge" id="cartCount">2</span>
      </button>

      <div class="dropdown-menu cart-dropdown">
        <div class="cart-header">
          <h4>Shopping Cart</h4>
          <span id="cartItemsLabel">2 items</span>
        </div>

        <div class="cart-items" id="cartItemsList">
          <!-- Item 1 -->
          <div class="cart-item" data-id="1">
            <div class="cart-thumb">🔴</div>
            <div class="cart-info">
              <div class="item-name">Manchester United Home Jersey</div>
              <div class="item-meta">Size: L &nbsp;·&nbsp; Qty: 1</div>
              <div class="item-price">NPR 4,500</div>
            </div>
            <button class="cart-remove" onclick="removeCartItem(1)" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <!-- Item 2 -->
          <div class="cart-item" data-id="2">
            <div class="cart-thumb">🔵</div>
            <div class="cart-info">
              <div class="item-name">Real Madrid Away Kit 2025</div>
              <div class="item-meta">Size: M &nbsp;·&nbsp; Qty: 1</div>
              <div class="item-price">NPR 5,200</div>
            </div>
            <button class="cart-remove" onclick="removeCartItem(2)" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>

        <div class="cart-footer">
          <div class="cart-total">
            <span class="label">Total</span>
            <span class="amount" id="cartTotal">NPR 9,700</span>
          </div>
          <div class="cart-btns">
            <a href="/jerseyflow-ecommerce/cart.php" class="btn-outline">View Cart</a>
            <a href="/jerseyflow-ecommerce/checkout.php" class="btn-fill">Checkout</a>
          </div>
        </div>
      </div>
    </div>

    <!-- 🔔 Notifications -->
    <div class="dropdown" id="notifDropdown">
      <button class="nav-icon" aria-label="Notifications" onclick="toggleDropdown('notifDropdown')">
        <i class="fa-regular fa-bell"></i>
        <span class="badge" id="notifCount">2</span>
      </button>

      <div class="dropdown-menu notif-dropdown">
        <div class="notif-header">
          <h4>Notifications</h4>
          <button class="notif-mark" onclick="markAllRead()">Mark all read</button>
        </div>

        <div class="notif-list">
          <div class="notif-item unread">
            <div class="notif-icon shipped"><i class="fa-solid fa-truck"></i></div>
            <div class="notif-body">
              <div class="notif-title">Order Shipped!</div>
              <div class="notif-sub">Your Manchester United jersey is on its way.</div>
              <div class="notif-time">2 hours ago</div>
            </div>
            <div class="notif-dot"></div>
          </div>

          <div class="notif-item unread">
            <div class="notif-icon offer"><i class="fa-solid fa-tag"></i></div>
            <div class="notif-body">
              <div class="notif-title">Flash Sale — 20% Off!</div>
              <div class="notif-sub">La Liga jerseys on sale for 24 hours only.</div>
              <div class="notif-time">5 hours ago</div>
            </div>
            <div class="notif-dot"></div>
          </div>

          <div class="notif-item">
            <div class="notif-icon delivered"><i class="fa-solid fa-circle-check"></i></div>
            <div class="notif-body">
              <div class="notif-title">Order Delivered</div>
              <div class="notif-sub">Your Barcelona home kit has been delivered.</div>
              <div class="notif-time">Yesterday</div>
            </div>
            <div class="notif-dot"></div>
          </div>
        </div>

        <div class="notif-footer">
          <a href="/jerseyflow-ecommerce/notifications.php">View All Notifications</a>
        </div>
      </div>
    </div>

    <!-- 👤 Profile -->
    <div class="dropdown" id="profileDropdown">
      <button class="nav-icon" onclick="toggleDropdown('profileDropdown')" aria-label="Profile">
        <div class="profile-trigger">
          <div class="avatar-sm">JD</div>
          <span class="user-name">John</span>
          <i class="fa-solid fa-chevron-down caret"></i>
        </div>
      </button>

      <div class="dropdown-menu profile-dropdown">
        <div class="profile-header">
          <div class="profile-avatar">JD</div>
          <div class="profile-info">
            <div class="name">John Doe</div>
            <div class="email">johndoe@email.com</div>
          </div>
        </div>

        <div class="profile-menu-items">
          <a href="/jerseyflow-ecommerce/dashboard.php">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
          </a>
          <a href="/jerseyflow-ecommerce/orders.php">
            <i class="fa-solid fa-box-open"></i> My Orders
          </a>
          <a href="/jerseyflow-ecommerce/track-order.php">
            <i class="fa-solid fa-location-dot"></i> Track Order
          </a>
          <a href="/jerseyflow-ecommerce/wishlist.php">
            <i class="fa-regular fa-heart"></i> Wishlist
          </a>
          <a href="/jerseyflow-ecommerce/profile-settings.php">
            <i class="fa-solid fa-gear"></i> Profile Settings
          </a>
          <hr />
          <a href="#" class="logout-link" onclick="openLogoutModal(); return false;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
          </a>
        </div>
      </div>
    </div>

    <!-- ☰ Hamburger (mobile) -->
    <button class="hamburger" id="hamburger" onclick="toggleMobileDrawer()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>

<!-- ── MOBILE DRAWER ────────────────────────────────────────── -->
<div class="mobile-drawer" id="mobileDrawer">
  <div class="mobile-search">
    <input type="text" placeholder="Search jerseys, teams, players..." />
    <button aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
  </div>

  <nav class="mobile-nav-links">
    <a href="/jerseyflow-ecommerce/dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="/jerseyflow-ecommerce/orders.php"><i class="fa-solid fa-box-open"></i> My Orders</a>
    <a href="/jerseyflow-ecommerce/track-order.php"><i class="fa-solid fa-location-dot"></i> Track Order</a>
    <a href="/jerseyflow-ecommerce/wishlist.php"><i class="fa-regular fa-heart"></i> Wishlist</a>
    <a href="/jerseyflow-ecommerce/cart.php"><i class="fa-solid fa-bag-shopping"></i> Cart <span id="mobileCartBadge">(2)</span></a>
    <a href="/jerseyflow-ecommerce/profile-settings.php"><i class="fa-solid fa-gear"></i> Profile Settings</a>
    <hr />
    <a href="#" class="logout-mobile" onclick="openLogoutModal(); return false;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </nav>
</div>

<!-- ── LOGOUT MODAL ─────────────────────────────────────────── -->
<div id="logoutModal">
  <div class="logout-box">
    <div class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
    <h2>Confirm Logout</h2>
    <p>Are you sure you want to log out of your account?</p>
    <div class="logout-actions">
      <button class="cancel-btn" onclick="closeLogoutModal()">Cancel</button>
      <a href="/jerseyflow-ecommerce/logout.php" class="logout-btn">Logout</a>
    </div>
  </div>
</div>

<!-- ── DEMO CONTENT ─────────────────────────────────────────── -->
<div class="page-demo">
  <i class="fa-solid fa-shirt"></i>
  <p><strong>JerseyFlow</strong> — Customer Navbar Demo</p>
  <p style="font-size:.8rem">Click the icons on the right to interact with the dropdowns.</p>
</div>

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->
<script>
/* ── DROPDOWN TOGGLE ─────────────────────────────────────── */
function toggleDropdown(id) {
  const target = document.getElementById(id);
  const allDropdowns = document.querySelectorAll('.dropdown.open');

  allDropdowns.forEach(d => {
    if (d !== target) d.classList.remove('open');
  });

  target.classList.toggle('open');
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

/* ── SEARCH SUGGESTIONS ──────────────────────────────────── */
const searchInput = document.getElementById('searchInput');
const suggestions = document.getElementById('searchSuggestions');

searchInput.addEventListener('focus', () => suggestions.classList.add('active'));
searchInput.addEventListener('blur',  () => setTimeout(() => suggestions.classList.remove('active'), 150));

searchInput.addEventListener('input', () => {
  suggestions.classList.toggle('active', searchInput.value.length === 0);
});

document.querySelectorAll('.suggestion-item').forEach(item => {
  item.addEventListener('mousedown', () => {
    searchInput.value = item.textContent.trim();
    suggestions.classList.remove('active');
  });
});

/* ── MOBILE DRAWER ───────────────────────────────────────── */
function toggleMobileDrawer() {
  const drawer = document.getElementById('mobileDrawer');
  const burger = document.getElementById('hamburger');
  drawer.classList.toggle('open');
  burger.classList.toggle('active');
}

/* ── CART REMOVE ─────────────────────────────────────────── */
function removeCartItem(id) {
  const item = document.querySelector(`.cart-item[data-id="${id}"]`);
  if (!item) return;
  item.style.transition = 'opacity .2s, max-height .25s';
  item.style.opacity = '0';
  setTimeout(() => {
    item.remove();
    updateCartUI();
  }, 220);
}

function updateCartUI() {
  const items = document.querySelectorAll('.cart-item');
  const count = items.length;
  document.getElementById('cartCount').textContent = count;
  document.getElementById('cartItemsLabel').textContent = `${count} item${count !== 1 ? 's' : ''}`;
  document.getElementById('mobileCartBadge').textContent = count > 0 ? `(${count})` : '';

  const cartItems = document.getElementById('cartItemsList');
  if (count === 0) {
    cartItems.innerHTML = `<div class="cart-empty"><i class="fa-solid fa-bag-shopping"></i>Your cart is empty</div>`;
    document.getElementById('cartTotal').textContent = 'NPR 0';
  }
}

/* ── NOTIFICATIONS ───────────────────────────────────────── */
function markAllRead() {
  document.querySelectorAll('.notif-item.unread').forEach(n => n.classList.remove('unread'));
  document.getElementById('notifCount').style.display = 'none';
}

/* ── LOGOUT MODAL ────────────────────────────────────────── */
function openLogoutModal() {
  document.getElementById('logoutModal').style.display = 'flex';
  document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  const drawer = document.getElementById('mobileDrawer');
  if (drawer.classList.contains('open')) toggleMobileDrawer();
}

function closeLogoutModal() {
  document.getElementById('logoutModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('logoutModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeLogoutModal();
});
</script>

</body>
</html>