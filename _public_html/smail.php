<?php
/**
 * Smail (Spencer Mail) - Spencer's Website v7.0
 * Internal messaging system. Community role excluded.
 * Standard users: 25 sends/day. Contributor/Designer/Admin: unlimited.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/display_name.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? 'community';
$userId = (int)$_SESSION['user_id'];

if ($role === 'community') {
    header('Location: main.php');
    exit;
}

$csrfToken = generateCsrfToken();
$displayName = getCurrentDisplayName();
$elevatedRoles = ['contributor', 'designer', 'admin'];
$isElevated = in_array($role, $elevatedRoles);

// Get today's send count for rate limit display
$todaySent = 0;
$dailyLimit = $isElevated ? '&infin;' : '25';
try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$isElevated) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE sender_id = ? AND created_at >= CURDATE()");
        $stmt->execute([$userId]);
        $todaySent = (int)$stmt->fetchColumn();
    }
    // Get unread count
    $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE receiver_id = ? AND read_status = FALSE");
    $stmt->execute([$userId]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Smail init error: " . $e->getMessage());
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smail - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        .sm-wrap { max-width: 900px; margin: 0 auto; padding: 28px 20px 60px; }
        .sm-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.88rem; margin-bottom: 20px; transition: color .2s; }
        .sm-back:hover { color: #4ECDC4; }

        .sm-hero { text-align: center; margin-bottom: 20px; }
        .sm-hero h1 {
            font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .sm-hero p { color: #94a3b8; font-size: 0.92rem; margin-top: 4px; }

        .sm-stats {
            display: flex; align-items: center; justify-content: center; gap: 18px;
            padding: 10px 18px; background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px; margin-bottom: 22px; font-size: 0.84rem; color: #94a3b8; flex-wrap: wrap;
        }
        .sm-stats .stat-val { font-weight: 700; }

        /* Tabs */
        .sm-tabs { display: flex; gap: 6px; margin-bottom: 18px; justify-content: center; }
        .sm-tab {
            padding: 8px 20px; border-radius: 10px; font-size: 0.88rem; font-weight: 600;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8; cursor: pointer; transition: all .2s; user-select: none;
        }
        .sm-tab:hover { border-color: rgba(245,158,11,0.3); color: #cbd5e1; }
        .sm-tab.active { background: rgba(245,158,11,0.12); border-color: #f59e0b; color: #f59e0b; }
        .sm-tab .badge { background: #ef4444; color: #fff; font-size: 0.65rem; padding: 1px 6px; border-radius: 8px; margin-left: 5px; }

        .sm-panel { display: none; }
        .sm-panel.active { display: block; }

        /* Compose form */
        .sm-compose {
            background: rgba(15,23,42,0.75); border: 1px solid rgba(245,158,11,0.15);
            border-radius: 14px; padding: 24px; margin-bottom: 18px;
        }
        .sm-compose h2 { font-size: 1.05rem; color: #f59e0b; font-weight: 700; margin-bottom: 16px; }
        .sm-fg { margin-bottom: 12px; }
        .sm-fg label { display: block; font-size: 0.8rem; font-weight: 600; color: #94a3b8; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .4px; }
        .sm-fg input, .sm-fg textarea, .sm-fg select {
            width: 100%; padding: 10px 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; color: #e2e8f0; font-size: 0.9rem; font-family: inherit;
        }
        .sm-fg input:focus, .sm-fg textarea:focus { outline: none; border-color: #f59e0b; }
        .sm-fg textarea { resize: vertical; min-height: 100px; }
        .sm-fg-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        @media (max-width: 600px) { .sm-fg-row { grid-template-columns: 1fr; } }

        .sm-send-btn {
            padding: 10px 24px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff;
            font-size: 0.92rem; font-weight: 700; cursor: pointer; transition: opacity .2s;
        }
        .sm-send-btn:hover { opacity: .9; }
        .sm-send-btn:disabled { opacity: .4; cursor: not-allowed; }
        .sm-form-msg { margin-top: 8px; font-size: 0.85rem; }
        .sm-form-msg.ok { color: #10b981; }
        .sm-form-msg.err { color: #ef4444; }

        /* Autocomplete */
        .sm-ac { position: relative; }
        .sm-ac-list {
            position: absolute; top: 100%; left: 0; right: 0; z-index: 10;
            background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;
            max-height: 160px; overflow-y: auto; display: none;
        }
        .sm-ac-list.show { display: block; }
        .sm-ac-item { padding: 8px 12px; color: #cbd5e1; font-size: 0.88rem; cursor: pointer; }
        .sm-ac-item:hover { background: rgba(245,158,11,0.15); }

        /* Message list */
        .sm-msg-list { display: flex; flex-direction: column; gap: 8px; }
        .sm-msg {
            background: rgba(15,23,42,0.65); border-radius: 12px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.05); transition: border-color .2s;
        }
        .sm-msg:hover { border-color: rgba(245,158,11,0.2); }
        .sm-msg.unread { border-left: 3px solid #f59e0b; }
        .sm-msg-head {
            display: flex; align-items: center; justify-content: space-between; padding: 12px 16px;
            cursor: pointer; gap: 10px; flex-wrap: wrap;
        }
        .sm-msg-head:hover { background: rgba(255,255,255,0.015); }
        .sm-msg-title { font-weight: 700; font-size: 0.9rem; color: #e2e8f0; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sm-msg-from { font-size: 0.78rem; color: #94a3b8; flex-shrink: 0; }
        .sm-msg-urgency { padding: 2px 8px; border-radius: 5px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
        .urg-low { background: rgba(100,116,139,0.15); color: #94a3b8; }
        .urg-normal { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .urg-high { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .urg-urgent { background: rgba(239,68,68,0.15); color: #f87171; }
        .sm-msg-time { font-size: 0.72rem; color: #475569; flex-shrink: 0; }
        .sm-chevron { color: #475569; font-size: 0.75rem; transition: transform .25s; }
        .sm-msg.expanded .sm-chevron { transform: rotate(180deg); }

        .sm-msg-body { display: none; padding: 0 16px 16px; }
        .sm-msg.expanded .sm-msg-body { display: block; }
        .sm-msg-content { color: #cbd5e1; font-size: 0.88rem; line-height: 1.6; padding: 12px; background: rgba(0,0,0,0.15); border-radius: 8px; }
        .sm-msg-actions { display: flex; gap: 8px; margin-top: 10px; }
        .sm-msg-actions button {
            padding: 5px 12px; border: none; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;
        }
        .sm-del-btn { background: rgba(239,68,68,0.15); color: #f87171; }
        .sm-del-btn:hover { background: rgba(239,68,68,0.3); }

        .sm-empty { text-align: center; padding: 40px 16px; color: #475569; }
        .sm-empty i { font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: .3; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="sm-wrap">
    <a href="main.php" class="sm-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="sm-hero">
        <h1><i class="fas fa-envelope"></i> Smail</h1>
        <p>Spencer's internal messaging system</p>
    </div>

    <div class="sm-stats">
        <span><i class="fas fa-inbox" style="color:#f59e0b;"></i> Unread: <span class="stat-val" style="color:#f59e0b;"><?php echo $unreadCount; ?></span></span>
        <span style="color:#334155;">|</span>
        <span>Sent today: <span class="stat-val"><?php echo $todaySent; ?></span> / <?php echo $dailyLimit; ?></span>
        <span style="color:#334155;">|</span>
        <span>Role: <span class="stat-val" style="text-transform:capitalize;"><?php echo htmlspecialchars($role); ?></span></span>
    </div>

    <div class="sm-tabs">
        <div class="sm-tab active" onclick="switchSmailTab('inbox')"><i class="fas fa-inbox"></i> Inbox <?php if ($unreadCount > 0): ?><span class="badge"><?php echo $unreadCount; ?></span><?php endif; ?></div>
        <div class="sm-tab" onclick="switchSmailTab('compose')"><i class="fas fa-pen"></i> Compose</div>
        <div class="sm-tab" onclick="switchSmailTab('outbox')"><i class="fas fa-paper-plane"></i> Sent</div>
    </div>

    <!-- Inbox Panel -->
    <div class="sm-panel active" id="panel-inbox">
        <div class="sm-msg-list" id="inboxList">
            <div class="sm-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading inbox...</p></div>
        </div>
    </div>

    <!-- Compose Panel -->
    <div class="sm-panel" id="panel-compose">
        <div class="sm-compose">
            <h2><i class="fas fa-pen-fancy"></i> New Message</h2>
            <form id="composeForm" onsubmit="return sendSmail(event)">
                <div class="sm-fg sm-ac">
                    <label for="smTo">To</label>
                    <input type="text" id="smTo" placeholder="Start typing a username..." autocomplete="off" required>
                    <div class="sm-ac-list" id="acList"></div>
                </div>
                <div class="sm-fg">
                    <label for="smTitle">Subject</label>
                    <input type="text" id="smTitle" maxlength="255" placeholder="Message subject" required>
                </div>
                <div class="sm-fg-row">
                    <div class="sm-fg">
                        <label for="smColor">Color</label>
                        <input type="color" id="smColor" value="#3b82f6" style="height:40px; cursor:pointer;">
                    </div>
                    <div class="sm-fg">
                        <label for="smUrgency">Urgency</label>
                        <select id="smUrgency">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div class="sm-fg">
                    <label for="smBody">Message</label>
                    <textarea id="smBody" maxlength="5000" placeholder="Write your message..." required></textarea>
                </div>
                <button type="submit" class="sm-send-btn" id="sendBtn"><i class="fas fa-paper-plane"></i> Send Smail</button>
                <div class="sm-form-msg" id="composeMsg"></div>
            </form>
        </div>
    </div>

    <!-- Outbox Panel -->
    <div class="sm-panel" id="panel-outbox">
        <div class="sm-msg-list" id="outboxList">
            <div class="sm-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading sent messages...</p></div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

<script>
const csrf = '<?php echo htmlspecialchars($csrfToken); ?>';

function switchSmailTab(tab) {
    document.querySelectorAll('.sm-tab').forEach((t, i) => {
        t.classList.toggle('active', ['inbox','compose','outbox'][i] === tab);
    });
    document.querySelectorAll('.sm-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
    if (tab === 'inbox') loadInbox();
    if (tab === 'outbox') loadOutbox();
}

function renderMessage(msg, type) {
    const isUnread = type === 'inbox' && !msg.read_status;
    const who = type === 'inbox' ? ('From: ' + (msg.sender_name || '?')) : ('To: ' + (msg.receiver_name || '?'));
    const time = new Date(msg.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

    return `<div class="sm-msg ${isUnread ? 'unread' : ''}" data-id="${msg.id}" style="border-left-color: ${msg.color_code || '#3b82f6'};">
        <div class="sm-msg-head" onclick="toggleMsg(this, ${msg.id}, '${type}')">
            <div class="sm-msg-title" style="color: ${msg.color_code || '#e2e8f0'}">${escHtml(msg.title)}</div>
            <span class="sm-msg-urgency urg-${msg.urgency_level}">${msg.urgency_level}</span>
            <span class="sm-msg-from">${who}</span>
            <span class="sm-msg-time">${time}</span>
            <i class="fas fa-chevron-down sm-chevron"></i>
        </div>
        <div class="sm-msg-body">
            <div class="sm-msg-content">${escHtml(msg.message_body).replace(/\n/g, '<br>')}</div>
            <div class="sm-msg-actions">
                ${type === 'inbox' ? `<button class="sm-read-btn" onclick="toggleReadStatus(${msg.id}, this)" style="background:rgba(59,130,246,0.15);color:#60a5fa;padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;"><i class="fas ${isUnread ? 'fa-envelope-open' : 'fa-envelope'}"></i> ${isUnread ? 'Mark Read' : 'Mark Unread'}</button>` : ''}
                ${type === 'inbox' && msg.sender_name ? `<button onclick="showReplyForm(${msg.id}, '${escHtml(msg.sender_name)}', '${escHtml(msg.title)}')" style="background:rgba(78,205,196,0.15);color:#4ECDC4;padding:5px 12px;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;"><i class="fas fa-reply"></i> Reply</button>` : ''}
                <button class="sm-del-btn" onclick="deleteSmail(${msg.id})"><i class="fas fa-trash"></i> Delete</button>
            </div>
            <div class="sm-reply-form" id="reply-${msg.id}" style="display:none;margin-top:12px;padding:12px;background:rgba(0,0,0,0.15);border-radius:8px;">
                <textarea id="replyBody-${msg.id}" placeholder="Write your reply..." style="width:100%;padding:8px 10px;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:#e2e8f0;font-size:0.85rem;font-family:inherit;resize:vertical;min-height:60px;box-sizing:border-box;"></textarea>
                <div style="display:flex;gap:6px;margin-top:8px;">
                    <button onclick="sendReply(${msg.id})" id="replyBtn-${msg.id}" style="padding:6px 16px;border:none;border-radius:6px;background:linear-gradient(135deg,#4ECDC4,#6366f1);color:#fff;font-size:0.8rem;font-weight:600;cursor:pointer;">Send Reply</button>
                    <button onclick="document.getElementById('reply-${msg.id}').style.display='none'" style="padding:6px 12px;border:none;border-radius:6px;background:#334155;color:#94a3b8;font-size:0.8rem;cursor:pointer;">Cancel</button>
                </div>
                <div id="replyMsg-${msg.id}" style="margin-top:6px;font-size:0.8rem;"></div>
            </div>
        </div>
    </div>`;
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function toggleMsg(head, id, type) {
    const card = head.closest('.sm-msg');
    card.classList.toggle('expanded');
    if (type === 'inbox' && card.classList.contains('unread') && card.classList.contains('expanded')) {
        markRead(id);
        card.classList.remove('unread');
    }
}

async function loadInbox() {
    const body = new URLSearchParams({ action: 'get_inbox', csrf_token: csrf });
    try {
        const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        const list = document.getElementById('inboxList');
        if (data.success && data.messages.length > 0) {
            list.innerHTML = data.messages.map(m => renderMessage(m, 'inbox')).join('');
        } else {
            list.innerHTML = '<div class="sm-empty"><i class="fas fa-inbox"></i><p>No messages yet.</p></div>';
        }
    } catch (e) {
        document.getElementById('inboxList').innerHTML = '<div class="sm-empty"><p>Failed to load inbox.</p></div>';
    }
}

async function loadOutbox() {
    const body = new URLSearchParams({ action: 'get_outbox', csrf_token: csrf });
    try {
        const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        const list = document.getElementById('outboxList');
        if (data.success && data.messages.length > 0) {
            list.innerHTML = data.messages.map(m => renderMessage(m, 'outbox')).join('');
        } else {
            list.innerHTML = '<div class="sm-empty"><i class="fas fa-paper-plane"></i><p>No sent messages.</p></div>';
        }
    } catch (e) {
        document.getElementById('outboxList').innerHTML = '<div class="sm-empty"><p>Failed to load outbox.</p></div>';
    }
}

async function markRead(id) {
    await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'mark_read', csrf_token: csrf, message_id: id }) });
}

async function deleteSmail(id) {
    if (!confirm('Delete this message?')) return;
    const body = new URLSearchParams({ action: 'delete', csrf_token: csrf, message_id: id });
    const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body });
    const data = await resp.json();
    if (data.success) {
        const el = document.querySelector(`.sm-msg[data-id="${id}"]`);
        if (el) el.remove();
    }
}

async function sendSmail(e) {
    e.preventDefault();
    const btn = document.getElementById('sendBtn');
    const msg = document.getElementById('composeMsg');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    msg.textContent = ''; msg.className = 'sm-form-msg';

    const body = new URLSearchParams({
        action: 'send', csrf_token: csrf,
        receiver: document.getElementById('smTo').value,
        title: document.getElementById('smTitle').value,
        message_body: document.getElementById('smBody').value,
        color_code: document.getElementById('smColor').value,
        urgency_level: document.getElementById('smUrgency').value,
    });

    try {
        const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        msg.textContent = data.message || data.error;
        msg.className = 'sm-form-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) {
            document.getElementById('composeForm').reset();
            document.getElementById('smColor').value = '#3b82f6';
        }
    } catch (err) { msg.textContent = 'Network error.'; msg.className = 'sm-form-msg err'; }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Smail';
    return false;
}

// Username autocomplete
let acTimeout;
document.getElementById('smTo').addEventListener('input', function() {
    clearTimeout(acTimeout);
    const val = this.value.trim();
    if (val.length < 2) { document.getElementById('acList').classList.remove('show'); return; }
    acTimeout = setTimeout(async () => {
        const body = new URLSearchParams({ action: 'search_users', csrf_token: csrf, query: val });
        const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        const list = document.getElementById('acList');
        if (data.success && data.users.length > 0) {
            list.innerHTML = data.users.map(u => `<div class="sm-ac-item" onclick="selectUser('${escHtml(u)}')">${escHtml(u)}</div>`).join('');
            list.classList.add('show');
        } else {
            list.classList.remove('show');
        }
    }, 250);
});

function selectUser(username) {
    document.getElementById('smTo').value = username;
    document.getElementById('acList').classList.remove('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.sm-ac')) document.getElementById('acList').classList.remove('show');
});

// Toggle read/unread status
async function toggleReadStatus(id, btn) {
    const card = btn.closest('.sm-msg');
    const isCurrentlyUnread = card.classList.contains('unread');
    const action = isCurrentlyUnread ? 'mark_read' : 'mark_unread';
    await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action, csrf_token: csrf, message_id: id }) });
    card.classList.toggle('unread');
    const icon = isCurrentlyUnread ? 'fa-envelope' : 'fa-envelope-open';
    const text = isCurrentlyUnread ? 'Mark Unread' : 'Mark Read';
    btn.innerHTML = `<i class="fas ${icon}"></i> ${text}`;
}

// Show inline reply form
function showReplyForm(msgId, senderName, originalTitle) {
    const form = document.getElementById('reply-' + msgId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    form.dataset.to = senderName;
    form.dataset.title = originalTitle.startsWith('Re: ') ? originalTitle : 'Re: ' + originalTitle;
}

// Send reply
async function sendReply(msgId) {
    const form = document.getElementById('reply-' + msgId);
    const btn = document.getElementById('replyBtn-' + msgId);
    const msg = document.getElementById('replyMsg-' + msgId);
    const bodyText = document.getElementById('replyBody-' + msgId).value;
    if (!bodyText.trim()) { msg.textContent = 'Please write a message.'; msg.style.color = '#ef4444'; return; }
    btn.disabled = true; btn.textContent = 'Sending...';
    msg.textContent = ''; 
    try {
        const resp = await fetch('api/smail.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({
            action: 'send', csrf_token: csrf,
            receiver: form.dataset.to,
            title: form.dataset.title,
            message_body: bodyText,
            color_code: '#4ECDC4',
            urgency_level: 'normal'
        })});
        const data = await resp.json();
        msg.textContent = data.success ? 'Reply sent!' : (data.error || 'Failed');
        msg.style.color = data.success ? '#10b981' : '#ef4444';
        if (data.success) setTimeout(() => { form.style.display = 'none'; }, 1500);
    } catch(e) { msg.textContent = 'Network error.'; msg.style.color = '#ef4444'; }
    btn.disabled = false; btn.textContent = 'Send Reply';
}

// Load inbox on page load
loadInbox();
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
