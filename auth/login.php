<?php
require '../includes/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /ums/index.php");
    exit;
}

$error = '';

// Show message if redirected due to suspension
if (isset($_GET['suspended'])) {
    $error = "Your account has been suspended. Please contact the admin.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
    if (!$user['is_active']) {
        $error = "Your account has been suspended. Please contact the admin.";
    } elseif (!$user['is_verified']) {
        $error = "Please verify your email before logging in. 
                  <a href='/ums/auth/resend_verify.php?email=" 
                  . urlencode($user['email']) . "'>Resend verification email</a>";
    } elseif (password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: /ums/admin/dashboard.php");
        } else {
            header("Location: /ums/index.php");
        }
        exit;
    } else {
        $error = "Invalid email or password. Please try again.";
    }
    
    } else {
    $error = "Invalid email or password. Please try again.";
    }

}
?>
<?php require '../includes/header.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="/ums/assets/logo.png" alt="University of Nairobi">
        </div>
        <h2>Login to UMS</h2>
        <p class="auth-subtitle">Student-only marketplace &mdash; UoN</p>

        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Student Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="yourname@students.uonbi.ac.ke" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="Your password" required>
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>

        <p class="auth-footer">No account yet? <a href="register.php">Register</a></p>
            <div style="text-align:right; margin-top:8px;">
            <a href="/ums/auth/forgot_password.php"
            style="font-size:0.85rem; color:var(--color-primary);">
                Forgot password?
                </a>
            </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>