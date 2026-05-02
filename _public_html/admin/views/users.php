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

    $stmt = $db->prepare("SELECT id, username, email, role, is_suspended, created_at, last_login, pfp_type, pfp_pending_url FROM users WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) $stmt->bindValue($k + 1, $v);
    $stmt->bindValue(':limit', (int)$usersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
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

<!-- Filters & Header -->
<div class="admin-card">
    <div class="card-header">
        <span class="card-title">User Management</span>
        <div style="display:flex;gap:10px;align-items:center">
            <div style="position:relative">
                <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:12px"></i>
                <input type="text" class="form-input" style="width:260px;padding-left:34px" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>" id="userSearch" onkeydown="if(event.key==='Enter')searchUsers()">
            </div>
            <button class="btn btn-primary btn-sm" onclick="searchUsers()" style="border-radius:10px"><i class="fas fa-search"></i> Search</button>
        </div>
    </div>
    
    <!-- Role filter pills -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
        <a href="?tab=users" class="chip <?= !$roleFilter ? 'active' : '' ?>">All Users (<?= $totalUsers ?>)</a>
        <?php foreach ($roleCounts as $rc): ?>
            <a href="?tab=users&role=<?= $rc['role'] ?>" class="chip <?= $roleFilter === $rc['role'] ? 'active' : '' ?>"><?= ucfirst($rc['role']) ?> (<?= $rc['cnt'] ?>)</a>
        <?php endforeach; ?>
    </div>

    <!-- User table -->
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Activity</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:11px;color:var(--text-dim)">#<?= $u['id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:30px;height:30px;border-radius:8px;background:var(--bg-elevated);border:var(--border-subtle);display:flex;align-items:center;justify-content:center;overflow:hidden">
                                <i class="fas fa-user-ninja" style="font-size:14px;color:var(--text-faint)"></i>
                            </div>
                            <div>
                                <a href="#" onclick="openUserProfile(<?= $u['id'] ?>);return false" style="color:var(--text-soft);text-decoration:none;font-weight:500;font-size:13.5px">
                                    <?= htmlspecialchars($u['username']) ?>
                                </a>
                                <?php if ($u['pfp_type'] === 'pending'): ?><span class="badge badge--warn" style="font-size:8px;margin-left:4px">Pending PFP</span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge--violet"><?= strtoupper($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_suspended']): ?><span class="badge badge--danger">Restricted</span>
                        <?php else: ?><span class="badge badge--teal">Active</span><?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:11px"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td style="color:var(--text-muted);font-size:11px"><?= $u['last_login'] ? date('M j, g:i A', strtotime($u['last_login'])) : 'Unknown' ?></td>
                    <td>
                        <button class="btn btn-ghost btn-action" onclick="openUserProfile(<?= $u['id'] ?>)"><i class="fas fa-fingerprint"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:24px">
        <?php 
        $startP = max(1, $page - 2);
        $endP = min($totalPages, $page + 2);
        if ($startP > 1) echo '<span class="text-faint" style="padding:4px 8px">...</span>';
        for ($i = $startP; $i <= $endP; $i++): ?>
            <a href="?tab=users&p=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>" style="min-width:32px;height:32px;padding:0;border-radius:8px"><?= $i ?></a>
        <?php endfor;
        if ($endP < $totalPages) echo '<span class="text-faint" style="padding:4px 8px">...</span>';
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- User Profile Slide-out -->
<div id="userProfilePanel" style="display:none;position:fixed;top:0;right:0;width:480px;height:100vh;background:rgba(8,8,26,0.92);border-left:var(--border-violet);backdrop-filter:blur(40px);-webkit-backdrop-filter:blur(40px);z-index:200;overflow-y:auto;box-shadow:-20px 0 80px rgba(0,0,0,0.8);transition:transform 0.4s var(--ease-out);transform:translateX(100%)">
    <div style="padding:32px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:12px;background:var(--accent-halo);display:flex;align-items:center;justify-content:center;color:var(--accent)">
                    <i class="fas fa-user-shield"></i>
                </div>
                <span class="card-title" id="profileUsername" style="font-size:18px;letter-spacing:-0.01em">Entity Profile</span>
            </div>
            <button class="btn btn-ghost btn-action" onclick="closeUserProfile()"><i class="fas fa-times"></i></button>
        </div>
        <div id="profileContent"><p class="text-muted">Initializing telemetry...</p></div>
    </div>
</div>

<script>
function searchUsers() {
    const q = document.getElementById('userSearch').value;
    const role = '<?= urlencode($roleFilter) ?>';
    window.location.href = '?tab=users&search=' + encodeURIComponent(q) + (role ? '&role=' + role : '');
}

function openUserProfile(userId) {
    const panel = document.getElementById('userProfilePanel');
    panel.style.display = 'block';
    setTimeout(() => panel.style.transform = 'translateX(0)', 10);
    document.getElementById('profileContent').innerHTML = '<p class="text-muted">Fetching remote data...</p>';
    
    fetch('admin/api/user_analytics.php?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById('profileContent').innerHTML = '<p style="color:var(--red)">Telemetry link failed</p>'; return; }
            const u = data.user;
            document.getElementById('profileUsername').textContent = u.username;
            const uid = parseInt(u.id);
            let html = `
                <div style="margin-bottom:32px">
                    <div style="display:flex;gap:8px;margin-bottom:12px">
                        <span class="badge badge--violet">UID: ${uid}</span>
                        <span class="badge badge--pink">${esc(u.role.toUpperCase())}</span>
                        ${u.is_suspended ? '<span class="badge badge--danger">RESTRICTED</span>' : '<span class="badge badge--teal">ACTIVE</span>'}
                    </div>
                    <div class="text-muted" style="font-size:12px;display:flex;flex-direction:column;gap:4px">
                        <span><i class="fas fa-calendar-alt" style="width:16px"></i> Registered: ${esc(u.created_at)}</span>
                        <span><i class="fas fa-clock" style="width:16px"></i> Last Access: ${esc(u.last_login || 'Never')}</span>
                        <span><i class="fas fa-envelope" style="width:16px"></i> Alias Email: ${esc(u.email || 'N/A')}</span>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:32px">
                    <button class="btn btn-ghost btn-sm" onclick="userAction('suspend',${uid})"><i class="fas fa-user-lock"></i> ${u.is_suspended ? 'Unrestrict' : 'Restrict'}</button>
                    <button class="btn btn-ghost btn-sm" onclick="userAction('force_logout',${uid})"><i class="fas fa-sign-out-alt"></i> Kill Sessions</button>
                    <button class="btn btn-ghost btn-sm" onclick="generateResetLink(${uid})"><i class="fas fa-key"></i> Pass Reset</button>
                    <button class="btn btn-ghost btn-sm" onclick="impersonateUser(${uid})"><i class="fas fa-user-secret"></i> Override</button>
                </div>

                <div class="divider"></div>

                <h4 class="form-label" style="margin-top:24px">Payment Telemetry</h4>
                <div class="admin-table-wrap" style="margin-bottom:24px">
                    <table class="admin-table">
                        <tbody style="font-size:11px">
                            ${data.payments?.length ? data.payments.map(p => `
                                <tr>
                                    <td>$${(p.amount_cents/100).toFixed(2)}</td>
                                    <td><span class="badge badge--${p.status==='completed'?'teal':'warn'}" style="font-size:9px">${p.status}</span></td>
                                    <td class="text-muted">${esc(p.created_at.split(' ')[0])}</td>
                                </tr>
                            `).join('') : '<tr><td colspan="3" class="text-muted">No transactions recorded</td></tr>'}
                        </tbody>
                    </table>
                </div>

                <h4 class="form-label">Recent Uplinks</h4>
                <div class="text-muted" style="font-size:11px;margin-bottom:24px">
                    ${data.sessions?.length ? data.sessions.map(s => `
                        <div style="padding:8px 0;border-bottom:var(--border-subtle);display:flex;justify-content:space-between">
                            <span>${esc(s.ip_address)}</span>
                            <span class="text-faint">${esc(s.current_page.split('/').pop() || 'main.php')}</span>
                        </div>
                    `).join('') : 'No active uplinks'}
                </div>

                <h4 class="form-label">Administrative Journal</h4>
                <div id="adminNotes" style="font-size:12px;margin-bottom:16px">
                    ${data.notes?.length ? data.notes.map(n => `
                        <div style="padding:10px;background:rgba(255,255,255,0.02);border-radius:8px;margin-bottom:8px;border:var(--border-subtle)">
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                <span style="color:var(--accent);font-weight:600">${esc(n.admin_name)}</span>
                                <span class="text-faint" style="font-size:10px">${esc(n.created_at)}</span>
                            </div>
                            <div style="color:var(--text-soft)">${esc(n.note)}</div>
                        </div>
                    `).join('') : '<p class="text-faint">No journal entries found for this entity.</p>'}
                </div>
                
                <div style="display:flex;gap:8px">
                    <input type="text" class="form-input" style="flex:1" placeholder="Append to journal..." id="newNoteInput">
                    <button class="btn btn-primary btn-sm" onclick="addNote(${uid})" style="border-radius:10px">Append</button>
                </div>

                <div style="margin-top:48px;padding:20px;border:1px solid rgba(239,68,68,0.2);border-radius:12px;background:rgba(239,68,68,0.05)">
                    <h4 class="form-label" style="color:var(--danger)">Destructive Actions</h4>
                    <p class="text-muted" style="font-size:11px;margin-bottom:12px">Permanently purge this entity from the core database. This cannot be reversed.</p>
                    <button class="btn btn-danger btn-sm" onclick="userAction('delete',${uid})" style="width:100%">Purge Entity</button>
                </div>
            `;
            document.getElementById('profileContent').innerHTML = html;
        })
        .catch(() => { document.getElementById('profileContent').innerHTML = '<p style="color:var(--red)">Neural link severed. Try again.</p>'; });
}

function closeUserProfile() { 
    const panel = document.getElementById('userProfilePanel');
    panel.style.transform = 'translateX(100%)';
    setTimeout(() => panel.style.display = 'none', 400);
}

async function userAction(action, userId) {
    if (action === 'delete' && !confirm('Are you sure you want to PURGE this entity? This cannot be undone.')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action, user_id: userId, csrf_token: getCsrf() });
    toast(r.message || (r.success ? 'Protocol executed' : 'Execution failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function generateResetLink(userId) {
    const r = await apiCall('admin/api/generate_reset_link.php', { user_id: userId, csrf_token: getCsrf() });
    if (r.success && r.url) { 
        openModal('<div class="card-title" style="margin-bottom:16px">Pass-Reset Link Generated</div><p class="text-muted" style="font-size:13px;margin-bottom:20px">Transmission ready. Link expires in 60 minutes.</p><input class="form-input" value="' + esc(r.url) + '" onclick="this.select()" readonly style="font-family:var(--font-mono);font-size:12px"><div class="modal-actions" style="margin-top:24px"><button class="btn btn-primary" onclick="closeModal()" style="border-radius:10px">Close Signal</button></div>'); 
    }
    else toast('Failed to generate link', 'error');
}

async function impersonateUser(userId) {
    if (!confirm('Initiate Administrative Override? You will control this entity for 15 minutes.')) return;
    const r = await apiCall('admin/api/impersonate.php', { action: 'start', user_id: userId, csrf_token: getCsrf() });
    if (r.success) window.location.href = 'main.php';
    else toast(r.message || 'Override failed', 'error');
}

async function addNote(userId) {
    const note = document.getElementById('newNoteInput').value.trim();
    if (!note) return;
    const r = await apiCall('admin/api/user_notes.php', { user_id: userId, note, csrf_token: getCsrf() });
    if (r.success) { toast('Journal entry appended'); openUserProfile(userId); }
    else toast('Failed to append entry', 'error');
}
</script>
