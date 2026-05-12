<?php
require_once '../includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /ums/index.php");
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = strtolower(trim($_POST['email']));
    $phone     = trim($_POST['phone']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    // --- Validation ---

    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }

    // School email validation — only @students.uonbi.ac.ke allowed
    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@students\.uonbi\.ac\.ke$/', $email)) {
        $errors[] = "You must use your UoN student email (e.g. johndoe@students.uonbi.ac.ke).";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already registered
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "This email is already registered. Try logging in.";
        }
    }

// --- Insert into DB ---
if (empty($errors)) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, phone, password, verify_token, token_expires_at, is_verified)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$full_name, $email, $phone, $hashed, $token, $expires]);

    // Send verification email
    require '../includes/mailer.php';
    $sent = sendVerificationEmail($email, $full_name, $token);

    if ($sent) {
        $success = "registered";
    } else {
        $success = "registered_no_email";
    }
}
}
?>
<?php require '../includes/header.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="/ums/assets/logo.png" alt="University of Nairobi">
        </div>
        <h2>Create Your UMS Account</h2>
        <p class="auth-subtitle">Use your UoN student email to register</p>

        <?php if ($success === 'registered'): ?>
    <div class="alert success">
        ✅ Account created! We've sent a verification link to
        <strong><?= htmlspecialchars($email) ?></strong>.
        Please check your inbox and verify before logging in.
    </div>
<?php elseif ($success === 'registered_no_email'): ?>
    <div class="alert error">
        ⚠️ Account created but we couldn't send the verification email.
        <a href="/ums/auth/resend_verify.php?email=<?= urlencode($email) ?>">
            Resend verification email
        </a>
    </div>
<?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       placeholder="e.g. Nguru Joel Kamau" required>
            </div>

            <div class="form-group">
                <label>Student Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="yourname@students.uonbi.ac.ke" required>
            </div>

            <div class="form-group">
                <label>Phone Number <span class="optional">(optional)</span></label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="e.g. 0712345678">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="At least 6 characters" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password"
                       placeholder="Repeat your password" required>
            </div>

            <button type="submit" class="btn-primary">Register</button>
        </form>

        <p class="auth-footer">Already have an account? <a href="login.php">Login</a></p>
    </div>
</div>

<?php require '../includes/footer.php'; ?>