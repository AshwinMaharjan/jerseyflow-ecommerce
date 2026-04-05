<?php
require_once 'connect.php'; // provides $conn (mysqli)

// ── Resolve club from URL ─────────────────────────────────────
// URL pattern: clubs.php?club=fcbarcelona
$club_slug = isset($_GET['club']) ? trim($_GET['club']) : '';

// Match club by name (case-insensitive, space-insensitive)
$club = null;
if ($club_slug !== '') {
    $club_stmt = $conn->prepare(
        "SELECT club_id, club_name, country_id
         FROM clubs
         WHERE LOWER(REPLACE(club_name, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
         LIMIT 1"
    );
    $club_stmt->bind_param('s', $club_slug);
    $club_stmt->execute();
    $club = $club_stmt->get_result()->fetch_assoc();
    $club_stmt->close();
}

// No valid club → show all clubs browse grid
$show_all_clubs = ($club === null);

// ── Pagination setup ──────────────────────────────────────────
$per_page       = 20;
$page           = max(1, (int)($_GET['page'] ?? 1));
$total_products = 0;
$total_pages    = 1;
$offset         = 0;
$jerseys        = [];

if (!$show_all_clubs) {

    // Count jerseys for this club
    $count_stmt = $conn->prepare(
        "SELECT COUNT(*) FROM products p WHERE p.club_id = ?"
    );
    $count_stmt->bind_param('i', $club['club_id']);
    $count_stmt->execute();
    $total_products = $count_stmt->get_result()->fetch_row()[0];
    $count_stmt->close();

    $total_pages = max(1, (int)ceil($total_products / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    // Fetch paginated jerseys
    $data_stmt = $conn->prepare(
        "SELECT
            p.product_id, p.product_name, p.price, p.image,
            p.special_type,
            cl.club_name,
            s.size_name,
            co.country_name,
            (SELECT pi.image_path FROM product_images pi
             WHERE pi.product_id = p.product_id AND pi.is_primary = 1
             LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN clubs     cl ON p.club_id    = cl.club_id
        LEFT JOIN sizes     s  ON p.size_id    = s.size_id
        LEFT JOIN countries co ON p.country_id = co.country_id
        WHERE p.club_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?"
    );
    $data_stmt->bind_param('iii', $club['club_id'], $per_page, $offset);
    $data_stmt->execute();
    $jerseys = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $data_stmt->close();

} else {
    // Fetch all clubs with jersey counts
    $clubs_stmt = $conn->prepare(
        "SELECT cl.club_id, cl.club_name,
                COUNT(p.product_id) AS jersey_count
         FROM clubs cl
         LEFT JOIN products p ON p.club_id = cl.club_id
         GROUP BY cl.club_id, cl.club_name
         ORDER BY cl.club_name ASC"
    );
    $clubs_stmt->execute();
    $all_clubs = $clubs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $clubs_stmt->close();
}

// ── Type badge labels (same as jersey.php) ────────────────────
$type_labels = [
    'standard'       => 'Standard',
    'player_edition' => 'Player Edition',
    'limited'        => 'Limited Edition',
    'worldcup_2026'  => 'World Cup 2026',
    'retro'          => 'Retro',
];

// ── Page title ────────────────────────────────────────────────
$page_title = $show_all_clubs
    ? 'Browse by Club'
    : htmlspecialchars($club['club_name']) . ' Jerseys';

// ── Pagination URL helper — preserves ?club= across pages ─────
$q = $club_slug !== '' ? '&club=' . urlencode($club_slug) : '';

// ── Club initial helper — used as avatar in place of logo ─────
function club_initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    if (count($words) >= 2) {
        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $page_title ?> – Jersey Store</title>

    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">

    <!-- Font Awesome (offline) -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <!-- Barlow Condensed (offline) -->
    <link rel="stylesheet" href="assets/fonts/barlow-condensed/barlow-condensed.css">

    <!-- Page styles -->
    <link rel="stylesheet" href="style/footer.css">
    <link rel="stylesheet" href="style/navbar.css">
    <link rel="stylesheet" href="style/clubs.css">
</head>
<body>
<?php include("homepage/navbar.php"); ?>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<main class="clubs-page">

    <?php if ($show_all_clubs): ?>
    <!-- ══ ALL CLUBS BROWSE VIEW ══════════════════════════════ -->

        <div class="page-header">
            <h1>Browse by Club</h1>
            <p>Select a club to view all available jerseys.</p>
        </div>

        <div class="clubs-grid">
            <?php if (empty($all_clubs)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-shield-halved empty-icon"></i>
                    <h2>No clubs found</h2>
                    <p>No clubs are available at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_clubs as $c): ?>
                    <?php $slug = strtolower(str_replace(' ', '', $c['club_name'])); ?>
                    <a href="clubs.php?club=<?= urlencode($slug) ?>" class="club-card">

                        <!-- Club initial avatar (no image needed) -->
                        <div class="club-avatar">
                            <?= htmlspecialchars(club_initials($c['club_name'])) ?>
                        </div>

                        <div class="club-card-body">
                            <span class="club-card-name"><?= htmlspecialchars($c['club_name']) ?></span>
                            <span class="club-card-count">
                                <?= (int)$c['jersey_count'] ?>
                                <?= $c['jersey_count'] == 1 ? 'jersey' : 'jerseys' ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
    <!-- ══ SINGLE CLUB JERSEY LISTING ════════════════════════ -->

        <!-- Club hero header -->
        <div class="club-hero">

            <!-- Club initial avatar (large) -->
            <div class="club-hero-avatar">
                <?= htmlspecialchars(club_initials($club['club_name'])) ?>
            </div>

            <div class="club-hero-info">
                <span class="club-hero-breadcrumb">
                    <a href="clubs.php">All Clubs</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <?= htmlspecialchars($club['club_name']) ?>
                </span>
                <h1><?= htmlspecialchars($club['club_name']) ?> Jerseys</h1>
                <p>Browse all available <?= htmlspecialchars($club['club_name']) ?> jerseys.</p>
            </div>
        </div>

        <!-- Result count -->
        <p class="result-count">
            <?php if ($total_products > 0): ?>
                Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?>
                of <?= $total_products ?> <?= $total_products === 1 ? 'jersey' : 'jerseys' ?>
            <?php else: ?>
                No jerseys found for this club
            <?php endif; ?>
        </p>

        <!-- Jersey grid -->
        <div class="jersey-grid">
            <?php if (empty($jerseys)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-shirt empty-icon"></i>
                    <h2>No jerseys found</h2>
                    <p>We don't have any jerseys for this club right now.</p>
                    <a href="clubs.php">Browse all clubs</a>
                </div>

            <?php else: ?>
                <?php foreach ($jerseys as $jersey): ?>
                    <?php
                        $display_img = !empty($jersey['primary_image'])
                            ? $jersey['primary_image']
                            : ($jersey['image'] ?? null);

                        $badge_label = isset($jersey['special_type'], $type_labels[$jersey['special_type']])
                            ? $type_labels[$jersey['special_type']]
                            : null;
                    ?>
                    <a href="product.php?id=<?= (int)$jersey['product_id'] ?>" class="jersey-card">

                        <div class="card-img">
                            <?php if (!empty($display_img)): ?>
                                <img
                                    src="/jerseyflow-ecommerce/uploads/products/<?= htmlspecialchars($display_img) ?>"
                                    alt="<?= htmlspecialchars($jersey['product_name']) ?>"
                                    loading="lazy"
                                    onerror="this.parentElement.innerHTML='<div class=\'no-img\'><i class=\'fa-solid fa-shirt\'></i></div>'"
                                />
                            <?php else: ?>
                                <div class="no-img"><i class="fa-solid fa-shirt"></i></div>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <?php if ($badge_label): ?>
                                <span class="card-type-badge"><?= htmlspecialchars($badge_label) ?></span>
                            <?php endif; ?>

                            <div class="card-name"><?= htmlspecialchars($jersey['product_name']) ?></div>

                            <div class="card-footer">
                                <span class="card-price">
                                    Rs. <?= number_format((float)$jersey['price'], 2) ?>
                                </span>
                            </div>
                        </div>

                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">

                <a href="?page=1<?= $q ?>"
                   class="page-btn <?= $page === 1 ? 'disabled' : '' ?>"
                   title="First page">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
                <a href="?page=<?= max(1, $page - 1) ?><?= $q ?>"
                   class="page-btn <?= $page === 1 ? 'disabled' : '' ?>"
                   title="Previous page">
                    <i class="fa-solid fa-angle-left"></i>
                </a>

                <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                ?>
                <?php if ($start > 1): ?><span class="page-gap">…</span><?php endif; ?>

                <?php for ($p2 = $start; $p2 <= $end; $p2++): ?>
                    <a href="?page=<?= $p2 ?><?= $q ?>"
                       class="page-btn <?= $p2 === $page ? 'active' : '' ?>">
                        <?= $p2 ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?><span class="page-gap">…</span><?php endif; ?>

                <a href="?page=<?= min($total_pages, $page + 1) ?><?= $q ?>"
                   class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"
                   title="Next page">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
                <a href="?page=<?= $total_pages ?><?= $q ?>"
                   class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"
                   title="Last page">
                    <i class="fa-solid fa-angles-right"></i>
                </a>

            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>
<!-- ══════════════════ END MAIN ══════════════════ -->

<?php include("footer.php"); ?>

</body>
</html>