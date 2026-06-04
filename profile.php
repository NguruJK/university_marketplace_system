<?php
require 'includes/auth_check.php';
require_once 'includes/db.php';

$success = '';
$errors  = [];

// Handle avatar removal via GET
if (isset($_GET['remove_avatar']) && $_GET['remove_avatar'] == 1) {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    if ($currentUser['avatar']) {
        $old = __DIR__ . '/uploads/avatars/' . $currentUser['avatar'];
        if (file_exists($old)) unlink($old);
        $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
    }
    header("Location: /ums/profile.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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

    $new_avatar = $user['avatar'];

    if (!empty($_POST['avatar_data'])) {
        $data_url = $_POST['avatar_data'];
        if (preg_match('/^data:image\/(jpeg|png|webp);base64,/', $data_url, $type)) {
            $image_data = base64_decode(substr($data_url, strpos($data_url, ',') + 1));
            $ext        = $type[1] === 'jpeg' ? 'jpg' : $type[1];
            if ($image_data !== false && strlen($image_data) < 5 * 1024 * 1024) {
                if ($user['avatar']) {
                    $old = __DIR__ . '/uploads/avatars/' . $user['avatar'];
                    if (file_exists($old)) unlink($old);
                }
                $new_avatar = uniqid('avatar_', true) . '.' . $ext;
                file_put_contents(__DIR__ . '/uploads/avatars/' . $new_avatar, $image_data);
            } else {
                $errors[] = "Photo capture failed. Please try again.";
            }
        }
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
}

if (isset($_GET['mark_sold']) && is_numeric($_GET['mark_sold'])) {
    $lid = intval($_GET['mark_sold']);
    $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ? AND seller_id = ?")
        ->execute([$lid, $_SESSION['user_id']]);
    header("Location: /ums/profile.php");
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $lid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM listings WHERE id = ? AND seller_id = ?")
        ->execute([$lid, $_SESSION['user_id']]);
    header("Location: /ums/profile.php");
    exit;
}

$listings = $pdo->prepare("
    SELECT l.*, c.name AS category_name
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    WHERE l.seller_id = ?
    ORDER BY l.created_at DESC
");
$listings->execute([$_SESSION['user_id']]);
$my_listings = $listings->fetchAll();

$total     = count($my_listings);
$available = count(array_filter($my_listings, fn($l) => $l['status'] === 'available'));
$sold      = count(array_filter($my_listings, fn($l) => $l['status'] === 'sold'));

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

        <!-- LEFT -->
        <div>
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
                    <i class="fa-solid fa-graduation-cap"></i>
                    Student &mdash; Joined <?= date('M Y', strtotime($user['created_at'])) ?>
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
            <div class="form-card" style="margin-top:20px;">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Profile</h3>

                <?php if ($success): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert error">
                        <ul><?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="profileForm">

                    <!-- Avatar Camera Section -->
                    <div class="form-group avatar-upload-group">
                        <label>Profile Photo</label>
                        <p style="font-size:0.82rem;color:var(--color-text-faint);margin-bottom:10px;">
                            <i class="fa-solid fa-circle-info"></i>
                            Profile photos must be taken with your camera.
                        </p>

                        <div class="avatar-cam-row">
                            <div class="avatar-cam-current">
                                <?php if ($user['avatar']): ?>
                                    <img src="/ums/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                                         alt="Current avatar" class="avatar-preview" id="avatarPreview">
                                <?php else: ?>
                                    <div class="avatar-preview avatar-preview-placeholder" id="avatarPlaceholder">
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="avatar-cam-label">Current Photo</span>
                            </div>

                            <div class="avatar-cam-arrow">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>

                            <div class="avatar-cam-capture">
                                <div class="avatar-cam-wrap" id="avatarCamWrap">
                                    <video id="avatarCamFeed" autoplay playsinline muted style="display:none;"></video>
                                    <canvas id="avatarCamCanvas" style="display:none;"></canvas>
                                    <div class="avatar-cam-placeholder" id="avatarCamPlaceholder">
                                        <i class="fa-solid fa-camera" style="font-size:1.8rem;color:var(--color-primary-faint);"></i>
                                        <span>Camera preview</span>
                                    </div>
                                    <img id="avatarCapturedPreview" src="" alt="Captured"
                                         style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                </div>
                                <span class="avatar-cam-label">New Photo</span>
                            </div>
                        </div>

                        <div class="avatar-cam-controls" id="avatarCamControls">
                            <button type="button" class="btn-outline btn-sm" onclick="startAvatarCamera()">
                                <i class="fa-solid fa-camera"></i> Open Camera
                            </button>
                        </div>

                        <div class="avatar-cam-controls" id="avatarShootControls" style="display:none;">
                            <button type="button" class="btn-capture" style="flex:1;padding:10px;" onclick="shootAvatar()">
                                <i class="fa-solid fa-camera-retro"></i> Take Photo
                            </button>
                            <button type="button" class="btn-outline btn-sm" onclick="flipAvatarCamera()">
                                <i class="fa-solid fa-rotate"></i>
                            </button>
                            <button type="button" class="btn-outline btn-sm" onclick="stopAvatarCamera()">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="avatar-cam-controls" id="avatarRetakeControls" style="display:none;">
                            <button type="button" class="btn-outline btn-sm" onclick="retakeAvatar()">
                                <i class="fa-solid fa-rotate-left"></i> Retake
                            </button>
                            <span style="font-size:0.82rem;color:var(--color-success);font-weight:600;">
                                <i class="fa-solid fa-circle-check"></i> Photo captured!
                            </span>
                        </div>

                        <input type="hidden" name="avatar_data" id="avatarData">

                        <?php if ($user['avatar']): ?>
                            <a href="/ums/profile.php?remove_avatar=1"
                               class="tbl-btn btn-danger"
                               style="display:inline-block;margin-top:10px;"
                               onclick="return confirm('Remove your profile photo?')">
                                <i class="fa-solid fa-trash"></i> Remove Photo
                            </a>
                        <?php endif; ?>
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

                    <hr style="margin:16px 0;border-color:#eee;">
                    <p style="font-size:0.85rem;color:#888;margin-bottom:12px;">
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

        <!-- RIGHT -->
        <div>
            <div class="admin-section">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3><i class="fa-solid fa-box-open"></i> My Listings</h3>
                    <a href="/ums/post_item.php" class="btn-primary"
                       style="width:auto;padding:8px 18px;font-size:0.88rem;">
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
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
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

            <div class="admin-section">
                <h3><i class="fa-solid fa-comments"></i> My Enquiries</h3>

                <?php if (empty($enquiries)): ?>
                    <p class="no-enquiries">You haven't asked any questions yet.</p>
                <?php else: ?>
                    <?php foreach ($enquiries as $q): ?>
                        <div class="enquiry-item <?= $q['is_resolved'] ? 'resolved' : '' ?>">
                            <p style="font-size:0.8rem;color:#888;margin-bottom:4px;">
                                On: <a href="/ums/listing.php?id=<?= $q['listing_id'] ?>" class="tbl-link">
                                    <?= htmlspecialchars($q['listing_title']) ?>
                                </a>
                            </p>
                            <p class="enquiry-question">
                                <strong>You asked:</strong> <?= htmlspecialchars($q['question']) ?>
                            </p>
                            <?php if ($q['answer']): ?>
                                <p class="enquiry-answer">
                                    <i class="fa-solid fa-comment-dots"></i>
                                    <em><?= htmlspecialchars($q['answer']) ?></em>
                                </p>
                            <?php else: ?>
                                <p class="no-answer">
                                    <i class="fa-solid fa-hourglass-half"></i> Awaiting seller reply...
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var avatarStream = null;
var avatarFacing = 'user';

function startAvatarCamera() {
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: avatarFacing, width: { ideal: 400 }, height: { ideal: 400 } }
    }).then(function(stream) {
        avatarStream = stream;
        var video = document.getElementById('avatarCamFeed');
        video.srcObject = stream;
        video.style.display = 'block';
        document.getElementById('avatarCamPlaceholder').style.display = 'none';
        document.getElementById('avatarCamControls').style.display    = 'none';
        document.getElementById('avatarShootControls').style.display  = 'flex';
    }).catch(function(err) {
        alert('Could not access camera: ' + err.message);
    });
}

function stopAvatarCamera() {
    if (avatarStream) avatarStream.getTracks().forEach(function(t) { t.stop(); });
    avatarStream = null;
    document.getElementById('avatarCamFeed').style.display         = 'none';
    document.getElementById('avatarCamPlaceholder').style.display  = 'flex';
    document.getElementById('avatarShootControls').style.display   = 'none';
    document.getElementById('avatarCamControls').style.display     = 'flex';
    document.getElementById('avatarRetakeControls').style.display  = 'none';
    document.getElementById('avatarCapturedPreview').style.display = 'none';
    document.getElementById('avatarData').value = '';
}

function flipAvatarCamera() {
    avatarFacing = avatarFacing === 'user' ? 'environment' : 'user';
    if (avatarStream) avatarStream.getTracks().forEach(function(t) { t.stop(); });
    startAvatarCamera();
}

function shootAvatar() {
    var video  = document.getElementById('avatarCamFeed');
    var canvas = document.getElementById('avatarCamCanvas');
    var size   = Math.min(video.videoWidth, video.videoHeight);
    var sx     = (video.videoWidth  - size) / 2;
    var sy     = (video.videoHeight - size) / 2;
    canvas.width  = 400;
    canvas.height = 400;
    canvas.getContext('2d').drawImage(video, sx, sy, size, size, 0, 0, 400, 400);

    var dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    document.getElementById('avatarData').value = dataUrl;

    var preview = document.getElementById('avatarCapturedPreview');
    preview.src           = dataUrl;
    preview.style.display = 'block';
    video.style.display   = 'none';

    if (avatarStream) avatarStream.getTracks().forEach(function(t) { t.stop(); });
    document.getElementById('avatarShootControls').style.display  = 'none';
    document.getElementById('avatarRetakeControls').style.display = 'flex';
}

function retakeAvatar() {
    document.getElementById('avatarCapturedPreview').style.display = 'none';
    document.getElementById('avatarRetakeControls').style.display  = 'none';
    document.getElementById('avatarData').value = '';
    startAvatarCamera();
}
</script>

<?php require 'includes/footer.php'; ?>