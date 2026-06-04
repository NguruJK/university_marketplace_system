<?php
require_once '../includes/db.php';
require '../includes/mailer.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /ums/index.php");
    exit;
}

$message = '';
$type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = strtolower(trim($_POST['email']));
    $message = "If that email is registered, a reset link has been sent. Check your inbox.";
    $type    = "success";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 AND is_verified = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $pdo->prepare("
            UPDATE users
            SET reset_token = ?, reset_token_expires = ?
            WHERE id = ?
        ")->execute([$token, $expires, $user['id']]);

        sendPasswordResetEmail($user['email'], $user['full_name'], $token);
    }
}
?>
<?php require '../includes/header.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="/ums/assets/logo.png" alt="UoN"
                 onerror="this.style.display='none'">
        </div>

        <h2><i class="fa-solid fa-lock"></i> Forgot Password</h2>
        <p class="auth-subtitle">
            Enter your student email and we'll send you a reset link.
        </p>

        <?php if ($message): ?>
            <div class="alert <?= $type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <div class="form-group">
                <label>Student Email</label>
                <input type="email" name="email"
                       placeholder="yourname@students.uonbi.ac.ke"
                       required autofocus>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-envelope"></i>\
                 Send Reset Link
            </button>
        </form>
        <?php endif; ?>

        <p class="auth-footer">
            Remember your password? <a href="/ums/auth/login.php">Login</a>
        </p>
    </div>
</div>

<?php require '../includes/footer.php'; ?>