<?php
require '../includes/auth_check.php';
require '../includes/db.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /ums/index.php");
    exit;
}

// Update report status
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $rid    = intval($_GET['id']);
    if (in_array($action, ['reviewed', 'dismissed'])) {
        $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?")
            ->execute([$action, $rid]);
    }
    header("Location: /ums/admin/reports.php");
    exit;
}

$reports = $pdo->query("
    SELECT r.*, u.full_name AS reporter_name, l.title AS listing_title
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    LEFT JOIN listings l ON r.listing_id = l.id
    ORDER BY r.created_at DESC
")->fetchAll();
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="admin-header">
        <h2>⚑ Reported Content</h2>
        <a href="/ums/admin/dashboard.php" class="back-link">← Dashboard</a>
    </div>

    <div class="admin-section">
        <?php if (empty($reports)): ?>
            <p class="no-enquiries" style="padding:20px">No reports submitted yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reporter</th>
                    <th>Listing</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                    <td>
                        <?php if ($r['listing_title']): ?>
                            <a href="/ums/listing.php?id=<?= $r['listing_id'] ?>" class="tbl-link">
                                <?= htmlspecialchars($r['listing_title']) ?>
                            </a>
                        <?php else: ?>
                            <em>Deleted</em>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['reason']) ?></td>
                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <span class="status-badge
                            <?= $r['status'] === 'pending'   ? 'status-removed'    : '' ?>
                            <?= $r['status'] === 'reviewed'  ? 'status-available'  : '' ?>
                            <?= $r['status'] === 'dismissed' ? 'status-sold'       : '' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="action-btns">
                        <?php if ($r['status'] === 'pending'): ?>
                            <a href="?action=reviewed&id=<?= $r['id'] ?>"
                               class="tbl-btn btn-success">Mark Reviewed</a>
                            <a href="?action=dismissed&id=<?= $r['id'] ?>"
                               class="tbl-btn btn-warn">Dismiss</a>
                        <?php else: ?>
                            <span class="tbl-link" style="color:#aaa">Handled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require '../includes/footer.php'; ?>