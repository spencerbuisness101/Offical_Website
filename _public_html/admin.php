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
    <title>Admin Dashboard — Spencer's Website v7.0</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    
    <!-- Design System -->
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/cinematic-bg.css">
    <link rel="stylesheet" href="css/admin-v7.css">
    <link rel="stylesheet" href="css/command-palette.css">
    <link rel="stylesheet" href="css/toast.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    
    <script src="js/cinematic-bg.js" defer></script>
    <script src="js/toast.js" defer></script>
    <script src="js/command-palette.js" defer></script>
</head>
<body data-cinematic-bg="dim"
      data-user-role='<?php echo htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES); ?>'
      data-username='<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES); ?>'
      data-user-id='<?php echo htmlspecialchars($_SESSION['user_id'] ?? 0, ENT_QUOTES); ?>'>

    <!-- Background Layer handled by cinematic-bg.js -->
    <div class="admin-bg-base" style="display:none"></div>
    <div class="admin-nebula" style="display:none">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
    </div>

    <a href="#viewContent" class="sr-only">Skip to main content</a>

    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <a href="main.php" class="sidebar-brand">
                <svg viewBox="0 0 28 28" fill="none"><path d="M14 2L26 8V20L14 26L2 20V8L14 2Z" stroke="#7B6EF6" stroke-width="1.5" fill="rgba(123,110,246,0.12)"/><path d="M14 8L20 11V17L14 20L8 17V11L14 8Z" fill="#7B6EF6"/><circle cx="14" cy="14" r="2" fill="#1DFFC4"/></svg>
                <span>CORE ACCESS</span>
            </a>
            
            <nav class="sidebar-nav dark-scroll">
                <div class="nav-group-label">Analytics</div>
                <a class="nav-item <?= $activeTab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
                <a class="nav-item <?= $activeTab==='analytics'?'active':'' ?>" href="?tab=analytics"><i class="fas fa-chart-line"></i><span>Traffic Metrics</span></a>

                <div class="nav-group-label">User Management</div>
                <a class="nav-item <?= $activeTab==='users'?'active':'' ?>" href="?tab=users"><i class="fas fa-users"></i><span>User Management</span></a>
                <a class="nav-item <?= $activeTab==='sessions'?'active':'' ?>" href="?tab=sessions"><i class="fas fa-user-clock"></i><span>Live Sessions</span></a>
                
                <div class="nav-group-label">Content</div>
                <a class="nav-item <?= $activeTab==='content'?'active':'' ?>" href="?tab=content"><i class="fas fa-folder-open"></i><span>Content Management</span></a>
                <a class="nav-item <?= $activeTab==='ai-chats'?'active':'' ?>" href="?tab=ai-chats"><i class="fas fa-comments"></i><span>AI Interactions</span></a>
                <a class="nav-item <?= $activeTab==='announcements'?'active':'' ?>" href="?tab=announcements"><i class="fas fa-bullhorn"></i><span>Site Bulletins</span></a>

                <div class="nav-group-label">Security</div>
                <a class="nav-item <?= $activeTab==='threats'?'active':'' ?>" href="?tab=threats"><i class="fas fa-shield-alt"></i><span>Threat Monitor</span></a>
                <a class="nav-item <?= $activeTab==='access'?'active':'' ?>" href="?tab=access"><i class="fas fa-ban"></i><span>Access Restrictions</span></a>
                <a class="nav-item <?= $activeTab==='audit'?'active':'' ?>" href="?tab=audit"><i class="fas fa-list-ul"></i><span>Audit Log</span></a>

                <div class="nav-group-label">System</div>
                <a class="nav-item <?= $activeTab==='performance'?'active':'' ?>" href="?tab=performance"><i class="fas fa-microchip"></i><span>Infrastructure</span></a>
                <a class="nav-item <?= $activeTab==='logs'?'active':'' ?>" href="?tab=logs"><i class="fas fa-terminal"></i><span>System Logs</span></a>
            </nav>

            <button class="nav-item" onclick="toggleSidebar()" style="margin-top:auto;border:none;background:none;width:calc(100% - 16px);cursor:pointer">
                <i class="fas fa-columns"></i><span>Collapse Interface</span>
            </button>
        </aside>

        <!-- Main Workspace -->
        <div class="main-wrapper">
            <?php if ($impersonating): ?>
            <div class="impersonate-banner" style="background:rgba(245,158,11,0.1);border-bottom:1px solid rgba(245,158,11,0.2);padding:10px 24px;color:var(--warning);font-size:13px;display:flex;align-items:center;gap:12px;z-index:95">
                <i class="fas fa-user-secret"></i>
                <span>Impersonation Protocol Active: Administrative override in effect.</span>
                <form method="POST" action="admin/api/impersonate.php" style="margin-left:auto">
                    <input type="hidden" name="action" value="stop">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="badge badge--teal" style="cursor:pointer;border:none">Terminate Override</button>
                </form>
            </div>
            <?php endif; ?>

            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn btn-ghost btn-action" id="mobileMenuBtn" style="display:none"><i class="fas fa-bars"></i></button>
                    <div class="page-title"><?= strtoupper(str_replace('-', ' ', $activeTab)) ?></div>
                </div>
                <div class="topbar-right">
                    <div style="text-align:right;margin-right:12px">
                        <div style="font-size:13px;font-weight:500;color:var(--text)"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
                        <div style="font-size:10px;color:var(--accent);text-transform:uppercase;letter-spacing:0.05em">System Overseer</div>
                    </div>
                    <a href="main.php" class="btn btn-ghost btn-sm" style="border-radius:10px"><i class="fas fa-external-link-alt"></i> Exit to Site</a>
                </div>
            </header>

            <main class="admin-content" id="viewContent">
                <?php
                $viewFile = __DIR__ . '/admin/views/' . $activeTab . '.php';
                if (file_exists($viewFile)) {
                    include $viewFile;
                } else {
                    echo '<div class="admin-card"><p class="text-muted">Section mapping for "'.htmlspecialchars($activeTab).'" is pending implementation.</p></div>';
                }
                ?>
            </main>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal-backdrop" id="modalOverlay" onclick="if(event.target===this)closeModal()">
        <div class="modal-window" id="modalContent"></div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Sidebar logic
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            localStorage.setItem('admin_sidebar_collapsed', document.getElementById('sidebar').classList.contains('collapsed'));
        }
        
        // Restore sidebar state
        if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
            document.getElementById('sidebar').classList.add('collapsed');
        }

        function openModal(html) {
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('modalOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('modalOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }

        function toast(msg, type = 'success') {
            if (window.showToast) { window.showToast(msg, type); return; }
            const el = document.createElement('div');
            el.className = 'toast ' + type;
            el.textContent = msg;
            document.getElementById('toastContainer').appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateX(20px)';
                setTimeout(() => el.remove(), 400);
            }, 4000);
        }

        async function apiCall(url, data = {}) {
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                    body: new URLSearchParams(data).toString()
                });
                return await resp.json();
            } catch (e) {
                toast('Communication error', 'error');
                return {success: false};
            }
        }

        function getCsrf() { return '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'; }
        function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    </script>
</body>
</html>
