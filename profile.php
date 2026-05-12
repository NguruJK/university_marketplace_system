<?php
require 'includes/auth_check.php';
require 'includes/db.php';

$success = '';
$errors  = [];

// Fetch current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    if (empty($full_name)) $errors[] = "Full name is required.";

    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm) {
            $errors[] = "Passwords do not match.";
        }
    }

    // Handle avatar upload
    $new_avatar = $user['avatar']; // keep existing by default
    if (!empty($_FILES['avatar']['name'])) {
        $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
        $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $errors[] = "Avatar must be a JPG, PNG, or WEBP image.";
        } elseif ($_FILES['avatar']['size'] > $max_size) {
            $errors[] = "Avatar image must be under 2MB.";
        } else {
            // Delete old avatar if exists
            if ($user['avatar']) {
                $old = __DIR__ . '/uploads/avatars/' . $user['avatar'];
                if (file_exists($old)) unlink($old);
            }
            $new_avatar = uniqid('avatar_', true) . '.' . $ext;
            move_uploaded_file(
                $_FILES['avatar']['tmp_name'],
                __DIR__ . '/uploads/avatars/' . $new_avatar
            );
        }
    }

    // Handle avatar removal
    if (isset($_POST['remove_avatar']) && $user['avatar']) {
        $old = __DIR__ . '/uploads/avatars/' . $user['avatar'];
        if (file_exists($old)) unlink($old);
        $new_avatar = null;
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET full_name=?, phone=?, password=?, avatar=? WHERE id=?")
                ->execute([$full_name, $phone, $hashed, $new_avatar, $_SESSION['user_id']]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, phone=?, avatar=? WHERE id=?")
                ->execute([$full_name, $phone, $new_avatar, $_SESSION['user_id']]);
        }

        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";

        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
}

// Handle listing status change (mark as sold)
if (isset($_GET['mark_sold']) && is_numeric($_GET['mark_sold'])) {
    $lid = intval($_GET['mark_sold']);
    $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ? AND seller_id = ?")
        ->execute([$lid, $_SESSION['user_id']]);
    header("Location: /ums/profile.php");
    exit;
}

// Handle listing delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $lid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM listings WHERE id = ? AND seller_id = ?")
        ->execute([$lid, $_SESSION['user_id']]);
    header("Location: /ums/profile.php");
    exit;
}

// Fetch user's listings
$listings = $pdo->prepare("
    SELECT l.*, c.name AS category_name
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    WHERE l.seller_id = ?
    ORDER BY l.created_at DESC
");
$listings->execute([$_SESSION['user_id']]);
$my_listings = $listings->fetchAll();

// Count stats
$total     = count($my_listings);
$available = count(array_filter($my_listings, fn($l) => $l['status'] === 'available'));
$sold      = count(array_filter($my_listings, fn($l) => $l['status'] === 'sold'));

// Fetch enquiries made by this user
$my_enquiries = $pdo->prepare("
    SELECT e.*, l.title AS listing_title, l.id AS listing_id
    FROM enquiries e
    JOIN listings l ON e.listing_id = l.id
    WHERE e.buyer_id = ?
    ORDER BY e.created_at DESC
");
$my_enquiries->execute([$_SESSION['user_id']]);
$enquiries = $my_enquiries->fetchAll();
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">

    <div class="profile-grid">

        <!-- LEFT: Profile Info -->
        <div>
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-wrap">
            <?php if ($user['avatar']): ?>
                <img src="/ums/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                        alt="Avatar" class="profile-avatar-img">
                <?php else: ?>
                        <div class="profile-avatar">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
                <p class="profile-joined">
                    🎓 Student &mdash; Joined <?= date('M Y', strtotime($user['created_at'])) ?>
                </p>

                <div class="profile-stats">
                    <div class="pstat">
                        <span class="pstat-num"><?= $total ?></span>
                        <span class="pstat-label">Listed</span>
                    </div>
                    <div class="pstat">
                        <span class="pstat-num"><?= $available ?></span>
                        <span class="pstat-label">Active</span>
                    </div>
                    <div class="pstat">
                        <span class="pstat-num"><?= $sold ?></span>
                        <span class="pstat-label">Sold</span>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="form-card" style="margin-top: 20px;">
                <h3>✏️ Edit Profile</h3>

                <?php if ($success): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert error">
                        <ul><?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Avatar Upload -->
<div class="form-group avatar-upload-group">
    <label>Profile Photo</label>
    <div class="avatar-upload-row">
        <?php if ($user['avatar']): ?>
            <img src="/ums/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                 alt="Current avatar" class="avatar-preview">
        <?php else: ?>
            <div class="avatar-preview avatar-preview-placeholder">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div class="avatar-upload-actions">
            <input type="file" name="avatar" id="avatar_input"
                   accept=".jpg,.jpeg,.png,.webp"
                   style="display:none"
                   onchange="previewAvatar(this)">
            <button type="button" class="btn-outline btn-sm"
                    onclick="document.getElementById('avatar_input').click()">
                📷 Choose Photo
            </button>
            <?php if ($user['avatar']): ?>
                <button type="submit" name="remove_avatar"
                        class="tbl-btn btn-danger"
                        onclick="return confirm('Remove your profile photo?')">
                    🗑 Remove
                </button>
            <?php endif; ?>
        </div>
    </div>
    <p style="font-size:0.8rem; color:#aaa; margin-top:6px;">
        JPG, PNG or WEBP — max 2MB
    </p>
</div>

<div class="form-group">
    <label>Full Name</label>
    <input type="text" name="full_name"
           value="<?= htmlspecialchars($user['full_name']) ?>" required>
</div>
                <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name"
                               value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email <span class="optional">(cannot change)</span></label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                               placeholder="e.g. 0712345678">
                    </div>

                    <hr style="margin: 16px 0; border-color: #eee;">
                    <p style="font-size:0.85rem; color:#888; margin-bottom:12px;">
                        Leave password fields blank to keep your current password.
                    </p>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="New password (optional)">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password">
                    </div>

                    <button type="submit" name="update_profile" class="btn-primary">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT: My Listings + Enquiries -->
        <div>

            <!-- My Listings -->
            <div class="admin-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3>📦 My Listings</h3>
                    <a href="/ums/post_item.php" class="btn-primary"
                       style="width:auto; padding: 8px 18px; font-size:0.88rem;">
                        + Post New
                    </a>
                </div>

                <?php if (empty($my_listings)): ?>
                    <p class="no-enquiries">You haven't posted any items yet.
                        <a href="/ums/post_item.php">Post one now!</a>
                    </p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_listings as $l): ?>
                            <tr>
                                <td>
                                    <a href="/ums/listing.php?id=<?= $l['id'] ?>" class="tbl-link">
                                        <?= htmlspecialchars($l['title']) ?>
                                    </a>
                                </td>
                                <td>KSh <?= number_format($l['price'], 2) ?></td>
                                <td><?= htmlspecialchars($l['category_name']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $l['status'] ?>">
                                        <?= ucfirst($l['status']) ?>
                                    </span>
                                </td>
                                <td class="action-btns">
                                    <a href="/ums/edit_listing.php?id=<?= $l['id'] ?>"
                                    class="tbl-btn btn-success">
                                        ✏️ Edit
                                    </a>
                                    <?php if ($l['status'] === 'available'): ?>
                                        <a href="?mark_sold=<?= $l['id'] ?>"
                                           class="tbl-btn btn-success"
                                           onclick="return confirm('Mark this item as sold?')">
                                           Mark Sold
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $l['id'] ?>"
                                       class="tbl-btn btn-danger"
                                       onclick="return confirm('Delete this listing permanently?')">
                                       Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- My Enquiries -->
            <div class="admin-section">
                <h3>💬 My Enquiries</h3>

                <?php if (empty($enquiries)): ?>
                    <p class="no-enquiries">You haven't asked any questions yet.</p>
                <?php else: ?>
                    <?php foreach ($enquiries as $q): ?>
                        <div class="enquiry-item <?= $q['is_resolved'] ? 'resolved' : '' ?>">
                            <p style="font-size:0.8rem; color:#888; margin-bottom:4px;">
                                On: <a href="/ums/listing.php?id=<?= $q['listing_id'] ?>" class="tbl-link">
                                    <?= htmlspecialchars($q['listing_title']) ?>
                                </a>
                            </p>
                            <p class="enquiry-question">
                                <strong>You asked:</strong> <?= htmlspecialchars($q['question']) ?>
                            </p>
                            <?php if ($q['answer']): ?>
                                <p class="enquiry-answer">
                                    💬 <em><?= htmlspecialchars($q['answer']) ?></em>
                                </p>
                            <?php else: ?>
                                <p class="no-answer">⏳ Awaiting seller reply...</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Replace preview with new image
            const wrap = document.querySelector('.avatar-upload-row');
            const existing = wrap.querySelector('.avatar-preview, .avatar-preview-placeholder');
            if (existing) existing.remove();

            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'avatar-preview';
            wrap.insertBefore(img, wrap.firstChild);
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require 'includes/footer.php'; ?>