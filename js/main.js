// ===== COMMUNITY SIDEBAR JS =====
let csOpen      = false;
let csMaximized = false;
let csPage      = 1;
let csMode      = 'recent';
let csCategory  = '';
let csSearch    = '';
let searchTimer = null;
let loadedIds   = new Set();

// ---- Toggle Sidebar ----
function toggleCommunity() {
    csOpen = !csOpen;
    const sidebar = document.getElementById('communitySidebar');
    const overlay = document.getElementById('csOverlay');
    const trigger = document.getElementById('communityTrigger');

    sidebar.classList.toggle('open', csOpen);
    overlay.classList.toggle('active', csOpen);
    trigger.classList.toggle('active', csOpen);

    if (csOpen) {
        // Clear badge when user opens sidebar — they are now "seeing" questions
        const badge = document.getElementById('triggerBadge');
        if (badge) badge.style.display = 'none';

        // Load questions if first time opening
        if (loadedIds.size === 0) loadQuestions(true);

        // Save last seen timestamp in localStorage
        localStorage.setItem('cs_last_seen', Date.now());
    }
}

// ---- Maximize/Restore ----
function maximizeSidebar() {
    csMaximized = !csMaximized;
    const sidebar = document.getElementById('communitySidebar');
    const btn     = document.getElementById('btnMaximize');
    sidebar.classList.toggle('maximized', csMaximized);
    btn.textContent = csMaximized ? '⛶' : '⛶';
}

// ---- Toggle Ask Form ----
function toggleAskForm() {
    const form = document.getElementById('csAskForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// ---- Character Count ----
document.addEventListener('DOMContentLoaded', () => {
    // Character counter
    const ta = document.getElementById('csQuestion');
    if (ta) {
        ta.addEventListener('input', () => {
            const left = 500 - ta.value.length;
            const counter = document.getElementById('csCharCount');
            if (counter) counter.textContent = left + ' chars left';
        });
    }

    // Load questions silently on page load (don't open sidebar)
    loadQuestions(true);

    // Make sure trigger is clickable
    const trigger = document.getElementById('communityTrigger');
    if (trigger) {
        trigger.addEventListener('click', toggleCommunity);
    }
});

// ---- Set Mode (recent/hot) ----
function setMode(mode) {
    csMode = mode;
    document.getElementById('modeRecent').classList.toggle('active', mode === 'recent');
    document.getElementById('modeHot').classList.toggle('active', mode === 'hot');
    loadQuestions(true);
}

// ---- Filter by Category ----
function filterCategory(cat, el) {
    csCategory = cat;
    document.querySelectorAll('.cs-tab').forEach(t => t.classList.remove('active'));
    if (el) el.classList.add('active');
    loadQuestions(true);
}

// ---- Debounce Search ----
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        csSearch = document.getElementById('csSearch').value;
        loadQuestions(true);
    }, 400);
}

// ---- Load Questions ----
async function loadQuestions(reset = false) {
    if (reset) {
        csPage    = 1;
        loadedIds = new Set();
        document.getElementById('csFeed').innerHTML =
            '<div class="cs-loading"><div class="cs-spinner"></div><span>Loading...</span></div>';
    }

    const url = `/ums/community/fetch.php?mode=${csMode}&category=${encodeURIComponent(csCategory)}&search=${encodeURIComponent(csSearch)}&page=${csPage}`;

    try {
        const res  = await fetch(url);
        const data = await res.json();

        if (reset) document.getElementById('csFeed').innerHTML = '';

        if (!data.enquiries || data.enquiries.length === 0) {
            if (reset) {
                document.getElementById('csFeed').innerHTML =
                    '<div class="cs-empty">😕 No questions yet. Be the first to ask!</div>';
            }
            document.getElementById('csLoadMore').style.display = 'none';
            return;
        }

        data.enquiries.forEach(q => {
            if (!loadedIds.has(q.enquiry_id)) {
                loadedIds.add(q.enquiry_id);
                document.getElementById('csFeed').appendChild(buildThread(q));
            }
        });

        document.getElementById('csLoadMore').style.display =
            data.has_more ? 'block' : 'none';

        // Only badge questions newer than last time sidebar was opened
        const lastSeen  = parseInt(localStorage.getItem('cs_last_seen') || '0');
        const newCount  = data.enquiries.filter(function(q) {
            const qTime = new Date(q.created_at).getTime();
            return q.status === 'open' && qTime > lastSeen;
        }).length;

        const badge = document.getElementById('triggerBadge');
        if (badge) {
            if (!csOpen && newCount > 0) {
                badge.style.display = 'flex';
                badge.textContent   = newCount > 99 ? '99+' : newCount;
            } else {
                badge.style.display = 'none';
            }
        }

    } catch(e) {
        document.getElementById('csFeed').innerHTML =
            '<div class="cs-empty">⚠️ Could not load questions.</div>';
    }
}

// ---- Build Thread HTML ----
function buildThread(q) {
    const div       = document.createElement('div');
    div.className   = 'cs-thread' + (q.status === 'resolved' ? ' resolved' : '');
    div.id          = 'thread_' + q.enquiry_id;

    const avatar    = q.avatar
        ? `<img src="/ums/uploads/avatars/${q.avatar}" class="cs-avatar-img">`
        : `<div class="cs-avatar-letter">${q.student_name.charAt(0).toUpperCase()}</div>`;

    const statusBadge = q.status === 'resolved'
        ? '<span class="cs-status resolved">✅ Resolved</span>'
        : '<span class="cs-status open">🟢 Open</span>';

    const catStyles = {
    'Lecturer':     { bg: '#dbeafe', color: '#1e40af', icon: '👨‍🏫' },
    'Lost & Found': { bg: '#fef3c7', color: '#92400e', icon: '🔍' },
    'Room Change':  { bg: '#d1fae5', color: '#065f46', icon: '🚪' },
    'Events':       { bg: '#ede9fe', color: '#5b21b6', icon: '📅' },
    'General':      { bg: '#f0f4ff', color: '#003366', icon: '💬' }
    };
    const catStyle = catStyles[q.category] || catStyles['General'];

    div.innerHTML = `
        <div class="cs-q-header">
            <div class="cs-avatar">${avatar}</div>
            <div class="cs-q-meta">
                <span class="cs-q-author">${escHtml(q.student_name)}</span>
                <span class="cs-q-time">${timeAgo(q.created_at)}</span>
                <span class="cs-q-cat" style="background:${catStyle.bg}; color:${catStyle.color}">
                    ${catStyle.icon} ${q.category}
                </span>
            </div>
            ${statusBadge}
        </div>
        <p class="cs-q-content">${escHtml(q.content)}</p>
        <div class="cs-q-footer">
            <button class="cs-reply-toggle"
                    onclick="toggleReplies(${q.enquiry_id}, this)">
                💬 ${q.reply_count} Repl${q.reply_count == 1 ? 'y' : 'ies'}
            </button>
            <span class="cs-views">👁 ${q.views}</span>
        </div>
        <div class="cs-replies" id="replies_${q.enquiry_id}" style="display:none;"></div>
        <div class="cs-reply-form" id="replyform_${q.enquiry_id}" style="display:none;">
            <textarea id="replytext_${q.enquiry_id}"
                      placeholder="Write your answer..." rows="2" maxlength="300"></textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                <span style="font-size:0.75rem; color:#aaa;">Max 300 chars</span>
                <button onclick="submitReply(${q.enquiry_id})" class="cs-submit-btn">
                    Post Reply
                </button>
            </div>
        </div>
    `;
    return div;
}

// ---- Toggle Replies ----
async function toggleReplies(id, btn) {
    const repliesDiv  = document.getElementById('replies_'   + id);
    const replyForm   = document.getElementById('replyform_' + id);
    const isVisible   = repliesDiv.style.display !== 'none';

    if (isVisible) {
        repliesDiv.style.display = 'none';
        replyForm.style.display  = 'none';
        return;
    }

    repliesDiv.style.display = 'block';
    replyForm.style.display  = 'block';
    repliesDiv.innerHTML     = '<div class="cs-loading"><div class="cs-spinner"></div></div>';

    try {
        const res  = await fetch(`/ums/community/replies_fetch.php?enquiry_id=${id}`);
        const data = await res.json();

        repliesDiv.innerHTML = '';

        if (!data.replies || data.replies.length === 0) {
            repliesDiv.innerHTML = '<p class="cs-no-replies">No replies yet. Be the first!</p>';
            return;
        }

        data.replies.forEach(r => {
            repliesDiv.appendChild(buildReply(r, id));
        });
    } catch(e) {
        repliesDiv.innerHTML = '<p class="cs-no-replies">Could not load replies.</p>';
    }
}

// ---- Build Reply HTML ----
function buildReply(r, enquiry_id) {
    const div     = document.createElement('div');
    div.className = 'cs-reply' + (r.is_best_answer == 1 ? ' best-answer' : '');
    div.id        = 'reply_' + r.reply_id;

    const avatar  = r.avatar
        ? `<img src="/ums/uploads/avatars/${r.avatar}" class="cs-avatar-img sm">`
        : `<div class="cs-avatar-letter sm">${r.student_name.charAt(0).toUpperCase()}</div>`;

    div.innerHTML = `
        <div class="cs-reply-header">
            <div class="cs-avatar">${avatar}</div>
            <span class="cs-reply-author">${escHtml(r.student_name)}</span>
            <span class="cs-reply-time">${timeAgo(r.created_at)}</span>
            ${r.is_best_answer == 1 ? '<span class="cs-best">⭐ Best Answer</span>' : ''}
        </div>
        <p class="cs-reply-text">${escHtml(r.reply_text)}</p>
    `;
    return div;
}

// ---- Submit Question ----
async function submitQuestion() {
    const content  = document.getElementById('csQuestion').value.trim();
    const category = document.getElementById('csCategory').value;

    if (!content || content.length < 10) {
        showCsToast('Question must be at least 10 characters.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('content',  content);
    formData.append('category', category);

    try {
        const res  = await fetch('/ums/community/ask.php', {
            method: 'POST', body: formData
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('csQuestion').value = '';
            document.getElementById('csAskForm').style.display = 'none';
            showCsToast('✅ Question posted!', 'success');

            // Prepend new question to feed
            const feed = document.getElementById('csFeed');
            const empty = feed.querySelector('.cs-empty');
            if (empty) empty.remove();

            const thread = buildThread({
                ...data.enquiry,
                reply_count: 0,
                views: 0
            });
            feed.insertBefore(thread, feed.firstChild);
            loadedIds.add(data.enquiry.enquiry_id);
        } else {
            showCsToast(data.message, 'error');
        }
    } catch(e) {
        showCsToast('Network error. Please try again.', 'error');
    }
}

// ---- Submit Reply ----
async function submitReply(enquiry_id) {
    const ta       = document.getElementById('replytext_' + enquiry_id);
    const reply    = ta.value.trim();

    if (!reply) {
        showCsToast('Reply cannot be empty.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('enquiry_id', enquiry_id);
    formData.append('reply_text', reply);

    try {
        const res  = await fetch('/ums/community/reply.php', {
            method: 'POST', body: formData
        });
        const data = await res.json();

        if (data.success) {
            ta.value = '';
            showCsToast('✅ Reply posted!', 'success');

            // Append reply to thread
            const repliesDiv = document.getElementById('replies_' + enquiry_id);
            const noReply    = repliesDiv.querySelector('.cs-no-replies');
            if (noReply) noReply.remove();
            repliesDiv.appendChild(buildReply(data.reply, enquiry_id));

            // Update reply count
            const btn = document.querySelector(`#thread_${enquiry_id} .cs-reply-toggle`);
            if (btn) {
                const count = repliesDiv.querySelectorAll('.cs-reply').length;
                btn.innerHTML = `💬 ${count} Repl${count == 1 ? 'y' : 'ies'}`;
            }
        } else {
            showCsToast(data.message, 'error');
        }
    } catch(e) {
        showCsToast('Network error. Please try again.', 'error');
    }
}

// ---- Load More ----
function loadMore() {
    csPage++;
    loadQuestions(false);
}

// ---- Helpers ----
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return Math.floor(diff/60)   + 'm ago';
    if (diff < 86400)  return Math.floor(diff/3600)  + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function showCsToast(msg, type) {
    const t = document.createElement('div');
    t.className   = `toast toast-${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
}

// Auto-refresh every 30 seconds
setInterval(() => { if (csOpen) loadQuestions(true); }, 30000);

// ---- Prevent Double Form Submission ----
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled     = true;
                btn.dataset.orig = btn.textContent;
                btn.textContent  = '⏳ Please wait...';

                setTimeout(function() {
                    btn.disabled    = false;
                    btn.textContent = btn.dataset.orig;
                }, 10000);
            }
        });
    });
});