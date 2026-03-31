<?php
/**
 * JerseyFlow — Admin Navbar Component
 * File: admin_navbar.php
 *
 * Usage: <?php include 'admin_navbar.php'; ?>
 *
 * Make sure session_start() is called before including this file.
 */


// Admin name (optional, adjust based on your DB/session)
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

// Current page for active state (optional future use)
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../style/admin_navbar.css" />

<!-- ═════════════════════ ADMIN NAVBAR ═════════════════════ -->
<nav class="admin-nav" id="adminNav">

  <!-- ── LEFT: BRAND ───────────────────────────── -->
  <div class="admin-logo">
    <a href="admin_homepage.php">
      <img src="/jerseyflow-ecommerce/images/logo.png" alt="JerseyFlow Admin" />
    </a>
  </div>

  <!-- ── CENTER: SEARCH (optional but powerful) ── -->
  <div class="admin-search">
    <input
      type="text"
      placeholder="Search users, orders, jerseys..."
      id="adminSearchInput"
    />
    <button aria-label="Search">
      <i class="fa-solid fa-magnifying-glass"></i>
    </button>
  </div>

  <!-- ── RIGHT: ACTIONS ────────────────────────── -->
  <div class="admin-actions">

    <!-- 🔔 Notifications -->
    <div class="admin-icon dropdown">
      <i class="fa-regular fa-bell"></i>

      <div class="dropdown-menu">
        <h4>Notifications</h4>
        <a href="orders.php">🛒 New order received</a>
        <a href="stock.php">⚠ Low stock alert</a>
        <a href="users.php">👤 New user registered</a>
      </div>
    </div>

    <!-- ⚡ Quick Actions -->
    <div class="admin-icon dropdown">
      <i class="fa-solid fa-bolt"></i>

      <div class="dropdown-menu">
        <h4>Quick Actions</h4>
        <a href="add_jersey.php">➕ Add Jersey</a>
        <a href="orders.php">📦 View Orders</a>
        <a href="users.php">👥 Manage Users</a>
      </div>
    </div>

    <!-- 💬 Messages -->
    <div class="admin-icon dropdown">
      <i class="fa-regular fa-envelope"></i>

      <div class="dropdown-menu">
        <h4>Messages</h4>
        <a href="messages.php">View all messages</a>
      </div>
    </div>

    <!-- 👤 PROFILE -->
    <div class="admin-profile dropdown">
      <i class="fa-regular fa-circle-user"></i>
      <span class="admin-name"><?= htmlspecialchars($admin_name) ?></span>

      <div class="dropdown-menu profile-menu">
        <a href="profile.php">My Profile</a>
        <a href="change_password.php">Change Password</a>
        <hr />
          <a href="#" onclick="openLogoutModal()">Logout</a>
      </div>
    </div>

  </div>
</nav>

<!-- LOGOUT MODAL -->
<div id="logoutModal">
  <div class="logout-box">

    <h2>Confirm Logout</h2>
    <p>Are you sure you want to logout?</p>

    <div class="logout-actions">
      <button class="cancel-btn" onclick="closeLogoutModal()">Cancel</button>
      <a href="../homepage.php" class="logout-btn">Logout</a>
    </div>

  </div>
</div>
<!-- ═════════════════════ JS ═════════════════════ -->
<script src="../script/admin_navbar.js"></script>
