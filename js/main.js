// ===== COMMUNITY SIDEBAR =====
var csOpen      = false;
var csMaximized = false;
var csPage      = 1;
var csMode      = 'recent';
var csCategory  = '';
var csSearch    = '';
var searchTimer = null;
var loadedIds   = [];

// ---- Toggle Sidebar ----
function toggleCommunity() {
    csOpen = !csOpen;

    var sidebar = document.getElementById('communitySidebar');
    var overlay = document.getElementById('csOverlay');
    var trigger = document.getElementById('communityTrigger');

    if (!sidebar || !overlay || !trigger) {
        console.error('Community elements not found!');
        return;
    }

    if (csOpen) {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        trigger.classList.add('active');
        if (loadedIds.length === 0) loadQuestions(true);
        localStorage.setItem('cs_last_seen', Date.now());
        var badge = document.getElementById('triggerBadge');
        if (badge) badge.style.display = 'none';
    } else {
        // Restore from maximized first if needed
        if (csMaximized) {
            csMaximized = false;
            sidebar.classList.remove('maximized');
            var btn = document.getElementById('btnMaximize');
            if (btn) btn.textContent = '⛶';
        }
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        trigger.classList.remove('active');
    }
}

// ---- Maximize/Restore ----
function maximizeSidebar() {
    csMaximized = !csMaximized;
    var sidebar = document.getElementById('communitySidebar');
    var btn     = document.getElementById('btnMaximize');

    if (csMaximized) {
        sidebar.classList.add('maximized');
        if (btn) btn.textContent = '🗗';
    } else {
        sidebar.classList.remove('maximized');
        if (btn) btn.textContent = '⛶';
    }
}

// ---- Toggle Ask Form ----
function toggleAskForm() {
    var form = document.getElementById('csAskForm');
    if (!form) return;
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// ---- Set Mode ----
function setMode(mode) {
    csMode = mode;
    var btnRecent = document.getElementById('modeRecent');
    var btnHot    = document.getElementById('modeHot');
    if (btnRecent) btnRecent.classList.toggle('active', mode === 'recent');
    if (btnHot)    btnHot.classList.toggle('active',    mode === 'hot');
    loadQuestions(true);
}

// ---- Filter Category ----
function filterCategory(cat, el) {
    csCategory = cat;
    var tabs = document.querySelectorAll('.cs-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    if (el) el.classList.add('active');
    loadQuestions(true);
}

// ---- Debounce Search ----
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
        var input = document.getElementById('csSearch');
        csSearch  = input ? input.value : '';
        loadQuestions(true);
    }, 400);
}

// ---- Load Questions ----
function loadQuestions(reset) {
    if (reset) {
        csPage    = 1;
        loadedIds = [];
        var feed  = document.getElementById('csFeed');
        if (feed) {
            feed.innerHTML = '<div class="cs-loading"><div class="cs-spinner"></div><span>Loading...</span></div>';
        }
    }

    var url = '/ums/community/fetch.php?mode=' + csMode +
              '&category=' + encodeURIComponent(csCategory) +
              '&search='   + encodeURIComponent(csSearch) +
              '&page='     + csPage;

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var feed = document.getElementById('csFeed');
            if (!feed) return;

            if (reset) feed.innerHTML = '';

            if (!data.enquiries || data.enquiries.length === 0) {
                if (reset) {
                    feed.innerHTML = '<div class="cs-empty">😕 No questions yet. Be the first to ask!</div>';
                }
                var lm = document.getElementById('csLoadMore');
                if (lm) lm.style.display = 'none';
                return;
            }

            data.enquiries.forEach(function(q) {
                if (loadedIds.indexOf(q.enquiry_id) === -1) {
                    loadedIds.push(q.enquiry_id);
                    feed.appendChild(buildThread(q));
                }
            });

            var loadMore = document.getElementById('csLoadMore');
            if (loadMore) loadMore.style.display = data.has_more ? 'block' : 'none';

            // Update badge
            var lastSeen = parseInt(localStorage.getItem('cs_last_seen') || '0');
            var newCount = data.enquiries.filter(function(q) {
                return q.status === 'open' && new Date(q.created_at).getTime() > lastSeen;
            }).length;

            var badge = document.getElementById('triggerBadge');
            if (badge) {
                if (!csOpen && newCount > 0) {
                    badge.style.display = 'flex';
                    badge.textContent   = newCount > 99 ? '99+' : newCount;
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(function(err) {
            console.error('Community load error:', err);
            var feed = document.getElementById('csFeed');
            if (feed) feed.innerHTML = '<div class="cs-empty"><i class="fas fa-triangle-exclamation"></i> Could not load questions.</div>';
        });
}

// ---- Build Thread ----
function buildThread(q) {
    var catStyles = {
        'Lecturer':     { bg: '#dbeafe', color: '#1e40af', icon: '<i class="fas fa-chalkboard-user"></i>' },
        'Lost & Found': { bg: '#fef3c7', color: '#92400e', icon: '<i class="fas fa-search"></i>' },
        'Room Change':  { bg: '#d1fae5', color: '#065f46', icon: '<i class="fas fa-door-open"></i>' },
        'Events':       { bg: '#ede9fe', color: '#5b21b6', icon: '<i class="fas fa-calendar-alt"></i>' },
        'General':      { bg: '#f0f4ff', color: '#003366', icon: '<i class="fa-solid fa-comments"></i>' }
    };
    var catStyle = catStyles[q.category] || catStyles['General'];

    var avatar = q.avatar
        ? '<img src="/ums/uploads/avatars/' + escHtml(q.avatar) + '" class="cs-avatar-img">'
        : '<div class="cs-avatar-letter">' + escHtml(q.student_name.charAt(0).toUpperCase()) + '</div>';

    var statusBadge = q.status === 'resolved'
        ? '<span class="cs-status resolved"><span class="cs-status resolved"><i class="fa-solid fa-circle-check"></i> Resolved</span>'
        : '<span class="cs-status open"><span class="cs-status open"><i class="fa-solid fa-circle-dot"></i> Open</span>';

    var div       = document.createElement('div');
    div.className = 'cs-thread' + (q.status === 'resolved' ? ' resolved' : '');
    div.id        = 'thread_' + q.enquiry_id;

    div.innerHTML =
        '<div class="cs-q-header">' +
            '<div class="cs-avatar">' + avatar + '</div>' +
            '<div class="cs-q-meta">' +
                '<span class="cs-q-author">' + escHtml(q.student_name) + '</span>' +
                '<span class="cs-q-time">'   + timeAgo(q.created_at)   + '</span>' +
                '<span class="cs-q-cat" style="background:' + catStyle.bg + ';color:' + catStyle.color + '">' +
                    catStyle.icon + ' ' + escHtml(q.category) +
                '</span>' +
            '</div>' +
            statusBadge +
        '</div>' +
        '<p class="cs-q-content">' + escHtml(q.content) + '</p>' +
        '<div class="cs-q-footer">' +
            '<button class="cs-reply-toggle" onclick="toggleReplies(' + q.enquiry_id + ', this)">' +
                '<i class="fa-solid fa-comments"></i> ' + q.reply_count + ' Repl' + (q.reply_count == 1 ? 'y' : 'ies') +
            '</button>' +
            '<span class="cs-views"><i class="fa-solid fa-eye"></i> ' + q.views + '</span>' +
        '</div>' +
        '<div class="cs-replies"    id="replies_'   + q.enquiry_id + '" style="display:none;"></div>' +
        '<div class="cs-reply-form" id="replyform_' + q.enquiry_id + '" style="display:none;">' +
            '<textarea id="replytext_' + q.enquiry_id + '" ' +
                      'placeholder="Write your answer..." rows="2" maxlength="300"></textarea>' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">' +
                '<span style="font-size:0.75rem;color:#aaa;">Max 300 chars</span>' +
                '<button onclick="submitReply(' + q.enquiry_id + ')" class="cs-submit-btn">Post Reply</button>' +
            '</div>' +
        '</div>';

    return div;
}

// ---- Toggle Replies ----
function toggleReplies(id, btn) {
    var repliesDiv = document.getElementById('replies_'   + id);
    var replyForm  = document.getElementById('replyform_' + id);

    if (!repliesDiv || !replyForm) return;

    var isVisible = repliesDiv.style.display !== 'none';

    if (isVisible) {
        repliesDiv.style.display = 'none';
        replyForm.style.display  = 'none';
        return;
    }

    repliesDiv.style.display = 'block';
    replyForm.style.display  = 'block';
    repliesDiv.innerHTML     = '<div class="cs-loading"><div class="cs-spinner"></div></div>';

    fetch('/ums/community/replies_fetch.php?enquiry_id=' + id)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            repliesDiv.innerHTML = '';
            if (!data.replies || data.replies.length === 0) {
                repliesDiv.innerHTML = '<p class="cs-no-replies">No replies yet. Be the first!</p>';
                return;
            }
            data.replies.forEach(function(r) {
                repliesDiv.appendChild(buildReply(r));
            });
        })
        .catch(function() {
            repliesDiv.innerHTML = '<p class="cs-no-replies">Could not load replies.</p>';
        });
}

// ---- Build Reply ----
function buildReply(r) {
    var div       = document.createElement('div');
    div.className = 'cs-reply' + (r.is_best_answer == 1 ? ' best-answer' : '');
    div.id        = 'reply_' + r.reply_id;

    var avatar = r.avatar
        ? '<img src="/ums/uploads/avatars/' + escHtml(r.avatar) + '" class="cs-avatar-img sm">'
        : '<div class="cs-avatar-letter sm">' + escHtml(r.student_name.charAt(0).toUpperCase()) + '</div>';

    div.innerHTML =
        '<div class="cs-reply-header">' +
            '<div class="cs-avatar">' + avatar + '</div>' +
            '<span class="cs-reply-author">' + escHtml(r.student_name) + '</span>' +
            '<span class="cs-reply-time">'   + timeAgo(r.created_at)   + '</span>' +
            (r.is_best_answer == 1 ? '<span class="cs-best"><i class="fa-solid fa-star"></i> Best Answer</span>' : '') +
        '</div>' +
        '<p class="cs-reply-text">' + escHtml(r.reply_text) + '</p>';

    return div;
}

// ---- Submit Question ----
function submitQuestion() {
    var content  = document.getElementById('csQuestion').value.trim();
    var catEl    = document.getElementById('csCategory');
    var category = catEl ? catEl.value : 'General';

    if (!content || content.length < 10) {
        showCsToast('Question must be at least 10 characters.', 'error');
        return;
    }

    var formData = new FormData();
    formData.append('content',  content);
    formData.append('category', category);

    fetch('/ums/community/ask.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('csQuestion').value = '';
                document.getElementById('csAskForm').style.display = 'none';
                showCsToast('<span class="cs-status resolved"><i class="fa-solid fa-circle-check"></i> Question posted!', 'success');

                var feed  = document.getElementById('csFeed');
                var empty = feed ? feed.querySelector('.cs-empty') : null;
                if (empty) empty.remove();

                var enquiry = data.enquiry;
                enquiry.reply_count = 0;
                enquiry.views       = 0;
                var thread = buildThread(enquiry);
                if (feed) feed.insertBefore(thread, feed.firstChild);
                loadedIds.unshift(data.enquiry.enquiry_id);
            } else {
                showCsToast(data.message || 'Error posting question.', 'error');
            }
        })
        .catch(function() {
            showCsToast('Network error. Please try again.', 'error');
        });
}

// ---- Submit Reply ----
function submitReply(enquiry_id) {
    var ta    = document.getElementById('replytext_' + enquiry_id);
    var reply = ta ? ta.value.trim() : '';

    if (!reply) {
        showCsToast('Reply cannot be empty.', 'error');
        return;
    }

    var formData = new FormData();
    formData.append('enquiry_id', enquiry_id);
    formData.append('reply_text', reply);

    fetch('/ums/community/reply.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                if (ta) ta.value = '';
                showCsToast('<span class="cs-status resolved"><i class="fa-solid fa-circle-check"></i> Reply posted!', 'success');

                var repliesDiv = document.getElementById('replies_' + enquiry_id);
                if (repliesDiv) {
                    var noReply = repliesDiv.querySelector('.cs-no-replies');
                    if (noReply) noReply.remove();
                    repliesDiv.appendChild(buildReply(data.reply));

                    var count = repliesDiv.querySelectorAll('.cs-reply').length;
                    var btn   = document.querySelector('#thread_' + enquiry_id + ' .cs-reply-toggle');
                    if (btn) btn.innerHTML = '<i class="fa-solid fa-comments"></i> ' + count + ' Repl' + (count == 1 ? 'y' : 'ies');
                }
            } else {
                showCsToast(data.message || 'Error posting reply.', 'error');
            }
        })
        .catch(function() {
            showCsToast('Network error. Please try again.', 'error');
        });
}

// ---- Load More ----
function loadMore() {
    csPage++;
    loadQuestions(false);
}

// ---- Helpers ----
function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
}

function timeAgo(dateStr) {
    var diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff / 60)   + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600)  + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function showCsToast(msg, type) {
    var t = document.createElement('div');
    t.className   = 'toast toast-' + type;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.classList.add('show'); }, 10);
    setTimeout(function() {
        t.classList.remove('show');
        setTimeout(function() { t.remove(); }, 300);
    }, 3500);
}

// ---- Auto Refresh every 30 seconds ----
setInterval(function() {
    if (!csOpen) return;

    var url = '/ums/community/fetch.php?mode=' + csMode +
              '&category=' + encodeURIComponent(csCategory) +
              '&search='   + encodeURIComponent(csSearch) +
              '&page=1';

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.enquiries) return;

            var newOnes = data.enquiries.filter(function(q) {
                return loadedIds.indexOf(q.enquiry_id) === -1;
            });

            if (newOnes.length > 0) {
                var feed  = document.getElementById('csFeed');
                var empty = feed ? feed.querySelector('.cs-empty') : null;
                if (empty) empty.remove();

                newOnes.forEach(function(q) {
                    loadedIds.unshift(q.enquiry_id);
                    if (feed) feed.insertBefore(buildThread(q), feed.firstChild);
                });

                showCsToast('<i class="fa-solid fa-comments"></i> ' + newOnes.length + ' new question(s)!', 'success');
            }
        })
        .catch(function() {});
}, 30000);

// ---- Init on DOM Ready ----
document.addEventListener('DOMContentLoaded', function() {
    // Character counter
    var ta = document.getElementById('csQuestion');
    if (ta) {
        ta.addEventListener('input', function() {
            var counter = document.getElementById('csCharCount');
            if (counter) counter.textContent = (500 - ta.value.length) + ' chars left';
        });
    }

    // Trigger click — open/close sidebar
    var trigger = document.getElementById('communityTrigger');
    if (trigger) {
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleCommunity();
        });
    }

    // Overlay click — close sidebar
    var overlay = document.getElementById('csOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (csOpen) toggleCommunity();
        });
    }

    // Prevent sidebar clicks from closing it
    var sidebar = document.getElementById('communitySidebar');
    if (sidebar) {
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Silent background load
    loadQuestions(true);
});

// ---- Prevent Double Form Submission ----
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        if (form.id === 'listingForm') return;
        if (form.id === 'profileForm') return;

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