<?php
require_once '../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /ums/index.php");
    exit;
}

$token   = trim($_GET['token'] ?? '');
$errors  = [];
$success = '';
$user    = null;
$status  = 'invalid';

if ($token) {
    // Fetch user by token — check expiry in PHP not MySQL
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE reset_token = ?
        AND is_active = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if (!$user['reset_token_expires'] ||
            strtotime($user['reset_token_expires']) < time()) {
            // Token expired — clear it
            $pdo->prepare("
                UPDATE users
                SET reset_token = NULL, reset_token_expires = NULL
                WHERE id = ?
            ")->execute([$user['id']]);
            $status = 'expired';
            $user   = null;
        } else {
            $status = 'valid';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === 'valid' && $user) {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("
            UPDATE users
            SET password = ?,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE id = ?
        ")->execute([$hashed, $user['id']]);
        $success = "password_changed";
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

        <?php if ($success === 'password_changed'): ?>
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i></div>
                <h2 style="color:var(--color-primary);">Password Changed!</h2>
                <p style="color:var(--color-text-muted); margin:12px 0 24px;">
                    Your password has been successfully updated.
                </p>
                <a href="/ums/auth/login.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Login Now →
                </a>
            </div>

        <?php elseif ($status === 'expired'): ?>
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-clock"></i></div>
                <h2 style="color:var(--color-warning-text);">Link Expired</h2>
                <p style="color:#555; margin:12px 0 24px;">
                    Your reset link expired. Links are valid for
                    <strong>1 hour</strong> only.
                </p>
                <a href="/ums/auth/forgot_password.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Request New Link
                </a>
            </div>

        <?php elseif ($status !== 'valid'): ?>
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:16px;"><i class="fa-solid fa-circle-xmark"></i></div>
                <h2 style="color:var(--color-danger-text);">Invalid Link</h2>
                <p style="color:#555; margin:12px 0 24px;">
                    This password reset link is invalid or has already been used.
                </p>
                <a href="/ums/auth/forgot_password.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Request New Link
                </a>
            </div>

        <?php else: ?>
            <h2>🔑 Reset Password</h2>
            <p class="auth-subtitle">
                Setting new password for
                <strong><?= htmlspecialchars($user['email']) ?></strong>
            </p>

            <?php if ($errors): ?>
                <div class="alert error">
                    <ul><?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password"
                               id="newPassword"
                               placeholder="At least 6 characters" required>
                        <button type="button" class="pwd-toggle"
                                onclick="togglePwd('newPassword', this)"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="pwd-strength-bar">
                        <div class="pwd-strength-fill" id="strengthFill"></div>
                    </div>
                    <small class="pwd-strength-label" id="strengthLabel"></small>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-wrap">
                        <input type="password" name="confirm_password"
                               id="confirmPassword"
                               placeholder="Repeat your password" required>
                        <button type="button" class="pwd-toggle"
                                onclick="togglePwd('confirmPassword', this)"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <small class="pwd-match-label" id="matchLabel"></small>
                </div>

                <button type="submit" class="btn-primary"><i class="fa-solid fa-key"></i> Reset Password</button>
            </form>

            <p class="auth-footer">
                <a href="/ums/auth/login.php">← Back to Login</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePwd(id, btn) {
    var input = document.getElementById(id);
    input.type      = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
}

var pwdInput     = document.getElementById('newPassword');
var confirmInput = document.getElementById('confirmPassword');

if (pwdInput) {
    pwdInput.addEventListener('input', function() {
        var val = this.value, strength = 0, text = '', color = '';
        if (val.length >= 6)           strength++;
        if (val.length >= 10)          strength++;
        if (/[A-Z]/.test(val))         strength++;
        if (/[0-9]/.test(val))         strength++;
        if (/[^A-Za-z0-9]/.test(val))  strength++;

        if      (strength <= 1) { text = 'Weak';   color = '#dc2626'; }
        else if (strength <= 2) { text = 'Fair';   color = '#e07b00'; }
        else if (strength <= 3) { text = 'Good';   color = '#2d7a4f'; }
        else                    { text = 'Strong'; color = '#40916c'; }

        var fill  = document.getElementById('strengthFill');
        var label = document.getElementById('strengthLabel');
        if (fill)  { fill.style.width = (strength/5*100)+'%'; fill.style.background = color; }
        if (label) { label.textContent = val.length > 0 ? text : ''; label.style.color = color; }
    });
}

if (confirmInput) {
    confirmInput.addEventListener('input', function() {
        var match = document.getElementById('matchLabel');
        if (!match) return;
        if (this.value === pwdInput.value) {
            match.textContent = '✅ Passwords match';
            match.style.color = '#40916c';
        } else {
            match.textContent = '❌ Passwords do not match';
            match.style.color = '#dc2626';
        }
    });
}
</script>

<?php require '../includes/footer.php'; ?>