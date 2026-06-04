<?php
require 'includes/auth_check.php';
require_once 'includes/db.php';

$errors  = [];
$success = '';

$listing_id = intval($_GET['listing_id'] ?? 0);

// Validate listing exists
$stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ? AND status = 'available'");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch();

if (!$listing) {
    echo "<p style='text-align:center; padding:40px;'>
            <i class="fas fa-triangle-exclamation"></i> Listing not found. <a href='/ums/index.php'>Go back</a>
          </p>";
    exit;
}

// Prevent seller from reporting own listing
if ($listing['seller_id'] === $_SESSION['user_id']) {
    echo "<p style='text-align:center; padding:40px;'>
            <i class="fas fa-triangle-exclamation"></i> You cannot report your own listing. <a href='/ums/index.php'>Go back</a>
          </p>";
    exit;
}

// Check if user already reported this listing
$already = $pdo->prepare("SELECT id FROM reports WHERE reporter_id = ? AND listing_id = ?");
$already->execute([$_SESSION['user_id'], $listing_id]);
if ($already->rowCount() > 0) {
    $success = "already_reported";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $success !== 'already_reported') {
    $reason = trim($_POST['reason']);

    if (empty($reason)) {
        $errors[] = "Please provide a reason for the report.";
    } elseif (strlen($reason) < 10) {
        $errors[] = "Reason must be at least 10 characters.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, listing_id, reason)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $listing_id, $reason]);
        $success = "submitted";
    }
}
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
    <a href="/ums/listing.php?id=<?= $listing_id ?>" class="back-link">
        ← Back to listing
    </a>

    <div class="form-card">
        <h2><i class="fa-solid fa-flag"></i> Report Listing</h2>
        <p class="auth-subtitle">
            Reporting: <strong><?= htmlspecialchars($listing['title']) ?></strong>
        </p>

        <?php if ($success === 'submitted'): ?>
            <div class="alert success">
                <i class="fas fa-circle-check"></i> Your report has been submitted. Our admin team will review it shortly.
                <br><br>
                <a href="/ums/index.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:10px 24px;">
                   Back to Marketplace
                </a>
            </div>

        <?php elseif ($success === 'already_reported'): ?>
            <div class="alert error">
                <i class="fas fa-triangle-exclamation"></i> You have already reported this listing. 
                Our admin team will review it.
                <br><br>
                <a href="/ums/index.php">← Back to Marketplace</a>
            </div>

        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert error">
                    <ul><?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <!-- Listing Preview -->
            <div class="report-preview">
                <?php if ($listing['image']): ?>
                    <img src="/ums/uploads/items/<?= htmlspecialchars($listing['image']) ?>"
                         alt="item">
                <?php else: ?>
                    <div class="report-no-img"><i class="fa-solid fa-box"></i></div>
                <?php endif; ?>
                <div>
                    <p class="report-title"><?= htmlspecialchars($listing['title']) ?></p>
                    <p class="report-price">KSh <?= number_format($listing['price'], 2) ?></p>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Reason for Reporting</label>
                    <div class="reason-options">
                        <?php
                        $reasons = [
                            'Scam or fraudulent listing',
                            'Inappropriate or offensive content',
                            'Item already sold but still listed',
                            'Wrong category',
                            'Spam or duplicate listing',
                            'Other'
                        ];
                        foreach ($reasons as $r):
                        ?>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="<?= $r ?>">
                                <?= $r ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Additional Details <span class="optional">(optional)</span></label>
                    <textarea name="reason" rows="4"
                              placeholder="Describe the issue in more detail..."
                              id="custom_reason"
                    ><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-primary">Submit Report</button>
                <a href="/ums/listing.php?id=<?= $listing_id ?>"
                   class="btn-outline" style="margin-left:10px;">
                   Cancel
                </a>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-fill textarea when a radio option is selected
document.querySelectorAll('.reason-option input').forEach(radio => {
    radio.addEventListener('change', function () {
        document.getElementById('custom_reason').value = this.value;
    });
});
</script>

<?php require 'includes/footer.php'; ?>