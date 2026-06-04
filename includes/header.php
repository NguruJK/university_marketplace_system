<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMS – University Marketplace</title>
    <link rel="stylesheet" href="/ums/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<nav class="navbar">
    <a class="nav-brand" href="/ums/index.php">
        <img src="/ums/assets/logo.png" alt="UoN Logo" class="nav-logo">
        <span>UMS</span>
    </a>
    <div class="nav-links">
        <a href="/ums/index.php">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Items
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/ums/post_item.php">
                <i class="fa-solid fa-circle-plus"></i> Post Item
            </a>
            <a href="/ums/profile.php">
                <i class="fa-solid fa-user"></i> My Profile
            </a>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <a href="/ums/admin/dashboard.php">
                    <i class="fa-solid fa-user-tie"></i> Admin
                </a>
            <?php endif; ?>
            <a href="/ums/auth/logout.php">
                <i class="fa-solid fa-lock"></i> Logout
            </a>
        <?php else: ?>
            <a href="/ums/auth/login.php">
                <i class="fa-solid fa-key"></i> Login
            </a>
            <a href="/ums/auth/register.php">
                <i class="fa-solid fa-user-plus"></i> Register
            </a>
        <?php endif; ?>
    </div>
</nav>

<?php if (isset($_SESSION['user_id'])): ?>
<!-- ===== COMMUNITY TRIGGER — fixed below navbar, horizontal ===== -->
<div id="communityTrigger" class="community-trigger">
    <span class="trigger-icon"><i class="fa-solid fa-comments"></i></span>
    <span class="trigger-label">Community Help</span>
    <span class="trigger-badge" id="triggerBadge" style="display:none;">0</span>
</div>

<!-- ===== COMMUNITY HELP SIDEBAR ===== -->
<div id="communitySidebar" class="community-sidebar">
    <div class="cs-header">
        <div class="cs-title">
            <span><i class="fa-solid fa-comments"></i></span>
            <h3>Community Help</h3>
        </div>
        <div class="cs-header-actions">
            <button onclick="maximizeSidebar()" title="Maximize" class="cs-btn" id="btnMaximize">
                <i class="fa-solid fa-expand"></i>
            </button>
            <button onclick="toggleCommunity()" title="Close" class="cs-btn">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <div class="cs-filters">
        <input type="text" id="csSearch"
               placeholder="Search questions..."
               oninput="debounceSearch()">
        <div class="cs-category-tabs">
            <button class="cs-tab active" onclick="filterCategory('', this)">All</button>
            <button class="cs-tab" onclick="filterCategory('Lecturer', this)">
                <i class="fa-solid fa-chalkboard-user"></i> Lecturer
            </button>
            <button class="cs-tab" onclick="filterCategory('Lost & Found', this)">
                <i class="fa-solid fa-magnifying-glass"></i> Lost
            </button>
            <button class="cs-tab" onclick="filterCategory('Room Change', this)">
                <i class="fa-solid fa-door-open"></i> Rooms
            </button>
            <button class="cs-tab" onclick="filterCategory('Events', this)">
                <i class="fa-solid fa-calendar-days"></i> Events
            </button>
            <button class="cs-tab" onclick="filterCategory('General', this)">
                <i class="fa-solid fa-comment"></i> General
            </button>
        </div>
        <div class="cs-mode-tabs">
            <button class="cs-mode active" id="modeRecent" onclick="setMode('recent')">
                <i class="fa-solid fa-clock"></i> Recent
            </button>
            <button class="cs-mode" id="modeHot" onclick="setMode('hot')">
                <i class="fa-solid fa-fire"></i> Hot
            </button>
        </div>
    </div>

    <div class="cs-ask">
        <button class="cs-ask-toggle" onclick="toggleAskForm()">
            <i class="fa-solid fa-pen-to-square"></i> Ask a Question
        </button>
        <div id="csAskForm" class="cs-ask-form" style="display:none;">
            <select id="csCategory">
                <option value="General">General</option>
                <option value="Lecturer">Lecturer</option>
                <option value="Lost & Found">Lost &amp; Found</option>
                <option value="Room Change">Room Change</option>
                <option value="Events">Events</option>
            </select>
            <textarea id="csQuestion"
                      placeholder="e.g. Where is Dr. Kamau's office?"
                      rows="3" maxlength="500"></textarea>
            <div class="cs-ask-footer">
                <span id="csCharCount" class="cs-char-count">500 chars left</span>
                <button onclick="submitQuestion()" class="cs-submit-btn">Post Question</button>
            </div>
        </div>
    </div>

    <div class="cs-feed" id="csFeed">
        <div class="cs-loading">
            <div class="cs-spinner"></div>
            <span>Loading questions...</span>
        </div>
    </div>

    <div class="cs-load-more" id="csLoadMore" style="display:none;">
        <button onclick="loadMore()">Load more questions</button>
    </div>
</div>

<div id="csOverlay" class="cs-overlay"></div>

<script src="/ums/js/main.js"></script>
<?php endif; ?>