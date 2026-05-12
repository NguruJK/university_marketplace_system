<?php
require 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Search & filter inputs
$search     = trim($_GET['search'] ?? '');
$category   = intval($_GET['category'] ?? 0);
$condition  = $_GET['condition'] ?? '';

// Build dynamic query
$sql    = "SELECT l.*, u.full_name AS seller_name, c.name AS category_name
           FROM listings l
           JOIN users u ON l.seller_id = u.id
           JOIN categories c ON l.category_id = c.id
           WHERE l.status = 'available'";
$params = [];

if ($search) {
    $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category) {
    $sql .= " AND l.category_id = ?";
    $params[] = $category;
}
if ($condition) {
    $sql .= " AND l.condition = ?";
    $params[] = $condition;
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">

    <!-- Hero Banner -->
    <div class="hero">
        <img src="/ums/assets/logo.png" alt="UoN Logo" class="hero-logo">
        <h1>University Marketplace</h1>
        <p>Buy and sell within the UoN student community — safely and affordably.</p>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="🔍 Search items...">

        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"
                    <?= $category == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="condition">
            <option value="">Any Condition</option>
            <option value="new"          <?= $condition === 'new'          ? 'selected' : '' ?>>New</option>
            <option value="like_new"     <?= $condition === 'like_new'     ? 'selected' : '' ?>>Like New</option>
            <option value="used"         <?= $condition === 'used'         ? 'selected' : '' ?>>Used</option>
            <option value="heavily_used" <?= $condition === 'heavily_used' ? 'selected' : '' ?>>Heavily Used</option>
        </select>

        <button type="submit" class="btn-primary btn-search">Search</button>
        <a href="/ums/index.php" class="btn-outline">Clear</a>
    </form>

    <!-- Results Count -->
    <p class="results-count">
        <?= count($listings) ?> item<?= count($listings) !== 1 ? 's' : '' ?> found
        <?= $search ? " for \"<strong>" . htmlspecialchars($search) . "</strong>\"" : '' ?>
    </p>

    <!-- Listings Grid -->
    <?php if (empty($listings)): ?>
        <div class="empty-state">
            <p>😕 No items found. Be the first to <a href="/ums/post_item.php">post one!</a></p>
        </div>
    <?php else: ?>
        <div class="listings-grid">
            <?php foreach ($listings as $item): ?>
                <a href="/ums/listing.php?id=<?= $item['id'] ?>" class="listing-card">
                    <div class="card-image">
                        <?php if ($item['image']): ?>
                            <img src="/ums/uploads/items/<?= htmlspecialchars($item['image']) ?>"
                                 alt="<?= htmlspecialchars($item['title']) ?>">
                        <?php else: ?>
                            <div class="no-image">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <span class="card-category"><?= htmlspecialchars($item['category_name']) ?></span>
                        <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="card-price">KSh <?= number_format($item['price'], 2) ?></p>
                        <div class="card-meta">
                            <span class="badge condition-<?= $item['condition'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $item['condition'])) ?>
                            </span>
                            <?php if ($item['is_verified']): ?>
                                <span class="verified-badge">✅ Verified</span>
                            <?php endif; ?>
                            <span class="card-seller">by <?= htmlspecialchars($item['seller_name']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>