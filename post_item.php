<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/vision.php';

$errors     = [];
$success    = '';
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

if (!isset($_SESSION['upload_session'])) {
    $_SESSION['upload_session'] = uniqid('upload_', true);
}
$upload_session = $_SESSION['upload_session'];

// ============================================================
// AJAX: Photo upload & AI verification
// ============================================================
if (isset($_POST['ajax_verify']) && !empty($_FILES['angle_image']['name'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }

    $angle       = htmlspecialchars($_POST['angle'] ?? 'front');
    $category_id = intval($_POST['category_id'] ?? 0);

    $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_id]);
    $cat_name = $cat_stmt->fetchColumn() ?: 'Other';

    $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
    $ext      = strtolower(pathinfo($_FILES['angle_image']['name'], PATHINFO_EXTENSION));
    $max_size = 5 * 1024 * 1024;

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG or WEBP allowed.']);
        exit;
    }
    if ($_FILES['angle_image']['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 5MB.']);
        exit;
    }

    $temp_name = 'temp_' . str_replace([' ', '.'], ['', '_'], uniqid('', true)) . '.' . $ext;
    $temp_path = __DIR__ . '/uploads/temp/' . $temp_name;
    move_uploaded_file($_FILES['angle_image']['tmp_name'], $temp_path);

    $pdo->prepare("INSERT INTO temp_images (session_id, filename, angle) VALUES (?, ?, ?)")
        ->execute([$upload_session, $temp_name, $angle]);

    $result = verifyImageWithAI($temp_path, $cat_name);

    if (!$result['verified']) {
        unlink($temp_path);
        $pdo->prepare("DELETE FROM temp_images WHERE filename = ?")->execute([$temp_name]);
        echo json_encode(['success' => false, 'message' => $result['note'], 'labels' => $result['labels']]);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'message'  => $result['note'],
        'score'    => $result['score'],
        'filename' => $temp_name,
        'angle'    => $angle,
        'labels'   => $result['labels']
    ]);
    exit;
}

// ============================================================
// Final Submission
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_listing'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $condition   = $_POST['condition'];
    $cover_photo = $_POST['cover_photo'] ?? '';
    $photo_count = intval($_POST['photo_count'] ?? 0);

    if (empty($title))       $errors[] = "Title is required.";
    if ($price <= 0)         $errors[] = "Price must be greater than 0.";
    if (!$category_id)       $errors[] = "Please select a category.";
    if (empty($cover_photo)) $errors[] = "Please capture at least one verified photo.";
    if ($photo_count < 3)    $errors[] = "Please capture at least 3 photos.";
    if (!in_array($condition, ['new','like_new','used','heavily_used']))
                             $errors[] = "Invalid condition.";

    if (empty($errors)) {
        $final_name = uniqid('item_', true) . '.' . pathinfo($cover_photo, PATHINFO_EXTENSION);
        $temp_path  = __DIR__ . '/uploads/temp/'  . $cover_photo;
        $final_path = __DIR__ . '/uploads/items/' . $final_name;

        if (file_exists($temp_path)) copy($temp_path, $final_path);

        $pdo->prepare("
            INSERT INTO listings
            (seller_id, category_id, title, description, price, `condition`,
             image, status, is_verified, verification_score, verification_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available', 1, 95.00, 'AI Verified')
        ")->execute([
            $_SESSION['user_id'], $category_id, $title,
            $description, $price, $condition, $final_name
        ]);

        cleanupTempImages($upload_session, $pdo);
        unset($_SESSION['upload_session']);
        $success = "Item posted successfully!";
    }
}
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
<div class="wizard-card">

    <?php if ($success): ?>
        <div class="wizard-success">
            <div class="success-icon">
                <i class="fa-solid fa-circle-check" style="font-size:4rem;color:var(--color-success);"></i>
            </div>
            <h2>Item Posted!</h2>
            <p>Your item is now live on the marketplace.</p>
            <div class="success-actions">
                <a href="/ums/index.php" class="btn-primary" style="width:auto;padding:12px 28px;">
                    Browse Marketplace
                </a>
                <a href="/ums/post_item.php" class="btn-outline" style="padding:12px 28px;">
                    Post Another
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if ($errors): ?>
            <div class="alert error" id="serverErrors">
                <ul><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="listingForm" enctype="multipart/form-data">

            <!-- STEP 1: ITEM DETAILS -->
            <div class="wizard-pane" id="pane1">
                <h3 class="pane-title">
                    <i class="fa-solid fa-list-check"></i> Item Details
                </h3>

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

                    <div class="fee-breakdown" id="feeBreakdown" style="display:none;">
                        <div class="fee-row">
                            <span>Listing Price</span>
                            <span id="feeListingPrice">KSh 0.00</span>
                        </div>
                        <div class="fee-row fee-deduction">
                            <span>
                                <i class="fa-solid fa-building-columns"></i> Platform Fee (5%)
                                <small>Funds platform &amp; student welfare</small>
                            </span>
                            <span id="feePlatformFee">- KSh 0.00</span>
                        </div>
                        <div class="fee-row fee-total">
                            <span>
                                <i class="fa-solid fa-coins"></i> You Receive
                            </span>
                            <span id="feeSellerAmount">KSh 0.00</span>
                        </div>
                    </div>
                    <div class="fee-notice">
                        <i class="fa-solid fa-circle-info"></i>
                        UMS charges a <strong>5% platform fee</strong> on all sales.
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"
                              placeholder="Describe the item — condition, reason for selling, etc."
                    ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="wizard-nav">
                    <span></span>
                    <button type="button" class="btn-primary"
                            style="width:auto;padding:12px 32px;"
                            onclick="goToStep2()">
                        Next: Add Photos
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: PHOTOS & VERIFICATION -->
            <div class="wizard-pane" id="pane2" style="display:none;">
                <h3 class="pane-title">
                    <i class="fa-solid fa-camera"></i> Photos &amp; Verification
                </h3>
                <p class="auth-subtitle">
                    Capture <strong>at least 3 angles</strong>.
                    AI will verify each photo matches the category.
                </p>

                <!-- Photo Counter -->
                <div class="photo-counter">
                    <span id="photoCountText">0 of 3 required photos taken</span>
                    <div class="photo-count-bar">
                        <div class="photo-count-fill" id="photoCountFill" style="width:0%"></div>
                    </div>
                </div>

                <!-- Angle Guide -->
                <div class="angle-guide">
                    <div class="angle-card" id="angle_front">
                        <span class="angle-icon">
                            <i class="fa-solid fa-mobile-screen"></i>
                        </span>
                        <span class="angle-label">Front</span>
                        <span class="angle-status" id="status_front">Required</span>
                    </div>
                    <div class="angle-card" id="angle_side">
                        <span class="angle-icon">
                            <i class="fa-solid fa-arrows-left-right"></i>
                        </span>
                        <span class="angle-label">Side</span>
                        <span class="angle-status" id="status_side">Required</span>
                    </div>
                    <div class="angle-card" id="angle_back">
                        <span class="angle-icon">
                            <i class="fa-solid fa-rotate"></i>
                        </span>
                        <span class="angle-label">Back</span>
                        <span class="angle-status" id="status_back">Required</span>
                    </div>
                    <div class="angle-card" id="angle_detail">
                        <span class="angle-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <span class="angle-label">Detail</span>
                        <span class="angle-status" id="status_detail">Optional</span>
                    </div>
                </div>

                <!-- Camera Tips -->
                <div class="camera-tips">
                    <i class="fa-solid fa-lightbulb"></i>
                    Good lighting &bull; Flat surface &bull; Avoid glare &bull; Item centered
                </div>

                <!-- Mode Toggle -->
                <div class="capture-toggle">
                    <button type="button" class="toggle-btn active" id="btnCamera"
                            onclick="switchMode('camera')">
                        <i class="fa-solid fa-camera"></i> Camera
                    </button>
                    <button type="button" class="toggle-btn" id="btnUpload"
                            onclick="switchMode('upload')">
                        <i class="fa-solid fa-folder-open"></i> Upload
                    </button>
                </div>

                <!-- Angle Selector -->
                <div class="form-group" style="margin-top:10px;">
                    <label>Capturing angle:</label>
                    <select id="activeAngle">
                        <option value="front">Front View</option>
                        <option value="side">Side View</option>
                        <option value="back">Back View</option>
                        <option value="detail">Close-up</option>
                    </select>
                </div>

                <!-- Camera -->
                <div id="cameraMode">
                    <div class="camera-preview-wrap">
                        <video id="cameraFeed" autoplay playsinline></video>
                        <canvas id="cameraCanvas" style="display:none;"></canvas>
                        <div class="camera-overlay">
                            <div class="camera-frame"></div>
                        </div>
                    </div>
                    <div class="camera-controls">
                        <button type="button" class="btn-capture" onclick="capturePhoto()">
                            <i class="fa-solid fa-camera-retro"></i> Capture
                        </button>
                        <button type="button" class="btn-outline btn-sm" onclick="switchCamera()">
                            <i class="fa-solid fa-rotate"></i> Flip
                        </button>
                    </div>
                </div>

                <!-- Upload -->
                <div id="uploadMode" style="display:none;">
                    <div class="form-group">
                        <label>Select photo</label>
                        <input type="file" id="uploadInput"
                               accept=".jpg,.jpeg,.png,.webp"
                               onchange="handleUpload(this)">
                    </div>
                </div>

                <!-- Verify Progress -->
                <div id="verifyProgress" style="display:none;" class="verify-progress">
                    <div class="verify-spinner"></div>
                    <span id="verifyText">Verifying with AI...</span>
                </div>

                <!-- Verified Photos -->
                <div id="verifiedPhotos" class="verified-photos">
                    <p class="no-photos-msg" id="noPhotosMsg">
                        No verified photos yet. Capture at least 3 angles.
                    </p>
                </div>

                <input type="hidden" name="cover_photo" id="coverPhotoInput">
                <input type="hidden" name="photo_count" id="photoCountInput" value="0">
                <input type="hidden" name="submit_listing" value="1">

                <div class="wizard-nav">
                    <button type="button" class="btn-outline"
                            style="padding:12px 24px;"
                            onclick="goToStep1()">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn-primary"
                            style="width:auto;padding:12px 32px;"
                            onclick="validateAndSubmit()">
                        <i class="fa-solid fa-paper-plane"></i> Post Item
                    </button>
                </div>
            </div>

        </form>
    <?php endif; ?>
</div>
</div>

<script>
var stream         = null;
var facingMode     = 'environment';
var verifiedPhotos = [];

function goToStep2() {
    var title     = document.querySelector('input[name="title"]');
    var category  = document.getElementById('categorySelect');
    var price     = document.querySelector('input[name="price"]');
    var condition = document.querySelector('select[name="condition"]');
    var errors    = [];

    if (!title || !title.value.trim())          errors.push('Title is required.');
    if (!category || !category.value)           errors.push('Please select a category.');
    if (!price || parseFloat(price.value) <= 0) errors.push('Price must be greater than 0.');
    if (!condition || !condition.value)         errors.push('Please select a condition.');

    var existing = document.querySelector('.step1-error');
    if (existing) existing.remove();

    if (errors.length > 0) {
        var div = document.createElement('div');
        div.className = 'alert error step1-error';
        div.innerHTML = '<ul>' + errors.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>';
        document.getElementById('pane1').insertBefore(div, document.querySelector('.pane-title').nextSibling);
        window.scrollTo(0, 0);
        return;
    }

    document.getElementById('pane1').style.display = 'none';
    document.getElementById('pane2').style.display = 'block';
    window.scrollTo(0, 0);
    startCamera();
}

function goToStep1() {
    document.getElementById('pane2').style.display = 'none';
    document.getElementById('pane1').style.display = 'block';
    window.scrollTo(0, 0);
    if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
}

function startCamera() {
    if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: facingMode, width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function(s) {
        stream = s;
        document.getElementById('cameraFeed').srcObject = stream;
    }).catch(function(err) {
        console.warn('Camera:', err.message);
        switchMode('upload');
        showPostToast('Camera unavailable — use Upload instead.', 'error');
    });
}

function switchCamera() {
    facingMode = facingMode === 'environment' ? 'user' : 'environment';
    startCamera();
}

function switchMode(mode) {
    var cameraDiv = document.getElementById('cameraMode');
    var uploadDiv = document.getElementById('uploadMode');
    var btnCam    = document.getElementById('btnCamera');
    var btnUp     = document.getElementById('btnUpload');

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
        if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
    }
}

function capturePhoto() {
    var video    = document.getElementById('cameraFeed');
    var canvas   = document.getElementById('cameraCanvas');
    var category = document.getElementById('categorySelect').value;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    canvas.toBlob(function(blob) {
        var file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
        sendForVerification(file, category);
    }, 'image/jpeg', 0.92);
}

function handleUpload(input) {
    var category = document.getElementById('categorySelect').value;
    if (input.files && input.files[0]) {
        sendForVerification(input.files[0], category);
    }
}

function sendForVerification(file, category_id) {
    var angle    = document.getElementById('activeAngle').value;
    var progress = document.getElementById('verifyProgress');
    var text     = document.getElementById('verifyText');

    progress.style.display = 'flex';
    text.textContent = 'Verifying ' + angle + ' view with AI...';

    var formData = new FormData();
    formData.append('ajax_verify',  '1');
    formData.append('angle_image',  file);
    formData.append('angle',        angle);
    formData.append('category_id',  category_id);

    fetch('/ums/post_item.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            progress.style.display = 'none';
            if (data.success) {
                addVerifiedPhoto(data.filename, angle, data.score, data.message);
            } else {
                showPostToast(data.message, 'error');
            }
        })
        .catch(function() {
            progress.style.display = 'none';
            showPostToast('Network error. Please try again.', 'error');
        });
}

function addVerifiedPhoto(filename, angle, score, message) {
    verifiedPhotos = verifiedPhotos.filter(function(p) { return p.angle !== angle; });
    var existing = document.getElementById('vphoto_' + angle);
    if (existing) existing.remove();

    verifiedPhotos.push({ filename: filename, angle: angle, score: score });
    updatePhotoCounter();

    document.getElementById('noPhotosMsg').style.display = 'none';
    if (verifiedPhotos.length === 1) {
        document.getElementById('coverPhotoInput').value = filename;
    }

    var card   = document.getElementById('angle_' + angle);
    var status = document.getElementById('status_' + angle);
    if (card)   card.classList.add('captured');
    if (status) {
        status.innerHTML  = '<i class="fa-solid fa-circle-check" style="color:var(--color-success);"></i> Verified';
        status.className  = 'angle-status verified';
    }

    var grid = document.getElementById('verifiedPhotos');
    var div  = document.createElement('div');
    div.className = 'vphoto-card';
    div.id        = 'vphoto_' + angle;
    div.innerHTML =
        '<div class="vphoto-badge">' +
            '<i class="fa-solid fa-circle-check" style="color:var(--color-success);"></i> ' +
            angle.charAt(0).toUpperCase() + angle.slice(1) +
        '</div>' +
        '<div class="vphoto-score">Score: ' + score + '%</div>' +
        '<div class="vphoto-actions">' +
            '<button type="button" class="tbl-btn btn-success" onclick="setCover(\'' + filename + '\')">' +
                '<i class="fa-solid fa-star"></i> Cover' +
            '</button>' +
            '<button type="button" class="tbl-btn btn-danger" onclick="removePhoto(\'' + angle + '\',\'' + filename + '\')">' +
                '<i class="fa-solid fa-trash"></i>' +
            '</button>' +
        '</div>';
    grid.appendChild(div);

    showPostToast(message, 'success');
}

function updatePhotoCounter() {
    var count    = verifiedPhotos.length;
    var required = 3;
    var pct      = Math.min(100, (count / required) * 100);
    var text     = document.getElementById('photoCountText');
    var fill     = document.getElementById('photoCountFill');
    var input    = document.getElementById('photoCountInput');

    if (text)  text.textContent = count + ' of ' + required + ' required' + (count >= required ? ' — complete' : '');
    if (fill)  { fill.style.width = pct + '%'; fill.style.background = count >= required ? '#40916c' : '#e07b00'; }
    if (input) input.value = count;
}

function setCover(filename) {
    document.getElementById('coverPhotoInput').value = filename;
    showPostToast('Cover photo updated!', 'success');
}

function removePhoto(angle, filename) {
    verifiedPhotos = verifiedPhotos.filter(function(p) { return p.filename !== filename; });
    var div = document.getElementById('vphoto_' + angle);
    if (div) div.remove();

    var card   = document.getElementById('angle_' + angle);
    var status = document.getElementById('status_' + angle);
    if (card)   card.classList.remove('captured');
    if (status) {
        status.textContent = angle === 'detail' ? 'Optional' : 'Required';
        status.className   = 'angle-status';
    }

    if (document.getElementById('coverPhotoInput').value === filename) {
        document.getElementById('coverPhotoInput').value =
            verifiedPhotos.length > 0 ? verifiedPhotos[0].filename : '';
    }
    if (verifiedPhotos.length === 0) {
        document.getElementById('noPhotosMsg').style.display = 'block';
    }
    updatePhotoCounter();
}

function validateAndSubmit() {
    var coverPhoto = document.getElementById('coverPhotoInput').value;
    var photoCount = parseInt(document.getElementById('photoCountInput').value || '0');
    var errors = [];

    if (!coverPhoto) errors.push('Please capture at least one verified photo.');
    if (photoCount < 3) errors.push('Please capture at least 3 photos (' + photoCount + '/3 taken).');

    var existing = document.querySelector('.step2-error');
    if (existing) existing.remove();

    if (errors.length > 0) {
        var div = document.createElement('div');
        div.className = 'alert error step2-error';
        div.innerHTML = '<ul>' + errors.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>';
        document.getElementById('pane2').insertBefore(div, document.querySelector('#pane2 .pane-title').nextSibling);
        window.scrollTo(0, 0);
        return;
    }

    document.getElementById('listingForm').submit();
}

function showPostToast(msg, type) {
    var t = document.createElement('div');
    t.className   = 'toast toast-' + type;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.classList.add('show'); }, 10);
    setTimeout(function() { t.classList.remove('show'); setTimeout(function() { t.remove(); }, 300); }, 4000);
}

function updateFeeBreakdown(price) {
    price = parseFloat(price);
    if (isNaN(price) || price <= 0) {
        document.getElementById('feeBreakdown').style.display = 'none';
        return;
    }
    var fee          = Math.max(10, parseFloat((price * 0.05).toFixed(2)));
    var sellerAmount = Math.max(0, price - fee).toFixed(2);
    document.getElementById('feeBreakdown').style.display  = 'block';
    document.getElementById('feeListingPrice').textContent = 'KSh ' + price.toFixed(2);
    document.getElementById('feePlatformFee').textContent  = '- KSh ' + fee.toFixed(2);
    document.getElementById('feeSellerAmount').textContent = 'KSh ' + sellerAmount;
}

window.addEventListener('beforeunload', function() {
    if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
});
</script>

<?php require 'includes/footer.php'; ?>