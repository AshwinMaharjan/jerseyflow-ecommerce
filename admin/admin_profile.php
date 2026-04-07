<?php
session_start();
require_once 'connect.php'; // Database connection

// ─── AUTH GUARD ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$session_user_id = (int) $_SESSION['user_id'];

// Users can only view their own profile; admins may view any
$view_id = isset($_GET['id']) ? (int) $_GET['id'] : $session_user_id;

if ($view_id !== $session_user_id) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? AND is_deleted = 0");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
    if (!$current || $current['role'] !== 'admin') {
        http_response_code(403);
        die("Access denied. You can only view your own profile.");
    }
}

// ─── FETCH PROFILE ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT user_id, full_name, email, phone, address, role, status,
           profile_image, created_at, updated_at
    FROM users
    WHERE user_id = ? AND is_deleted = 0
    LIMIT 1
");
$stmt->bind_param("i", $view_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    die("User not found.");
}

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$joined    = date('F j, Y', strtotime($user['created_at']));
$updated   = date('M j, Y', strtotime($user['updated_at']));
$nameParts = array_slice(explode(' ', trim($user['full_name'])), 0, 2);
$initials  = implode('', array_map(fn($w) => strtoupper($w[0]), $nameParts));
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($user['full_name']) ?> — Profile</title>
  <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    
  <link rel="stylesheet" href="../style/navbar.css" />
  <link rel="stylesheet" href="../style/footer.css" />
  <link rel="stylesheet" href="../style/users_profile.css" />  
</head>
<body>

<?php include 'admin_navbar.php'; ?>
<div class="page-wrapper">
<?php include 'admin_menu.php'; ?>

<!-- ── MAIN CONTENT ───────────────────────────────────────────────────────────── -->
<main class="profile-page">

  <h1 class="page-heading">My Profile</h1>

  <!-- HERO CARD -->
  <div class="hero-card">

    <!-- Avatar -->
    <div class="avatar-wrap">
      <div class="avatar-circle">
        <?php if (!empty($profileImage)): ?>
  <img src="/jerseyflow-ecommerce/uploads/<?= e($user['profile_image']) ?>" />
<?php else: ?>
  <span class="avatar-initials"><?= e($initials) ?></span>
<?php endif; ?>
      </div>
      <span class="avatar-dot <?= e($user['status']) ?>"
            title="<?= e(ucfirst($user['status'])) ?>"></span>
    </div>

    <!-- Name / Email / Badges -->
    <div class="hero-info">
      <h2 class="hero-name"><?= e($user['full_name']) ?></h2>
      <p class="hero-email"><?= e($user['email']) ?></p>
      <div class="badges">
        <span class="badge badge-role">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77
                     l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
          <?= e(ucfirst($user['role'])) ?>
        </span>
        <span class="badge badge-<?= e($user['status']) ?>">
          <?= e(ucfirst($user['status'])) ?>
        </span>
      </div>
    </div>

    <!-- Edit button (own profile only) -->
    <?php if ($view_id === $session_user_id): ?>
      <a href="edit_profile.php" class="edit-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Edit Profile
      </a>
    <?php endif; ?>

  </div><!-- /hero-card -->

  <!-- CONTACT & DETAILS -->
  <p class="section-label">Contact &amp; Details</p>

  <div class="info-grid">

    <!-- Email -->
    <div class="info-cell">
      <div class="cell-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4
                   c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
        Email Address
      </div>
      <div class="cell-value"><?= e($user['email']) ?></div>
    </div>

    <!-- Phone -->
    <div class="info-cell">
      <div class="cell-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07
                   A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.18
                   A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72
                   12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 8.91
                   a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45
                   12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        Phone Number
      </div>
      <?php if (!empty($user['phone'])): ?>
        <div class="cell-value"><?= e($user['phone']) ?></div>
      <?php else: ?>
        <div class="cell-value empty">Not provided</div>
      <?php endif; ?>
    </div>

    <!-- Address — full width -->
    <div class="info-cell wide">
      <div class="cell-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
          <circle cx="12" cy="10" r="3"/>
        </svg>
        Address
      </div>
      <?php if (!empty($user['address'])): ?>
        <div class="cell-value"><?= e($user['address']) ?></div>
      <?php else: ?>
        <div class="cell-value empty">No address on file</div>
      <?php endif; ?>
    </div>

    <!-- Role -->
    <div class="info-cell">
      <div class="cell-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="8" r="4"/>
          <path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>
        </svg>
        Account Role
      </div>
      <div class="cell-value"><?= e(ucfirst($user['role'])) ?></div>
    </div>

    <!-- Status -->
    <div class="info-cell">
      <div class="cell-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
        Account Status
      </div>
      <div class="cell-value"><?= e(ucfirst($user['status'])) ?></div>
    </div>

  </div><!-- /info-grid -->

  <!-- ACCOUNT TIMELINE -->
  <p class="section-label">Account Timeline</p>

  <div class="meta-strip">
    <div class="meta-cell">
      <div class="meta-label">Member Since</div>
      <div class="meta-value"><?= e($joined) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Last Updated</div>
      <div class="meta-value"><?= e($updated) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">User ID</div>
      <div class="meta-value">#<?= e($user['user_id']) ?></div>
    </div>
  </div>
  </div>

</main>

<?php include '../footer.php'; ?>

</body>
</html>