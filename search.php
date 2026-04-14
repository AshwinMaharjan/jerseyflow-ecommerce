<?php
require_once 'connect.php'; // adjust path to your DB connection file

$query   = trim($_GET['q'] ?? '');
$results = [];
$total   = 0;

// --- Filter inputs ---
$min_price  = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price  = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$club_id    = isset($_GET['club_id'])   && $_GET['club_id']   !== '' ? (int)$_GET['club_id']   : null;
$size_id    = isset($_GET['size_id'])   && $_GET['size_id']   !== '' ? (int)$_GET['size_id']   : null;
$sort       = $_GET['sort'] ?? 'relevance';

// --- Fetch filter option lists ---
$clubs = $conn->query("SELECT club_id, club_name FROM clubs ORDER BY club_name ASC")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT size_id, size_name FROM sizes ORDER BY size_id ASC")->fetch_all(MYSQLI_ASSOC);

// --- Build search query ---
if ($query !== '') {
    $like = '%' . $conn->real_escape_string($query) . '%';

    $order_clause = match($sort) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'name_asc'   => 'p.product_name ASC',
        default      => 'p.product_name ASC',
    };

    $where_parts = ["(p.product_name LIKE ? OR c.club_name LIKE ? OR co.country_name LIKE ? OR p.description LIKE ?)"];
    $bind_types  = "ssss";
    $bind_values = [$like, $like, $like, $like];

    if ($min_price !== null) {
        $where_parts[] = "p.price >= ?";
        $bind_types   .= "d";
        $bind_values[] = $min_price;
    }
    if ($max_price !== null) {
        $where_parts[] = "p.price <= ?";
        $bind_types   .= "d";
        $bind_values[] = $max_price;
    }
    if ($club_id !== null) {
        $where_parts[] = "p.club_id = ?";
        $bind_types   .= "i";
        $bind_values[] = $club_id;
    }
    if ($size_id !== null) {
        $where_parts[] = "p.size_id = ?";
        $bind_types   .= "i";
        $bind_values[] = $size_id;
    }

    $where_sql = implode(' AND ', $where_parts);

    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.price,
            p.stock,
            p.description,
            c.club_name,
            co.country_name,
            pi.image_path
        FROM products p
        LEFT JOIN clubs     c  ON p.club_id    = c.club_id
        LEFT JOIN countries co ON p.country_id = co.country_id
        LEFT JOIN product_images pi
               ON pi.product_id = p.product_id AND pi.is_primary = 1
        WHERE $where_sql
        ORDER BY $order_clause
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total   = count($results);
    $stmt->close();
}

$page_title = $query !== '' ? 'Search: ' . htmlspecialchars($query) : 'Search';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — JerseyFlow</title>
    <link rel="stylesheet" href="style/navbar.css">
    <link rel="stylesheet" href="style/footer.css">
    <link rel="stylesheet" href="style/search.css">
    <link rel="icon" href="images/logo_icon.ico" type="image/x-icon">

</head>
<body>

<?php include 'homepage/navbar.php'; ?>

<main class="search-main">

    <!-- ── Search Bar ─────────────────────────────────────── -->
    <section class="search-hero">
        <form class="search-form" action="/jerseyflow-ecommerce/search.php" method="GET">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    name="q"
                    id="search-input"
                    class="search-input"
                    placeholder="Search jerseys, clubs, countries…"
                    value="<?= htmlspecialchars($query) ?>"
                    autocomplete="off"
                    autofocus
                >
                <?php if ($query !== ''): ?>
                <a href="/jerseyflow-ecommerce/search.php" class="search-clear" title="Clear">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </a>
                <?php endif; ?>
                <button type="submit" class="search-btn">Search</button>
            </div>
        </form>
    </section>

    <?php if ($query !== ''): ?>

    <div class="search-body">

        <!-- ── Filters Sidebar ────────────────────────────── -->
        <aside class="filters-sidebar" id="filtersSidebar">
            <div class="filters-header">
                <span class="filters-title">Filters</span>
                <button class="filters-reset" id="resetFilters">Reset all</button>
            </div>

            <form id="filtersForm" action="/jerseyflow-ecommerce/search.php" method="GET">
                <input type="hidden" name="q" value="<?= htmlspecialchars($query) ?>">

                <!-- Price Range -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle" aria-expanded="true">
                        Price Range
                        <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="filter-group-body">
                        <div class="price-inputs">
                            <div class="price-field">
                                <label>Min (Rs.)</label>
                                <input type="number" name="min_price" min="0" step="50"
                                       value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
                                       placeholder="0">
                            </div>
                            <span class="price-sep">—</span>
                            <div class="price-field">
                                <label>Max (Rs.)</label>
                                <input type="number" name="max_price" min="0" step="50"
                                       value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
                                       placeholder="Any">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Club -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle" aria-expanded="true">
                        Club
                        <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="filter-group-body">
                        <select name="club_id" class="filter-select">
                            <option value="">All Clubs</option>
                            <?php foreach ($clubs as $club): ?>
                            <option value="<?= $club['club_id'] ?>"
                                <?= ($club_id == $club['club_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($club['club_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Size -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle" aria-expanded="true">
                        Size
                        <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="filter-group-body">
                        <div class="size-pills">
                            <label class="size-pill <?= ($size_id === null) ? 'active' : '' ?>">
                                <input type="radio" name="size_id" value=""> All
                            </label>
                            <?php foreach ($sizes as $size): ?>
                            <label class="size-pill <?= ($size_id == $size['size_id']) ? 'active' : '' ?>">
                                <input type="radio" name="size_id" value="<?= $size['size_id'] ?>"
                                    <?= ($size_id == $size['size_id']) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($size['size_name']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="filter-apply-btn">Apply Filters</button>
            </form>
        </aside>

        <!-- ── Results Area ───────────────────────────────── -->
        <section class="results-area">

            <!-- Results Meta Bar -->
            <div class="results-meta">
                <p class="results-count">
                    <?php if ($total > 0): ?>
                        <strong><?= $total ?></strong> result<?= $total !== 1 ? 's' : '' ?> for
                        <span class="query-tag"><?= htmlspecialchars($query) ?></span>
                    <?php else: ?>
                        No results for <span class="query-tag"><?= htmlspecialchars($query) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($total > 0): ?>
                <div class="sort-wrap">
                    <label for="sortSelect">Sort:</label>
                    <select id="sortSelect" class="sort-select" onchange="applySort(this.value)">
                        <option value="relevance"  <?= $sort === 'relevance'  ? 'selected' : '' ?>>Relevance</option>
                        <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low → High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
                        <option value="name_asc"   <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name: A → Z</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mobile filter toggle -->
            <button class="mobile-filter-btn" id="mobileFilterBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="6" x2="20" y2="6"/>
                    <line x1="4" y1="12" x2="14" y2="12"/>
                    <line x1="4" y1="18" x2="10" y2="18"/>
                </svg>
                Filters
            </button>

            <?php if ($total > 0): ?>
            <!-- Product Grid -->
            <div class="product-grid" id="productGrid">
                <?php foreach ($results as $i => $p):
           $img = !empty($p['image_path'])
    ? '/jerseyflow-ecommerce/uploads/products/' . htmlspecialchars($p['image_path'])
    : '/jerseyflow-ecommerce/uploads/products/placeholder-jersey.png';
                    $in_stock = (int)$p['stock'] > 0;
                ?>
                <a href="/jerseyflow-ecommerce/product.php?id=<?= $p['product_id'] ?>"
                   class="product-card"
                   style="animation-delay: <?= $i * 0.04 ?>s">

                    <div class="card-image-wrap">
                        <?php if (!empty($img)): ?>
    <img
        src="<?= $img ?>"
        alt="<?= htmlspecialchars($p['product_name']) ?>"
        class="card-image"
        loading="lazy"
        onerror="this.parentElement.innerHTML='<div class=\'no-img\'><i class=\'fa-solid fa-shirt\'></i></div>'"
    />
<?php else: ?>
    <div class="no-img"><i class="fa-solid fa-shirt"></i></div>
<?php endif; ?>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($p['club_name'])): ?>
                        <span class="card-club"><?= htmlspecialchars($p['club_name']) ?></span>
                        <?php endif; ?>
                        <h3 class="card-name"><?= htmlspecialchars($p['product_name']) ?></h3>
                        <p class="card-price">Rs. <?= number_format($p['price'], 2) ?></p>
                    </div>

                </a>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        <line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                </div>
                <h2 class="empty-title">No jerseys found</h2>
                <p class="empty-sub">Try a different keyword — club name, country, or jersey type.</p>
                <a href="/jerseyflow-ecommerce/search.php" class="empty-btn">Clear Search</a>
            </div>
            <?php endif; ?>

        </section>
    </div>

    <?php else: ?>

    <!-- Landing state — no query yet -->
    <div class="search-landing">
        <div class="landing-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
        <p class="landing-hint">Start typing to find your jersey</p>
    </div>

    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>

<script src="script/search.js"></script>
</body>
</html>