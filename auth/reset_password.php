<?php
require '../includes/db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /ums/index.php");
    exit;
}

$token   = trim($_GET['token'] ?? '');
$errors  = [];
$success = '';

// Validate token
$stmt = $pdo->prepare("
    SELECT * FROM users
    WHERE reset_token = ?
    AND reset_token_expires > NOW()
    AND is_active = 1
");
$stmt->execute([$token]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
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

        // Update password and clear reset token
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
            <!-- Success State -->
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:16px;">✅</div>
                <h2 style="color:var(--color-primary);">Password Changed!</h2>
                <p style="color:var(--color-text-muted); margin:12px 0 24px;">
                    Your password has been successfully updated.
                    You can now login with your new password.
                </p>
                <a href="/ums/auth/login.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Login Now →
                </a>
            </div>

        <?php elseif (!$token || !$user): ?>
            <!-- Invalid/Expired Token -->
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:16px;">❌</div>
                <h2 style="color:var(--color-danger-text);">Invalid or Expired Link</h2>
                <p style="color:var(--color-text-muted); margin:12px 0 24px;">
                    This password reset link is invalid or has expired.
                    Reset links are only valid for <strong>1 hour</strong>.
                </p>
                <a href="/ums/auth/forgot_password.php" class="btn-primary"
                   style="display:inline-block; width:auto; padding:12px 32px;">
                    Request New Link
                </a>
            </div>

        <?php else: ?>
            <!-- Reset Form -->
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
                               placeholder="At least 6 characters"
                               required>
                        <button type="button" class="pwd-toggle"
                                onclick="togglePwd('newPassword', this)">
                            👁
                        </button>
                    </div>

                    <!-- Password Strength Bar -->
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
                               placeholder="Repeat your password"
                               required>
                        <button type="button" class="pwd-toggle"
                                onclick="togglePwd('confirmPassword', this)">
                            👁
                        </button>
                    </div>
                    <small class="pwd-match-label" id="matchLabel"></small>
                </div>

                <button type="submit" class="btn-primary">
                    🔐 Reset Password
                </button>
            </form>

            <p class="auth-footer">
                <a href="/ums/auth/login.php">← Back to Login</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
// ---- Toggle Password Visibility ----
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type  = 'text';
        btn.textContent = '🙈';
    } else {
        input.type  = 'password';
        btn.textContent = '👁';
    }
}

// ---- Password Strength ----
const pwdInput     = document.getElementById('newPassword');
const confirmInput = document.getElementById('confirmPassword');
const fill         = document.getElementById('strengthFill');
const label        = document.getElementById('strengthLabel');
const matchLabel   = document.getElementById('matchLabel');

if (pwdInput) {
    pwdInput.addEventListener('input', function() {
        const val      = this.value;
        let strength   = 0;
        let text       = '';
        let color      = '';

        if (val.length >= 6)                          strength++;
        if (val.length >= 10)                         strength++;
        if (/[A-Z]/.test(val))                        strength++;
        if (/[0-9]/.test(val))                        strength++;
        if (/[^A-Za-z0-9]/.test(val))                strength++;

        switch (true) {
            case strength <= 1:
                text = 'Weak';     color = '#dc2626'; break;
            case strength <= 2:
                text = 'Fair';     color = '#e07b00'; break;
            case strength <= 3:
                text = 'Good';     color = '#2d7a4f'; break;
            default:
                text = 'Strong';   color = '#40916c'; break;
        }

        if (fill)  {
            fill.style.width      = (strength / 5 * 100) + '%';
            fill.style.background = color;
        }
        if (label) {
            label.textContent = val.length > 0 ? text : '';
            label.style.color = color;
        }
    });
}

if (confirmInput) {
    confirmInput.addEventListener('input', function() {
        const pwd  = pwdInput ? pwdInput.value : '';
        if (matchLabel) {
            if (this.value === pwd) {
                matchLabel.textContent = '✅ Passwords match';
                matchLabel.style.color = '#40916c';
            } else {
                matchLabel.textContent = '❌ Passwords do not match';
                matchLabel.style.color = '#dc2626';
            }
        }
    });
}
</script>

<?php require '../includes/footer.php'; ?>