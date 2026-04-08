<?php
session_start();
require_once 'connect.php';

// ── SVG helpers ───────────────────────────────────────────────────────────────
function eyeIcon() {
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>';
}
function circleIcon() {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="5"/>
    </svg>';
}
function jsEyeIcon() {
    return addslashes('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>');
}
function jsEyeOffIcon() {
    return addslashes('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>');
}

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_pw = $_POST['current_password'] ?? '';
    $new_pw     = $_POST['new_password']     ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // 1. Basic presence check
    if (empty($current_pw) || empty($new_pw) || empty($confirm_pw)) {
        $error = 'All fields are required.';

    // 2. New passwords must match
    } elseif ($new_pw !== $confirm_pw) {
        $error = 'New password and confirmation do not match.';

    // 3. Minimum length
    } elseif (strlen($new_pw) < 8) {
        $error = 'New password must be at least 8 characters long.';

    // 4. New password must differ from current
    } elseif ($current_pw === $new_pw) {
        $error = 'New password must be different from your current password.';

    } else {
        // 5. Fetch current hashed password — only for THIS user
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'User not found. Please log in again.';

        // 6. Verify current password against stored hash
        } elseif (!password_verify($current_pw, $user['password'])) {
            $error = 'Current password is incorrect.';

        } else {
            // 7. Hash the new password and update — scoped to user_id only
            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $upd = mysqli_prepare($conn, "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, 'si', $new_hash, $user_id);

            if (mysqli_stmt_execute($upd)) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            mysqli_stmt_close($upd);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>
<link rel="icon"        href="../images/logo_icon.ico" type="image/x-icon">
<link rel="stylesheet"  href="../assets/fontawesome/css/all.min.css">
<link rel="stylesheet"  href="../style/footer.css">
<link rel="stylesheet"  href="../style/change_password.css">
<link rel="stylesheet"  href="../style/users_menu.css">
</head>
<body>

<?php include 'users_navbar.php'; ?>
<div class="page-wrapper">
    <?php include 'users_menu.php'; ?>
<div class="cp-page-content">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <h1 class="page-title">Change <span>Password</span></h1>
    </div>

    <!-- ── Alerts ── -->
    <?php if ($success): ?>
    <div class="alert alert-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- ── Info tip ── -->
    <div class="info-tip">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Choose a strong password you don't use on other sites. It must be at least 8 characters and contain a mix of letters, numbers, and symbols.
    </div>

    <!-- ── Form Panel ── -->
    <div class="form-panel">
        <div class="form-panel-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Update Your Password
        </div>

        <form method="POST" id="cpForm" novalidate>

            <!-- Current Password -->
            <div class="form-group">
                <label for="current_password">Current Password <span class="req">*</span></label>
                <div class="input-wrap">
                    <input type="password" id="current_password" name="current_password"
                           placeholder="Enter your current password"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-eye" onclick="toggleVisibility('current_password', this)" aria-label="Toggle visibility">
                        <?= eyeIcon() ?>
                    </button>
                </div>
            </div>

            <!-- New Password -->
            <div class="form-group">
                <label for="new_password">New Password <span class="req">*</span></label>
                <div class="input-wrap">
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Enter new password"
                           autocomplete="new-password"
                           oninput="checkStrength(this.value); checkMatch()"
                           required>
                    <button type="button" class="toggle-eye" onclick="toggleVisibility('new_password', this)" aria-label="Toggle visibility">
                        <?= eyeIcon() ?>
                    </button>
                </div>

                <!-- Strength bar -->
                <div class="strength-wrap" id="strengthWrap" style="display:none;">
                    <div class="strength-bar" id="strengthBar">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <!-- Requirements -->
                <div class="req-list" id="reqList">
                    <div class="req-item" id="req-len">
                        <?= circleIcon() ?> At least 8 characters
                    </div>
                    <div class="req-item" id="req-upper">
                        <?= circleIcon() ?> One uppercase letter (A–Z)
                    </div>
                    <div class="req-item" id="req-num">
                        <?= circleIcon() ?> One number (0–9)
                    </div>
                    <div class="req-item" id="req-sym">
                        <?= circleIcon() ?> One symbol (!@#$…)
                    </div>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
                <div class="input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter new password"
                           autocomplete="new-password"
                           oninput="checkMatch()"
                           required>
                    <button type="button" class="toggle-eye" onclick="toggleVisibility('confirm_password', this)" aria-label="Toggle visibility">
                        <?= eyeIcon() ?>
                    </button>
                </div>
                <div class="match-hint" id="matchHint"></div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="profile.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<?php include '../footer.php'; ?>

<script>
// ── Toggle password visibility ────────────────────────────────────────────────
function toggleVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? `<?= jsEyeIcon() ?>` : `<?= jsEyeOffIcon() ?>`;
}

// ── Password strength checker ─────────────────────────────────────────────────
function checkStrength(val) {
    const wrap  = document.getElementById('strengthWrap');
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');

    if (!val) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    const checks = {
        len:   val.length >= 8,
        upper: /[A-Z]/.test(val),
        num:   /[0-9]/.test(val),
        sym:   /[^A-Za-z0-9]/.test(val)
    };

    // Update requirement items
    toggleReq('req-len',   checks.len);
    toggleReq('req-upper', checks.upper);
    toggleReq('req-num',   checks.num);
    toggleReq('req-sym',   checks.sym);

    const score = Object.values(checks).filter(Boolean).length;
    const levels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['', '#f28b82', '#e9a84c', 'var(--accent)', '#6fcf97'];

    bar.className = 'strength-bar str-' + score;
    label.textContent = levels[score] ? 'Strength: ' + levels[score] : '';
    label.style.color = colors[score];

    updateSubmitState();
}

function toggleReq(id, met) {
    const el = document.getElementById(id);
    if (met) {
        el.classList.add('met');
        el.querySelector('svg').outerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    } else {
        el.classList.remove('met');
        // restore circle if it was changed
        const svg = el.querySelector('svg');
        if (svg) svg.outerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/></svg>`;
    }
}

// ── Confirm password match ─────────────────────────────────────────────────────
function checkMatch() {
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    const hint = document.getElementById('matchHint');
    const confirmInput = document.getElementById('confirm_password');

    if (!cp) {
        hint.textContent = '';
        hint.className = 'match-hint';
        confirmInput.classList.remove('input-ok', 'input-error');
        updateSubmitState();
        return;
    }

    if (np === cp) {
        hint.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Passwords match`;
        hint.className = 'match-hint ok';
        confirmInput.classList.remove('input-error');
        confirmInput.classList.add('input-ok');
    } else {
        hint.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Passwords do not match`;
        hint.className = 'match-hint no';
        confirmInput.classList.remove('input-ok');
        confirmInput.classList.add('input-error');
    }

    updateSubmitState();
}

// ── Enable/disable submit ──────────────────────────────────────────────────────
function updateSubmitState() {
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    const cur = document.getElementById('current_password').value;
    const btn = document.getElementById('submitBtn');

    const allFilled  = cur.length > 0 && np.length >= 8 && cp.length > 0;
    const matched    = np === cp;

    btn.disabled = !(allFilled && matched);
}

// Bind current_password input to state update too
document.getElementById('current_password').addEventListener('input', updateSubmitState);

// Initial state
updateSubmitState();
</script>
</body>
</html>