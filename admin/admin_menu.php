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
  <a href="dashboard.php" class="sidebar-brand">
    <span class="sidebar-brand-icon">⚙️</span>
    <span class="sidebar-brand-text">
      <span class="sidebar-brand-name">AdminPanel</span>
      <span class="sidebar-brand-sub">Control Center</span>
    </span>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <!-- ── 1. Dashboard ─────────────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['dashboard', 'overview', 'analytics'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('dashboard', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-gauge-high"></i></span>
        <span class="sidebar-link-label">Dashboard</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="dashboard.php"      class="sidebar-dropdown-link<?= isActive('dashboard', $current_page) ?>">Overview</a>
        <a href="analytics.php"      class="sidebar-dropdown-link<?= isActive('analytics', $current_page) ?>">Analytics</a>
        <a href="reports.php"        class="sidebar-dropdown-link<?= isActive('reports', $current_page) ?>">Reports</a>
      </div>
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
        <a href="products.php"             class="sidebar-dropdown-link<?= isActive('products', $current_page) ?>">All Products</a>
        <a href="add_product.php"          class="sidebar-dropdown-link<?= isActive('add_product', $current_page) ?>">Add New Product</a>
        <a href="product_categories.php"   class="sidebar-dropdown-link<?= isActive('product_categories', $current_page) ?>">Categories</a>
        <a href="product_attributes.php"   class="sidebar-dropdown-link<?= isActive('product_attributes', $current_page) ?>">Attributes &amp; Variants</a>
      </div>
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
        <a href="orders.php"       class="sidebar-dropdown-link<?= isActive('orders', $current_page) ?>">All Orders</a>
        <a href="shipments.php"    class="sidebar-dropdown-link<?= isActive('shipments', $current_page) ?>">Shipments</a>
        <a href="returns.php"      class="sidebar-dropdown-link<?= isActive('returns', $current_page) ?>">Returns &amp; Refunds</a>
      </div>
    </div>

    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">People</div>

    <!-- ── 4. Users Management ───────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['users', 'add_user', 'user_roles'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('users', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-users"></i></span>
        <span class="sidebar-link-label">Users Management</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="users.php"      class="sidebar-dropdown-link<?= isActive('users', $current_page) ?>">All Users</a>
        <a href="add_user.php"   class="sidebar-dropdown-link<?= isActive('add_user', $current_page) ?>">Add User</a>
        <a href="user_roles.php" class="sidebar-dropdown-link<?= isActive('user_roles', $current_page) ?>">Roles &amp; Permissions</a>
      </div>
    </div>

    <!-- ── 5. Club Management ────────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['clubs', 'club_members', 'club_events', 'club_rewards'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('clubs', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-shield-halved"></i></span>
        <span class="sidebar-link-label">Club Management</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="clubs.php"         class="sidebar-dropdown-link<?= isActive('clubs', $current_page) ?>">All Clubs</a>
        <a href="club_members.php"  class="sidebar-dropdown-link<?= isActive('club_members', $current_page) ?>">Members</a>
        <a href="club_events.php"   class="sidebar-dropdown-link<?= isActive('club_events', $current_page) ?>">Events</a>
        <a href="club_rewards.php"  class="sidebar-dropdown-link<?= isActive('club_rewards', $current_page) ?>">Rewards &amp; Points</a>
      </div>
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
        <a href="inventory.php"       class="sidebar-dropdown-link<?= isActive('inventory', $current_page) ?>">Stock Overview</a>
        <a href="stock_alerts.php"    class="sidebar-dropdown-link<?= isActive('stock_alerts', $current_page) ?>">Low-Stock Alerts</a>
        <a href="suppliers.php"       class="sidebar-dropdown-link<?= isActive('suppliers', $current_page) ?>">Suppliers</a>
        <a href="purchase_orders.php" class="sidebar-dropdown-link<?= isActive('purchase_orders', $current_page) ?>">Purchase Orders</a>
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
        <a href="payments.php"      class="sidebar-dropdown-link<?= isActive('payments', $current_page) ?>">All Payments</a>
        <a href="transactions.php"  class="sidebar-dropdown-link<?= isActive('transactions', $current_page) ?>">Transaction Log</a>
        <a href="invoices.php"      class="sidebar-dropdown-link<?= isActive('invoices', $current_page) ?>">Invoices</a>
        <a href="payouts.php"       class="sidebar-dropdown-link<?= isActive('payouts', $current_page) ?>">Payouts</a>
      </div>
    </div>

    <!-- ── 8. Review / Feedback ──────────────────────────────────────────── -->
    <div class="sidebar-item<?= isOpen(['reviews', 'feedback', 'flagged_reviews'], $current_page) ?>">
      <button class="sidebar-link<?= isActive('reviews', $current_page) ?>"
              onclick="toggleMenu(this)">
        <span class="sidebar-link-icon"><i class="fa-solid fa-star-half-stroke"></i></span>
        <span class="sidebar-link-label">Review / Feedback</span>
        <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
      </button>
      <div class="sidebar-dropdown">
        <a href="reviews.php"         class="sidebar-dropdown-link<?= isActive('reviews', $current_page) ?>">All Reviews</a>
        <a href="feedback.php"        class="sidebar-dropdown-link<?= isActive('feedback', $current_page) ?>">Customer Feedback</a>
        <a href="flagged_reviews.php" class="sidebar-dropdown-link<?= isActive('flagged_reviews', $current_page) ?>">Flagged / Pending</a>
      </div>
    </div>

  </nav><!-- /sidebar-nav -->

  <!-- ── Footer: Logout ───────────────────────────────────────────────────── -->
  <div class="sidebar-footer">
    <form method="POST" action="logout.php">
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

</aside><!-- /admin-sidebar -->

<!-- ── Sidebar Toggle Script ─────────────────────────────────────────────── -->
<script>
/**
 * toggleMenu(btn)
 * Toggles the .open class on the parent .sidebar-item.
 * Other open items are closed automatically (accordion behaviour).
 */
function toggleMenu(btn) {
    const item    = btn.closest('.sidebar-item');
    const isOpen  = item.classList.contains('open');

    // Close all siblings first (accordion)
    item.parentElement.querySelectorAll('.sidebar-item.open').forEach(el => {
        el.classList.remove('open');
    });

    if (!isOpen) {
        item.classList.add('open');
    }
}

// Auto-open the active parent on page load
document.addEventListener('DOMContentLoaded', () => {
    const activeChild = document.querySelector('.sidebar-dropdown .sidebar-dropdown-link.active');
    if (activeChild) {
        const parentItem = activeChild.closest('.sidebar-item');
        if (parentItem) parentItem.classList.add('open');
    }
});
</script>