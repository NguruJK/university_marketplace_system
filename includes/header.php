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
    <i class="fa-solid fa-magnifying-glass"></i> Browse
</a>

<?php if (isset($_SESSION['user_id'])): ?>
            <a href="/ums/post_item.php">
                <i class="fa-solid fa-circle-plus"></i> Post Item
            </a>           
            <a href="/ums/profile.php">
                <i class="fa-solid fa-user"></i> My Profile
            </a>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
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
<!-- ===== COMMUNITY HELP SIDEBAR ===== -->
<div id="communityTrigger" class="community-trigger">
    <span class="trigger-icon">💬</span>
    <span class="trigger-label">Community Help</span>
    <span class="trigger-badge" id="triggerBadge" style="display:none;">0</span>
</div>

<div id="communitySidebar" class="community-sidebar">

    <!-- Sidebar Header -->
    <div class="cs-header">
        <div class="cs-title">
            <span>💬</span>
            <h3>Community Help</h3>
        </div>
        <div class="cs-header-actions">
            <button onclick="maximizeSidebar()" title="Maximize" class="cs-btn" id="btnMaximize">⛶</button>
            <button onclick="toggleCommunity()"  title="Close"    class="cs-btn">✕</button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="cs-filters">
        <input type="text" id="csSearch"
               placeholder="🔍 Search questions..."
               oninput="debounceSearch()">
        <div class="cs-category-tabs">
            <button class="cs-tab active" onclick="filterCategory('', this)">All</button>
            <button class="cs-tab" onclick="filterCategory('Lecturer', this)">👨‍🏫 Lecturer</button>
            <button class="cs-tab" onclick="filterCategory('Lost &amp; Found', this)">🔍 Lost</button>
            <button class="cs-tab" onclick="filterCategory('Room Change', this)">🚪 Rooms</button>
            <button class="cs-tab" onclick="filterCategory('Events', this)">📅 Events</button>
            <button class="cs-tab" onclick="filterCategory('General', this)">💬 General</button>        </div>
        <div class="cs-mode-tabs">
            <button class="cs-mode active" id="modeRecent" onclick="setMode('recent')">🕐 Recent</button>
            <button class="cs-mode"        id="modeHot"    onclick="setMode('hot')">🔥 Hot</button>
        </div>
    </div>

    <!-- Ask Question -->
    <div class="cs-ask">
        <button class="cs-ask-toggle" onclick="toggleAskForm()">
            ✏️ Ask a Question
        </button>
        <div id="csAskForm" class="cs-ask-form" style="display:none;">
            <select id="csCategory">
                <option value="General">💬 General</option>
                <option value="Lecturer">👨‍🏫 Lecturer</option>
                <option value="Lost & Found">🔍 Lost & Found</option>
                <option value="Room Change">🚪 Room Change</option>
                <option value="Events">📅 Events</option>
            </select>
            <textarea id="csQuestion"
                      placeholder="e.g. Where is Dr. Kamau's office? Has anyone seen a blue calculator?"
                      rows="3" maxlength="500"></textarea>
            <div class="cs-ask-footer">
                <span id="csCharCount" class="cs-char-count">500 chars left</span>
                <button onclick="submitQuestion()" class="cs-submit-btn">Post Question</button>
            </div>
        </div>
    </div>

    <!-- Questions Feed -->
    <div class="cs-feed" id="csFeed">
        <div class="cs-loading" id="csFeedLoading">
            <div class="cs-spinner"></div>
            <span>Loading questions...</span>
        </div>
    </div>

    <!-- Load More -->
    <div class="cs-load-more" id="csLoadMore" style="display:none;">
        <button onclick="loadMore()">Load more questions</button>
    </div>

</div>

<!-- Overlay -->
<div id="csOverlay" class="cs-overlay" onclick="toggleCommunity()"></div>

<!-- Question Thread Template (hidden) -->
<div id="csThreadTemplate" style="display:none;">
    <div class="cs-thread" id="thread_PLACEHOLDER">
        <div class="cs-q-header">
            <div class="cs-avatar" id="thread_avatar_PLACEHOLDER"></div>
            <div class="cs-q-meta">
                <span class="cs-q-author"  id="thread_author_PLACEHOLDER"></span>
                <span class="cs-q-time"    id="thread_time_PLACEHOLDER"></span>
                <span class="cs-q-cat"     id="thread_cat_PLACEHOLDER"></span>
            </div>
            <span class="cs-status" id="thread_status_PLACEHOLDER"></span>
        </div>
        <p class="cs-q-content" id="thread_content_PLACEHOLDER"></p>
        <div class="cs-q-footer">
            <button class="cs-reply-toggle" id="thread_replybtn_PLACEHOLDER">
                💬 <span id="thread_replycount_PLACEHOLDER">0</span> Replies
            </button>
            <span class="cs-views" id="thread_views_PLACEHOLDER"></span>
        </div>
        <div class="cs-replies" id="thread_replies_PLACEHOLDER" style="display:none;"></div>
        <div class="cs-reply-form" id="thread_replyform_PLACEHOLDER" style="display:none;">
            <textarea placeholder="Write your answer..." rows="2" maxlength="300"
                      id="thread_replytext_PLACEHOLDER"></textarea>
            <button onclick="submitReply(ENQUIRY_ID)">Post Reply</button>
        </div>
    </div>
</div>

<script src="/ums/js/main.js"></script>
</body>
<?php endif; ?>