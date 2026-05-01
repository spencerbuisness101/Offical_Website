<?php
/**
 * User Profile Page - Spencer's Website v7.0
 * View/edit user profiles. PFP uploads go to 'pending' until admin approves.
 * Community role users cannot access this page.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/display_name.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$currentRole = $_SESSION['role'] ?? 'community';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($currentRole === 'community') {
    header('Location: main.php');
    exit;
}

$csrfToken = generateCsrfToken();
$db = null;
$profileUser = null;
$isOwnProfile = false;
$canEdit = false;
$saveMsg = '';
$saveMsgType = '';

try {
    require_once __DIR__ . '/includes/db.php';
    $db = db();
} catch (Exception $e) {
    error_log("Profile DB error: " . $e->getMessage());
    die('Database error.');
}

// Detect which optional v7.0 columns exist on the live DB
$profileOptCols = [];
try {
    foreach (['nickname', 'description', 'about', 'profile_picture_url', 'pfp_status', 'pfp_decline_reason'] as $col) {
        $chk = $db->query("SHOW COLUMNS FROM users LIKE '{$col}'");
        if ($chk->rowCount() > 0) $profileOptCols[] = $col;
    }
} catch (Exception $e) { /* proceed with base columns */ }

$profileSelect = "id, username, role, created_at";
if (!empty($profileOptCols)) $profileSelect .= ", " . implode(", ", $profileOptCols);

// Determine which user to show
$targetUsername = $_GET['user'] ?? null;
$targetId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($targetUsername) {
    $stmt = $db->prepare("SELECT {$profileSelect} FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$targetUsername]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($targetId) {
    $stmt = $db->prepare("SELECT {$profileSelect} FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetId]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Default to own profile
    $stmt = $db->prepare("SELECT {$profileSelect} FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$currentUserId]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$profileUser) {
    header('Location: userlist.php');
    exit;
}

// Exclude community profiles from viewing
if ($profileUser['role'] === 'community') {
    header('Location: userlist.php');
    exit;
}

$isOwnProfile = ((int)$profileUser['id'] === $currentUserId);
$canEdit = ($isOwnProfile || $currentRole === 'admin');

// Handle profile update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    $editUserId = (int)$profileUser['id'];

    if ($_POST['action'] === 'update_profile') {
        $nickname = trim($_POST['nickname'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $about = trim($_POST['about'] ?? '');

        // Validate
        if (strlen($nickname) > 50) {
            echo json_encode(['success' => false, 'error' => 'Nickname must be 50 characters or less.']);
            exit;
        }
        if (strlen($description) > 500) {
            echo json_encode(['success' => false, 'error' => 'Description must be 500 characters or less.']);
            exit;
        }
        if (strlen($about) > 2000) {
            echo json_encode(['success' => false, 'error' => 'About must be 2000 characters or less.']);
            exit;
        }

        $nickname = htmlspecialchars($nickname, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $about = htmlspecialchars($about, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        try {
            // Ensure columns exist before UPDATE (idempotent ALTERs)
            foreach (['nickname VARCHAR(50) NULL', 'description TEXT NULL', 'about TEXT NULL'] as $colDef) {
                try { $db->exec("ALTER TABLE users ADD COLUMN $colDef"); } catch (Exception $ae) { /* exists */ }
            }

            $stmt = $db->prepare("UPDATE users SET nickname = ?, description = ?, about = ? WHERE id = ?");
            $stmt->execute([$nickname ?: null, $description ?: null, $about ?: null, $editUserId]);

            // Update session nickname if editing own profile
            if ($isOwnProfile) {
                $_SESSION['nickname'] = $nickname ?: null;
            }

            echo json_encode(['success' => true, 'message' => 'Profile updated!']);
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save profile. Please try again.']);
        }
        exit;
    }

    if ($_POST['action'] === 'update_pfp') {
        try {
            // Ensure dual-PFP columns exist
            foreach ([
                'pfp_type ENUM("url","upload") DEFAULT "url"',
                'pfp_pending_url VARCHAR(500) DEFAULT NULL',
                'pfp_pending_path VARCHAR(255) DEFAULT NULL',
                'pfp_decline_reason TEXT DEFAULT NULL',
            ] as $colDef) {
                try { $db->exec("ALTER TABLE users ADD COLUMN $colDef"); } catch (Exception $ae) { /* exists */ }
            }

            $pfpUrl = trim($_POST['profile_picture_url'] ?? '');

            if (empty($pfpUrl) && empty($_FILES['pfp_file']['name'])) {
                // Remove PFP
                $stmt = $db->prepare("UPDATE users SET profile_picture_url = NULL, pfp_status = NULL, pfp_type = 'url', pfp_pending_url = NULL, pfp_pending_path = NULL WHERE id = ?");
                $stmt->execute([$editUserId]);
                echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
                exit;
            }

            // Admin uploads are auto-approved; others go to pending
            $isAdmin = ($currentRole === 'admin');

            // --- FILE UPLOAD PATH ---
            if (!empty($_FILES['pfp_file']['name']) && $_FILES['pfp_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['pfp_file'];

                // Validate MIME type via getimagesize
                $imgInfo = @getimagesize($file['tmp_name']);
                if (!$imgInfo) {
                    echo json_encode(['success' => false, 'error' => 'File is not a valid image.']);
                    exit;
                }
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imgInfo['mime'], $allowedMimes)) {
                    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, GIF, and WEBP images are allowed.']);
                    exit;
                }

                // Max 5MB
                if ($file['size'] > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'error' => 'Image must be under 5MB.']);
                    exit;
                }

                // Generate UUID-based filename
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$imgInfo['mime']];
                $uuid = bin2hex(random_bytes(16));
                $filename = $uuid . '.' . $ext;

                // Ensure pending_uploads directory exists with .htaccess protection
                $uploadDir = __DIR__ . '/pending_uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                    file_put_contents($uploadDir . '/.htaccess', "php_flag engine off\nOrder deny,allow\nDeny from all\n");
                }

                $destPath = $uploadDir . '/' . $filename;

                // Strip EXIF by re-processing through GD
                switch ($imgInfo['mime']) {
                    case 'image/jpeg':
                        $img = @imagecreatefromjpeg($file['tmp_name']);
                        if ($img) { imagejpeg($img, $destPath, 85); imagedestroy($img); }
                        else { move_uploaded_file($file['tmp_name'], $destPath); }
                        break;
                    case 'image/png':
                        $img = @imagecreatefrompng($file['tmp_name']);
                        if ($img) { imagesavealpha($img, true); imagepng($img, $destPath, 8); imagedestroy($img); }
                        else { move_uploaded_file($file['tmp_name'], $destPath); }
                        break;
                    case 'image/gif':
                        $img = @imagecreatefromgif($file['tmp_name']);
                        if ($img) { imagegif($img, $destPath); imagedestroy($img); }
                        else { move_uploaded_file($file['tmp_name'], $destPath); }
                        break;
                    case 'image/webp':
                        $img = @imagecreatefromwebp($file['tmp_name']);
                        if ($img) { imagewebp($img, $destPath, 85); imagedestroy($img); }
                        else { move_uploaded_file($file['tmp_name'], $destPath); }
                        break;
                }

                if (!file_exists($destPath)) {
                    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded image.']);
                    exit;
                }

                $relativePath = 'pending_uploads/' . $filename;

                if ($isAdmin) {
                    // Auto-approve for admin
                    $publicUrl = 'image_serve.php?file=' . urlencode($filename);
                    $stmt = $db->prepare("UPDATE users SET profile_picture_url = ?, pfp_status = 'approved', pfp_type = 'upload', pfp_pending_url = NULL, pfp_pending_path = NULL WHERE id = ?");
                    $stmt->execute([$publicUrl, $editUserId]);
                    echo json_encode(['success' => true, 'message' => 'Profile picture updated!']);
                } else {
                    $stmt = $db->prepare("UPDATE users SET pfp_type = 'upload', pfp_pending_path = ?, pfp_pending_url = NULL, pfp_status = 'pending', pfp_decline_reason = NULL WHERE id = ?");
                    $stmt->execute([$relativePath, $editUserId]);
                    echo json_encode(['success' => true, 'message' => 'Profile picture uploaded and submitted for approval.']);
                }
                exit;
            }

            // --- URL PATH ---
            if (!empty($pfpUrl)) {
                if (strlen($pfpUrl) > 500) {
                    echo json_encode(['success' => false, 'error' => 'URL must be 500 characters or less.']);
                    exit;
                }
                if (!filter_var($pfpUrl, FILTER_VALIDATE_URL)) {
                    echo json_encode(['success' => false, 'error' => 'Please enter a valid URL.']);
                    exit;
                }

                if ($isAdmin) {
                    $stmt = $db->prepare("UPDATE users SET profile_picture_url = ?, pfp_status = 'approved', pfp_type = 'url', pfp_pending_url = NULL, pfp_pending_path = NULL WHERE id = ?");
                    $stmt->execute([$pfpUrl, $editUserId]);
                    echo json_encode(['success' => true, 'message' => 'Profile picture updated!']);
                } else {
                    $stmt = $db->prepare("UPDATE users SET pfp_type = 'url', pfp_pending_url = ?, pfp_pending_path = NULL, pfp_status = 'pending', pfp_decline_reason = NULL WHERE id = ?");
                    $stmt->execute([$pfpUrl, $editUserId]);
                    echo json_encode(['success' => true, 'message' => 'Profile picture submitted for approval.']);
                }
                exit;
            }
        } catch (Exception $e) {
            error_log("PFP update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update profile picture. Please try again.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

$displayName = getDisplayName($profileUser);
$hasPfp = !empty($profileUser['profile_picture_url']) && ($profileUser['pfp_status'] ?? '') === 'approved';
$pfpPending = (($profileUser['pfp_status'] ?? '') === 'pending');
$initial = htmlspecialchars(strtoupper(substr($profileUser['username'], 0, 1)), ENT_QUOTES, 'UTF-8');

// Gamer Card stats
$accountAge = '';
if (!empty($profileUser['created_at'])) {
    $diff = (new DateTime($profileUser['created_at']))->diff(new DateTime());
    if ($diff->y > 0) $accountAge = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
    elseif ($diff->m > 0) $accountAge = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
    else $accountAge = max(1, $diff->d) . ' day' . ($diff->d > 1 ? 's' : '');
}

$mostPlayedGame = '';
$smailCount = 0;
$isProfileOnline = false;
try {
    // Most played game (from page_views)
    try {
        $mpStmt = $db->prepare("SELECT page_url, COUNT(*) as visits FROM page_views WHERE user_id = ? AND page_url LIKE '%/Games/%' GROUP BY page_url ORDER BY visits DESC LIMIT 1");
        $mpStmt->execute([$profileUser['id']]);
        $mpRow = $mpStmt->fetch(PDO::FETCH_ASSOC);
        if ($mpRow) {
            $mostPlayedGame = ucfirst(str_replace(['/Games/', '.php', '/'], '', basename($mpRow['page_url'])));
        }
    } catch (Exception $e) { /* page_views may not exist */ }

    // Smail message count
    try {
        $scStmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE sender_id = ? OR receiver_id = ?");
        $scStmt->execute([$profileUser['id'], $profileUser['id']]);
        $smailCount = (int)$scStmt->fetchColumn();
    } catch (Exception $e) { /* table may not exist */ }

    // Online status
    try {
        $onStmt = $db->prepare("SELECT 1 FROM user_sessions WHERE user_id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
        $onStmt->execute([$profileUser['id']]);
        $isProfileOnline = (bool)$onStmt->fetch();
    } catch (Exception $e) { /* table may not exist */ }
} catch (Exception $e) {}

$roleColors = [
    'admin' => '#ef4444', 'contributor' => '#f59e0b',
    'designer' => '#ec4899', 'user' => '#3b82f6',
];
$roleIcons = [
    'admin' => 'fa-crown', 'contributor' => 'fa-lightbulb',
    'designer' => 'fa-palette', 'user' => 'fa-user',
];
$color = $roleColors[$profileUser['role']] ?? '#64748b';
$icon = $roleIcons[$profileUser['role']] ?? 'fa-user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profileUser['username']); ?>'s Profile - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        *{box-sizing:border-box;}
        .gc-wrap{max-width:800px;margin:0 auto;padding:24px 20px 60px;}
        .gc-back{display:inline-flex;align-items:center;gap:6px;color:#64748b;text-decoration:none;font-size:0.85rem;margin-bottom:18px;transition:color .2s;}
        .gc-back:hover{color:#4ECDC4;}

        /* Gamer Card */
        .gc-card{background:rgba(15,23,42,0.65);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.06);border-radius:20px;overflow:hidden;margin-bottom:20px;}
        .gc-banner{height:100px;background:linear-gradient(135deg,<?php echo $color; ?>30,rgba(15,23,42,0.8));position:relative;}
        .gc-banner::after{content:'';position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path d="M0 20 Q25 0 50 15 Q75 5 100 20Z" fill="rgba(15,23,42,0.4)"/></svg>') bottom/100% no-repeat;}
        .gc-profile{padding:0 28px 28px;margin-top:-40px;position:relative;}
        .gc-avatar-wrap{position:relative;display:inline-block;}
        .gc-avatar{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;font-weight:800;background:linear-gradient(135deg,#334155,#1e293b);border:3px solid <?php echo $color; ?>50;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);}
        .gc-avatar img{width:100%;height:100%;object-fit:cover;}
        .gc-status-dot{position:absolute;bottom:3px;right:3px;width:14px;height:14px;border-radius:50%;border:3px solid #0f172a;}
        .gc-online{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,0.6);}
        .gc-offline{background:#475569;}
        .gc-identity{margin-top:12px;}
        .gc-username{font-size:1.6rem;font-weight:800;color:#e2e8f0;margin:0;}
        .gc-nickname{color:#64748b;font-size:0.88rem;font-style:italic;margin-top:2px;}
        .gc-role-row{display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap;}
        .gc-role{display:inline-flex;align-items:center;gap:5px;padding:4px 14px;border-radius:8px;font-size:0.76rem;font-weight:700;text-transform:uppercase;background:<?php echo $color; ?>18;color:<?php echo $color; ?>;}
        .gc-status-text{font-size:0.75rem;font-weight:600;}
        .gc-pfp-pending{color:#f59e0b;font-size:0.76rem;margin-top:6px;}

        /* Stats Row */
        .gc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;padding:0 28px 24px;}
        .gc-stat{background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.04);border-radius:12px;padding:14px 16px;text-align:center;}
        .gc-stat-val{font-size:1.1rem;font-weight:700;color:#e2e8f0;}
        .gc-stat-label{font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}

        /* Bio Sections */
        .gc-section{background:rgba(15,23,42,0.55);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.05);border-radius:16px;padding:22px;margin-bottom:14px;}
        .gc-section h3{font-size:0.85rem;color:#94a3b8;font-weight:600;margin:0 0 10px;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;}
        .gc-section p{color:#cbd5e1;font-size:0.9rem;line-height:1.65;margin:0;}
        .gc-section .empty{color:#475569;font-style:italic;font-size:0.86rem;}

        /* Edit form */
        .gc-edit{background:rgba(15,23,42,0.55);backdrop-filter:blur(8px);border:1px solid rgba(78,205,196,0.12);border-radius:16px;padding:24px;margin-bottom:14px;}
        .gc-edit h3{font-size:0.95rem;color:#4ECDC4;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:6px;}
        .up-fg{margin-bottom:14px;}
        .up-fg label{display:block;font-size:0.78rem;font-weight:600;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;}
        .up-fg input,.up-fg textarea{width:100%;padding:10px 12px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:#e2e8f0;font-size:0.88rem;font-family:inherit;transition:border-color .2s;box-sizing:border-box;}
        .up-fg input:focus,.up-fg textarea:focus{outline:none;border-color:#4ECDC4;}
        .up-fg textarea{resize:vertical;min-height:80px;}
        .fg-hint{font-size:0.7rem;color:#475569;margin-top:3px;}
        .up-save-btn{padding:10px 24px;border:none;border-radius:10px;background:linear-gradient(135deg,#4ECDC4,#6366f1);color:#fff;font-size:0.9rem;font-weight:700;cursor:pointer;transition:all .2s;}
        .up-save-btn:hover{opacity:.9;transform:translateY(-1px);}
        .up-save-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;}
        .up-msg{margin-top:10px;font-size:0.83rem;min-height:1.2em;}
        .up-msg.ok{color:#10b981;}.up-msg.err{color:#ef4444;}

        @media(max-width:600px){.gc-profile{padding:0 16px 20px;}.gc-stats{padding:0 16px 16px;grid-template-columns:1fr 1fr;}.gc-username{font-size:1.3rem;}}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="gc-wrap">
    <a href="userlist.php" class="gc-back"><i class="fas fa-arrow-left"></i> User Directory</a>

    <!-- Gamer Card -->
    <div class="gc-card">
        <div class="gc-banner"></div>
        <div class="gc-profile">
            <div class="gc-avatar-wrap">
                <div class="gc-avatar">
                    <?php if ($hasPfp): ?>
                        <img src="<?php echo htmlspecialchars($profileUser['profile_picture_url']); ?>" alt="">
                    <?php else: ?>
                        <?php echo $initial; ?>
                    <?php endif; ?>
                </div>
                <div class="gc-status-dot <?php echo $isProfileOnline ? 'gc-online' : 'gc-offline'; ?>"></div>
            </div>
            <div class="gc-identity">
                <h1 class="gc-username"><?php echo htmlspecialchars($profileUser['username']); ?></h1>
                <?php if (!empty($profileUser['nickname'])): ?>
                <div class="gc-nickname">"<?php echo htmlspecialchars($profileUser['nickname']); ?>"</div>
                <?php endif; ?>
                <div class="gc-role-row">
                    <span class="gc-role"><i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars(ucfirst($profileUser['role'])); ?></span>
                    <span class="gc-status-text" style="color:<?php echo $isProfileOnline ? '#22c55e' : '#64748b'; ?>;">
                        <i class="fas fa-circle" style="font-size:0.5rem;vertical-align:middle;margin-right:3px;"></i>
                        <?php echo $isProfileOnline ? 'Online Now' : 'Offline'; ?>
                    </span>
                </div>
                <?php if ($pfpPending && ($isOwnProfile || $currentRole === 'admin')): ?>
                <div class="gc-pfp-pending"><i class="fas fa-clock"></i> PFP pending approval</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="gc-stats">
            <div class="gc-stat">
                <div class="gc-stat-val"><?php echo $accountAge ?: 'N/A'; ?></div>
                <div class="gc-stat-label">Account Age</div>
            </div>
            <div class="gc-stat">
                <div class="gc-stat-val" style="color:<?php echo $color; ?>;"><?php echo ucfirst($profileUser['role']); ?></div>
                <div class="gc-stat-label">Role</div>
            </div>
            <div class="gc-stat">
                <div class="gc-stat-val"><?php echo $mostPlayedGame ?: '—'; ?></div>
                <div class="gc-stat-label">Most Played</div>
            </div>
            <div class="gc-stat">
                <div class="gc-stat-val"><?php echo $smailCount; ?></div>
                <div class="gc-stat-label">Messages</div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="gc-section">
        <h3><i class="fas fa-align-left"></i> Description</h3>
        <?php if (!empty($profileUser['description'])): ?>
        <p><?php echo nl2br(htmlspecialchars($profileUser['description'])); ?></p>
        <?php else: ?>
        <p class="empty">No description set.</p>
        <?php endif; ?>
    </div>

    <!-- About / Bio -->
    <div class="gc-section">
        <h3><i class="fas fa-info-circle"></i> About</h3>
        <?php if (!empty($profileUser['about'])): ?>
        <p><?php echo nl2br(htmlspecialchars($profileUser['about'])); ?></p>
        <?php else: ?>
        <p class="empty">No about section written yet.</p>
        <?php endif; ?>
    </div>

    <!-- Edit Form (only if canEdit) -->
    <?php if ($canEdit): ?>
    <div class="gc-edit">
        <h3><i class="fas fa-pen"></i> Edit Profile<?php echo !$isOwnProfile ? ' (Admin)' : ''; ?></h3>
        <form id="profileForm" onsubmit="return saveProfile(event)">
            <div class="up-fg">
                <label for="pNickname">Nickname</label>
                <input type="text" id="pNickname" maxlength="50" value="<?php echo htmlspecialchars($profileUser['nickname'] ?? ''); ?>" placeholder="Optional display name">
                <div class="fg-hint">Shown instead of your username across the site</div>
            </div>
            <div class="up-fg">
                <label for="pDesc">Description</label>
                <input type="text" id="pDesc" maxlength="500" value="<?php echo htmlspecialchars($profileUser['description'] ?? ''); ?>" placeholder="A short one-liner about yourself">
            </div>
            <div class="up-fg">
                <label for="pAbout">About</label>
                <textarea id="pAbout" maxlength="2000" placeholder="Tell people more about yourself..."><?php echo htmlspecialchars($profileUser['about'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="up-save-btn" id="saveProfileBtn">Save Profile</button>
            <div class="up-msg" id="profileMsg"></div>
        </form>

        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.06);margin:20px 0;">

        <h3 style="font-size:0.9rem;color:#4ECDC4;margin:0 0 12px;display:flex;align-items:center;gap:6px;"><i class="fas fa-image"></i> Profile Picture</h3>
        <?php if (($profileUser['pfp_status'] ?? '') === 'rejected' && !empty($profileUser['pfp_decline_reason'])): ?>
        <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:0.85rem;color:#fca5a5;">
            <strong style="color:#f87171;"><i class="fas fa-times-circle"></i> Picture Declined</strong><br>
            Reason: <?php echo htmlspecialchars($profileUser['pfp_decline_reason']); ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <button type="button" class="up-save-btn" style="padding:7px 16px;font-size:0.82rem;background:linear-gradient(135deg,#4ECDC4,#6366f1);" onclick="showPfpTab('url')" id="pfpTabUrl">Paste URL</button>
            <button type="button" class="up-save-btn" style="padding:7px 16px;font-size:0.82rem;background:#334155;" onclick="showPfpTab('upload')" id="pfpTabUpload">Upload File</button>
        </div>

        <form id="pfpFormUrl" onsubmit="return savePfp(event)">
            <div class="up-fg">
                <label for="pPfpUrl">Image URL</label>
                <input type="url" id="pPfpUrl" maxlength="500" value="<?php echo htmlspecialchars($profileUser['profile_picture_url'] ?? ''); ?>" placeholder="https://example.com/your-photo.jpg">
                <div class="fg-hint"><?php echo $currentRole === 'admin' ? 'Admin: auto-approved' : 'Reviewed by an admin before becoming visible'; ?></div>
            </div>
            <button type="submit" class="up-save-btn" id="savePfpBtn">Update Picture</button>
            <div class="up-msg" id="pfpMsg"></div>
        </form>

        <form id="pfpFormUpload" style="display:none;" onsubmit="return uploadPfp(event)" enctype="multipart/form-data">
            <div class="up-fg">
                <label for="pPfpFile">Choose Image</label>
                <input type="file" id="pPfpFile" name="pfp_file" accept="image/jpeg,image/png,image/gif,image/webp" style="padding:8px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:#e2e8f0;width:100%;box-sizing:border-box;">
                <div class="fg-hint">JPG, PNG, GIF, or WEBP. Max 5MB. <?php echo $currentRole === 'admin' ? 'Admin: auto-approved' : 'Reviewed by an admin before becoming visible'; ?></div>
            </div>
            <button type="submit" class="up-save-btn" id="uploadPfpBtn">Upload Picture</button>
            <div class="up-msg" id="pfpUploadMsg"></div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

<script>
const csrf = '<?php echo htmlspecialchars($csrfToken); ?>';
const profileUrl = 'userprofile.php?user=<?php echo urlencode($profileUser['username']); ?>';

async function saveProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const msg = document.getElementById('profileMsg');
    btn.disabled = true; btn.textContent = 'Saving...';
    msg.textContent = ''; msg.className = 'up-msg';

    try {
        const body = new URLSearchParams({
            action: 'update_profile', csrf_token: csrf,
            nickname: document.getElementById('pNickname').value,
            description: document.getElementById('pDesc').value,
            about: document.getElementById('pAbout').value,
        });
        const resp = await fetch(profileUrl, { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        msg.textContent = data.message || data.error;
        msg.className = 'up-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) { msg.textContent = 'Network error.'; msg.className = 'up-msg err'; }
    btn.disabled = false; btn.textContent = 'Save Profile';
    return false;
}

async function savePfp(e) {
    e.preventDefault();
    const btn = document.getElementById('savePfpBtn');
    const msg = document.getElementById('pfpMsg');
    btn.disabled = true; btn.textContent = 'Saving...';
    msg.textContent = ''; msg.className = 'up-msg';

    try {
        const body = new URLSearchParams({
            action: 'update_pfp', csrf_token: csrf,
            profile_picture_url: document.getElementById('pPfpUrl').value,
        });
        const resp = await fetch(profileUrl, { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        msg.textContent = data.message || data.error;
        msg.className = 'up-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) { msg.textContent = 'Network error.'; msg.className = 'up-msg err'; }
    btn.disabled = false; btn.textContent = 'Update Picture';
    return false;
}

async function uploadPfp(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadPfpBtn');
    const msg = document.getElementById('pfpUploadMsg');
    const fileInput = document.getElementById('pPfpFile');
    btn.disabled = true; btn.textContent = 'Uploading...';
    msg.textContent = ''; msg.className = 'up-msg';

    if (!fileInput.files.length) {
        msg.textContent = 'Please select a file.'; msg.className = 'up-msg err';
        btn.disabled = false; btn.textContent = 'Upload Picture';
        return false;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'update_pfp');
        formData.append('csrf_token', csrf);
        formData.append('pfp_file', fileInput.files[0]);
        const resp = await fetch(profileUrl, { method: 'POST', credentials: 'same-origin', body: formData });
        const data = await resp.json();
        msg.textContent = data.message || data.error;
        msg.className = 'up-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) { msg.textContent = 'Network error.'; msg.className = 'up-msg err'; }
    btn.disabled = false; btn.textContent = 'Upload Picture';
    return false;
}

function showPfpTab(tab) {
    const urlForm = document.getElementById('pfpFormUrl');
    const uploadForm = document.getElementById('pfpFormUpload');
    const urlTab = document.getElementById('pfpTabUrl');
    const uploadTab = document.getElementById('pfpTabUpload');
    if (tab === 'url') {
        urlForm.style.display = ''; uploadForm.style.display = 'none';
        urlTab.style.background = 'linear-gradient(135deg,#4ECDC4,#6366f1)';
        uploadTab.style.background = '#334155';
    } else {
        urlForm.style.display = 'none'; uploadForm.style.display = '';
        uploadTab.style.background = 'linear-gradient(135deg,#4ECDC4,#6366f1)';
        urlTab.style.background = '#334155';
    }
}
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
