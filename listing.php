<?php
require_once 'includes/db.php';

$id   = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT l.*, u.full_name AS seller_name, u.phone AS seller_phone,
           u.email AS seller_email, c.name AS category_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    JOIN categories c ON l.category_id = c.id
    WHERE l.id = ? AND l.status = 'available'
");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    echo "<p style='text-align:center;padding:40px'>Item not found or no longer available.</p>";
    exit;
}

// Fetch enquiries for this listing
$enq_stmt = $pdo->prepare("
    SELECT e.*, u.full_name AS buyer_name
    FROM enquiries e
    JOIN users u ON e.buyer_id = u.id
    WHERE e.listing_id = ?
    ORDER BY e.created_at DESC
");
$enq_stmt->execute([$id]);
$enquiries = $enq_stmt->fetchAll();
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
    <a href="/ums/index.php" class="back-link">← Back to listings</a>

    <div class="listing-detail">
        <!-- Image -->
        <div class="detail-image">
            <?php if ($item['image']): ?>
                <img src="/ums/uploads/items/<?= htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['title']) ?>">
            <?php else: ?>
                <div class="no-image large">📦</div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="detail-info">
            <span class="card-category"><?= htmlspecialchars($item['category_name']) ?></span>
            <h1><?= htmlspecialchars($item['title']) ?></h1>
            <?php
            require_once 'includes/fee.php';
            $fee = calculateFee($item['price']);
            
            ?>
            <p class="detail-price">KSh <?= number_format($item['price'], 2) ?></p>
            <div class="listing-fee-note">
                <span>🏛️ <?= PLATFORM_FEE_PERCENT ?>% platform fee applies on purchase</span>
                <span>Seller receives: <strong>KSh <?= number_format($fee['seller_amount'], 2) ?></strong></span>
            </div>
            <span class="badge condition-<?= $item['condition'] ?>">
                <?= ucfirst(str_replace('_', ' ', $item['condition'])) ?>
            </span>
                <?php if ($item['is_verified']): ?>
                    <div class="verified-box">
                        ✅ <strong>Verified Authentic</strong>
                        <span>AI-confirmed real item p
                            hoto</span>
                    </div>
                <?php endif; ?>
            <p class="detail-description"><?= nl2br(htmlspecialchars($item['description'])) ?></p>

            <div class="seller-box">
                <h4>Seller Info</h4>
                <p><strong><?= htmlspecialchars($item['seller_name']) ?></strong></p>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <p>📧 <?= htmlspecialchars($item['seller_email']) ?></p>
                    <?php if ($item['seller_phone']): ?>
                        <p>📞 <?= htmlspecialchars($item['seller_phone']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><a href="/ums/auth/login.php">Login to view seller contact</a></p>
                <?php endif; ?>
            </div>
            <!-- Buy Button -->
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $item['seller_id']): ?>
                    <a href="/ums/pay.php?listing_id=<?= $item['id'] ?>"
                    class="btn-primary"
                    style="display:block; text-align:center; margin-top:16px;">
                        💚 Buy Now — KSh <?= number_format($item['price'], 2) ?>
                    </a>
                <?php endif; ?>
            <!-- Report button -->
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $item['seller_id']): ?>
                <a href="/ums/report.php?listing_id=<?= $item['id'] ?>" class="btn-outline btn-sm">
                    ⚑ Report this listing
                </a>
            <?php endif; ?>
            <!-- Edit button — only visible to owner -->
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $item['seller_id']): ?>
                <a href="/ums/edit_listing.php?id=<?= $item['id'] ?>"
                class="btn-primary"
                style="display:block; text-align:center; margin-top:12px;">
                    ✏️ Edit This Listing
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enquiries Section -->
    <div class="enquiry-section">
        <h3>Questions about this item</h3>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $item['seller_id']): ?>
            <form method="POST" action="/ums/enquiry/ask.php">
                <input type="hidden" name="listing_id" value="<?= $item['id'] ?>">
                <div class="form-group">
                    <textarea name="question" rows="3"
                              placeholder="Ask the seller a question..." required></textarea>
                </div>
                <button type="submit" class="btn-primary">Ask Question</button>
            </form>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <p><a href="/ums/auth/login.php">Login</a> to ask a question.</p>
        <?php endif; ?>

        <div class="enquiry-list">
            <?php if (empty($enquiries)): ?>
                <p class="no-enquiries">No questions yet. Be the first to ask!</p>
            <?php else: ?>
                <?php foreach ($enquiries as $q): ?>
                    <div class="enquiry-item <?= $q['is_resolved'] ? 'resolved' : '' ?>">
                        <p class="enquiry-question">
                            <strong><?= htmlspecialchars($q['buyer_name']) ?>:</strong>
                            <?= htmlspecialchars($q['question']) ?>
                        </p>
                        <?php if ($q['answer']): ?>
                            <p class="enquiry-answer">
                                💬 <em><?= htmlspecialchars($q['answer']) ?></em>
                            </p>
                        <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $item['seller_id']): ?>
                            <form method="POST" action="/ums/enquiry/reply.php">
                                <input type="hidden" name="enquiry_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="listing_id"  value="<?= $item['id'] ?>">
                                <textarea name="answer" rows="2" placeholder="Write your reply..."></textarea>
                                <button type="submit" class="btn-primary btn-sm">Reply</button>
                            </form>
                        <?php else: ?>
                            <p class="no-answer">⏳ Awaiting seller reply...</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>