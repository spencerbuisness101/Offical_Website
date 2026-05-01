<?php
/**
 * Modular Admin Router v2.0
 * Routes to appropriate view based on tab parameter
 */

require_once __DIR__ . '/includes/init_admin.php';

// Map tabs to view files
$tabViews = [
    'dashboard' => 'views/dashboard.php',
    'users' => 'views/users/list.php',
    'sessions' => 'views/system/sessions.php',
    'tracking' => 'views/system/tracking.php',
    'contributor-ideas' => 'views/content/ideas.php',
    'designer-backgrounds' => 'views/content/backgrounds.php',
    'player-adjustments' => 'views/users/adjustments.php',
    'ai-chats' => 'views/system/ai-chats.php',
    'admin-messages' => 'views/system/messages.php',
    'access-restrictions' => 'views/system/access-restrictions.php',
    'logs' => 'views/system/logs.php',
    'announcements' => 'views/content/announcements.php',
    'performance' => 'views/system/performance.php',
    'system-health' => 'views/system/health.php',
    'payment-management' => 'views/payments/overview.php',
];

// Get view file
$viewFile = $tabViews[$currentTab] ?? 'views/dashboard.php';

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <title>Admin Dashboard - Spencer's Website</title>
    <link rel="stylesheet" href="../css/tokens.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
</head>
<body class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar__header">
            <div class="admin-sidebar__logo">SPENCER</div>
            <div class="admin-sidebar__subtitle">Admin Control Panel</div>
        </div>
        
        <nav class="admin-sidebar__nav">
            <!-- Main Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">Overview</span>
                <a href="?tab=dashboard" class="admin-sidebar__link <?php echo $currentTab === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <!-- Users Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">Users</span>
                <a href="?tab=users" class="admin-sidebar__link <?php echo $currentTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
                <a href="?tab=sessions" class="admin-sidebar__link <?php echo $currentTab === 'sessions' ? 'active' : ''; ?>">
                    <i class="fas fa-user-clock"></i>
                    <span>Session Control</span>
                    <?php if ($activeSessions > 0): ?>
                        <span class="admin-sidebar__badge"><?php echo $activeSessions; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=player-adjustments" class="admin-sidebar__link <?php echo $currentTab === 'player-adjustments' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Player Adjustments</span>
                    <?php if ($pendingPfps > 0): ?>
                        <span class="admin-sidebar__badge"><?php echo $pendingPfps; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Content Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">Content</span>
                <a href="?tab=contributor-ideas" class="admin-sidebar__link <?php echo $currentTab === 'contributor-ideas' ? 'active' : ''; ?>">
                    <i class="fas fa-lightbulb"></i>
                    <span>Contributor Ideas</span>
                    <?php if ($pendingIdeas > 0): ?>
                        <span class="admin-sidebar__badge"><?php echo $pendingIdeas; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=designer-backgrounds" class="admin-sidebar__link <?php echo $currentTab === 'designer-backgrounds' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i>
                    <span>Designer Backgrounds</span>
                    <?php if ($pendingBackgrounds > 0): ?>
                        <span class="admin-sidebar__badge admin-sidebar__badge--creative"><?php echo $pendingBackgrounds; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=announcements" class="admin-sidebar__link <?php echo $currentTab === 'announcements' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </div>
            
            <!-- System Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">System</span>
                <a href="?tab=tracking" class="admin-sidebar__link <?php echo $currentTab === 'tracking' ? 'active' : ''; ?>">
                    <i class="fas fa-fingerprint"></i>
                    <span>Tracking</span>
                </a>
                <a href="?tab=ai-chats" class="admin-sidebar__link <?php echo $currentTab === 'ai-chats' ? 'active' : ''; ?>">
                    <i class="fas fa-robot"></i>
                    <span>AI Chats</span>
                </a>
                <a href="?tab=admin-messages" class="admin-sidebar__link <?php echo $currentTab === 'admin-messages' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Messages</span>
                </a>
                <a href="?tab=access-restrictions" class="admin-sidebar__link <?php echo $currentTab === 'access-restrictions' ? 'active' : ''; ?>">
                    <i class="fas fa-ban"></i>
                    <span>Access Restrictions</span>
                </a>
                <a href="?tab=logs" class="admin-sidebar__link <?php echo $currentTab === 'logs' ? 'active' : ''; ?>">
                    <i class="fas fa-terminal"></i>
                    <span>System Logs</span>
                </a>
            </div>
            
            <!-- Monitoring Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">Monitoring</span>
                <a href="?tab=performance" class="admin-sidebar__link <?php echo $currentTab === 'performance' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Performance</span>
                </a>
                <a href="?tab=system-health" class="admin-sidebar__link <?php echo $currentTab === 'system-health' ? 'active' : ''; ?>">
                    <i class="fas fa-heartbeat"></i>
                    <span>System Health</span>
                </a>
            </div>
            
            <!-- Payments Section -->
            <div class="admin-sidebar__nav-section">
                <span class="admin-sidebar__nav-title">Payments</span>
                <a href="?tab=payment-management" class="admin-sidebar__link <?php echo $currentTab === 'payment-management' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </div>
        </nav>
        
        <div class="admin-sidebar__footer">
            <a href="/main.php" class="btn btn--outline" style="width: 100%; justify-content: center;">
                <i class="fas fa-arrow-left"></i>
                Back to Site
            </a>
        </div>
    </aside>
    
    <!-- Main Content Area -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <h1 class="admin-header__title"><?php echo ucfirst(str_replace('-', ' ', $currentTab)); ?></h1>
            <div class="admin-header__actions">
                <div class="admin-header__user">
                    <span><?php echo htmlspecialchars($currentAdmin['username']); ?></span>
                    <div class="admin-header__avatar">
                        <?php echo strtoupper(substr($currentAdmin['username'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="notification <?php echo $_SESSION['flash_type'] ?? 'info'; ?>" style="margin: var(--space-4) var(--space-6) 0;">
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Dynamic Content -->
        <div class="admin-content">
            <?php
            // Include the appropriate view
            $fullViewPath = __DIR__ . '/' . $viewFile;
            if (file_exists($fullViewPath)) {
                include $fullViewPath;
            } else {
                echo '<div class="content-section"><div class="content-section__body"><p>View not found: ' . htmlspecialchars($viewFile) . '</p></div></div>';
            }
            ?>
        </div>
    </main>
    
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            
            // Add mobile menu button if on small screen
            if (window.innerWidth <= 1024) {
                const toggleBtn = document.createElement('button');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                toggleBtn.className = 'btn btn--ghost';
                toggleBtn.style.cssText = 'position: fixed; top: 10px; left: 10px; z-index: 9999;';
                toggleBtn.onclick = () => sidebar.classList.toggle('open');
                document.body.appendChild(toggleBtn);
            }
        });
    </script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
