<?php
require_once '../includes/auth_check.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /ums/index.php");
    exit;
}

$total_users     = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_listings  = $pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
$active_listings = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'available'")->fetchColumn();
$total_enquiries = $pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn();
$pending_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$sold_listings   = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'sold'")->fetchColumn();

$total_fees = $pdo->query("
    SELECT COALESCE(SUM(platform_fee), 0)
    FROM orders
    WHERE status = 'completed'
")->fetchColumn();

$recent_listings = $pdo->query("
    SELECT l.*, u.full_name AS seller_name, c.name AS category_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    JOIN categories c ON l.category_id = c.id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll();

$recent_users = $pdo->query("
    SELECT * FROM users WHERE role = 'student'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="admin-header">
        <h2>
            <i class="fa-solid fa-gear"></i> Admin Dashboard
        </h2>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>. Here's your system overview.</p>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fa-solid fa-users"></i></span>
            <div>
                <p class="stat-number"><?= $total_users ?></p>
                <p class="stat-label">Registered Students</p>
            </div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon"><i class="fa-solid fa-box-open"></i></span>
            <div>
                <p class="stat-number"><?= $active_listings ?></p>
                <p class="stat-label">Active Listings</p>
            </div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon"><i class="fa-solid fa-circle-check"></i></span>
            <div>
                <p class="stat-number"><?= $sold_listings ?></p>
                <p class="stat-label">Sold Items</p>
            </div>
        </div>
        <div class="stat-card purple">
            <span class="stat-icon"><i class="fa-solid fa-comments"></i></span>
            <div>
                <p class="stat-number"><?= $total_enquiries ?></p>
                <p class="stat-label">Total Enquiries</p>
            </div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon"><i class="fa-solid fa-flag"></i></span>
            <div>
                <p class="stat-number"><?= $pending_reports ?></p>
                <p class="stat-label">Pending Reports</p>
            </div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon"><i class="fa-solid fa-building-columns"></i></span>
            <div>
                <p class="stat-number">KSh <?= number_format($total_fees, 2) ?></p>
                <p class="stat-label">Platform Fees Collected</p>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="admin-quick-links">
        <a href="/ums/admin/users.php" class="quick-link">
            <i class="fa-solid fa-users"></i> Manage Users
        </a>
        <a href="/ums/admin/listings.php" class="quick-link">
            <i class="fa-solid fa-box"></i> Manage Listings
        </a>
        <a href="/ums/admin/reports.php" class="quick-link">
            <i class="fa-solid fa-flag"></i> View Reports
            <?php if ($pending_reports > 0): ?>
                <span class="badge-count"><?= $pending_reports ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="admin-two-col">

        <!-- Recent Listings -->
        <div class="admin-section">
            <h3>Recent Listings</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_listings as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['title']) ?></td>
                        <td><?= htmlspecialchars($l['seller_name']) ?></td>
                        <td>KSh <?= number_format($l['price'], 2) ?></td>
                        <td>
                            <span class="status-badge status-<?= $l['status'] ?>">
                                <?= ucfirst($l['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="/ums/listing.php?id=<?= $l['id'] ?>" class="tbl-link">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="/ums/admin/listings.php" class="see-all">See all listings &rarr;</a>
        </div>

        <!-- Recent Users -->
        <div class="admin-section">
            <h3>Recent Registrations</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="status-badge <?= $u['is_active'] ? 'status-available' : 'status-removed' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                            </span>
                        </td>
                        <td>
                            <a href="/ums/admin/users.php" class="tbl-link">Manage</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="/ums/admin/users.php" class="see-all">See all users &rarr;</a>
        </div>

    </div>
</div>

<?php require '../includes/footer.php'; ?>