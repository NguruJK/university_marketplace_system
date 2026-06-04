<?php
require_once '../includes/db.php';

$token  = trim($_GET['token'] ?? '');
$status = 'invalid';

if ($token) {
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE verify_token = ?
        AND is_verified = 0
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Check if token has expired
        if ($user['token_expires_at'] && strtotime($user['token_expires_at']) < time()) {
            $status = 'expired';

            // Clean up expired token
            $pdo->prepare("
                UPDATE users SET verify_token = NULL, token_expires_at = NULL WHERE id = ?
            ")->execute([$user['id']]);
        } else {
            // Activate the account
            $pdo->prepare("
                UPDATE users
                SET is_verified = 1,
                    verify_token = NULL,
                    token_expires_at = NULL
                WHERE id = ?
            ")->execute([$user['id']]);
            $status = 'success';
        }
    }
}
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="auth-wrapper">
        <div class="auth-card" style="text-align:center;">

            <?php if ($status === 'success'): ?>
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i></div>
                <h2 style="color:#003366;">Email Verified!</h2>
                <p style="color:#555; margin:12px 0 24px;">
                    Your account has been successfully verified.
                    You can now log in to the UMS platform.
                </p>
                <a href="/ums/auth/login.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Login Now →
                </a>

            <?php elseif ($status === 'expired'): ?>
                <!-- Expired Token -->
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-clock"></i></div>
                <h2 style="color:var(--color-warning-text);">Link Expired</h2>
                <p style="color:#555; margin:12px 0 24px;">
                    Your verification link expired after <strong>24 hours</strong>.
                    Request a new one below.
                </p>
                <a href="/ums/auth/resend_verify.php?email=<?= urlencode($user['email'] ?? '') ?>"
                class="btn-primary"
                style="display:inline-block; width:auto; padding:12px 32px;">
                    📧 Resend Verification Email
                </a>

            <?php else: ?>
                <!-- Invalid Token -->
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-circle-xmark"></i></div>
                <h2 style="color:var(--color-danger-text);">Invalid Link</h2>
                <p style="color:#555; margin:12px 0 24px;">
                    This verification link is invalid or has already been used.
                </p>
                <a href="/ums/auth/register.php" class="btn-primary"
                style="display:inline-block; width:auto; padding:12px 32px;">
                    Register Again
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>