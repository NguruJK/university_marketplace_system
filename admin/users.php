<?php
require_once '../includes/auth_check.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /ums/index.php");
    exit;
}

// Toggle active/suspended
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = intval($_GET['toggle']);
    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'student'")
        ->execute([$uid]);
    header("Location: /ums/admin/users.php");
    exit;
}

// Remove user (cascade delete all related data)
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $uid = intval($_GET['remove']);

    try {
        // 1. Delete community replies by this user
        $pdo->prepare("DELETE FROM enquiry_replies WHERE student_id = ?")->execute([$uid]);

        // 2. Delete community questions by this user
        $pdo->prepare("DELETE FROM general_enquiries WHERE student_id = ?")->execute([$uid]);

        // 3. Delete enquiries made BY this user (on listings)
        $pdo->prepare("DELETE FROM enquiries WHERE buyer_id = ?")->execute([$uid]);

        // 4. Delete enquiries ON this user's listings
        $pdo->prepare("DELETE FROM enquiries WHERE listing_id IN (
            SELECT id FROM listings WHERE seller_id = ?
        )")->execute([$uid]);

        // 5. Delete reports made BY this user
        $pdo->prepare("DELETE FROM reports WHERE reporter_id = ?")->execute([$uid]);

        // 6. Delete reports ON this user's listings
        $pdo->prepare("DELETE FROM reports WHERE listing_id IN (
            SELECT id FROM listings WHERE seller_id = ?
        )")->execute([$uid]);

        // 7. Delete transactions linked to this user's orders (as buyer)
        $pdo->prepare("DELETE FROM transactions WHERE order_id IN (
            SELECT id FROM orders WHERE buyer_id = ?
        )")->execute([$uid]);

        // 8. Delete orders where user is buyer
        $pdo->prepare("DELETE FROM orders WHERE buyer_id = ?")->execute([$uid]);

        // 9. Delete transactions linked to this user's listings (as seller)
        $pdo->prepare("DELETE FROM transactions WHERE order_id IN (
            SELECT id FROM orders WHERE listing_id IN (
                SELECT id FROM listings WHERE seller_id = ?
            )
        )")->execute([$uid]);

        // 10. Delete orders on this user's listings
        $pdo->prepare("DELETE FROM orders WHERE listing_id IN (
            SELECT id FROM listings WHERE seller_id = ?
        )")->execute([$uid]);

        // 11. Delete this user's listings
        $pdo->prepare("DELETE FROM listings WHERE seller_id = ?")->execute([$uid]);

        // 12. Finally remove the user
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$uid]);

        header("Location: /ums/admin/users.php?removed=1");
    } catch (PDOException $e) {
        header("Location: /ums/admin/users.php?error=" . urlencode($e->getMessage()));
    }
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
        <h2><i class="fa-solid fa-users"></i> Manage Users</h2>
        <a href="/ums/admin/dashboard.php" class="back-link">← Dashboard</a>
    </div>

    <?php if (isset($_GET['removed'])): ?>
        <div class="alert success"><i class="fas fa-check-circle"></i> User removed successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert error"><i class="fas fa-exclamation-triangle"></i> Error: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

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
                        <a href="?remove=<?= $u['id'] ?>"
                           class="tbl-btn btn-danger"
                           onclick="return confirm('Remove this user and all their data from the platform?')">
                            Remove User
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>