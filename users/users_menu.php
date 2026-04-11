<?php
/**
 * admin_menu.php
 * Include this file inside your admin layout to render the left sidebar.
 * Usage: <?php include 'admin_menu.php'; ?>
 *
 * Requires: admin_menu.css (link it in your <head>)
 * Requires: Font Awesome 6 (or swap icons to your preferred library)
 */

// ── Determine active page ─────────────────────────────────────────────────
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Helper: returns 'active' class string when the given page matches current
function isActive(string $page, string $current): string {
    return $page === $current ? ' active' : '';
}

// Helper: returns 'open' on the parent item when any child page is active
function isOpen(array $pages, string $current): string {
    return in_array($current, $pages, true) ? ' open' : '';
}
?>

<!-- ── Admin Sidebar ───────────────────────────────────────────────────── -->
<link rel="stylesheet" href="../style/admin_menu.css">
<!-- Font Awesome (swap CDN link if you self-host) -->
<link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">

<aside class="admin-sidebar" id="adminSidebar">

  <!-- Brand -->
  <!-- <a href="dashboard.php" class="sidebar-brand">
    <span class="sidebar-brand-icon">⚙️</span>
    <span class="sidebar-brand-text">
      <span class="sidebar-brand-name">AdminPanel</span>
      <span class="sidebar-brand-sub">Control Center</span>
    </span>
  </a> -->

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <!-- ── 1. Dashboard ─────────────────────────────────────────────────── -->
<div class="sidebar-item<?= isOpen(['dashboard'], $current_page) ?>">
<a href="/jerseyflow-ecommerce/users/users_homepage.php"     class="sidebar-link<?= isActive('dashboard', $current_page) ?>">
     
    <span class="sidebar-link-icon">
      <i class="fa-solid fa-gauge-high"></i>
    </span>
    <span class="sidebar-link-label">Dashboard</span>
  </a>
</div>

    <div class="sidebar-divider"></div>


      <!-- ── 3. Orders Management ──────────────────────────────────────────── -->
<div class="sidebar-item">
<button class="sidebar-link" onclick="window.location.href='/jerseyflow-ecommerce/users/users_orders.php'">
          <span class="sidebar-link-icon"><i class="fa-solid fa-users"></i></span>
        <span class="sidebar-link-label">My Orders</span>
      </button>
    </div>

    <div class="sidebar-item<?= isOpen(['orders', 'order_detail', 'shipments', 'returns'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('orders', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-bag-shopping"></i></span>
        <span class="sidebar-link-label">Profile Settings</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
<a href="/jerseyflow-ecommerce/users/users_profile.php" class="sidebar-dropdown-link"> Personal Information</a>
<a href="/jerseyflow-ecommerce/users/address_book.php" class="sidebar-dropdown-link"> Address Book </a>
<a href="/jerseyflow-ecommerce/users/change_password.php" class="sidebar-dropdown-link"> Change Password </a>
    </div>
    </div>

  <div class="sidebar-footer">

  <!-- ── Footer: Logout ───────────────────────────────────────────────────── -->
    <form method="POST" action="../logout.php">
      <?php
        // CSRF token – assumes you have session_start() called on the parent page
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
      ?>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <button type="submit" class="sidebar-logout">
        <span class="sidebar-logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
        <span>Logout</span>
      </button>
    </form>
  </div>
  </nav><!-- /sidebar-nav -->

</aside><!-- /admin-sidebar -->

<script src="../script/admin_menu.js"></script>
