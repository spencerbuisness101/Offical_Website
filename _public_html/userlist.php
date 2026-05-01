<?php
/**
 * User Directory - Spencer's Website v7.0
 * Shows all registered users (excludes community role).
 * Community role users cannot access this page.
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
$isAdmin = ($role === 'admin');
$csrfToken = function_exists('generateCsrfToken') ? generateCsrfToken() : '';

// Community users cannot access this page
if ($role === 'community') {
    header('Location: main.php');
    exit;
}

$users = [];
$dbError = false;
$roleFilter = $_GET['role'] ?? 'all';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Detect which optional v7.0 columns exist on the live DB
    $hasIsActive = false;
    $optionalCols = [];
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        $hasIsActive = ($colCheck->rowCount() > 0);
        foreach (['nickname', 'profile_picture_url', 'pfp_status'] as $col) {
            $chk = $db->query("SHOW COLUMNS FROM users LIKE '{$col}'");
            if ($chk->rowCount() > 0) $optionalCols[] = $col;
        }
    } catch (Exception $e) { /* column check failed, proceed without */ }

    $selectCols = "id, username, role, created_at";
    if (!empty($optionalCols)) $selectCols .= ", " . implode(", ", $optionalCols);
    $sql = "SELECT {$selectCols}
            FROM users
            WHERE 1=1";
    if (!$isAdmin) {
        $sql .= " AND role != 'community'";
    }
    if ($hasIsActive) {
        $sql .= " AND is_active = 1";
    }
    $params = [];

    if ($roleFilter !== 'all' && in_array($roleFilter, ['user', 'contributor', 'designer', 'admin'])) {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }

    $sql .= " ORDER BY FIELD(role, 'admin', 'contributor', 'designer', 'user'), created_at ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Get online status from user_sessions (active within 5 minutes = online)
    $onlineUsers = [];
    try {
        $onlineStmt = $db->query("SELECT DISTINCT user_id FROM user_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $onlineUsers = $onlineStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { /* table may not exist */ }

} catch (Exception $e) {
    error_log("User directory error: " . $e->getMessage());
    $dbError = true;
}

$roleColors = [
    'admin' => '#ef4444',
    'contributor' => '#f59e0b',
    'designer' => '#ec4899',
    'user' => '#3b82f6',
];
$roleIcons = [
    'admin' => 'fa-crown',
    'contributor' => 'fa-lightbulb',
    'designer' => 'fa-palette',
    'user' => 'fa-user',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Directory - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        *{box-sizing:border-box;}
        .ud-wrap{max-width:1060px;margin:0 auto;padding:24px 20px 60px;}
        .ud-back{display:inline-flex;align-items:center;gap:6px;color:#64748b;text-decoration:none;font-size:0.85rem;margin-bottom:18px;transition:color .2s;}
        .ud-back:hover{color:#4ECDC4;}

        .ud-hero{text-align:center;margin-bottom:28px;padding:32px 20px;background:rgba(15,23,42,0.6);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.06);border-radius:18px;}
        .ud-hero h1{font-size:2.2rem;font-weight:800;background:linear-gradient(135deg,#4ECDC4,#6366f1,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0 0 6px;}
        .ud-hero p{color:#94a3b8;font-size:0.92rem;margin:0 0 10px;}
        .ud-stats-row{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;}
        .ud-stat{padding:6px 16px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;font-size:0.8rem;color:#94a3b8;}
        .ud-stat b{color:#e2e8f0;margin-right:4px;}

        .ud-toolbar{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;}
        .ud-search{flex:1;min-width:180px;padding:10px 14px 10px 36px;background:rgba(15,23,42,0.7);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#e2e8f0;font-size:0.88rem;outline:none;transition:border-color .2s;}
        .ud-search:focus{border-color:#4ECDC4;}
        .ud-search-wrap{position:relative;flex:1;min-width:180px;}
        .ud-search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#475569;font-size:0.82rem;}
        .ud-filters{display:flex;gap:5px;flex-wrap:wrap;}
        .ud-pill{padding:7px 14px;border-radius:20px;font-size:0.78rem;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#94a3b8;transition:all .2s;cursor:pointer;}
        .ud-pill:hover{border-color:rgba(78,205,196,0.3);color:#cbd5e1;}
        .ud-pill.active{background:rgba(78,205,196,0.12);border-color:#4ECDC4;color:#4ECDC4;}

        .ud-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}

        .ud-card{position:relative;background:rgba(15,23,42,0.6);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:20px;display:flex;align-items:center;gap:14px;transition:all .25s;text-decoration:none;overflow:hidden;}
        .ud-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 60%,rgba(78,205,196,0.04));opacity:0;transition:opacity .3s;}
        .ud-card:hover{border-color:rgba(78,205,196,0.25);transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.2);}
        .ud-card:hover::before{opacity:1;}

        .ud-avatar-wrap{position:relative;flex-shrink:0;}
        .ud-avatar{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;font-weight:700;background:linear-gradient(135deg,#334155,#1e293b);border:2px solid rgba(255,255,255,0.08);overflow:hidden;}
        .ud-avatar img{width:100%;height:100%;object-fit:cover;}
        .ud-status{position:absolute;bottom:1px;right:1px;width:12px;height:12px;border-radius:50%;border:2px solid #0f172a;}
        .ud-online{background:#22c55e;box-shadow:0 0 6px rgba(34,197,94,0.5);}
        .ud-offline{background:#475569;}

        .ud-info{flex:1;min-width:0;position:relative;z-index:1;}
        .ud-name{font-weight:700;font-size:0.95rem;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .ud-nick{font-size:0.76rem;color:#64748b;font-style:italic;}
        .ud-role-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:6px;font-size:0.67rem;font-weight:700;text-transform:uppercase;margin-top:4px;}
        .ud-joined{font-size:0.7rem;color:#475569;margin-top:3px;}

        .ud-empty{text-align:center;padding:52px 16px;color:#475569;}
        .ud-empty i{font-size:2.8rem;margin-bottom:14px;display:block;opacity:.25;}

        .ud-pagination{display:flex;justify-content:center;gap:6px;margin-top:22px;}
        .ud-page-btn{padding:7px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#94a3b8;font-size:0.82rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .ud-page-btn:hover{border-color:#4ECDC4;color:#4ECDC4;}
        .ud-page-btn.active{background:rgba(78,205,196,0.15);border-color:#4ECDC4;color:#4ECDC4;}
        .ud-page-btn:disabled{opacity:.3;cursor:not-allowed;}

        .ud-notify-btn{position:absolute;top:10px;right:10px;width:30px;height:30px;border-radius:50%;border:none;background:rgba(99,102,241,0.12);color:#818cf8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.75rem;transition:all .2s;z-index:2;}
        .ud-notify-btn:hover{background:rgba(99,102,241,0.3);transform:scale(1.1);}

        .ud-card.ud-hidden{display:none;}
        @media(max-width:640px){.ud-grid{grid-template-columns:1fr;}.ud-toolbar{flex-direction:column;}}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="ud-wrap">
    <a href="main.php" class="ud-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="ud-hero">
        <h1>User Directory</h1>
        <p>Browse the members of Spencer's Website</p>
        <div class="ud-stats-row">
            <div class="ud-stat"><b><?php echo count($users); ?></b> member<?php echo count($users) !== 1 ? 's' : ''; ?></div>
            <div class="ud-stat"><b id="udOnlineCount"><?php echo count(array_filter($users, function($u) use ($onlineUsers) { return in_array($u['id'], $onlineUsers); })); ?></b> online</div>
        </div>
    </div>

    <div class="ud-toolbar">
        <div class="ud-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="ud-search" id="udSearch" placeholder="Search by username or nickname..." oninput="udFilter()">
        </div>
        <div class="ud-filters" id="udRoleFilters">
            <button type="button" class="ud-pill active" data-filter-role="all" onclick="udFilterByRole('all',this)">All</button>
            <button type="button" class="ud-pill" data-filter-role="admin" onclick="udFilterByRole('admin',this)">Admin</button>
            <button type="button" class="ud-pill" data-filter-role="contributor" onclick="udFilterByRole('contributor',this)">Contributor</button>
            <button type="button" class="ud-pill" data-filter-role="designer" onclick="udFilterByRole('designer',this)">Designer</button>
            <button type="button" class="ud-pill" data-filter-role="user" onclick="udFilterByRole('user',this)">User</button>
        </div>
    </div>

    <?php if ($dbError): ?>
    <div class="ud-empty">
        <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
        <p style="color:#ef4444;">Database error. Please try again later.</p>
    </div>
    <?php elseif (empty($users)): ?>
    <div class="ud-empty">
        <i class="fas fa-users"></i>
        <p>No members found<?php echo $roleFilter !== 'all' ? ' with the "' . htmlspecialchars($roleFilter) . '" role' : ''; ?>.</p>
    </div>
    <?php else: ?>
    <div class="ud-grid" id="udGrid">
        <?php foreach ($users as $idx => $u):
            $color = $roleColors[$u['role']] ?? '#64748b';
            $icon = $roleIcons[$u['role']] ?? 'fa-user';
            $hasPfp = !empty($u['profile_picture_url']) && ($u['pfp_status'] ?? '') === 'approved';
            $initial = strtoupper(substr($u['username'], 0, 1));
            $isOnline = in_array($u['id'], $onlineUsers);
        ?>
        <a href="userprofile.php?user=<?php echo urlencode($u['username']); ?>" class="ud-card" data-username="<?php echo htmlspecialchars(strtolower($u['username'])); ?>" data-nick="<?php echo htmlspecialchars(strtolower($u['nickname'] ?? '')); ?>" data-role="<?php echo htmlspecialchars($u['role']); ?>" data-page="<?php echo floor($idx / 20); ?>">
            <div class="ud-avatar-wrap">
                <div class="ud-avatar" style="border-color:<?php echo $color; ?>30;">
                    <?php if ($hasPfp): ?>
                        <img src="<?php echo htmlspecialchars($u['profile_picture_url']); ?>" alt="">
                    <?php else: ?>
                        <?php echo $initial; ?>
                    <?php endif; ?>
                </div>
                <div class="ud-status <?php echo $isOnline ? 'ud-online' : 'ud-offline'; ?>" title="<?php echo $isOnline ? 'Online' : 'Offline'; ?>"></div>
            </div>
            <div class="ud-info">
                <div class="ud-name"><?php echo htmlspecialchars($u['username']); ?></div>
                <?php if (!empty($u['nickname'])): ?>
                <div class="ud-nick">"<?php echo htmlspecialchars($u['nickname']); ?>"</div>
                <?php endif; ?>
                <div class="ud-role-badge" style="background:<?php echo $color; ?>18;color:<?php echo $color; ?>;">
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo ucfirst($u['role']); ?>
                </div>
                <div class="ud-joined">Joined <?php echo date('M Y', strtotime($u['created_at'])); ?></div>
            </div>
            <?php if ($isAdmin): ?>
            <button class="ud-notify-btn" onclick="event.preventDefault();event.stopPropagation();openSmailDispatch('<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',<?php echo (int)$u['id']; ?>)" title="Send Smail"><i class="fas fa-paper-plane"></i></button>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="ud-pagination" id="udPagination"></div>
    <?php endif; ?>
</div>

<script>
// Client-side search + role filtering + pagination
var udPerPage = 20, udCurrentPage = 0, udActiveRole = 'all';

function udFilterByRole(role, btn) {
    udActiveRole = role;
    // Update active pill styling
    document.querySelectorAll('#udRoleFilters .ud-pill').forEach(function(p) { p.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    udFilter();
}

function udFilter() {
    var q = document.getElementById('udSearch').value.toLowerCase();
    var cards = document.querySelectorAll('.ud-card');
    var visible = 0;
    cards.forEach(function(c) {
        var textMatch = !q || c.dataset.username.includes(q) || c.dataset.nick.includes(q);
        var roleMatch = udActiveRole === 'all' || c.dataset.role === udActiveRole;
        var show = textMatch && roleMatch;
        c.classList.toggle('ud-hidden', !show);
        if (show) visible++;
    });
    udBuildPagination(visible);
    udShowPage(0);
}
function udBuildPagination(total) {
    var pages = Math.ceil(total / udPerPage);
    var el = document.getElementById('udPagination');
    if (!el || pages <= 1) { if (el) el.innerHTML = ''; return; }
    var html = '';
    for (var i = 0; i < pages; i++) {
        html += '<button class="ud-page-btn' + (i === 0 ? ' active' : '') + '" onclick="udShowPage(' + i + ')">' + (i + 1) + '</button>';
    }
    el.innerHTML = html;
}
function udShowPage(page) {
    udCurrentPage = page;
    var cards = document.querySelectorAll('.ud-card:not(.ud-hidden)');
    var start = page * udPerPage, end = start + udPerPage;
    var idx = 0;
    document.querySelectorAll('.ud-card').forEach(function(c) {
        if (c.classList.contains('ud-hidden')) return;
        c.style.display = (idx >= start && idx < end) ? '' : 'none';
        idx++;
    });
    document.querySelectorAll('.ud-page-btn').forEach(function(b, i) {
        b.classList.toggle('active', i === page);
    });
}
// Init pagination
document.addEventListener('DOMContentLoaded', function() {
    var total = document.querySelectorAll('.ud-card').length;
    udBuildPagination(total);
    udShowPage(0);
});
</script>

<?php if ($isAdmin): ?>
<!-- Admin Smail Dispatch Modal -->
<div id="smailDispatchModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(99,102,241,0.3);border-radius:14px;padding:28px;max-width:460px;width:100%;color:#e2e8f0;">
        <h3 style="margin:0 0 4px;color:#818cf8;"><i class="fas fa-paper-plane"></i> Send Smail</h3>
        <p style="color:#64748b;font-size:0.8rem;margin-bottom:14px;">To: <strong id="dispatchTo" style="color:#e2e8f0;"></strong></p>
        <input type="hidden" id="dispatchUserId">
        <input type="text" id="dispatchSubject" placeholder="Subject" maxlength="255" style="width:100%;padding:8px 12px;border-radius:8px;background:#16213e;color:#e2e8f0;border:1px solid #2d3a5c;margin-bottom:8px;box-sizing:border-box;">
        <select id="dispatchUrgency" style="width:100%;padding:8px 12px;border-radius:8px;background:#16213e;color:#e2e8f0;border:1px solid #2d3a5c;margin-bottom:8px;box-sizing:border-box;">
            <option value="low">Low Urgency</option>
            <option value="normal" selected>Normal</option>
            <option value="high">High Urgency</option>
            <option value="urgent">Urgent</option>
        </select>
        <textarea id="dispatchBody" placeholder="Message..." maxlength="5000" style="width:100%;height:100px;padding:8px 12px;border-radius:8px;background:#16213e;color:#e2e8f0;border:1px solid #2d3a5c;resize:vertical;margin-bottom:12px;box-sizing:border-box;"></textarea>
        <div style="display:flex;gap:8px;">
            <button onclick="sendSmailDispatch()" id="dispatchSendBtn" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-weight:600;cursor:pointer;">Send</button>
            <button onclick="document.getElementById('smailDispatchModal').style.display='none'" style="flex:1;padding:10px;border:none;border-radius:8px;background:#334155;color:#e2e8f0;cursor:pointer;">Cancel</button>
        </div>
        <div id="dispatchMsg" style="margin-top:8px;font-size:0.83rem;display:none;"></div>
    </div>
</div>
<style>
.ud-notify-btn{position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;border:none;background:rgba(99,102,241,0.15);color:#818cf8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.75rem;transition:all 0.2s;z-index:2;}
.ud-notify-btn:hover{background:rgba(99,102,241,0.3);transform:scale(1.1);}
.ud-card{position:relative;}
</style>
<script>
function openSmailDispatch(username,userId){document.getElementById('dispatchTo').textContent=username;document.getElementById('dispatchUserId').value=userId;document.getElementById('dispatchSubject').value='';document.getElementById('dispatchBody').value='';document.getElementById('dispatchUrgency').value='normal';document.getElementById('dispatchMsg').style.display='none';document.getElementById('smailDispatchModal').style.display='flex';}
function sendSmailDispatch(){var b=document.getElementById('dispatchSendBtn');b.disabled=true;b.textContent='Sending...';var m=document.getElementById('dispatchMsg');m.style.display='none';var body='action=send&csrf_token='+encodeURIComponent('<?php echo $csrfToken; ?>')+'&receiver_username='+encodeURIComponent(document.getElementById('dispatchTo').textContent)+'&title='+encodeURIComponent(document.getElementById('dispatchSubject').value)+'&message_body='+encodeURIComponent(document.getElementById('dispatchBody').value)+'&urgency_level='+document.getElementById('dispatchUrgency').value+'&color_code=%236366f1';fetch('api/smail.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(r=>r.json()).then(d=>{m.style.display='block';m.style.color=d.success?'#22c55e':'#ef4444';m.textContent=d.success?'Message sent!':d.error||'Failed';b.disabled=false;b.textContent='Send';if(d.success)setTimeout(()=>document.getElementById('smailDispatchModal').style.display='none',1500)}).catch(()=>{m.style.display='block';m.style.color='#ef4444';m.textContent='Connection error';b.disabled=false;b.textContent='Send';});}
</script>
<?php endif; ?>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
