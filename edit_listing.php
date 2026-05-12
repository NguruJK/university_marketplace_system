<?php
require 'includes/auth_check.php';
require_once 'includes/db.php';

$listing_id = intval($_GET['id'] ?? 0);
$errors     = [];
$success    = '';

// Fetch listing — only owner or admin can edit
$stmt = $pdo->prepare("
    SELECT l.*, c.name AS category_name
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch();

// Validate ownership
if (!$listing) {
    echo "<p style='text-align:center;padding:40px'>
            ⚠️ Listing not found. <a href='/ums/index.php'>Go back</a>
          </p>";
    exit;
}

if ($listing['seller_id'] !== $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
    echo "<p style='text-align:center;padding:40px'>
            ⚠️ You are not authorized to edit this listing.
          </p>";
    exit;
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $condition   = $_POST['condition'];
    $status      = $_POST['status'];

    // Validation
    if (empty($title))   $errors[] = "Title is required.";
    if ($price <= 0)     $errors[] = "Price must be greater than 0.";
    if (!$category_id)   $errors[] = "Please select a category.";
    if (!in_array($condition, ['new','like_new','used','heavily_used']))
                         $errors[] = "Invalid condition.";
    if (!in_array($status, ['available','sold','removed']))
                         $errors[] = "Invalid status.";

    // Handle image replacement
    $image_name = $listing['image']; // keep existing by default
    if (!empty($_FILES['image']['name'])) {
        $allowed  = ['jpg','jpeg','png','webp'];
        $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024;

        if (!in_array($ext, $allowed)) {
            $errors[] = "Only JPG, PNG or WEBP images allowed.";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image must be under 2MB.";
        } else {
            // Delete old image
            if ($listing['image']) {
                $old = __DIR__ . '/uploads/items/' . $listing['image'];
                if (file_exists($old)) unlink($old);
            }
            $image_name = uniqid('item_', true) . '.' . $ext;
            move_uploaded_file(
                $_FILES['image']['tmp_name'],
                __DIR__ . '/uploads/items/' . $image_name
            );
        }
    }

    // Handle image removal
    if (isset($_POST['remove_image']) && $listing['image']) {
        $old = __DIR__ . '/uploads/items/' . $listing['image'];
        if (file_exists($old)) unlink($old);
        $image_name = null;
    }

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE listings
            SET title       = ?,
                description = ?,
                price       = ?,
                category_id = ?,
                `condition` = ?,
                status      = ?,
                image       = ?
            WHERE id = ?
        ")->execute([
            $title, $description, $price,
            $category_id, $condition, $status,
            $image_name, $listing_id
        ]);

        $success = "Listing updated successfully!";

        // Refresh listing data
        $stmt = $pdo->prepare("
            SELECT l.*, c.name AS category_name
            FROM listings l
            JOIN categories c ON l.category_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$listing_id]);
        $listing = $stmt->fetch();
    }
}
?>
<?php require 'includes/header.php'; ?>

<div class="page-wrapper">
    <a href="/ums/listing.php?id=<?= $listing_id ?>" class="back-link">
        ← Back to listing
    </a>

    <div class="form-card" style="max-width:680px;">
        <h2>✏️ Edit Listing</h2>
        <p class="auth-subtitle">Update your item details below</p>

        <?php if ($success): ?>
            <div class="alert success">
                ✅ <?= $success ?>
                <a href="/ums/listing.php?id=<?= $listing_id ?>">View listing →</a>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert error">
                <ul><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- Title -->
            <div class="form-group">
                <label>Item Title</label>
                <input type="text" name="title"
                       value="<?= htmlspecialchars($listing['title']) ?>"
                       placeholder="e.g. Calculus Textbook" required>
            </div>

            <!-- Category + Condition -->
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= $listing['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition" required>
                        <option value="new"          <?= $listing['condition'] === 'new'          ? 'selected' : '' ?>>New</option>
                        <option value="like_new"     <?= $listing['condition'] === 'like_new'     ? 'selected' : '' ?>>Like New</option>
                        <option value="used"         <?= $listing['condition'] === 'used'         ? 'selected' : '' ?>>Used</option>
                        <option value="heavily_used" <?= $listing['condition'] === 'heavily_used' ? 'selected' : '' ?>>Heavily Used</option>
                    </select>
                </div>
            </div>

            <!-- Price + Status -->
            <div class="form-row">
                <div class="form-group">
                    <label>Price (KSh)</label>
                    <input type="number" name="price" min="1" step="0.01"
                           value="<?= htmlspecialchars($listing['price']) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Listing Status</label>
                    <select name="status">
                        <option value="available" <?= $listing['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="sold"      <?= $listing['status'] === 'sold'      ? 'selected' : '' ?>>Sold</option>
                        <option value="removed"   <?= $listing['status'] === 'removed'   ? 'selected' : '' ?>>Removed</option>
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"
                          placeholder="Describe the item..."
                ><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
            </div>

            <!-- Current Image -->
            <div class="form-group">
                <label>Item Photo</label>

                <?php if ($listing['image']): ?>
                    <div class="edit-image-preview">
                        <img src="/ums/uploads/items/<?= htmlspecialchars($listing['image']) ?>"
                             alt="Current photo" id="currentImg">
                        <div class="edit-image-actions">
                            <p class="edit-image-label">Current photo</p>
                            <button type="submit" name="remove_image"
                                    class="tbl-btn btn-danger"
                                    onclick="return confirm('Remove this photo?')">
                                🗑 Remove Photo
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-image" style="height:100px; border-radius:8px; margin-bottom:10px;">
                        📦 No photo
                    </div>
                <?php endif; ?>

                <!-- Upload New Image -->
                <label class="edit-upload-label">
                    📷 <?= $listing['image'] ? 'Replace' : 'Add' ?> Photo
                    <span class="optional">(JPG, PNG or WEBP — max 2MB)</span>
                </label>
                <input type="file" name="image"
                       accept=".jpg,.jpeg,.png,.webp"
                       onchange="previewNewImage(this)">

                <!-- Preview new image before submit -->
                <div id="newImgPreview" style="display:none; margin-top:10px;">
                    <p style="font-size:0.82rem; color:var(--color-text-faint);">New photo preview:</p>
                    <img id="newImgSrc" src="" alt="Preview"
                         style="max-width:200px; border-radius:8px; border:2px solid var(--color-primary);">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="edit-actions">
                <button type="submit" class="btn-primary" style="width:auto; padding:12px 32px;">
                    💾 Save Changes
                </button>
                <a href="/ums/listing.php?id=<?= $listing_id ?>"
                   class="btn-outline">
                    Cancel
                </a>
                <a href="/ums/profile.php"
                   class="btn-outline">
                    My Listings
                </a>
            </div>

        </form>
    </div>
</div>

<script>
function previewNewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newImgSrc').src    = e.target.result;
            document.getElementById('newImgPreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require 'includes/footer.php'; ?>