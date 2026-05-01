<?php
/**
 * Users Management View — Spencer's Website v7.0
 * Enhanced user profiles, account notes, password reset links, merge
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

// Search + pagination
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$usersPerPage = 30;
$page = max(1, intval($_GET['p'] ?? 1));
$offset = ($page - 1) * $usersPerPage;

$where = "1=1";
$params = [];
if ($search) { $where .= " AND (username LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($roleFilter) { $where .= " AND role = ?"; $params[] = $roleFilter; }

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $where");
    $stmt->execute($params);
    $totalUsers = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($totalUsers / $usersPerPage));

    $stmt = $db->prepare("SELECT id, username, email, role, is_suspended, created_at, last_login, pfp_type, pfp_pending_url FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $usersPerPage; $params[] = $offset;
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = []; $totalUsers = 0; $totalPages = 1;
}

// Role counts
try {
    $stmt = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role ORDER BY cnt DESC");
    $roleCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $roleCounts = []; }
?>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <span class="card-title">User Management</span>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" class="form-input" style="width:220px" placeholder="Search username/email..." value="<?= htmlspecialchars($search) ?>" id="userSearch" onkeydown="if(event.key==='Enter')searchUsers()">
            <button class="btn btn-primary btn-sm" onclick="searchUsers()"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <!-- Role filter pills -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
        <a href="?tab=users" class="btn btn-sm <?= !$roleFilter ? 'btn-primary' : 'btn-ghost' ?>">All (<?= $totalUsers ?>)</a>
        <?php foreach ($roleCounts as $rc): ?>
            <a href="?tab=users&role=<?= $rc['role'] ?>" class="btn btn-sm <?= $roleFilter === $rc['role'] ? 'btn-primary' : 'btn-ghost' ?>"><?= ucfirst($rc['role']) ?> (<?= $rc['cnt'] ?>)</a>
        <?php endforeach; ?>
    </div>

    <!-- User table -->
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Login</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td style="color:var(--text-muted)"><?= $u['id'] ?></td>
                <td>
                    <a href="#" onclick="openUserProfile(<?= $u['id'] ?>);return false" style="color:var(--accent);text-decoration:none">
                        <?= htmlspecialchars($u['username']) ?>
                    </a>
                    <?php if ($u['pfp_type'] === 'pending'): ?><span class="tag tag-amber" style="margin-left:4px">PFP</span><?php endif; ?>
                </td>
                <td><span class="tag tag-violet"><?= htmlspecialchars($u['role']) ?></span></td>
                <td>
                    <?php if ($u['is_suspended']): ?><span class="tag tag-red">Suspended</span>
                    <?php else: ?><span class="tag tag-teal">Active</span><?php endif; ?>
                </td>
                <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td style="color:var(--text-muted);font-size:12px"><?= $u['last_login'] ? date('M j, g:i A', strtotime($u['last_login'])) : 'Never' ?></td>
                <td>
                    <button class="btn btn-ghost btn-sm" onclick="openUserProfile(<?= $u['id'] ?>)"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:16px">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?tab=users&p=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- User Profile Slide-out -->
<div id="userProfilePanel" style="display:none;position:fixed;top:0;right:0;width:420px;height:100vh;background:rgba(4,4,10,0.97);border-left:var(--glass-border);backdrop-filter:blur(20px);z-index:200;overflow-y:auto;padding:24px;transition:transform 0.3s ease">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <span class="card-title" id="profileUsername">User Profile</span>
        <button class="btn btn-ghost btn-sm" onclick="closeUserProfile()"><i class="fas fa-times"></i></button>
    </div>
    <div id="profileContent"><p style="color:var(--text-muted)">Loading...</p></div>
</div>

<script>
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function searchUsers() {
    const q = document.getElementById('userSearch').value;
    const role = '<?= urlencode($roleFilter) ?>';
    window.location.href = '?tab=users&search=' + encodeURIComponent(q) + (role ? '&role=' + role : '');
}

function openUserProfile(userId) {
    const panel = document.getElementById('userProfilePanel');
    panel.style.display = 'block';
    document.getElementById('profileContent').innerHTML = '<p style="color:var(--text-muted)">Loading...</p>';
    fetch('admin/api/user_analytics.php?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById('profileContent').innerHTML = '<p style="color:var(--red)">Failed to load profile</p>'; return; }
            const u = data.user;
            document.getElementById('profileUsername').textContent = u.username;
            const uid = parseInt(u.id);
            let html = `
                <div style="margin-bottom:16px">
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">ID: ${uid} &middot; Role: <span class="tag tag-violet">${esc(u.role)}</span> &middot; ${u.is_suspended ? '<span class="tag tag-red">Suspended</span>' : '<span class="tag tag-teal">Active</span>'}</div>
                    <div style="font-size:12px;color:var(--text-muted)">Joined: ${esc(u.created_at)} &middot; Last login: ${esc(u.last_login || 'Never')}</div>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
                    <button class="btn btn-ghost btn-sm" onclick="userAction('suspend',${uid})">${u.is_suspended ? 'Unsuspend' : 'Suspend'}</button>
                    <button class="btn btn-ghost btn-sm" onclick="userAction('force_logout',${uid})">Force Logout</button>
                    <button class="btn btn-ghost btn-sm" onclick="generateResetLink(${uid})">Reset Password</button>
                    <button class="btn btn-ghost btn-sm" onclick="impersonateUser(${uid})">Login As User</button>
                    <button class="btn btn-danger btn-sm" onclick="userAction('delete',${uid})">Delete</button>
                </div>
                <h4 style="font-size:13px;color:var(--text-muted);margin:16px 0 8px;text-transform:uppercase;letter-spacing:0.05em">Payment History</h4>
                <div style="font-size:12px;color:var(--text-muted)">${data.payments?.length ? data.payments.map(p => '<div style="padding:4px 0;border-bottom:0.5px solid rgba(255,255,255,0.06)">$' + (p.amount_cents/100).toFixed(2) + ' — ' + esc(p.status) + ' — ' + esc(p.ip_address) + '</div>').join('') : 'No payments'}</div>
                <h4 style="font-size:13px;color:var(--text-muted);margin:16px 0 8px;text-transform:uppercase;letter-spacing:0.05em">Recent Sessions</h4>
                <div style="font-size:12px;color:var(--text-muted)">${data.sessions?.length ? data.sessions.slice(0,5).map(s => '<div style="padding:4px 0;border-bottom:0.5px solid rgba(255,255,255,0.06)">' + esc(s.ip_address) + ' — ' + esc(s.current_page) + '</div>').join('') : 'No sessions'}</div>
                <h4 style="font-size:13px;color:var(--text-muted);margin:16px 0 8px;text-transform:uppercase;letter-spacing:0.05em">Admin Notes</h4>
                <div id="adminNotes">${data.notes?.length ? data.notes.map(n => '<div style="padding:6px 0;border-bottom:0.5px solid rgba(255,255,255,0.06)"><span style="color:var(--accent)">' + esc(n.admin_name) + '</span>: ' + esc(n.note) + '</div>').join('') : 'No notes'}</div>
                <div style="margin-top:8px;display:flex;gap:6px">
                    <input type="text" class="form-input" style="flex:1" placeholder="Add a note..." id="newNoteInput">
                    <button class="btn btn-primary btn-sm" onclick="addNote(${uid})">Add</button>
                </div>
            `;
            document.getElementById('profileContent').innerHTML = html;
        })
        .catch(() => { document.getElementById('profileContent').innerHTML = '<p style="color:var(--red)">Error loading profile</p>'; });
}

function closeUserProfile() { document.getElementById('userProfilePanel').style.display = 'none'; }

async function userAction(action, userId) {
    if (action === 'delete' && !confirm('Are you sure you want to delete this account? This cannot be undone.')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action, user_id: userId, csrf_token: getCsrf() });
    toast(r.message || (r.success ? 'Done' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function generateResetLink(userId) {
    const r = await apiCall('admin/api/generate_reset_link.php', { user_id: userId, csrf_token: getCsrf() });
    if (r.success && r.url) { openModal('<div class="modal-title">Password Reset Link</div><p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">Copy this link and send it to the user. It expires in 1 hour.</p><input class="form-input" value="' + esc(r.url) + '" onclick="this.select()" readonly><div class="modal-actions"><button class="btn btn-primary" onclick="closeModal()">Close</button></div>'); }
    else toast('Failed to generate link', 'error');
}

async function impersonateUser(userId) {
    if (!confirm('You will be logged in as this user for up to 15 minutes. Continue?')) return;
    const r = await apiCall('admin/api/impersonate.php', { action: 'start', user_id: userId, csrf_token: getCsrf() });
    if (r.success) window.location.href = 'main.php';
    else toast(r.message || 'Failed', 'error');
}

async function addNote(userId) {
    const note = document.getElementById('newNoteInput').value.trim();
    if (!note) return;
    const r = await apiCall('admin/api/user_notes.php', { user_id: userId, note, csrf_token: getCsrf() });
    if (r.success) { toast('Note added'); openUserProfile(userId); }
    else toast('Failed', 'error');
}
</script>
