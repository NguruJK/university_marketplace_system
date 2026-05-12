<?php
require '../includes/auth_check.php';
require '../includes/db.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /ums/index.php");
    exit;
}

// Remove a listing
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $lid = intval($_GET['remove']);
    $pdo->prepare("UPDATE listings SET status = 'removed' WHERE id = ?")->execute([$lid]);
    header("Location: /ums/admin/listings.php");
    exit;
}

// Restore a listing
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $lid = intval($_GET['restore']);
    $pdo->prepare("UPDATE listings SET status = 'available' WHERE id = ?")->execute([$lid]);
    header("Location: /ums/admin/listings.php");
    exit;
}

// Delete permanently
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $lid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM listings WHERE id = ?")->execute([$lid]);
    header("Location: /ums/admin/listings.php");
    exit;
}

// Filter
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$sql      = "SELECT l.*, u.full_name AS seller_name, c.name AS category_name
             FROM listings l
             JOIN users u ON l.seller_id = u.id
             JOIN categories c ON l.category_id = c.id
             WHERE 1";
$params   = [];

if ($search) {
    $sql .= " AND (l.title LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $sql .= " AND l.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="admin-header">
        <h2>📦 Manage Listings</h2>
        <a href="/ums/admin/dashboard.php" class="back-link">← Dashboard</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search by title or seller...">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
            <option value="sold"      <?= $status === 'sold'      ? 'selected' : '' ?>>Sold</option>
            <option value="removed"   <?= $status === 'removed'   ? 'selected' : '' ?>>Removed</option>
        </select>
        <button type="submit" class="btn-primary btn-search">Filter</button>
        <a href="/ums/admin/listings.php" class="btn-outline">Clear</a>
    </form>

    <p class="results-count"><?= count($listings) ?> listing(s) found</p>

    <div class="admin-section">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Seller</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Condition</th>
                    <th>Posted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <a href="/ums/listing.php?id=<?= $l['id'] ?>" class="tbl-link">
                            <?= htmlspecialchars($l['title']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($l['seller_name']) ?></td>
                    <td><?= htmlspecialchars($l['category_name']) ?></td>
                    <td>KSh <?= number_format($l['price'], 2) ?></td>
                    <td><?= ucfirst(str_replace('_', ' ', $l['condition'])) ?></td>
                    <td><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                    <td>
                        <span class="status-badge status-<?= $l['status'] ?>">
                            <?= ucfirst($l['status']) ?>
                        </span>
                    </td>
                    <td class="action-btns">
                        <?php if ($l['status'] === 'available'): ?>
                            <a href="?remove=<?= $l['id'] ?>"
                               class="tbl-btn btn-warn"
                               onclick="return confirm('Remove this listing?')">Remove</a>
                        <?php elseif ($l['status'] === 'removed'): ?>
                            <a href="?restore=<?= $l['id'] ?>"
                               class="tbl-btn btn-success"
                               onclick="return confirm('Restore this listing?')">Restore</a>
                        <?php endif; ?>
                        <a href="?delete=<?= $l['id'] ?>"
                           class="tbl-btn btn-danger"
                           onclick="return confirm('Permanently delete this listing?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>