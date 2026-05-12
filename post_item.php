<?php
require 'includes/auth_check.php';
require 'includes/db.php';
require 'includes/vision.php';

$errors  = [];
$success = '';
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Generate unique session ID for this upload session
if (!isset($_SESSION['upload_session'])) {
    $_SESSION['upload_session'] = uniqid('upload_', true);
}
$upload_session = $_SESSION['upload_session'];

// ============================================================
// AJAX: Handle individual angle photo upload & verification
// ============================================================
if (isset($_POST['ajax_verify']) && !empty($_FILES['angle_image']['name'])) {
    header('Content-Type: application/json');

    $angle       = htmlspecialchars($_POST['angle'] ?? 'front');
    $category_id = intval($_POST['category_id'] ?? 0);

    // Get category name
    $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_id]);
    $cat_name = $cat_stmt->fetchColumn() ?: 'Other';

    // Validate image
    $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
    $ext      = strtolower(pathinfo($_FILES['angle_image']['name'], PATHINFO_EXTENSION));
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG or WEBP allowed.']);
        exit;
    }

    if ($_FILES['angle_image']['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 5MB.']);
        exit;
    }

    // Save to temp folder
    $temp_name = 'temp_' . str_replace([' ', '.'], ['', '_'], uniqid('', true)) . '.' . $ext;
    $temp_path = __DIR__ . '/uploads/temp/' . $temp_name;
    move_uploaded_file($_FILES['angle_image']['tmp_name'], $temp_path);

    // Store in DB
    $pdo->prepare("INSERT INTO temp_images (session_id, filename, angle) VALUES (?, ?, ?)")
        ->execute([$upload_session, $temp_name, $angle]);

    // Verify with Google Vision
    $result = verifyImageWithAI($temp_path, $cat_name);

    if (!$result['verified']) {
        // Delete failed image immediately
        unlink($temp_path);
        $pdo->prepare("DELETE FROM temp_images WHERE filename = ?")->execute([$temp_name]);

        echo json_encode([
            'success' => false,
            'message' => $result['note'],
            'labels'  => $result['labels']
        ]);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'message'  => '✅ ' . $result['note'],
        'score'    => $result['score'],
        'filename' => $temp_name,
        'angle'    => $angle,
        'labels'   => $result['labels']
    ]);
    exit;
}

// ============================================================
// Handle Final Form Submission
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_listing'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $condition   = $_POST['condition'];
    $cover_photo = $_POST['cover_photo'] ?? '';

    if (empty($title))       $errors[] = "Title is required.";
    if ($price <= 0)         $errors[] = "Price must be greater than 0.";
    if (!$category_id)       $errors[] = "Please select a category.";
    if (empty($cover_photo)) $errors[] = "Please capture at least one verified photo.";
    if (!in_array($condition, ['new','like_new','used','heavily_used']))
                             $errors[] = "Invalid condition.";

    if (empty($errors)) {
        // Move cover photo from temp to items folder
        $final_name = uniqid('item_', true) . '.' .
                      pathinfo($cover_photo, PATHINFO_EXTENSION);
        $temp_path  = __DIR__ . '/uploads/temp/'  . $cover_photo;
        $final_path = __DIR__ . '/uploads/items/' . $final_name;

        if (file_exists($temp_path)) {
            copy($temp_path, $final_path);
        }

        // Get verification score of cover photo
        $score_stmt = $pdo->prepare("SELECT * FROM temp_images WHERE filename = ?");
        $score_stmt->execute([$cover_photo]);
        $cover_data = $score_stmt->fetch();

        // Insert listing
        $stmt = $pdo->prepare("
            INSERT INTO listings
            (seller_id, category_id, title, description, price, `condition`,
             image, is_verified, verification_score, verification_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'AI Verified — Camera Captured')
        ");
        $stmt->execute([
            $_SESSION['user_id'], $category_id, $title,
            $description, $price, $condition,
            $final_name, 95.00
        ]);

        // 🗑 Cleanup ALL temp images for this session
        cleanupTempImages($upload_session, $pdo);
        unset($_SESSION['upload_session']);

        $success = "Item posted successfully!";
    }
}
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
    <div class="form-card" style="max-width:780px;">
        <h2>📦 Post an Item</h2>
        <p class="auth-subtitle">Fill in the details and capture real photos of your item</p>

        <?php if ($success): ?>
            <div class="alert success">
                ✅ <?= $success ?> <a href="/ums/index.php">View listings →</a>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert error">
                <ul><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="listingForm" enctype="multipart/form-data">

            <!-- Basic Details -->
            <div class="form-group">
                <label>Item Title</label>
                <input type="text" name="title"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       placeholder="e.g. Calculus Textbook 3rd Edition" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="categorySelect" required>
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                data-name="<?= htmlspecialchars($cat['name']) ?>"
                                <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition" required>
                        <option value="">Select condition...</option>
                        <option value="new">New</option>
                        <option value="like_new">Like New</option>
                        <option value="used">Used</option>
                        <option value="heavily_used">Heavily Used</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Price (KSh)</label>
                <input type="number" name="price" min="1" step="0.01"
                    value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                    placeholder="e.g. 500" required
                    oninput="updateFeeBreakdown(this.value)">

                <!-- Fee Breakdown -->
                <div class="fee-breakdown" id="feeBreakdown" style="display:none;">
                    <div class="fee-row">
                        <span>Listing Price</span>
                        <span id="feeListingPrice">KSh 0.00</span>
                    </div>
                    <div class="fee-row fee-deduction">
                        <span>
                            🏛️ UMS Platform Fee (5%)
                            <small>Funds platform maintenance & student welfare</small>
                        </span>
                        <span id="feePlatformFee">- KSh 0.00</span>
                    </div>
                    <div class="fee-row fee-total">
                        <span>💰 You Receive</span>
                        <span id="feeSellerAmount">KSh 0.00</span>
                    </div>
                </div>

                <div class="fee-notice">
                    ℹ️ UMS charges a <strong>5% platform fee</strong> on all sales.
                    This funds platform maintenance, content moderation, and student welfare programs.
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Describe the item — condition details, reason for selling, etc."
                ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- ===== CAMERA CAPTURE SECTION ===== -->
            <div class="camera-section">
                <h3>📸 Item Photos</h3>
                <p class="camera-subtitle">
                    Capture your item from multiple angles for verification.
                    Our AI will verify each photo is real and matches the category.
                </p>

                <!-- Angle Guide -->
                <div class="angle-guide">
                    <div class="angle-card" data-angle="front" id="angle_front">
                        <span class="angle-icon">📱</span>
                        <span class="angle-label">Front View</span>
                        <span class="angle-status" id="status_front">Required</span>
                    </div>
                    <div class="angle-card" data-angle="side" id="angle_side">
                        <span class="angle-icon">↔️</span>
                        <span class="angle-label">Side View</span>
                        <span class="angle-status" id="status_side">Optional</span>
                    </div>
                    <div class="angle-card" data-angle="back" id="angle_back">
                        <span class="angle-icon">🔄</span>
                        <span class="angle-label">Back View</span>
                        <span class="angle-status" id="status_back">Optional</span>
                    </div>
                    <div class="angle-card" data-angle="detail" id="angle_detail">
                        <span class="angle-icon">🔍</span>
                        <span class="angle-label">Close-up Detail</span>
                        <span class="angle-status" id="status_detail">Optional</span>
                    </div>
                </div>

                <!-- Camera Tips -->
                <div class="camera-tips">
                    <p>💡 <strong>Tips for best verification:</strong>
                        Good lighting &bull; Place item on flat surface &bull;
                        Avoid glare &bull; Keep item centered
                    </p>
                </div>

                <!-- Camera / Upload Toggle -->
                <div class="capture-toggle">
                    <button type="button" class="toggle-btn active" id="btnCamera"
                            onclick="switchMode('camera')">
                        📷 Use Camera
                    </button>
                    <button type="button" class="toggle-btn" id="btnUpload"
                            onclick="switchMode('upload')">
                        📁 Upload Photo
                    </button>
                </div>

                <!-- Active Angle Selector -->
                <div class="form-group" style="margin-top:12px;">
                    <label>Capturing angle:</label>
                    <select id="activeAngle">
                        <option value="front">Front View</option>
                        <option value="side">Side View</option>
                        <option value="back">Back View</option>
                        <option value="detail">Close-up Detail</option>
                    </select>
                </div>

                <!-- Camera Mode -->
                <div id="cameraMode">
                    <div class="camera-preview-wrap">
                        <video id="cameraFeed" autoplay playsinline></video>
                        <canvas id="cameraCanvas" style="display:none;"></canvas>
                        <div class="camera-overlay">
                            <div class="camera-frame"></div>
                        </div>
                    </div>
                    <div class="camera-controls">
                        <button type="button" class="btn-capture" id="btnCapture"
                                onclick="capturePhoto()">
                            📸 Capture Photo
                        </button>
                        <button type="button" class="btn-outline btn-sm"
                                onclick="switchCamera()">
                            🔄 Flip Camera
                        </button>
                    </div>
                </div>

                <!-- Upload Mode -->
                <div id="uploadMode" style="display:none;">
                    <div class="form-group">
                        <label>Select photo from device</label>
                        <input type="file" id="uploadInput"
                               accept=".jpg,.jpeg,.png,.webp"
                               onchange="handleUpload(this)">
                    </div>
                </div>

                <!-- Verification Progress -->
                <div id="verifyProgress" style="display:none;" class="verify-progress">
                    <div class="verify-spinner"></div>
                    <span id="verifyText">Verifying with AI...</span>
                </div>

                <!-- Verified Photos Grid -->
                <div id="verifiedPhotos" class="verified-photos">
                    <p class="no-photos-msg" id="noPhotosMsg">
                        No verified photos yet. Capture or upload a photo above.
                    </p>
                </div>

                <!-- Hidden cover photo input -->
                <input type="hidden" name="cover_photo" id="coverPhotoInput">
            </div>

            <button type="submit" name="submit_listing" class="btn-primary"
                    id="submitBtn" style="margin-top:20px;">
                🚀 Post Item
            </button>
        </form>
    </div>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
let stream        = null;
let facingMode    = 'environment'; // rear camera by default
let verifiedPhotos = [];

// ---- Start Camera ----
async function startCamera() {
    try {
        if (stream) stream.getTracks().forEach(t => t.stop());

        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode, width: { ideal: 1280 }, height: { ideal: 720 } }
        });
        document.getElementById('cameraFeed').srcObject = stream;
    } catch (err) {
        alert('Could not access camera: ' + err.message +
              '\nPlease use the Upload option instead.');
        switchMode('upload');
    }
}

// ---- Switch Camera (front/back) ----
function switchCamera() {
    facingMode = facingMode === 'environment' ? 'user' : 'environment';
    startCamera();
}

// ---- Switch between Camera and Upload mode ----
function switchMode(mode) {
    const cameraDiv = document.getElementById('cameraMode');
    const uploadDiv = document.getElementById('uploadMode');
    const btnCam    = document.getElementById('btnCamera');
    const btnUp     = document.getElementById('btnUpload');

    if (mode === 'camera') {
        cameraDiv.style.display = 'block';
        uploadDiv.style.display = 'none';
        btnCam.classList.add('active');
        btnUp.classList.remove('active');
        startCamera();
    } else {
        cameraDiv.style.display = 'none';
        uploadDiv.style.display = 'block';
        btnCam.classList.remove('active');
        btnUp.classList.add('active');
        if (stream) stream.getTracks().forEach(t => t.stop());
    }
}

// ---- Capture Photo from Camera ----
function capturePhoto() {
    const video    = document.getElementById('cameraFeed');
    const canvas   = document.getElementById('cameraCanvas');
    const category = document.getElementById('categorySelect').value;

    if (!category) {
        alert('Please select a category first!');
        return;
    }

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    canvas.toBlob(blob => {
        const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
        sendForVerification(file, category);
    }, 'image/jpeg', 0.92);
}

// ---- Handle File Upload ----
function handleUpload(input) {
    const category = document.getElementById('categorySelect').value;

    if (!category) {
        alert('Please select a category first!');
        input.value = '';
        return;
    }

    if (input.files && input.files[0]) {
        sendForVerification(input.files[0], category);
    }
}

// ---- Send Image to PHP for AI Verification ----
async function sendForVerification(file, category_id) {
    const angle   = document.getElementById('activeAngle').value;
    const progress = document.getElementById('verifyProgress');
    const text     = document.getElementById('verifyText');

    progress.style.display = 'flex';
    text.textContent       = `Verifying ${angle} view with AI...`;

    const formData = new FormData();
    formData.append('ajax_verify',  '1');
    formData.append('angle_image',  file);
    formData.append('angle',        angle);
    formData.append('category_id',  category_id);

    try {
        const res  = await fetch('/ums/post_item.php', {
            method: 'POST',
            body:   formData
        });
        const data = await res.json();

        progress.style.display = 'none';

        if (data.success) {
            addVerifiedPhoto(data.filename, angle, data.score, data.message);
        } else {
            showVerifyError(data.message);
        }
    } catch (err) {
        progress.style.display = 'none';
        showVerifyError('Network error. Please try again.');
    }
}

// ---- Add Verified Photo to Grid ----
function addVerifiedPhoto(filename, angle, score, message) {
    // Remove existing photo for same angle
    verifiedPhotos = verifiedPhotos.filter(p => p.angle !== angle);
    const existing = document.getElementById('vphoto_' + angle);
    if (existing) existing.remove();

    verifiedPhotos.push({ filename, angle, score });

    // Hide "no photos" message
    document.getElementById('noPhotosMsg').style.display = 'none';

    // Set first photo as cover
    if (verifiedPhotos.length === 1) {
        document.getElementById('coverPhotoInput').value = filename;
    }

    // Update angle card status
    const card   = document.getElementById('angle_' + angle);
    const status = document.getElementById('status_' + angle);
    if (card)   card.classList.add('captured');
    if (status) {
        status.textContent = '✅ Verified';
        status.className   = 'angle-status verified';
    }

    // Add to grid
    const grid = document.getElementById('verifiedPhotos');
    const div  = document.createElement('div');
    div.className = 'vphoto-card';
    div.id        = 'vphoto_' + angle;
    div.innerHTML = `
        <div class="vphoto-badge">✅ ${angle.charAt(0).toUpperCase() + angle.slice(1)}</div>
        <div class="vphoto-score">AI Score: ${score}%</div>
        <div class="vphoto-msg">${message}</div>
        <div class="vphoto-actions">
            <button type="button" class="tbl-btn btn-success"
                    onclick="setCover('${filename}')">
                ⭐ Set as Cover
            </button>
            <button type="button" class="tbl-btn btn-danger"
                    onclick="removePhoto('${angle}', '${filename}')">
                🗑 Remove
            </button>
        </div>
    `;
    grid.appendChild(div);

    // Show success toast
    showToast(message, 'success');
}

// ---- Set Cover Photo ----
function setCover(filename) {
    document.getElementById('coverPhotoInput').value = filename;
    showToast('Cover photo updated!', 'success');
}

// ---- Remove a Verified Photo ----
async function removePhoto(angle, filename) {
    verifiedPhotos = verifiedPhotos.filter(p => p.filename !== filename);

    const div = document.getElementById('vphoto_' + angle);
    if (div) div.remove();

    const card   = document.getElementById('angle_' + angle);
    const status = document.getElementById('status_' + angle);
    if (card)   card.classList.remove('captured');
    if (status) {
        status.textContent = angle === 'front' ? 'Required' : 'Optional';
        status.className   = 'angle-status';
    }

    // Reset cover if removed
    if (document.getElementById('coverPhotoInput').value === filename) {
        document.getElementById('coverPhotoInput').value =
            verifiedPhotos.length > 0 ? verifiedPhotos[0].filename : '';
    }

    if (verifiedPhotos.length === 0) {
        document.getElementById('noPhotosMsg').style.display = 'block';
    }
}

// ---- Show Verification Error ----
function showVerifyError(message) {
    showToast('❌ ' + message, 'error');
}

// ---- Toast Notification ----
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ---- Init on page load ----
window.addEventListener('load', () => {
    startCamera();
});

// ---- Stop camera when leaving page ----
window.addEventListener('beforeunload', () => {
    if (stream) stream.getTracks().forEach(t => t.stop());
});

// ---- Fee Breakdown Calculator ----
function updateFeeBreakdown(price) {
    price = parseFloat(price);
    if (isNaN(price) || price <= 0) {
        document.getElementById('feeBreakdown').style.display = 'none';
        return;
    }

    const feePercent   = 5;
    const minFee       = 10;
    const fee          = Math.max(minFee, Math.round(price * feePercent) / 100 * 100 / 100);
    const sellerAmount = Math.max(0, price - fee).toFixed(2);

    document.getElementById('feeBreakdown').style.display    = 'block';
    document.getElementById('feeListingPrice').textContent   = 'KSh ' + price.toFixed(2);
    document.getElementById('feePlatformFee').textContent    = '- KSh ' + fee.toFixed(2);
    document.getElementById('feeSellerAmount').textContent   = 'KSh ' + sellerAmount;
}

</script>

<?php require 'includes/footer.php'; ?>