<?php
session_start();
include('connect.php');
require_once 'auth_guard.php';

$success = '';
$error   = '';

// ── Fetch functions ────────────────────────────────────────────────────────

// List display: newest first
function fetchCountries($conn) {
    return mysqli_query($conn, "SELECT country_id, country_name, sort_order FROM countries ORDER BY created_at DESC");
}

// Dropdown only: alphabetical
function fetchCountriesAlpha($conn) {
    return mysqli_query($conn, "SELECT country_id, country_name FROM countries ORDER BY country_name ASC");
}

// List display: newest first
function fetchClubs($conn) {
    return mysqli_query($conn, "
        SELECT cl.club_id, cl.club_name, cl.country_id, co.country_name
        FROM clubs cl
        LEFT JOIN countries co ON cl.country_id = co.country_id
        ORDER BY cl.created_at DESC
    ");
}

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ════════════════════════════════════════════
    //  COUNTRY actions
    // ════════════════════════════════════════════

    if ($action === 'add_country') {
        $country_name = trim($_POST['country_name'] ?? '');

        if (empty($country_name) || strlen($country_name) > 100) {
            $error = 'Country name is required and must be under 100 characters.';
        } else {
            // Check for duplicate (case-insensitive)
            $dup_stmt = mysqli_prepare($conn, "SELECT country_id FROM countries WHERE LOWER(country_name) = LOWER(?)");
            mysqli_stmt_bind_param($dup_stmt, 's', $country_name);
            mysqli_stmt_execute($dup_stmt);
            mysqli_stmt_store_result($dup_stmt);
            $already_exists = mysqli_stmt_num_rows($dup_stmt) > 0;
            mysqli_stmt_close($dup_stmt);

            if ($already_exists) {
                $error = htmlspecialchars($country_name) . ' already exists. Country names must be unique.';
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO countries (country_name, created_at) VALUES (?, NOW())"
                );
                mysqli_stmt_bind_param($stmt, 's', $country_name);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Country <strong>' . htmlspecialchars($country_name) . '</strong> added successfully.';
                } else {
                    $error = 'Failed to add country.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    elseif ($action === 'edit_country') {
        $country_id   = intval($_POST['country_id'] ?? 0);
        $country_name = trim($_POST['country_name'] ?? '');

        if (!$country_id || empty($country_name) || strlen($country_name) > 100) {
            $error = 'Valid country name is required.';
        } else {
            // Check duplicate excluding self
            $dup_stmt = mysqli_prepare($conn, "SELECT country_id FROM countries WHERE LOWER(country_name) = LOWER(?) AND country_id != ?");
            mysqli_stmt_bind_param($dup_stmt, 'si', $country_name, $country_id);
            mysqli_stmt_execute($dup_stmt);
            mysqli_stmt_store_result($dup_stmt);
            $already_exists = mysqli_stmt_num_rows($dup_stmt) > 0;
            mysqli_stmt_close($dup_stmt);

            if ($already_exists) {
$error = htmlspecialchars($country_name) . ' already exists. Country names must be unique.';
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE countries SET country_name = ? WHERE country_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'si', $country_name, $country_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Country updated to <strong>' . htmlspecialchars($country_name) . '</strong>.';
                } else {
                    $error = 'Failed to update country.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    elseif ($action === 'delete_country') {
        $country_id = intval($_POST['country_id'] ?? 0);
        if ($country_id) {
            // Check if any clubs use this country
            $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM clubs WHERE country_id = $country_id");
            $row   = mysqli_fetch_assoc($check);
            if ($row['cnt'] > 0) {
                $error = 'Cannot delete — ' . $row['cnt'] . ' club(s) are linked to this country. Reassign them first.';
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM countries WHERE country_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $country_id);
                mysqli_stmt_execute($stmt) ? $success = 'Country deleted.' : $error = 'Failed to delete country.';
                mysqli_stmt_close($stmt);
            }
        }
    }

    // ════════════════════════════════════════════
    //  CLUB actions
    // ════════════════════════════════════════════

    elseif ($action === 'add_club') {
        $club_name  = trim($_POST['club_name'] ?? '');
        $country_id = intval($_POST['country_id'] ?? 0) ?: null;

        if (empty($club_name) || strlen($club_name) > 100) {
            $error = 'Club name is required and must be under 100 characters.';
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO clubs (club_name, country_id, created_at) VALUES (?, ?, NOW())"
            );
            mysqli_stmt_bind_param($stmt, 'si', $club_name, $country_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Club <strong>' . htmlspecialchars($club_name) . '</strong> added successfully.';
            } else {
                $error = 'Failed to add club. It may already exist.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === 'edit_club') {
        $club_id    = intval($_POST['club_id'] ?? 0);
        $club_name  = trim($_POST['club_name'] ?? '');
        $country_id = intval($_POST['country_id'] ?? 0) ?: null;

        if (!$club_id || empty($club_name) || strlen($club_name) > 100) {
            $error = 'Valid club name is required.';
        } else {
            $stmt = mysqli_prepare($conn,
                "UPDATE clubs SET club_name = ?, country_id = ? WHERE club_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'sii', $club_name, $country_id, $club_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Club updated to <strong>' . htmlspecialchars($club_name) . '</strong>.';
            } else {
                $error = 'Failed to update club.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === 'delete_club') {
        $club_id = intval($_POST['club_id'] ?? 0);
        if ($club_id) {
            // Check if any products use this club
            $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE club_id = $club_id");
            $row   = mysqli_fetch_assoc($check);
            if ($row['cnt'] > 0) {
                $error = 'Cannot delete — ' . $row['cnt'] . ' product(s) are linked to this club.';
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM clubs WHERE club_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $club_id);
                mysqli_stmt_execute($stmt) ? $success = 'Club deleted.' : $error = 'Failed to delete club.';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ── Re-fetch after any changes ─────────────────────────────────────────────
$countries_result       = fetchCountries($conn);      // newest first — for list display
$countries_alpha_result = fetchCountriesAlpha($conn); // A–Z — for dropdowns
$clubs_result           = fetchClubs($conn);          // newest first — for list display

$countries_data       = [];
while ($row = mysqli_fetch_assoc($countries_result)) $countries_data[] = $row;

$countries_alpha_data = [];
while ($row = mysqli_fetch_assoc($countries_alpha_result)) $countries_alpha_data[] = $row;

$clubs_data = [];
while ($row = mysqli_fetch_assoc($clubs_result)) $clubs_data[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | JerseyFlow Admin</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
    <link rel="stylesheet" href="../style/categories.css">
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="page-wrapper">
    <?php include 'admin_menu.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title">
                    <i class="fa-solid fa-tags"></i> Categories
                </h1>
                <p class="page-subtitle">Manage countries and clubs used across your product catalog.</p>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($success): ?>
            <div class="alert alert-success" id="alertBox">
                <i class="fa-solid fa-circle-check"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" id="alertBox">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- ── Two-column layout ──────────────────────────────────────── -->
        <div class="categories-grid">

            <!-- ══════════════════════════════════════════════════════════
                 LEFT: COUNTRIES
            ══════════════════════════════════════════════════════════ -->
            <div class="cat-panel">

                <div class="cat-panel-header">
                    <div class="cat-panel-title">
                        <i class="fa-solid fa-earth-asia"></i> Countries
                    </div>
                    <span class="cat-count-badge"><?= count($countries_data) ?></span>
                </div>

                <!-- Add Country Form -->
                <div class="cat-form-box">
                    <div class="cat-form-title">Add New Country</div>
                    <form method="POST" action="" id="addCountryForm">
                        <input type="hidden" name="action" value="add_country">
                        <div class="form-group">
                            <label for="country_name">Country Name <span class="required">*</span></label>
                            <input type="text" id="country_name" name="country_name"
                                   placeholder="e.g. England"
                                   maxlength="100" required
                                   value="<?= (($_POST['action'] ?? '') === 'add_country') ? htmlspecialchars($_POST['country_name'] ?? '') : '' ?>">
                        </div>
                        <button type="submit" class="btn-cat-add">
                            <i class="fa-solid fa-plus"></i> Add Country
                        </button>
                    </form>
                </div>

                <!-- Countries List -->
                <div class="cat-list">
                    <?php if (empty($countries_data)): ?>
                        <div class="cat-empty">
                            <i class="fa-solid fa-earth-asia"></i>
                            <p>No countries yet. Add one above.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($countries_data as $country): ?>
                            <div class="cat-item" id="country-item-<?= $country['country_id'] ?>">
                                <div class="cat-item-info">
                                    <span class="cat-item-name"><?= htmlspecialchars($country['country_name']) ?></span>
                                </div>
                                <div class="cat-item-actions">
                                    <button type="button" class="cat-btn cat-btn-edit"
                                            title="Edit"
                                            onclick="openEditCountry(<?= $country['country_id'] ?>, '<?= htmlspecialchars(addslashes($country['country_name'])) ?>', <?= intval($country['sort_order']) ?>)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="cat-btn cat-btn-delete"
                                            title="Delete"
                                            onclick="confirmDeleteCountry(<?= $country['country_id'] ?>, '<?= htmlspecialchars(addslashes($country['country_name'])) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /cat-panel countries -->

            <!-- ══════════════════════════════════════════════════════════
                 RIGHT: CLUBS
            ══════════════════════════════════════════════════════════ -->
            <div class="cat-panel">

                <div class="cat-panel-header">
                    <div class="cat-panel-title">
                        <i class="fa-solid fa-shield-halved"></i> Clubs
                    </div>
                    <span class="cat-count-badge"><?= count($clubs_data) ?></span>
                </div>

                <!-- Add Club Form -->
                <div class="cat-form-box">
                    <div class="cat-form-title">Add New Club</div>
                    <form method="POST" action="" id="addClubForm">
                        <input type="hidden" name="action" value="add_club">
                        <div class="inline-form-row">
                            <div class="form-group flex-grow">
                                <label for="club_name">Club Name <span class="required">*</span></label>
                                <input type="text" id="club_name" name="club_name"
                                       placeholder="e.g. Manchester United"
                                       maxlength="100" required
                                       value="<?= (($_POST['action'] ?? '') === 'add_club') ? htmlspecialchars($_POST['club_name'] ?? '') : '' ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="club_country_id">Country <span class="field-hint">(optional)</span></label>
                            <select id="club_country_id" name="country_id">
                                <option value="">-- No Country --</option>
                                <?php foreach ($countries_alpha_data as $c):
                                    $sel = ((($_POST['action'] ?? '') === 'add_club') && (($_POST['country_id'] ?? '') == $c['country_id'])) ? 'selected' : '';
                                ?>
                                    <option value="<?= $c['country_id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($c['country_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-cat-add">
                            <i class="fa-solid fa-plus"></i> Add Club
                        </button>
                    </form>
                </div>

                <!-- Clubs List -->
                <div class="cat-list">
                    <?php if (empty($clubs_data)): ?>
                        <div class="cat-empty">
                            <i class="fa-solid fa-shield-halved"></i>
                            <p>No clubs yet. Add one above.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clubs_data as $club): ?>
                            <div class="cat-item" id="club-item-<?= $club['club_id'] ?>">
                                <div class="cat-item-info">
                                    <span class="cat-item-name"><?= htmlspecialchars($club['club_name']) ?></span>
                                    <?php if (!empty($club['country_name'])): ?>
                                        <span class="cat-item-meta">
                                            <i class="fa-solid fa-earth-asia" style="font-size:10px;"></i>
                                            <?= htmlspecialchars($club['country_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="cat-item-meta no-country">No country</span>
                                    <?php endif; ?>
                                </div>
                                <div class="cat-item-actions">
                                    <button type="button" class="cat-btn cat-btn-edit"
                                            title="Edit"
                                            onclick="openEditClub(<?= $club['club_id'] ?>, '<?= htmlspecialchars(addslashes($club['club_name'])) ?>', <?= intval($club['country_id'] ?? 0) ?>)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="cat-btn cat-btn-delete"
                                            title="Delete"
                                            onclick="confirmDeleteClub(<?= $club['club_id'] ?>, '<?= htmlspecialchars(addslashes($club['club_name'])) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /cat-panel clubs -->

        </div><!-- /categories-grid -->

    </div><!-- /main-content -->
</div><!-- /page-wrapper -->

<?php include 'footer.php'; ?>

<!-- ── Edit Country Modal ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="editCountryModal">
    <div class="modal-box edit-modal-box">
        <div class="edit-modal-header">
            <h3 class="edit-modal-title">
                <i class="fa-solid fa-pen"></i> Edit Country
            </h3>
            <button class="modal-close-btn" onclick="closeEditCountry()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_country">
            <input type="hidden" name="country_id" id="edit-country-id">
            <div class="edit-modal-body">
                <div class="form-group">
                    <label for="edit-country-name">Country Name <span class="required">*</span></label>
                    <input type="text" id="edit-country-name" name="country_name"
                           maxlength="100" required placeholder="e.g. England">
                </div>
            </div>
            <div class="edit-modal-footer">
                <button type="button" class="modal-btn modal-cancel" onclick="closeEditCountry()">
                    Cancel
                </button>
                <button type="submit" class="modal-btn modal-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Club Modal ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="editClubModal">
    <div class="modal-box edit-modal-box">
        <div class="edit-modal-header">
            <h3 class="edit-modal-title">
                <i class="fa-solid fa-pen"></i> Edit Club
            </h3>
            <button class="modal-close-btn" onclick="closeEditClub()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_club">
            <input type="hidden" name="club_id" id="edit-club-id">
            <div class="edit-modal-body">
                <div class="form-group">
                    <label for="edit-club-name">Club Name <span class="required">*</span></label>
                    <input type="text" id="edit-club-name" name="club_name"
                           maxlength="100" required placeholder="e.g. Manchester United">
                </div>
                <div class="form-group">
                    <label for="edit-club-country">Country <span class="field-hint">(optional)</span></label>
                    <select id="edit-club-country" name="country_id">
                        <option value="">-- No Country --</option>
                        <?php foreach ($countries_alpha_data as $c): ?>
                            <option value="<?= $c['country_id'] ?>">
                                <?= htmlspecialchars($c['country_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="edit-modal-footer">
                <button type="button" class="modal-btn modal-cancel" onclick="closeEditClub()">
                    Cancel
                </button>
                <button type="submit" class="modal-btn modal-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirmation Modal ──────────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box delete-modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="modal-title" id="delete-modal-title">Delete?</h3>
        <p class="modal-message">You are about to delete <strong id="delete-item-name"></strong>. This cannot be undone.</p>
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="action" id="delete-action">
            <input type="hidden" name="country_id" id="delete-country-id" value="">
            <input type="hidden" name="club_id"    id="delete-club-id"    value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="modal-btn modal-confirm">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../script/categories.js"></script>

</body>
</html>