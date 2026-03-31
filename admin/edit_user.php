<?php
/**
 * JerseyFlow Admin — Edit User
 * File: edit_user.php
 */

session_start();
require_once 'connect.php';
require_once 'user_logger.php';

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) { header('Location: users.php'); exit; }

// Fetch existing user
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND is_deleted = 0");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: users.php'); exit; }

$errors = [];
$success = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $role      = $_POST['role'] ?? '';
    $status    = $_POST['status'] ?? '';

    // Validation
    if ($full_name === '') $errors['full_name'] = 'Full name is required.';
    elseif (mb_strlen($full_name) < 2) $errors['full_name'] = 'Name must be at least 2 characters.';

    if ($email === '') $errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format.';

    if (!in_array($role, ['admin', 'user'])) $errors['role'] = 'Invalid role selected.';
    if (!in_array($status, ['active', 'blocked'])) $errors['status'] = 'Invalid status.';

    if ($phone !== '' && !preg_match('/^[+\d\s\-()]{7,20}$/', $phone)) {
        $errors['phone'] = 'Invalid phone number format.';
    }

    // Check email uniqueness (excluding this user)
    if (!isset($errors['email'])) {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? AND is_deleted = 0");
        $chk->bind_param('si', $email, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors['email'] = 'This email is already in use.';
        $chk->close();
    }

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=?, role=?, status=?, updated_at=NOW() WHERE user_id=?");
        $upd->bind_param('ssssssi', $full_name, $email, $phone, $address, $role, $status, $user_id);

        if ($upd->execute()) {
            log_activity($conn, $user_id, 'Updated', 'Admin updated user profile.', $_SERVER['REMOTE_ADDR'] ?? '');
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'User updated successfully.'];
            header("Location: view_user.php?id=$user_id");
            exit;
        } else {
            $errors['general'] = 'Database error. Please try again.';
        }
        $upd->close();
    }

    // Keep submitted values on error
    $user['full_name'] = $full_name;
    $user['email']     = $email;
    $user['phone']     = $phone;
    $user['address']   = $address;
    $user['role']      = $role;
    $user['status']    = $status;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit User — JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/add_products.css">
  <link rel="stylesheet" href="../style/admin_panel.css" />
  <link rel="stylesheet" href="../style/users.css" />
  <link rel="stylesheet" href="../style/add_products.css" />
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa-solid fa-pen-to-square"></i> Edit User</h1>
      <p class="page-subtitle">Update details for <?= htmlspecialchars($user['full_name']) ?></p>
    </div>
    <a href="view_user.php?id=<?= $user_id ?>" class="btn-back">
      <i class="fa-solid fa-arrow-left"></i> Back to Profile
    </a>
  </div>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errors['general']) ?></div>
  <?php endif; ?>

  <div class="form-card">
    <form method="POST" novalidate>
      <div class="form-grid">

        <!-- Left Column -->
        <div class="form-col">
          <p class="form-section-title"><i class="fa-solid fa-id-card"></i> &nbsp;Personal Information</p>

          <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>"
              placeholder="Enter full name" required />
            <?php if (!empty($errors['full_name'])): ?>
              <span class="field-error" style="display:block"><?= htmlspecialchars($errors['full_name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Email Address <span class="required">*</span></label>
            <input type="text" name="email" value="<?= htmlspecialchars($user['email']) ?>"
              placeholder="user@example.com" required />
            <?php if (!empty($errors['email'])): ?>
              <span class="field-error" style="display:block"><?= htmlspecialchars($errors['email']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Phone <span class="field-hint">(optional)</span></label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
              placeholder="+1 555 000 0000" />
            <?php if (!empty($errors['phone'])): ?>
              <span class="field-error" style="display:block"><?= htmlspecialchars($errors['phone']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Address <span class="field-hint">(optional)</span></label>
            <textarea name="address" placeholder="Shipping / billing address…"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Right Column -->
        <div class="form-col">
          <p class="form-section-title"><i class="fa-solid fa-sliders"></i> &nbsp;Account Settings</p>

          <div class="form-group">
            <label>Role <span class="required">*</span></label>
            <select name="role">
              <option value="user"  <?= $user['role'] === 'user'  ? 'selected' : '' ?>>User</option>
              <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if (!empty($errors['role'])): ?>
              <span class="field-error" style="display:block"><?= htmlspecialchars($errors['role']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select name="status">
              <option value="active"  <?= $user['status'] === 'active'  ? 'selected' : '' ?>>Active</option>
              <option value="blocked" <?= $user['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
            <?php if (!empty($errors['status'])): ?>
              <span class="field-error" style="display:block"><?= htmlspecialchars($errors['status']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Summary -->
          <div class="summary-card" style="margin-top:24px;">
            <div class="summary-title"><i class="fa-solid fa-circle-info"></i> Account Info</div>
            <div class="summary-row">
              <span>User ID</span>
              <span>#<?= $user_id ?></span>
            </div>
            <div class="summary-row">
              <span>Member Since</span>
              <span><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
            </div>
            <div class="summary-row">
              <span>Last Updated</span>
              <span><?= date('M d, Y', strtotime($user['updated_at'])) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <a href="view_user.php?id=<?= $user_id ?>" class="btn-reset">
          <i class="fa-solid fa-xmark"></i> Cancel
        </a>
        <button type="submit" class="btn-submit">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </form>
</div>

</div>
            </div>
<?php include 'footer.php'; ?>

</body>
</html>