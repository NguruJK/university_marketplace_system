<?php
require 'includes/auth_check.php';
require 'includes/db.php';
require 'mpesa/mpesa.php';
require 'includes/fee.php';

$listing_id = intval($_GET['listing_id'] ?? 0);

// Get listing details
$stmt = $pdo->prepare("
    SELECT l.*, u.full_name AS seller_name
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    WHERE l.id = ? AND l.status = 'available'
");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch();

if (!$listing) {
    echo "<p style='text-align:center;padding:40px'>
            ⚠️ Item not available. <a href='/ums/index.php'>Go back</a>
          </p>";
    exit;
}

// Prevent buying own listing
if ($listing['seller_id'] === $_SESSION['user_id']) {
    echo "<p style='text-align:center;padding:40px'>
            ⚠️ You cannot buy your own listing.
          </p>";
    exit;
}

// Fetch buyer's phone
$buyer = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$buyer->execute([$_SESSION['user_id']]);
$buyer = $buyer->fetch();

$error          = '';
$checkout_id    = '';
$order_id       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone  = preg_replace('/\D/', '', $_POST['phone']); // digits only
    $amount = $listing['price'];

    // Validate phone
    if (!preg_match('/^(254|0)[17]\d{8}$/', $phone)) {
        $error = "Please enter a valid Safaricom number (e.g. 0712345678)";
    }

    if (empty($error)) {
        // Create order
require 'includes/fee.php';
$fee_data = calculateFee($amount);

        $pdo->prepare("
            INSERT INTO orders (listing_id, buyer_id, amount, platform_fee, seller_amount)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $listing_id,
            $_SESSION['user_id'],
            $fee_data['price'],
            $fee_data['fee'],
            $fee_data['seller_amount']
        ]);
        $order_id = $pdo->lastInsertId();

        // Initiate STK Push
        $mpesa    = new MpesaDaraja();
        $response = $mpesa->stkPush($phone, $amount, $order_id);

        if (isset($response['CheckoutRequestID'])) {
            $checkout_id = $response['CheckoutRequestID'];
            $merchant_id = $response['MerchantRequestID'];

            // Save transaction record
            $pdo->prepare("
                INSERT INTO transactions 
                (order_id, phone, amount, merchant_request_id, checkout_request_id)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $order_id, $phone, $amount,
                $merchant_id, $checkout_id
            ]);

        } else {
            $error = "Could not initiate payment. Please try again.";
            // Delete the pending order
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
        }
    }
}
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
    <a href="/ums/listing.php?id=<?= $listing_id ?>" class="back-link">← Back to listing</a>

    <div class="pay-grid">

        <!-- Item Summary -->
        <div class="pay-summary">
            <h3>Order Summary</h3>
            <div class="pay-item">
                <?php if ($listing['image']): ?>
                    <img src="/ums/uploads/items/<?= htmlspecialchars($listing['image']) ?>"
                         alt="item">
                <?php else: ?>
                    <div class="pay-no-img">📦</div>
                <?php endif; ?>
                <div>
                    <p class="pay-title"><?= htmlspecialchars($listing['title']) ?></p>
                    <p class="pay-seller">Sold by <?= htmlspecialchars($listing['seller_name']) ?></p>
                    <p class="pay-amount">KSh <?= number_format($listing['price'], 2) ?></p>
                </div>
            </div>

            <?php $fee = calculateFee($listing['price']); ?>
            <div class="pay-breakdown">
                <div class="pay-row">
                    <span>Item Price</span>
                    <span>KSh <?= number_format($fee['price'], 2) ?></span>
                </div>
                <div class="pay-row fee-row-info">
                    <span>
                        🏛️ Platform Fee (<?= PLATFORM_FEE_PERCENT ?>%)
                        <br><small>Funds student welfare & platform</small>
                    </span>
                    <span>KSh <?= number_format($fee['fee'], 2) ?></span>
                </div>
                <div class="pay-row">
                    <span>💰 Seller Receives</span>
                    <span>KSh <?= number_format($fee['seller_amount'], 2) ?></span>
                </div>
                <div class="pay-row total">
                    <span>You Pay</span>
                    <span>KSh <?= number_format($fee['price'], 2) ?></span>
                </div>
            </div>

            <div class="fee-notice" style="margin-top:12px;">
                ℹ️ A <strong><?= PLATFORM_FEE_PERCENT ?>% platform fee</strong> is deducted
                from the seller's payout. You pay the listed price only.
            </div>
        </div>

        <!-- Payment Form -->
        <div class="form-card">
            <h3>💳 Pay with M-Pesa</h3>
            <p class="auth-subtitle">Enter your Safaricom number to receive the STK push</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$checkout_id): ?>
                <!-- Payment Form -->
                <form method="POST">
                    <div class="form-group">
                        <label>M-Pesa Phone Number</label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($buyer['phone'] ?? '') ?>"
                               placeholder="e.g. 0712345678" required>
                        <small style="color:var(--color-text-faint);">
                            You will receive a prompt on this number
                        </small>
                    </div>
                    <div class="mpesa-logo">
                        <img src="/ums/assets/mpesa.png" alt="M-Pesa"
                             onerror="this.style.display='none'">
                        <span>Secured by M-Pesa</span>
                    </div>
                    <button type="submit" class="btn-primary">
                        💚 Pay KSh <?= number_format($listing['price'], 2) ?>
                    </button>
                </form>

            <?php else: ?>
                <!-- Waiting for Payment Confirmation -->
                <div class="pay-waiting" id="payWaiting">
                    <div class="pay-spinner"></div>
                    <h4>Check your phone!</h4>
                    <p>An M-Pesa prompt has been sent to your phone.<br>
                       Enter your PIN to complete the payment.</p>
                    <p class="pay-timer" id="payTimer">Waiting for confirmation...</p>
                </div>

                <div class="pay-result" id="payResult" style="display:none;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($checkout_id): ?>
<script>
const checkoutId = '<?= $checkout_id ?>';
let attempts     = 0;
const maxAttempts = 24; // 2 minutes (every 5 seconds)

const interval = setInterval(async () => {
    attempts++;

    const res  = await fetch(`/ums/mpesa/status.php?checkout_id=${checkoutId}`);
    const data = await res.json();

    if (data.status === 'success') {
        clearInterval(interval);
        document.getElementById('payWaiting').style.display = 'none';
        document.getElementById('payResult').style.display  = 'block';
        document.getElementById('payResult').innerHTML = `
            <div class="alert success" style="text-align:center;">
                <p style="font-size:2rem">✅</p>
                <h3>Payment Successful!</h3>
                <p>Receipt: <strong>${data.mpesa_receipt_number}</strong></p>
                <a href="/ums/index.php" class="btn-primary"
                   style="display:inline-block;width:auto;padding:10px 24px;margin-top:12px;">
                   Back to Marketplace
                </a>
            </div>`;

    } else if (data.status === 'failed') {
        clearInterval(interval);
        document.getElementById('payWaiting').style.display = 'none';
        document.getElementById('payResult').style.display  = 'block';
        document.getElementById('payResult').innerHTML = `
            <div class="alert error" style="text-align:center;">
                <p style="font-size:2rem">❌</p>
                <h3>Payment Failed</h3>
                <p>${data.result_desc}</p>
                <a href="/ums/pay.php?listing_id=<?= $listing_id ?>"
                   class="btn-primary"
                   style="display:inline-block;width:auto;padding:10px 24px;margin-top:12px;">
                   Try Again
                </a>
            </div>`;

    } else if (attempts >= maxAttempts) {
        clearInterval(interval);
        document.getElementById('payTimer').textContent =
            '⏱ Timed out. If you completed payment, check your profile for confirmation.';
    } else {
        const secs = (maxAttempts - attempts) * 5;
        document.getElementById('payTimer').textContent =
            `Waiting for confirmation... (${secs}s remaining)`;
    }

}, 5000); // Check every 5 seconds
</script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>