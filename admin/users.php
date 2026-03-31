<?php
/**
 * JerseyFlow Admin — User Management
 * File: users.php
 */

session_start();
require_once 'connect.php';
require_once 'user_logger.php';


// ── Pagination ────────────────────────────────────────────────
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── Filters ───────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_role   = $_GET['role']   ?? '';

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['u.is_deleted = 0'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if (in_array($filter_status, ['active', 'blocked'])) {
    $where[]  = 'u.status = ?';
    $params[] = $filter_status;
    $types   .= 's';
}
if (in_array($filter_role, ['admin', 'user'])) {
    $where[]  = 'u.role = ?';
    $params[] = $filter_role;
    $types   .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ── Total count ───────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) FROM users u $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_users / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// ── Fetch users ───────────────────────────────────────────────
$data_sql  = "SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.status, u.profile_image, u.created_at
              FROM users u $where_sql ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$data_stmt = $conn->prepare($data_sql);
$fetch_types  = $types . 'ii';
$fetch_params = array_merge($params, [$per_page, $offset]);
$data_stmt->bind_param($fetch_types, ...$fetch_params);
$data_stmt->execute();
$users = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Stats ─────────────────────────────────────────────────────
$stats_res = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(status='active') AS active,
    SUM(status='blocked') AS blocked,
    SUM(role='admin') AS admins
    FROM users WHERE is_deleted = 0");
$stats = $stats_res->fetch_assoc();

// ── Toast from redirect ───────────────────────────────────────
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management — JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../style/admin_panel.css" />
  <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/add_products.css">
    <link rel="stylesheet" href="../style/all_products.css">
    <link rel="stylesheet" href="../style/users.css" />

</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-users"></i> User Management</h1>
      <p class="page-subtitle">Manage accounts, roles, and access control</p>
    </div>
  </div>

  <!-- Toast -->
  <?php if ($toast): ?>
    <div class="toast toast-<?= htmlspecialchars($toast['type']) ?>" id="pageToast">
      <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($toast['msg']) ?>
      <button class="toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
  <?php endif; ?>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
      <div class="stat-body">
        <span class="stat-value"><?= number_format($stats['total']) ?></span>
        <span class="stat-label">Total Users</span>
      </div>
    </div>
    <div class="stat-card stat-active">
      <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="stat-body">
        <span class="stat-value"><?= number_format($stats['active']) ?></span>
        <span class="stat-label">Active</span>
      </div>
    </div>
    <div class="stat-card stat-blocked">
      <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
      <div class="stat-body">
        <span class="stat-value"><?= number_format($stats['blocked']) ?></span>
        <span class="stat-label">Blocked</span>
      </div>
    </div>
    <div class="stat-card stat-admin">
      <div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="stat-body">
        <span class="stat-value"><?= number_format($stats['admins']) ?></span>
        <span class="stat-label">Admins</span>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-card">
    <form method="GET" action="users.php" id="filterForm">
      <div class="filter-row">
        <div class="search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input
            type="text"
            name="search"
            placeholder="Search by name or email…"
            value="<?= htmlspecialchars($search) ?>"
            class="search-input"
            autocomplete="off"
          />
          <?php if ($search): ?>
            <button type="button" class="search-clear" onclick="clearSearch()"><i class="fa-solid fa-xmark"></i></button>
          <?php endif; ?>
        </div>

        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="active"   <?= $filter_status === 'active'  ? 'selected' : '' ?>>Active</option>
          <option value="blocked"  <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
        </select>

        <select name="role" class="filter-select" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="user"  <?= $filter_role === 'user'  ? 'selected' : '' ?>>User</option>
        </select>

        <button type="submit" class="btn-search"><i class="fa-solid fa-search"></i> Search</button>

        <?php if ($search || $filter_status || $filter_role): ?>
          <a href="users.php" class="btn-clear-filters"><i class="fa-solid fa-filter-circle-xmark"></i> Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Bulk Action Bar -->
  <div class="bulk-bar" id="bulkBar">
    <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
    <div class="bulk-actions">
      <button class="btn-bulk btn-bulk-activate"  onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check"></i> Activate</button>
      <button class="btn-bulk btn-bulk-block"     onclick="bulkAction('block')">   <i class="fa-solid fa-ban"></i> Block</button>
      <button class="btn-bulk btn-bulk-delete"    onclick="bulkAction('delete')">  <i class="fa-solid fa-trash"></i> Delete</button>
    </div>
    <button class="btn-bulk-cancel" onclick="clearSelection()"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- Table Card -->
  <div class="table-card">
    <div class="table-header">
      <span class="table-title">
        <?php if ($total_users > 0): ?>
          Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_users) ?> of <?= $total_users ?> users
        <?php else: ?>
          No users found
        <?php endif; ?>
      </span>
    </div>

    <div class="table-wrap">
      <table class="users-table" id="usersTable">
        <thead>
          <tr>
            <th class="col-check">
              <input type="checkbox" id="selectAll" class="jf-checkbox" />
            </th>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="empty-state">
                <div class="empty-inner">
                  <i class="fa-solid fa-users-slash"></i>
                  <p>No users match your filters.</p>
                  <a href="users.php" class="btn-back-plain">Clear filters</a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr class="user-row" data-id="<?= $u['user_id'] ?>">
                <td class="col-check">
                  <input type="checkbox" class="jf-checkbox row-check" value="<?= $u['user_id'] ?>" />
                </td>
                <td class="col-user">
                  <div class="user-cell">
                    <div class="user-avatar">
                      <?php if ($u['profile_image'] && file_exists('../uploads/' . $u['profile_image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($u['profile_image']) ?>" alt="" />
                      <?php else: ?>
                        <span><?= strtoupper(mb_substr($u['full_name'], 0, 1)) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="user-info">
                      <span class="user-name"><?= htmlspecialchars($u['full_name']) ?></span>
                      <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge badge-role badge-<?= $u['role'] ?>">
                    <?= $u['role'] === 'admin' ? '<i class="fa-solid fa-shield-halved"></i>' : '<i class="fa-solid fa-user"></i>' ?>
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge badge-status badge-<?= $u['status'] ?>">
                    <i class="fa-solid fa-circle status-dot"></i>
                    <?= ucfirst($u['status']) ?>
                  </span>
                </td>
                <td class="col-date">
                  <span class="date-text"><?= date('M d, Y', strtotime($u['created_at'])) ?></span>
                  <span class="date-sub"><?= date('h:i A', strtotime($u['created_at'])) ?></span>
                </td>
                <td class="col-actions">
                  <div class="action-wrap">
                    <button class="btn-action-toggle" onclick="toggleDropdown(this)" aria-label="Actions">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="action-dropdown">
                      <a href="view_user.php?id=<?= $u['user_id'] ?>" class="action-item">
                        <i class="fa-solid fa-eye"></i> View
                      </a>
                      <a href="edit_user.php?id=<?= $u['user_id'] ?>" class="action-item">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                      </a>
                      <a href="view_user.php?id=<?= $u['user_id'] ?>&tab=activity" class="action-item">
                      </a>
                      <div class="action-divider"></div>
                      <?php if ($u['status'] === 'active'): ?>
                        <button class="action-item action-warn"
                          onclick="confirmAction('block', <?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                          <i class="fa-solid fa-ban"></i> Block
                        </button>
                      <?php else: ?>
                        <button class="action-item action-success"
                          onclick="confirmAction('activate', <?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                          <i class="fa-solid fa-circle-check"></i> Activate
                        </button>
                      <?php endif; ?>
                      <button class="action-item action-danger"
                        onclick="confirmAction('delete', <?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                        <i class="fa-solid fa-trash"></i> Delete
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        $q = http_build_query(array_filter([
          'search' => $search,
          'status' => $filter_status,
          'role'   => $filter_role,
        ]));
        $q = $q ? "&$q" : '';
        ?>
        <a href="?page=1<?= $q ?>"        class="page-btn <?= $page === 1          ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="?page=<?= $page - 1 ?><?= $q ?>" class="page-btn <?= $page === 1  ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>

        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) echo '<span class="page-gap">…</span>';
        for ($p = $start; $p <= $end; $p++):
        ?>
          <a href="?page=<?= $p ?><?= $q ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor;
        if ($end < $total_pages) echo '<span class="page-gap">…</span>';
        ?>

        <a href="?page=<?= $page + 1 ?><?= $q ?>" class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="?page=<?= $total_pages ?><?= $q ?>" class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-icon" id="modalIcon"></div>
    <h3 class="modal-title" id="modalTitle"></h3>
    <p class="modal-msg"  id="modalMsg"></p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-modal-confirm" id="modalConfirmBtn" onclick="executeAction()">Confirm</button>
    </div>
  </div>
</div>
</div>

<!-- Hidden form for actions -->
<form method="POST" action="user_actions.php" id="actionForm">
  <input type="hidden" name="action"  id="actionField" />
  <input type="hidden" name="user_id" id="userIdField" />
  <input type="hidden" name="bulk_ids" id="bulkIdsField" />
  <input type="hidden" name="redirect" value="users.php" />
</form>

<?php include("footer.php")?>
<script src="../script/users.js"></script>
</body>
</html>