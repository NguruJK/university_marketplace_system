<?php
require '../includes/auth_check.php';
require '../includes/db.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /ums/index.php");
    exit;
}

// Toggle active/suspended
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid  = intval($_GET['toggle']);
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'student'");
    $stmt->execute([$uid]);
    header("Location: /ums/admin/users.php");
    exit;
}

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = intval($_GET['delete']);

    // Delete in correct order to respect foreign keys
    // 1. Delete enquiries made BY this user
    $pdo->prepare("DELETE FROM enquiries WHERE buyer_id = ?")->execute([$uid]);

    // 2. Delete enquiries ON this user's listings
    $pdo->prepare("DELETE FROM enquiries WHERE listing_id IN (
        SELECT id FROM listings WHERE seller_id = ?
    )")->execute([$uid]);

    // 3. Delete reports made BY this user
    $pdo->prepare("DELETE FROM reports WHERE reporter_id = ?")->execute([$uid]);

    // 4. Delete reports ON this user's listings
    $pdo->prepare("DELETE FROM reports WHERE listing_id IN (
        SELECT id FROM listings WHERE seller_id = ?
    )")->execute([$uid]);

    // 5. Delete this user's listings
    $pdo->prepare("DELETE FROM listings WHERE seller_id = ?")->execute([$uid]);

    // 6. Finally delete the user
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$uid]);

    header("Location: /ums/admin/users.php");
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');
$sql    = "SELECT u.*, COUNT(l.id) AS listing_count
           FROM users u
           LEFT JOIN listings l ON u.id = l.seller_id
           WHERE u.role = 'student'";
$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="admin-header">
        <h2>👥 Manage Users</h2>
        <a href="/ums/admin/dashboard.php" class="back-link">← Dashboard</a>
    </div>

    <!-- Search -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search by name or email...">
        <button type="submit" class="btn-primary btn-search">Search</button>
        <a href="/ums/admin/users.php" class="btn-outline">Clear</a>
    </form>

    <p class="results-count"><?= count($users) ?> student(s) found</p>

    <div class="admin-section">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Listings</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                    <td><?= $u['listing_count'] ?></td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <span class="status-badge <?= $u['is_active'] ? 'status-available' : 'status-removed' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                        </span>
                    </td>
                    <td class="action-btns">
                        <a href="?toggle=<?= $u['id'] ?>"
                           class="tbl-btn <?= $u['is_active'] ? 'btn-warn' : 'btn-success' ?>"
                           onclick="return confirm('Are you sure?')">
                            <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                        </a>
                        <a href="?delete=<?= $u['id'] ?>"
                           class="tbl-btn btn-danger"
                           onclick="return confirm('Delete this user and all their data?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>