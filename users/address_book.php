<?php
session_start();
require_once 'connect.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// ─── Helpers ────────────────────────────────────────────────────────────────
function sanitize($conn, $val) {
    return mysqli_real_escape_string($conn, trim($val));
}

// ─── POST Actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD
    if ($action === 'add') {
        $label      = sanitize($conn, $_POST['label']);
        $full_name  = sanitize($conn, $_POST['full_name']);
        $phone      = sanitize($conn, $_POST['phone']);
        $address_1  = sanitize($conn, $_POST['address_1']);
        $address_2  = sanitize($conn, $_POST['address_2']);
        $city       = sanitize($conn, $_POST['city']);
        $state      = sanitize($conn, $_POST['state']);
        $postal     = sanitize($conn, $_POST['postal']);
        $country    = sanitize($conn, $_POST['country']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($full_name) || empty($address_1) || empty($city) || empty($country)) {
            $error = 'Please fill in all required fields.';
        } else {
            if ($is_default) {
                mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id=$user_id");
            }
            // Check if first address → auto default
            $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM user_addresses WHERE user_id=$user_id"))['c'];
            if ($count == 0) $is_default = 1;

            $sql = "INSERT INTO user_addresses (user_id, label, full_name, phone, address_1, address_2, city, state, postal, country, is_default, created_at)
                    VALUES ($user_id, '$label', '$full_name', '$phone', '$address_1', '$address_2', '$city', '$state', '$postal', '$country', $is_default, NOW())";
            if (mysqli_query($conn, $sql)) {
                $success = 'Address added successfully.';
            } else {
                $error = 'Failed to add address. Please try again.';
            }
        }
    }

    // EDIT
    elseif ($action === 'edit') {
        $addr_id    = (int)$_POST['address_id'];
        $label      = sanitize($conn, $_POST['label']);
        $full_name  = sanitize($conn, $_POST['full_name']);
        $phone      = sanitize($conn, $_POST['phone']);
        $address_1  = sanitize($conn, $_POST['address_1']);
        $address_2  = sanitize($conn, $_POST['address_2']);
        $city       = sanitize($conn, $_POST['city']);
        $state      = sanitize($conn, $_POST['state']);
        $postal     = sanitize($conn, $_POST['postal']);
        $country    = sanitize($conn, $_POST['country']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        // Verify ownership
        $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM user_addresses WHERE id=$addr_id AND user_id=$user_id"));
        if (!$own) {
            $error = 'Unauthorized action.';
        } elseif (empty($full_name) || empty($address_1) || empty($city) || empty($country)) {
            $error = 'Please fill in all required fields.';
        } else {
            if ($is_default) {
                mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id=$user_id");
            }
            $sql = "UPDATE user_addresses SET
                        label='$label', full_name='$full_name', phone='$phone',
                        address_1='$address_1', address_2='$address_2',
                        city='$city', state='$state', postal='$postal',
                        country='$country', is_default=$is_default,
                        updated_at=NOW()
                    WHERE id=$addr_id AND user_id=$user_id";
            if (mysqli_query($conn, $sql)) {
                $success = 'Address updated successfully.';
            } else {
                $error = 'Failed to update address.';
            }
        }
    }

    // DELETE
    elseif ($action === 'delete') {
        $addr_id = (int)$_POST['address_id'];
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user_addresses WHERE id=$addr_id AND user_id=$user_id"));
        if (!$row) {
            $error = 'Unauthorized action.';
        } else {
            mysqli_query($conn, "DELETE FROM user_addresses WHERE id=$addr_id AND user_id=$user_id");
            // Reassign default if deleted was default
            if ($row['is_default']) {
                $first = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM user_addresses WHERE user_id=$user_id ORDER BY created_at ASC LIMIT 1"));
                if ($first) mysqli_query($conn, "UPDATE user_addresses SET is_default=1 WHERE id={$first['id']}");
            }
            $success = 'Address deleted.';
        }
    }

    // SET DEFAULT
    elseif ($action === 'set_default') {
        $addr_id = (int)$_POST['address_id'];
        $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM user_addresses WHERE id=$addr_id AND user_id=$user_id"));
        if ($own) {
            mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id=$user_id");
            mysqli_query($conn, "UPDATE user_addresses SET is_default=1 WHERE id=$addr_id AND user_id=$user_id");
            $success = 'Default address updated.';
        } else {
            $error = 'Unauthorized action.';
        }
    }
}

// ─── Fetch Addresses ─────────────────────────────────────────────────────────
$addresses = [];
$res = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id=$user_id ORDER BY is_default DESC, created_at ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $addresses[] = $row;
}

// ─── Fetch Edit Target ───────────────────────────────────────────────────────
$edit_addr = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_addr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user_addresses WHERE id=$eid AND user_id=$user_id"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Address Book</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/address_book.css">

</head>
<body>

<?php include 'users_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'users_menu.php'; ?>

    <div class="page-header">
        <h1 class="page-title">My <span>Address Book</span></h1>
        <?php if (!$edit_addr): ?>
        <button class="btn btn-primary" onclick="document.getElementById('add-form').scrollIntoView({behavior:'smooth'})">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add New Address
        </button>
        <?php endif; ?>
    </div>

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

    <!-- ── Address Cards ── -->
    <?php if (empty($addresses)): ?>
    <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>No saved addresses yet</h3>
        <p>Add your first address below to get started.</p>
    </div>
    <?php else: ?>
    <div class="addr-grid">
        <?php foreach ($addresses as $a): ?>
        <div class="addr-card <?= $a['is_default'] ? 'is-default' : '' ?>">
            <div>
                <div class="addr-card-header">
                    <div style="display:flex; flex-direction:column; gap:7px;">
                        <?php if ($a['label']): ?>
                        <span class="label-pill"><?= htmlspecialchars($a['label']) ?></span>
                        <?php endif; ?>
                        <?php if ($a['is_default']): ?>
                        <span class="default-badge">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Default
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="addr-name" style="margin-top:10px;"><?= htmlspecialchars($a['full_name']) ?></p>
                <?php if ($a['phone']): ?>
                <p class="addr-detail" style="margin-top:3px;"><?= htmlspecialchars($a['phone']) ?></p>
                <?php endif; ?>
            </div>

            <div class="addr-detail">
                <?= htmlspecialchars($a['address_1']) ?><br>
                <?php if ($a['address_2']): ?><?= htmlspecialchars($a['address_2']) ?><br><?php endif; ?>
                <?= htmlspecialchars($a['city']) ?><?= $a['state'] ? ', '.htmlspecialchars($a['state']) : '' ?> <?= htmlspecialchars($a['postal']) ?><br>
                <strong><?= htmlspecialchars($a['country']) ?></strong>
            </div>

            <div class="card-actions">
                <a href="?edit=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </a>
                <?php if (!$a['is_default']): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="set_default">
                    <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn btn-accent btn-sm">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Set Default
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['full_name'])) ?>')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Delete
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Add / Edit Form ── -->
    <div class="form-panel" id="add-form">
        <div class="form-panel-title">
            <?php if ($edit_addr): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Address
            <?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add New Address
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="<?= $edit_addr ? 'edit' : 'add' ?>">
            <?php if ($edit_addr): ?>
            <input type="hidden" name="address_id" value="<?= $edit_addr['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label for="label">Address Label <span style="color:var(--muted); font-weight:400;">(e.g. Home, Office)</span></label>
                    <input type="text" id="label" name="label" placeholder="Home" value="<?= htmlspecialchars($edit_addr['label'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name" placeholder="John Doe" value="<?= htmlspecialchars($edit_addr['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1 234 567 8900" value="<?= htmlspecialchars($edit_addr['phone'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label for="address_1">Address Line 1 <span class="req">*</span></label>
                    <input type="text" id="address_1" name="address_1" placeholder="Street address, P.O. box" value="<?= htmlspecialchars($edit_addr['address_1'] ?? '') ?>" required>
                </div>

                <div class="form-group full">
                    <label for="address_2">Address Line 2</label>
                    <input type="text" id="address_2" name="address_2" placeholder="Apartment, suite, unit, building (optional)" value="<?= htmlspecialchars($edit_addr['address_2'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="city">City <span class="req">*</span></label>
                    <input type="text" id="city" name="city" placeholder="Kathmandu" value="<?= htmlspecialchars($edit_addr['city'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="state">State / Province</label>
                    <input type="text" id="state" name="state" placeholder="Bagmati Province" value="<?= htmlspecialchars($edit_addr['state'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="postal">Postal / ZIP Code</label>
                    <input type="text" id="postal" name="postal" placeholder="44600" value="<?= htmlspecialchars($edit_addr['postal'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="country">Country <span class="req">*</span></label>
                    <input type="text" id="country" name="country" placeholder="Nepal" value="<?= htmlspecialchars($edit_addr['country'] ?? '') ?>" required>
                </div>

                <div class="toggle-row">
                    <input type="checkbox" id="is_default" name="is_default" value="1"
                        <?= (!empty($edit_addr) && $edit_addr['is_default']) || empty($addresses) ? 'checked' : '' ?>>
                    <label for="is_default">Set as default address</label>
                </div>

            </div>

            <div class="form-actions">
                <?php if ($edit_addr): ?>
                <a href="address_book.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Changes
                </button>
                <?php else: ?>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Address
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f28b82" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </div>
        <h3>Delete Address?</h3>
        <p id="deleteModalText">This action cannot be undone.</p>
        <form method="POST" class="modal-actions">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="address_id" id="deleteAddressId">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
        </form>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteAddressId').value = id;
    document.getElementById('deleteModalText').textContent =
        'Are you sure you want to delete the address for "' + name + '"? This cannot be undone.';
    document.getElementById('deleteModal').classList.add('active');
}
function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Auto-scroll to form if editing
<?php if ($edit_addr): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('add-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>
</body>
</html>