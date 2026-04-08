<?php
session_start();
require_once 'connect.php';

// ─── AUTH GUARD ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$session_user_id = (int) $_SESSION['user_id'];

// ─── FETCH CURRENT USER ───────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT user_id, full_name, email, phone, address, role, status,
           profile_image, created_at, updated_at
    FROM users
    WHERE user_id = ? AND is_deleted = 0
    LIMIT 1
");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    die("User not found.");
}

// ─── HANDLE FORM SUBMISSION ───────────────────────────────────────────────────
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    // ── Validate email ──────────────────────────────────────────────────────
    if ($new_email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($new_email !== $user['email']) {
        // Check uniqueness
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? AND is_deleted = 0");
        $chk->bind_param("si", $new_email, $session_user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errors[] = 'That email address is already in use.';
        }
    }

    // ── Validate phone (optional) ───────────────────────────────────────────
    if ($new_phone !== '' && !preg_match('/^\+?[\d\s\-\(\)]{6,20}$/', $new_phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    // ── Handle profile picture upload ───────────────────────────────────────
    $new_image = $user['profile_image']; // keep existing by default

    if (!empty($_FILES['profile_image']['name'])) {
        $file      = $_FILES['profile_image'];
        $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_bytes = 2 * 1024 * 1024; // 2 MB

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Profile picture must be a JPG, PNG, GIF, or WebP image.';
        } elseif ($file['size'] > $max_bytes) {
            $errors[] = 'Profile picture must be smaller than 2 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
        } else {
            $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename   = 'user_' . $session_user_id . '_' . time() . '.' . strtolower($ext);
            $upload_dir = __DIR__ . '/../uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // Delete old image if it exists
                if (!empty($user['profile_image'])) {
                    $old = $upload_dir . $user['profile_image'];
                    if (file_exists($old)) {
                        unlink($old);
                    }
                }
                $new_image = $filename;
            } else {
                $errors[] = 'Could not save the uploaded image. Please try again.';
            }
        }
    }

    // ── Handle remove-picture flag ──────────────────────────────────────────
    if (isset($_POST['remove_picture']) && $_POST['remove_picture'] === '1') {
        if (!empty($user['profile_image'])) {
            $old = __DIR__ . '/../uploads/' . $user['profile_image'];
            if (file_exists($old)) {
                unlink($old);
            }
        }
        $new_image = null;
    }

    // ── Save if no errors ───────────────────────────────────────────────────
    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE users
            SET email = ?, phone = ?, profile_image = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $upd->bind_param("sssi", $new_email, $new_phone, $new_image, $session_user_id);

        if ($upd->execute()) {
            $success = 'Your profile has been updated successfully.';
            // Refresh $user with new values
            $user['email']         = $new_email;
            $user['phone']         = $new_phone;
            $user['profile_image'] = $new_image;
        } else {
            $errors[] = 'Database error. Please try again.';
        }
    }
}

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$nameParts    = array_slice(explode(' ', trim($user['full_name'])), 0, 2);
$initials     = implode('', array_map(fn($w) => strtoupper($w[0]), $nameParts));
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile — <?= e($user['full_name']) ?></title>
  <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">

  <link rel="stylesheet" href="../style/navbar.css" />
  <link rel="stylesheet" href="../style/footer.css" />
  <link rel="stylesheet" href="../style/edit_profile.css" />
</head>
<body>

<?php include 'admin_navbar.php'; ?>
<div class="page-wrapper">
<?php include 'admin_menu.php'; ?>

<!-- ── MAIN CONTENT ───────────────────────────────────────────────────────── -->
<main class="profile-page">

  <h1 class="page-heading">Edit Profile</h1>

  <!-- ── FLASH MESSAGES ──────────────────────────────────────────────────── -->
  <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <?= e($success) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- ── HERO CARD (avatar + name) ────────────────────────────────────────── -->
  <form method="POST" enctype="multipart/form-data" id="editProfileForm" novalidate>
    <input type="hidden" name="remove_picture" id="remove_picture" value="0">

    <div class="hero-card">
      <!-- Red top accent via CSS -->

      <!-- Avatar -->
      <div class="avatar-wrap" id="avatarWrap">
        <div class="avatar-circle" id="avatarCircle">
          <?php if (!empty($profileImage)): ?>
            <img src="/jerseyflow-ecommerce/uploads/<?= e($profileImage) ?>" id="avatarPreview" alt="Profile picture"/>
          <?php else: ?>
            <span class="avatar-initials" id="avatarInitials"><?= e($initials) ?></span>
            <img src="" id="avatarPreview" alt="Profile picture" style="display:none;"/>
          <?php endif; ?>
        </div>
        <span class="avatar-dot <?= e($user['status']) ?>" title="<?= e(ucfirst($user['status'])) ?>"></span>

        <!-- Hover overlay -->
        <label for="profile_image" class="avatar-overlay" title="Change photo">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
          <span>Change Photo</span>
        </label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*" class="sr-only">
      </div>

      <!-- Name / email -->
      <div class="hero-info">
        <h2 class="hero-name"><?= e($user['full_name']) ?></h2>
        <p class="hero-email"><?= e($user['email']) ?></p>
        <div class="badges">
          <span class="badge badge-role">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
            <?= e(ucfirst($user['role'])) ?>
          </span>
          <span class="badge badge-<?= e($user['status']) ?>"><?= e(ucfirst($user['status'])) ?></span>
        </div>

        <?php if (!empty($profileImage)): ?>
          <button type="button" id="removePhotoBtn" class="remove-photo-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
              <path d="M9 6V4h6v2"/>
            </svg>
            Remove photo
          </button>
        <?php else: ?>
          <button type="button" id="removePhotoBtn" class="remove-photo-btn" style="display:none;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
              <path d="M9 6V4h6v2"/>
            </svg>
            Remove photo
          </button>
        <?php endif; ?>
      </div>

      <!-- Back link -->
      <a href="users_profile.php" class="edit-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Profile
      </a>
    </div><!-- /hero-card -->

    <!-- ── EDITABLE FIELDS ──────────────────────────────────────────────── -->
    <p class="section-label">Contact Information</p>

    <div class="info-grid form-grid">

      <!-- Email -->
      <div class="info-cell form-cell">
        <label class="cell-label" for="email">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
          Email Address
        </label>
        <input
          type="email"
          id="email"
          name="email"
          class="field-input"
          value="<?= e($user['email']) ?>"
          placeholder="your@email.com"
          required
          autocomplete="email"
        />
      </div>

      <!-- Phone -->
      <div class="info-cell form-cell">
        <label class="cell-label" for="phone">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.18A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
          Phone Number
        </label>
        <input
          type="tel"
          id="phone"
          name="phone"
          class="field-input"
          value="<?= e($user['phone'] ?? '') ?>"
          placeholder="e.g. +1 555 000 1234"
          autocomplete="tel"
        />
      </div>

      <!-- Address — redirect cell -->
      <div class="info-cell wide redirect-cell">
        <div class="cell-label">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
          Address
        </div>
        <div class="redirect-row">
          <span class="cell-value">
            <?php if (!empty($user['address'])): ?>
              <?= e($user['address']) ?>
            <?php else: ?>
              <span class="empty">No address on file</span>
            <?php endif; ?>
          </span>
        </div>
      </div>

    </div><!-- /info-grid -->

    <!-- ── SECURITY ─────────────────────────────────────────────────────── -->
    <p class="section-label">Security</p>

    <div class="info-grid">
      <div class="info-cell wide redirect-cell">
        <div class="cell-label">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Password
        </div>
        <div class="redirect-row">
          <span class="cell-value">••••••••••••</span>
          <a href="change_password.php" class="redirect-btn">
            Change Password
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </a>
        </div>
      </div>
    </div>

    <!-- ── ACTION BUTTONS ───────────────────────────────────────────────── -->
    <div class="form-actions">
      <a href="users_profile.php" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
          <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
        </svg>
        Save Changes
      </button>
    </div>
    </div>

  </form>

</main>

<?php include '../footer.php'; ?>

<script src="../script/edit_profile.js"></script>
</body>
</html>