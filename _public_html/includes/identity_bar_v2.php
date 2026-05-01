<?php
/**
 * Identity Bar v3.0 - Spencer's Website
 * Minimal glass navigation. CSS/JS loaded from external files.
 * Include: require_once __DIR__ . '/includes/identity_bar_v2.php';
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403); die('Direct access forbidden');
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) return;

$userId = $_SESSION['user_id'] ?? 0;
$displayName = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
$nickname = htmlspecialchars($_SESSION['nickname'] ?? '', ENT_QUOTES, 'UTF-8');
$displayName = $nickname ?: $displayName;
$role = $_SESSION['role'] ?? 'community';
$initial = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
$isAdmin = ($role === 'admin');
$roleColors = ['admin'=>'#ef4444','contributor'=>'#f59e0b','designer'=>'#ec4899','user'=>'#3b82f6','community'=>'#64748b'];
$roleColor = $roleColors[$role] ?? '#64748b';

try {
    require_once __DIR__ . '/db.php';
    $db = db();
    try {
        $stmt = $db->prepare("SELECT profile_picture_url, pfp_status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $profilePic = ($userData && !empty($userData['profile_picture_url']) && ($userData['pfp_status'] ?? '') === 'approved') ? $userData['profile_picture_url'] : '';
    } catch (Throwable $e) { $profilePic = ''; }

    $unreadSmails = 0;
    if ($role !== 'community') {
        try {
            require_once __DIR__ . '/smail_helpers.php';
            $unreadSmails = getUnreadSmailCount($db, $userId, 30);
        } catch (Throwable $e) { $unreadSmails = 0; }
    }

    $unreadNotifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (Throwable $e) { $unreadNotifications = 0; }

    $backgrounds = []; $activeBackground = null;
    try {
        $stmt = $db->prepare("
            SELECT b.id, b.name, b.thumbnail_url, b.category, b.image_url, ub.is_active
            FROM backgrounds b
            LEFT JOIN user_backgrounds ub ON b.id = ub.background_id AND ub.user_id = ?
            WHERE b.status = 'approved' OR (b.designer_id = ? AND b.status IN ('approved','pending'))
            ORDER BY ub.is_active DESC, b.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($backgrounds as $bg) {
            if ($bg['is_active']) { $activeBackground = $bg; break; }
        }
    } catch (Throwable $e) { $backgrounds = []; }

    $activeStrikes = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_strikes WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$userId]);
        $activeStrikes = (int)$stmt->fetchColumn();
    } catch (Throwable $e) { $activeStrikes = 0; }
} catch (Throwable $e) {
    $profilePic = ''; $unreadSmails = 0; $unreadNotifications = 0;
    $backgrounds = []; $activeStrikes = 0;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Load external CSS/JS once
if (!defined('IB_ASSETS_LOADED')) {
    define('IB_ASSETS_LOADED', true);
    echo '<link rel="stylesheet" href="css/identity-bar.css?v=7.0">';
}
?>
<!-- Identity Bar v3.0 -->
<nav class="identity-bar" id="identityBar">
    <div class="identity-bar__section identity-bar__left">
        <a href="/main.php" class="identity-bar__brand" title="Dashboard">
            <svg viewBox="0 0 28 28" fill="none"><path d="M14 2L26 8V20L14 26L2 20V8L14 2Z" stroke="#7B6EF6" stroke-width="1" fill="rgba(123,110,246,0.08)"/><path d="M14 8L20 11V17L14 20L8 17V11L14 8Z" fill="#7B6EF6" opacity="0.6"/><circle cx="14" cy="14" r="2" fill="#1DFFC4"/></svg>
            <span>SPENCER</span>
        </a>
        <div class="identity-bar__nav">
            <a href="/main.php" class="identity-bar__nav-item <?php echo $currentPage === 'main' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span class="identity-bar__nav-label">Home</span>
            </a>
            <a href="/game.php" class="identity-bar__nav-item <?php echo $currentPage === 'game' ? 'active' : ''; ?>">
                <i class="fas fa-gamepad"></i>
                <span class="identity-bar__nav-label">Games</span>
            </a>
            <a href="/yaps.php" class="identity-bar__nav-item <?php echo $currentPage === 'yaps' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span class="identity-bar__nav-label">Chat</span>
                <?php if ($unreadSmails > 0): ?><span class="identity-bar__badge"><?php echo min($unreadSmails, 99); ?></span><?php endif; ?>
            </a>
        </div>
    </div>

    <div class="identity-bar__section identity-bar__center"></div>

    <div class="identity-bar__section identity-bar__right">
        <button class="identity-bar__btn identity-bar__background-btn" onclick="openBackgroundSelector()" title="Theme">
            <?php if ($profilePic): ?>
                <img src="<?php echo htmlspecialchars($activeBackground['thumbnail_url'] ?? ''); ?>" alt="" class="identity-bar__bg-thumb">
            <?php else: ?>
                <i class="fas fa-palette"></i>
            <?php endif; ?>
        </button>
        <button class="identity-bar__btn identity-bar__notifications-btn" onclick="toggleNotifications()" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadNotifications > 0): ?><span class="identity-bar__badge identity-bar__badge--pulse"><?php echo min($unreadNotifications, 99); ?></span><?php endif; ?>
            <?php if ($activeStrikes > 0): ?><span class="identity-bar__badge identity-bar__badge--danger"><?php echo $activeStrikes; ?></span><?php endif; ?>
        </button>
        <div style="position:relative;">
            <button class="identity-bar__user-btn" aria-expanded="false">
                <?php if ($profilePic): ?>
                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="" class="identity-bar__avatar">
                <?php else: ?>
                    <div class="identity-bar__avatar identity-bar__avatar--initial" style="background:<?php echo $roleColor; ?>20; color:<?php echo $roleColor; ?>;"><?php echo $initial; ?></div>
                <?php endif; ?>
                <i class="fas fa-chevron-down identity-bar__chevron"></i>
            </button>
            <div class="identity-bar__dropdown" id="userDropdown">
                <div class="identity-bar__dropdown-header">
                    <span class="identity-bar__dropdown-name"><?php echo $displayName; ?></span>
                    <span class="identity-bar__dropdown-role" style="color:<?php echo $roleColor; ?>;"><?php echo ucfirst($role); ?></span>
                </div>
                <div class="identity-bar__dropdown-body">
                    <a href="/userprofile.php?id=<?php echo $userId; ?>" class="identity-bar__dropdown-link"><i class="fas fa-user"></i><span>My Profile</span></a>
                    <a href="/user_panel.php" class="identity-bar__dropdown-link"><i class="fas fa-cog"></i><span>Account Settings</span></a>
                    <a href="/set.php" class="identity-bar__dropdown-link"><i class="fas fa-sliders-h"></i><span>Preferences</span></a>
                    <?php if ($isAdmin): ?>
                    <div class="identity-bar__dropdown-divider"></div>
                    <a href="/admin/index.php" class="identity-bar__dropdown-link identity-bar__dropdown-link--admin"><i class="fas fa-shield-alt"></i><span>Admin Panel</span></a>
                    <?php endif; ?>
                    <div class="identity-bar__dropdown-divider"></div>
                    <?php if ($activeStrikes > 0): ?>
                    <a href="/community-standards.php" class="identity-bar__dropdown-link identity-bar__dropdown-link--warning"><i class="fas fa-exclamation-triangle"></i><span><?php echo $activeStrikes; ?> Active Strike(s)</span></a>
                    <?php endif; ?>
                    <a href="/auth/logout.php" class="identity-bar__dropdown-link identity-bar__dropdown-link--logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Background Selector Modal -->
<div class="bg-selector" id="bgSelector">
    <div class="bg-selector__backdrop" onclick="closeBackgroundSelector()"></div>
    <div class="bg-selector__modal">
        <div class="bg-selector__header">
            <h3 class="bg-selector__title"><i class="fas fa-palette"></i>Choose Your Background</h3>
            <button class="bg-selector__close" onclick="closeBackgroundSelector()"><i class="fas fa-times"></i></button>
        </div>
        <div class="bg-selector__filters">
            <button class="bg-selector__filter active" data-filter="all">All</button>
            <button class="bg-selector__filter" data-filter="nature">Nature</button>
            <button class="bg-selector__filter" data-filter="abstract">Abstract</button>
            <button class="bg-selector__filter" data-filter="gaming">Gaming</button>
            <button class="bg-selector__filter" data-filter="minimal">Minimal</button>
            <button class="bg-selector__filter" data-filter="dark">Dark</button>
        </div>
        <div class="bg-selector__grid" id="bgGrid">
            <?php foreach ($backgrounds as $bg): ?>
            <div class="bg-card <?php echo $bg['is_active'] ? 'bg-card--active' : ''; ?>" data-category="<?php echo htmlspecialchars($bg['category'] ?? 'all', ENT_QUOTES, 'UTF-8'); ?>" data-id="<?php echo $bg['id']; ?>">
                <div class="bg-card__image">
                    <img src="<?php echo htmlspecialchars($bg['thumbnail_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($bg['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    <?php if ($bg['is_active']): ?><div class="bg-card__badge">Active</div><?php endif; ?>
                </div>
                <div class="bg-card__info">
                    <span class="bg-card__name"><?php echo htmlspecialchars($bg['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <button class="bg-card__btn" onclick="selectBackground(<?php echo $bg['id']; ?>)" <?php echo $bg['is_active'] ? 'disabled' : ''; ?>>
                        <?php echo $bg['is_active'] ? '<i class="fas fa-check"></i> Active' : '<i class="fas fa-check"></i> Select'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($backgrounds)): ?>
            <div class="bg-selector__empty">
                <i class="fas fa-images"></i>
                <p>No backgrounds available</p>
                <a href="/set.php" class="btn btn--primary btn--sm">Browse Backgrounds</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notifications Panel -->
<div class="notifications-panel" id="notificationsPanel">
    <div class="notifications-panel__backdrop" onclick="toggleNotifications()"></div>
    <div class="notifications-panel__content">
        <div class="notifications-panel__header">
            <h3><i class="fas fa-bell"></i> Notifications</h3>
            <button onclick="markAllRead()" class="btn" style="background:transparent;border:0.5px solid rgba(255,255,255,0.1);padding:6px 12px;font-size:12px;border-radius:8px;color:var(--text-muted);cursor:pointer;font-family:inherit;">Mark all read</button>
        </div>
        <div class="notifications-panel__list" id="notificationsList">
            <div class="notifications-panel__empty">
                <i class="fas fa-bell-slash"></i>
                <p>No new notifications</p>
            </div>
        </div>
    </div>
</div>

<?php if (!defined('IB_JS_LOADED')): define('IB_JS_LOADED', true); ?>
<script src="js/identity-bar.js?v=7.0" defer></script>
<?php endif; ?>
