<?php
require '../includes/db.php';
require '../includes/mailer.php';

$email   = strtolower(trim($_GET['email'] ?? ''));
$message = '';
$type    = '';

if ($email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate a fresh token with new 24hr expiry
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $pdo->prepare("
            UPDATE users
            SET verify_token = ?,
                token_expires_at = ?
            WHERE id = ?
        ")->execute([$token, $expires, $user['id']]);

        $sent = sendVerificationEmail($user['email'], $user['full_name'], $token);

        if ($sent) {
            $message = "✅ Verification email resent to <strong>{$email}</strong>. Please check your inbox.";
            $type    = 'success';
        } else {
            $message = "⚠️ Could not send email. Please try again later.";
            $type    = 'error';
        }
    } else {
        $message = "⚠️ No unverified account found with that email.";
        $type    = 'error';
    }
}
?>
<?php require '../includes/header.php'; ?>

<div class="page-wrapper">
    <div class="auth-wrapper">
        <div class="auth-card" style="text-align:center;">
            <h2 style="color:#003366; margin-bottom:16px;">📧 Resend Verification</h2>

            <?php if ($message): ?>
                <div class="alert <?= $type ?>"><?= $message ?></div>
                <a href="/ums/auth/login.php">← Back to Login</a>
            <?php else: ?>
                <p style="color:#888;">Invalid request.</p>
                <a href="/ums/auth/register.php">← Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>