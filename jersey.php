<?php
require_once 'connect.php'; // provides $conn (mysqli)

// ── Allowed special_type values (whitelist) ───────────────────
$allowed_types = ['retro', 'limited', 'player_edition', 'worldcup_2026','standard'];

$type = isset($_GET['type']) && in_array($_GET['type'], $allowed_types, true)
    ? $_GET['type']
    : null;

// ── Pagination ────────────────────────────────────────────────
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));

// ── Count total matching jerseys ──────────────────────────────
if ($type) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM products p WHERE p.special_type = ?");
    $count_stmt->bind_param('s', $type);
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM products p");
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_products / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// ── Build & run paginated query ───────────────────────────────
$base_sql = "SELECT
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
LEFT JOIN countries co ON p.country_id = co.country_id";

$params = [];
$types  = '';

if ($type) {
    $base_sql .= " WHERE p.special_type = ?";
    $params[]  = $type;
    $types    .= 's';
}

$base_sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[]  = $per_page;
$params[]  = $offset;
$types    .= 'ii';

$stmt = $conn->prepare($base_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$jerseys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Filter tab labels ─────────────────────────────────────────
$filter_tabs = [
    ''               => 'All Jerseys',
    'standard'  => 'Standard',
    'retro'          => 'Retro',
    'limited'        => 'Limited Edition',
    'player_edition' => 'Player Edition',
    'worldcup_2026'  => 'FIFA 2026',
];

// ── Type badge display labels ─────────────────────────────────
$type_labels = [
    'standard'       => 'Standard',
    'player_edition' => 'Player Edition',
    'limited'        => 'Limited Edition',
    'worldcup_2026'  => 'World Cup 2026',
    'retro'          => 'Retro',
];

// Active page title
$page_title = ($type && isset($filter_tabs[$type])) ? $filter_tabs[$type] : 'All Jerseys';

// ── Pagination URL helper — preserves type filter ─────────────
$q_parts = array_filter(['type' => $type]);
$q       = $q_parts ? '&' . http_build_query($q_parts) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($page_title) ?> – Jersey Store</title>

    <!--
        FAVICON FIX:
        Browsers resolve <link rel="icon"> relative to the HTML document's URL,
        NOT relative to the PHP file location on disk.
        Using an absolute path from the web root avoids any mismatch.
        Adjust the path below to match your actual web root setup.
    -->
    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">

    <!-- Font Awesome (offline) -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <!--
        Barlow Condensed (offline).
        jersey.css references this via @font-face, but we also load the
        dedicated CSS file here so it works even if jersey.css is cached.
    -->
    <link rel="stylesheet" href="assets/fonts/barlow-condensed/barlow-condensed.css">

    <!-- Page styles -->
    <link rel="stylesheet" href="style/footer.css">
    <link rel="stylesheet" href="style/jersey.css">
</head>
<body>
<?php include("navbar.php"); ?>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<main class="jersey-page">

    <!-- Page header -->
    <div class="page-header">
        <h1><?= htmlspecialchars($page_title) ?></h1>
        <p>Browse our collection of authentic football jerseys.</p>
    </div>

    <!-- Filter tabs -->
    <nav class="filter-tabs" aria-label="Jersey type filter">
        <?php foreach ($filter_tabs as $key => $label): ?>
            <?php
                $href      = $key === '' ? 'jersey.php' : 'jersey.php?type=' . urlencode($key);
                $is_active = ($key === '' && $type === null) || ($key !== '' && $type === $key);
            ?>
            <a href="<?= $href ?>" class="<?= $is_active ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Result count -->
    <p class="result-count">
        <?php if ($total_products > 0): ?>
            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?>
            of <?= $total_products ?> <?= $total_products === 1 ? 'jersey' : 'jerseys' ?>
            <?= $type ? ' in <strong>' . htmlspecialchars($filter_tabs[$type]) . '</strong>' : '' ?>
        <?php else: ?>
            No jerseys found
        <?php endif; ?>
    </p>

    <!-- Jersey grid -->
    <div class="jersey-grid">
        <?php if (empty($jerseys)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5">
                    <path d="M9 2h6l1 3H8L9 2z"/>
                    <path d="M3 5l1.5 14h15L21 5H3z"/>
                    <path d="M9 5v4m6-4v4"/>
                </svg>
                <h2>No jerseys found</h2>
                <p>We couldn't find any jerseys in this category right now.</p>
                <a href="jersey.php">View all jerseys</a>
            </div>

        <?php else: ?>
            <?php foreach ($jerseys as $jersey): ?>
                <?php
                    // Primary image takes priority, fall back to main image column
                    $display_img = !empty($jersey['primary_image'])
                        ? $jersey['primary_image']
                        : ($jersey['image'] ?? null);

                    // Club or country affiliation label
                    $affiliation = $jersey['club_name'] ?? $jersey['country_name'] ?? null;

                    // Special type badge
                    $badge_label = isset($jersey['special_type'], $type_labels[$jersey['special_type']])
                        ? $type_labels[$jersey['special_type']]
                        : null;
                ?>
                <a href="jersey_detail.php?id=<?= (int)$jersey['product_id'] ?>" class="jersey-card">

                    <!-- Image -->
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

                    <!-- Body -->
                    <div class="card-body">

                        <?php if ($badge_label): ?>
                            <span class="card-type-badge"><?= htmlspecialchars($badge_label) ?></span>
                        <?php endif; ?>

                        <div class="card-name"><?= htmlspecialchars($jersey['product_name']) ?></div>

                        <?php if ($affiliation): ?>
                            <div class="card-club"><?= htmlspecialchars($affiliation) ?></div>
                        <?php endif; ?>

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

    <!-- ── Pagination ──────────────────────────────────────── -->
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

            <?php if ($start > 1): ?>
                <span class="page-gap">…</span>
            <?php endif; ?>

            <?php for ($p2 = $start; $p2 <= $end; $p2++): ?>
                <a href="?page=<?= $p2 ?><?= $q ?>"
                   class="page-btn <?= $p2 === $page ? 'active' : '' ?>">
                    <?= $p2 ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
                <span class="page-gap">…</span>
            <?php endif; ?>

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

</main>
<!-- ══════════════════ END MAIN ══════════════════ -->

<?php include("footer.php"); ?>

</body>
</html>