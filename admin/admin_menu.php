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
<a href="/jerseyflow-ecommerce/admin/admin_homepage.php"     class="sidebar-link<?= isActive('dashboard', $current_page) ?>">
     
    <span class="sidebar-link-icon">
      <i class="fa-solid fa-gauge-high"></i>
    </span>
    <span class="sidebar-link-label">Dashboard</span>
  </a>
</div>

    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">Catalog &amp; Sales</div>

    <!-- ── 2. Products Management ────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['products', 'add_product', 'product_categories', 'product_attributes'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('products', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-box-open"></i></span>
        <span class="sidebar-link-label">Products Management</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
<a href="/jerseyflow-ecommerce/admin/all_products.php" class="sidebar-dropdown-link"> All Products</a>
<a href="/jerseyflow-ecommerce/admin/add_products.php" class="sidebar-dropdown-link"> Add Products</a>
<a href="/jerseyflow-ecommerce/admin/categories.php" class="sidebar-dropdown-link"> Categories</a>      </div>
    </div>

    <!-- ── 3. Orders Management ──────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['orders', 'order_detail', 'shipments', 'returns'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('orders', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-bag-shopping"></i></span>
        <span class="sidebar-link-label">Orders Management</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
<a href="/jerseyflow-ecommerce/admin/orders_management/all_orders.php" class="sidebar-dropdown-link"> All Orders</a>
<!-- <a href="/jerseyflow-ecommerce/admin/orders_management/pending.php" class="sidebar-dropdown-link"> Pending </a>
<a href="/jerseyflow-ecommerce/admin/orders_management/delivered.php" class="sidebar-dropdown-link"> Delivered</a> -->
<!-- <a href="/jerseyflow-ecommerce/admin/orders_management/cancelled.php" class="sidebar-dropdown-link"> Cancelled </a>
<a href="/jerseyflow-ecommerce/admin/orders_management/returned.php" class="sidebar-dropdown-link"> Returned </a> -->
<!-- <a href="/jerseyflow-ecommerce/admin/orders_management/high_value_orders.php" class="sidebar-dropdown-link"> High Value Orders</a> -->

      </div>
    </div>

    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">People</div>

    <!-- ── 4. Users Management ───────────────────────────────────────────── -->
    <div class="sidebar-item">
<button class="sidebar-link" onclick="window.location.href='/jerseyflow-ecommerce/admin/users.php'">
          <span class="sidebar-link-icon"><i class="fa-solid fa-users"></i></span>
        <span class="sidebar-link-label">Users Management</span>
      </button>
    </div>

    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">Operations</div>

    <!-- ── 6. Inventory Management ───────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['inventory', 'stock_alerts', 'suppliers', 'purchase_orders'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('inventory', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-warehouse"></i></span>
        <span class="sidebar-link-label">Inventory Management</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="/jerseyflow-ecommerce/admin/ims/ims_dashboard.php" class="sidebar-dropdown-link">Dashboard</a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_stock_levels.php" class="sidebar-dropdown-link">Stock Levels</a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_movements.php" class="sidebar-dropdown-link">Stock Movement</a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_adjust.php" class="sidebar-dropdown-link">Adjust Stock </a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_low_stock.php" class="sidebar-dropdown-link">Low Stock Alerts</a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_reorder.php" class="sidebar-dropdown-link">Reorder Management</a>
        <a href="/jerseyflow-ecommerce/admin/ims/ims_reports.php" class="sidebar-dropdown-link">Inventory Reports</a>

      </div>
    </div>

    <!-- ── 7. Payment / Transactions ─────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['payments', 'transactions', 'invoices', 'payouts'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('payments', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-credit-card"></i></span>
        <span class="sidebar-link-label">Payment / Transactions</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="/jerseyflow-ecommerce/admin/pymt/all_transaction.php" class="sidebar-dropdown-link">All Transaction</a>
        <a href="/jerseyflow-ecommerce/admin/pymt/pending_payments.php" class="sidebar-dropdown-link">Pending Payments</a>
        <a href="/jerseyflow-ecommerce/admin/pymt/failed_transactions.php" class="sidebar-dropdown-link">Failed Transactions</a>
        <!-- <a href="/jerseyflow-ecommerce/admin/pymt/refund_requests.php" class="sidebar-dropdown-link">Refund Requests</a> -->
        <a href="/jerseyflow-ecommerce/admin/pymt/reports.php" class="sidebar-dropdown-link"> Reports / Analytics</a>

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
