<?php
/**
 * JerseyFlow Admin — View User
 * File: view_user.php
 */

session_start();
require_once 'connect.php';

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) { header('Location: users.php'); exit; }

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND is_deleted = 0");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: users.php'); exit; }

$active_tab = in_array($_GET['tab'] ?? '', ['details', 'activity']) ? $_GET['tab'] : 'details';

// Fetch activity logs
$logs = [];
if ($active_tab === 'activity') {
    $lstmt = $conn->prepare(
        "SELECT action, description, ip_address, created_at
         FROM user_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
    );
    $lstmt->bind_param('i', $user_id);
    $lstmt->execute();
    $logs = $lstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lstmt->close();
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>View User — JerseyFlow Admin</title>
      <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/add_products.css">
<link rel="stylesheet" href="../style/admin_panel.css" />
  <link rel="stylesheet" href="../style/users.css" />
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-user"></i> User Profile</h1>
      <p class="page-subtitle">Viewing account details for <?= htmlspecialchars($user['full_name']) ?></p>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="edit_user.php?id=<?= $user_id ?>" class="btn-add-user">
        <i class="fa-solid fa-pen-to-square"></i> Edit User
      </a>
      <a href="users.php" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Back
      </a>
    </div>
  </div>

  <?php if ($toast): ?>
    <div class="toast toast-<?= htmlspecialchars($toast['type']) ?>" id="pageToast">
      <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($toast['msg']) ?>
      <button class="toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
  <?php endif; ?>

  <div class="view-layout">

    <!-- Profile Card -->
    <div class="profile-card">
      <div class="profile-avatar-wrap">
        <?php if ($user['profile_image'] && file_exists('../uploads/' . $user['profile_image'])): ?>
          <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>" class="profile-avatar-img" alt="" />
        <?php else: ?>
          <div class="profile-avatar-placeholder">
            <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <span class="profile-status-dot status-<?= $user['status'] ?>"></span>
      </div>
      <h2 class="profile-name"><?= htmlspecialchars($user['full_name']) ?></h2>
      <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>

      <div class="profile-badges">
        <span class="badge badge-role badge-<?= $user['role'] ?>">
          <?= $user['role'] === 'admin' ? '<i class="fa-solid fa-shield-halved"></i>' : '<i class="fa-solid fa-user"></i>' ?>
          <?= ucfirst($user['role']) ?>
        </span>
        <span class="badge badge-status badge-<?= $user['status'] ?>">
          <i class="fa-solid fa-circle status-dot"></i>
          <?= ucfirst($user['status']) ?>
        </span>
      </div>

      <div class="profile-meta">
        <div class="profile-meta-row">
          <i class="fa-solid fa-calendar-plus"></i>
          Joined <?= date('M d, Y', strtotime($user['created_at'])) ?>
        </div>
        <?php if ($user['phone']): ?>
        <div class="profile-meta-row">
          <i class="fa-solid fa-phone"></i>
          <?= htmlspecialchars($user['phone']) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Quick Actions -->
      <div class="profile-quick-actions">
        <?php if ($user['status'] === 'active'): ?>
          <button class="btn-quick btn-quick-warn"
            onclick="confirmAction('block', <?= $user_id ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')">
            <i class="fa-solid fa-ban"></i> Block User
          </button>
        <?php else: ?>
          <button class="btn-quick btn-quick-success"
            onclick="confirmAction('activate', <?= $user_id ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')">
            <i class="fa-solid fa-circle-check"></i> Activate
          </button>
        <?php endif; ?>
        <button class="btn-quick btn-quick-danger"
          onclick="confirmAction('delete', <?= $user_id ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')">
          <i class="fa-solid fa-trash"></i> Delete
        </button>
      </div>
    </div>

    <!-- Detail Panel -->
    <div class="detail-panel">

      <!-- Tabs -->
      <div class="tab-nav">
        <a href="?id=<?= $user_id ?>&tab=details"  class="tab-link <?= $active_tab === 'details'  ? 'active' : '' ?>">
          <i class="fa-solid fa-id-card"></i> Details
        </a>
        <a href="?id=<?= $user_id ?>&tab=activity" class="tab-link <?= $active_tab === 'activity' ? 'active' : '' ?>">
        </a>
      </div>

      <?php if ($active_tab === 'details'): ?>
      <!-- Details Tab -->
      <div class="detail-grid">
        <div class="detail-group">
          <span class="detail-label">Full Name</span>
          <span class="detail-value"><?= htmlspecialchars($user['full_name']) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Email Address</span>
          <span class="detail-value"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Phone</span>
          <span class="detail-value"><?= $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="no-data">Not provided</span>' ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Role</span>
          <span class="detail-value"><?= ucfirst($user['role']) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Status</span>
          <span class="detail-value"><?= ucfirst($user['status']) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Created At</span>
          <span class="detail-value"><?= date('M d, Y — h:i A', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">Last Updated</span>
          <span class="detail-value"><?= date('M d, Y — h:i A', strtotime($user['updated_at'])) ?></span>
        </div>
        <div class="detail-group detail-group-full">
          <span class="detail-label">Address</span>
          <span class="detail-value"><?= $user['address'] ? nl2br(htmlspecialchars($user['address'])) : '<span class="no-data">Not provided</span>' ?></span>
        </div>
      </div>

      <?php else: ?>
      <!-- Activity Tab -->
      <div class="activity-wrap">
        <?php if (empty($logs)): ?>
          <div class="empty-state" style="padding:48px 0;">
            <div class="empty-inner">
              <i class="fa-solid fa-rectangle-list"></i>
              <p>No activity logs found for this user.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="activity-timeline">
            <?php foreach ($logs as $log): ?>
              <div class="activity-item">
                <div class="activity-dot activity-dot-<?= strtolower(str_replace(' ', '_', $log['action'])) ?>">
                  <i class="fa-solid <?= getActivityIcon($log['action']) ?>"></i>
                </div>
                <div class="activity-body">
                  <span class="activity-action"><?= htmlspecialchars($log['action']) ?></span>
                  <span class="activity-desc"><?= htmlspecialchars($log['description']) ?></span>
                  <span class="activity-meta">
                    <i class="fa-solid fa-clock"></i> <?= date('M d, Y h:i A', strtotime($log['created_at'])) ?>
                    <?php if ($log['ip_address']): ?>
                      &nbsp;·&nbsp; <i class="fa-solid fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?>
                    <?php endif; ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-icon" id="modalIcon"></div>
    <h3 class="modal-title" id="modalTitle"></h3>
    <p class="modal-msg"  id="modalMsg"></p>
    <div class="modal-actions">
      <button class="btn-modal-cancel"  onclick="closeModal()">Cancel</button>
      <button class="btn-modal-confirm" id="modalConfirmBtn" onclick="executeAction()">Confirm</button>
    </div>
  </div>
</div>
</div>
</div>

<form method="POST" action="user_actions.php" id="actionForm">
  <input type="hidden" name="action"   id="actionField" />
  <input type="hidden" name="user_id"  id="userIdField" />
  <input type="hidden" name="redirect" value="users.php" />
</form>
<?php include("footer.php") ?>

<script src="../script/users.js"></script>
</body>
</html>
<?php
function getActivityIcon(string $action): string {
    return match(strtolower($action)) {
        'login'     => 'fa-right-to-bracket',
        'blocked'   => 'fa-ban',
        'activated' => 'fa-circle-check',
        'deleted'   => 'fa-trash',
        'updated'   => 'fa-pen-to-square',
        default     => 'fa-circle-dot',
    };
}
?>