<?php
/**
 * Spencer's Website — Admin Panel v7.0
 * Dark futuristic design / Split-include architecture
 */
require_once __DIR__ . '/includes/init.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Admin audit log helper
// Table schema is maintained by migrations/012_consolidate_admin_audit_log.sql
function logAdminAction($db, $action, $targetId = null, $details = '') {
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $_SESSION['username'] ?? 'unknown',
            $action,
            $targetId ? (int)$targetId : null,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Admin audit log error: " . $e->getMessage());
    }
}

// Security headers — centralized via security.php setSecurityHeaders()
require_once __DIR__ . '/includes/security.php';
setSecurityHeaders();
header("Cache-Control: no-cache, no-store, must-revalidate");

// Auth check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: main.php');
    exit;
}

// DB connection (singleton)
$db = null;
try {
    require_once __DIR__ . '/includes/db.php';
    $db = db();
} catch (Exception $e) {
    error_log("Admin DB error: " . $e->getMessage());
    $db = null;
}

// Helper: get site setting
function getSetting($db, $key, $default = '') {
    if (!$db) return $default;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['setting_value'] : $default;
    } catch (Exception $e) { return $default; }
}

function setSetting($db, $key, $value) {
    if (!$db) return false;
    try {
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) { return false; }
}

// Analytics table helper
// page_views schema is maintained by migrations/014_create_page_views_table.sql
function createAnalyticsTables($db) {
    if (!$db) return;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS performance_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_url VARCHAR(500),
            load_time INT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20),
            message TEXT,
            user_id INT NULL,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log("Analytics tables error: " . $e->getMessage());
    }
}

if ($db) createAnalyticsTables($db);

// Tab routing
$allowedTabs = [
    'dashboard', 'users', 'analytics', 'content', 'ai-chats', 'messages',
    'live-chat', 'threats', 'access', 'sessions', 'tracking',
    'payments', 'operations', 'announcements', 'policies',
    'performance', 'audit', 'logs'
];
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, $allowedTabs)) $activeTab = 'dashboard';

// Impersonation check
$impersonating = !empty($_SESSION['impersonating']);
$impersonatorId = $_SESSION['impersonator_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/command-palette.css">
    <link rel="stylesheet" href="css/toast.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <script src="js/toast.js" defer></script>
    <script src="js/command-palette.js" defer></script>
    <style>
        :root {
            --sidebar-w: 240px;
            --sidebar-collapsed: 64px;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scrollbar-width: thin; scrollbar-color: rgba(123,110,246,0.3) transparent; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); line-height: 1.6; overflow: hidden; height: 100vh; }

        /* Layout */
        .app { display: flex; height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-w);
            min-width: var(--sidebar-w);
            background: rgba(4,4,10,0.95);
            backdrop-filter: var(--glass-blur);
            border-right: var(--glass-border);
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease, min-width 0.3s ease;
            overflow: hidden;
            z-index: 100;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); min-width: var(--sidebar-collapsed); }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 20px 16px; border-bottom: var(--glass-border);
            text-decoration: none; color: var(--text);
        }
        .sidebar-brand svg { width: 28px; height: 28px; flex-shrink: 0; }
        .sidebar-brand span { font-size: 14px; font-weight: 300; letter-spacing: 0.08em; white-space: nowrap; }
        .sidebar.collapsed .sidebar-brand span { display: none; }

        .sidebar-toggle {
            background: none; border: none; color: var(--text-dim); cursor: pointer;
            padding: 12px; display: flex; align-items: center; justify-content: center;
            border-bottom: var(--glass-border); transition: color 0.2s;
        }
        .sidebar-toggle:hover { color: var(--accent); }
        .sidebar-toggle svg { width: 18px; height: 18px; }

        .sidebar-nav { flex: 1; overflow-y: auto; padding: 8px 0; }
        .nav-group-label {
            font-size: 10px; font-weight: 600; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 0.12em;
            padding: 16px 16px 6px; white-space: nowrap;
        }
        .sidebar.collapsed .nav-group-label { display: none; }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px; margin: 1px 8px; border-radius: var(--radius-xs);
            color: var(--text-muted); text-decoration: none; font-size: 13px;
            transition: all 0.2s ease; cursor: pointer; white-space: nowrap;
        }
        .nav-item i { width: 18px; text-align: center; font-size: 14px; flex-shrink: 0; }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.04); }
        .nav-item.active { color: var(--accent); background: rgba(123,110,246,0.08); }
        .nav-badge {
            margin-left: auto; background: rgba(123,110,246,0.2); color: var(--accent);
            font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 100px;
        }
        .nav-badge.warn { background: rgba(245,158,11,0.2); color: var(--amber); }
        .nav-badge.danger { background: rgba(248,113,113,0.2); color: var(--red); }
        .sidebar.collapsed .nav-item span, .sidebar.collapsed .nav-badge { display: none; }

        /* Main content */
        .main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .topbar {
            height: 56px; display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: var(--glass-border);
            background: rgba(4,4,10,0.8); backdrop-filter: blur(12px);
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-title { font-size: 16px; font-weight: 400; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .topbar-user { font-size: 12px; color: var(--text-muted); }

        .content { flex: 1; padding: 24px; overflow-y: auto; }

        /* Impersonation banner */
        .impersonate-banner {
            background: rgba(245,158,11,0.1); border-bottom: 0.5px solid rgba(245,158,11,0.3);
            padding: 8px 24px; font-size: 13px; color: var(--amber);
            display: flex; align-items: center; gap: 12px;
        }
        .impersonate-banner a { color: var(--amber); font-weight: 600; text-decoration: underline; }

        /* Cards */
        .card {
            background: var(--glass-bg); border: var(--glass-border);
            backdrop-filter: var(--glass-blur); border-radius: var(--radius);
            padding: 24px; margin-bottom: 20px;
        }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .card-title { font-size: 15px; font-weight: 500; }

        /* Stat grid */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box {
            background: var(--glass-bg); border: var(--glass-border);
            backdrop-filter: var(--glass-blur); border-radius: var(--radius-sm);
            padding: 20px; text-align: center;
        }
        .stat-value { font-size: 28px; font-weight: 200; color: var(--text); }
        .stat-value.accent { color: var(--accent); }
        .stat-value.teal { color: var(--teal); }
        .stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 10px 14px; text-align: left; border-bottom: 0.5px solid rgba(255,255,255,0.06); font-size: 13px; }
        .data-table th { color: var(--text-muted); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .data-table tr:hover { background: rgba(123,110,246,0.03); }
        .data-table td { color: var(--text); }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: var(--radius-xs); font-size: 13px;
            font-weight: 500; cursor: pointer; transition: all 0.2s ease;
            border: none; font-family: var(--font);
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #6B5EE6; box-shadow: 0 0 20px var(--accent-glow); }
        .btn-ghost { background: transparent; border: 0.5px solid rgba(255,255,255,0.15); color: var(--text); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-danger { background: rgba(248,113,113,0.1); border: 0.5px solid rgba(248,113,113,0.3); color: var(--red); }
        .btn-danger:hover { background: rgba(248,113,113,0.2); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .btn-teal { background: rgba(29,255,196,0.1); border: 0.5px solid rgba(29,255,196,0.3); color: var(--teal); }

        /* Inputs */
        .form-input {
            width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03);
            border: 0.5px solid rgba(255,255,255,0.10); border-radius: var(--radius-xs);
            color: var(--text); font-size: 14px; font-family: var(--font);
            transition: border-color 0.2s;
        }
        .form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(123,110,246,0.1); }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-group { margin-bottom: 16px; }

        /* Toggle switch */
        .toggle { position: relative; width: 44px; height: 24px; cursor: pointer; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider {
            position: absolute; inset: 0; background: rgba(255,255,255,0.1);
            border-radius: 24px; transition: background 0.3s;
        }
        .toggle .slider::before {
            content: ''; position: absolute; width: 18px; height: 18px;
            left: 3px; bottom: 3px; background: var(--text-muted);
            border-radius: 50%; transition: all 0.3s;
        }
        .toggle input:checked + .slider { background: rgba(123,110,246,0.3); }
        .toggle input:checked + .slider::before { transform: translateX(20px); background: var(--accent); }

        /* Tags */
        .tag {
            display: inline-block; padding: 3px 10px; border-radius: 100px;
            font-size: 11px; font-weight: 600;
        }
        .tag-violet { background: rgba(123,110,246,0.15); color: var(--accent); }
        .tag-teal { background: rgba(29,255,196,0.15); color: var(--teal); }
        .tag-red { background: rgba(248,113,113,0.15); color: var(--red); }
        .tag-amber { background: rgba(245,158,11,0.15); color: var(--amber); }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: rgba(10,10,20,0.95); border: var(--glass-border);
            backdrop-filter: var(--glass-blur); border-radius: var(--radius);
            padding: 32px; width: 100%; max-width: 560px; max-height: 80vh; overflow-y: auto;
        }
        .modal-title { font-size: 18px; font-weight: 500; margin-bottom: 16px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }

        /* Toast */
        .toast-container { position: fixed; top: 16px; right: 16px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
        .toast {
            background: rgba(10,10,20,0.95); border: var(--glass-border);
            backdrop-filter: var(--glass-blur); border-radius: var(--radius-xs);
            padding: 12px 20px; font-size: 13px; color: var(--text);
            animation: toast-in 0.3s ease; min-width: 200px;
        }
        .toast.success { border-left: 3px solid var(--teal); }
        .toast.error { border-left: 3px solid var(--red); }
        @keyframes toast-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -240px; top: 0; bottom: 0; z-index: 200; }
            .sidebar.mobile-open { left: 0; }
            .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 199; }
            .mobile-overlay.open { display: block; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(123,110,246,0.2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(123,110,246,0.4); }

        /* Mobile hamburger - hidden on desktop */
        .mobile-hamburger { display: none; background: none; border: none; color: var(--text); font-size: 20px; cursor: pointer; padding: 8px; }
        @media (max-width: 768px) { .mobile-hamburger { display: inline-flex; align-items: center; } }

        /* Skip link for accessibility */
        .skip-link { position: absolute; top: -40px; left: 0; background: var(--accent); color: #fff; padding: 8px 16px; z-index: 99999; text-decoration: none; border-radius: 0 0 8px 0; }
        .skip-link:focus { top: 0; }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body data-user-role='<?php echo htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES); ?>'
      data-username='<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES); ?>'
      data-user-id='<?php echo htmlspecialchars($_SESSION['user_id'] ?? 0, ENT_QUOTES); ?>'>
<a href="#viewContent" class="skip-link">Skip to main content</a>
<div class="app">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="main.php" class="sidebar-brand">
            <svg viewBox="0 0 28 28" fill="none"><path d="M14 2L26 8V20L14 26L2 20V8L14 2Z" stroke="#7B6EF6" stroke-width="1" fill="rgba(123,110,246,0.08)"/><path d="M14 8L20 11V17L14 20L8 17V11L14 8Z" fill="#7B6EF6" opacity="0.6"/><circle cx="14" cy="14" r="2" fill="#1DFFC4"/></svg>
            <span>ADMIN PANEL</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <nav class="sidebar-nav">
            <div class="nav-group-label">Overview</div>
            <a class="nav-item <?= $activeTab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="fas fa-chart-line"></i><span>Dashboard</span></a>

            <div class="nav-group-label">Users & Content</div>
            <a class="nav-item <?= $activeTab==='users'?'active':'' ?>" href="?tab=users"><i class="fas fa-users"></i><span>Users</span></a>
            <a class="nav-item <?= $activeTab==='analytics'?'active':'' ?>" href="?tab=analytics"><i class="fas fa-search"></i><span>Analytics</span></a>
            <a class="nav-item <?= $activeTab==='content'?'active':'' ?>" href="?tab=content"><i class="fas fa-lightbulb"></i><span>Content Review</span></a>
            <a class="nav-item <?= $activeTab==='ai-chats'?'active':'' ?>" href="?tab=ai-chats"><i class="fas fa-robot"></i><span>AI Chats</span></a>
            <a class="nav-item <?= $activeTab==='messages'?'active':'' ?>" href="?tab=messages"><i class="fas fa-envelope"></i><span>Messages</span></a>
            <a class="nav-item <?= $activeTab==='live-chat'?'active':'' ?>" href="?tab=live-chat"><i class="fas fa-comments"></i><span>Live Chat</span></a>

            <div class="nav-group-label">Security</div>
            <a class="nav-item <?= $activeTab==='threats'?'active':'' ?>" href="?tab=threats"><i class="fas fa-shield-alt"></i><span>Threat Monitor</span></a>
            <a class="nav-item <?= $activeTab==='access'?'active':'' ?>" href="?tab=access"><i class="fas fa-ban"></i><span>Access Restrictions</span></a>
            <a class="nav-item <?= $activeTab==='sessions'?'active':'' ?>" href="?tab=sessions"><i class="fas fa-user-clock"></i><span>Sessions</span></a>
            <a class="nav-item <?= $activeTab==='tracking'?'active':'' ?>" href="?tab=tracking"><i class="fas fa-fingerprint"></i><span>Tracking</span></a>

            <div class="nav-group-label">Operations</div>
            <a class="nav-item <?= $activeTab==='payments'?'active':'' ?>" href="?tab=payments"><i class="fas fa-credit-card"></i><span>Payments</span></a>
            <a class="nav-item <?= $activeTab==='operations'?'active':'' ?>" href="?tab=operations"><i class="fas fa-cog"></i><span>Operations</span></a>
            <a class="nav-item <?= $activeTab==='announcements'?'active':'' ?>" href="?tab=announcements"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
            <a class="nav-item <?= $activeTab==='policies'?'active':'' ?>" href="?tab=policies"><i class="fas fa-file-alt"></i><span>Policies</span></a>

            <div class="nav-group-label">System</div>
            <a class="nav-item <?= $activeTab==='performance'?'active':'' ?>" href="?tab=performance"><i class="fas fa-tachometer-alt"></i><span>Performance</span></a>
            <a class="nav-item <?= $activeTab==='audit'?'active':'' ?>" href="?tab=audit"><i class="fas fa-clipboard-list"></i><span>Audit Log</span></a>
            <a class="nav-item <?= $activeTab==='logs'?'active':'' ?>" href="?tab=logs"><i class="fas fa-terminal"></i><span>System Logs</span></a>
        </nav>
    </aside>

    <!-- Main -->
    <div class="main">
        <?php if ($impersonating): ?>
        <div class="impersonate-banner">
            <i class="fas fa-user-secret"></i>
            You are impersonating another user.
            <form method="POST" action="admin/api/impersonate.php" style="display:inline">
                <input type="hidden" name="action" value="stop">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit" style="background:none;border:none;color:var(--teal);cursor:pointer;text-decoration:underline;font:inherit">Return to Admin</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="mobile-hamburger" onclick="openMobileSidebar()" aria-label="Open navigation menu"><i class="fas fa-bars"></i></button>
                <div class="topbar-title"><?= ucfirst(str_replace('-', ' ', $activeTab)) ?></div>
            </div>
            <div class="topbar-actions">
                <span class="topbar-user"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                <a href="main.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back to Site</a>
            </div>
        </div>

        <div class="content" id="viewContent">
            <?php
            $viewFile = __DIR__ . '/admin/views/' . $activeTab . '.php';
            if (file_exists($viewFile)) {
                include $viewFile;
            } else {
                echo '<div class="card"><p style="color:var(--text-muted)">This section is being built. Check back soon.</p></div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
    <div class="modal" id="modalContent"></div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Mobile overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>

<script>
// Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
function openMobileSidebar() {
    document.getElementById('sidebar').classList.add('mobile-open');
    document.getElementById('mobileOverlay').classList.add('open');
}
function closeMobileSidebar() {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('mobileOverlay').classList.remove('open');
}

// Modal
function openModal(html) {
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// Toast
function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

// Generic API helper
async function apiCall(url, data = {}) {
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(data).toString()
        });
        return await resp.json();
    } catch (e) {
        toast('Network error: ' + e.message, 'error');
        return {success: false};
    }
}

// CSRF token getter
function getCsrf() { return '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'; }

// XSS escape helper
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
