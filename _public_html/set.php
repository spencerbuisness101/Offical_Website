<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$__bgfile = __DIR__ . '/load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }

// Get user ID and role from session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'community';

// Only allow server-side storage for non-community roles
$can_sync = ($user_role !== 'community' && $user_id);

// Handle AJAX requests for settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // v5.1: CSRF token validation
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Create settings table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_setting (user_id, setting_key),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // v7.0: Privacy toggle - hide from user directory
        if ($_POST['action'] === 'toggle_privacy') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'message' => 'Not available for community role']);
                exit;
            }
            $hidden = ($_POST['is_hidden'] ?? '0') === '1' ? 1 : 0;
            try {
                $conn->exec("ALTER TABLE users ADD COLUMN is_hidden BOOLEAN DEFAULT FALSE");
            } catch (Exception $e) { /* column exists */ }
            $stmt = $conn->prepare("UPDATE users SET is_hidden = ? WHERE id = ?");
            $stmt->execute([$hidden, $user_id]);
            echo json_encode(['success' => true, 'message' => $hidden ? 'Profile hidden from directory' : 'Profile visible in directory']);
            exit;
        }

        if ($_POST['action'] === 'save_settings') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'message' => 'Server sync not available for community role']);
                exit;
            }

            $settings = json_decode($_POST['settings'], true);

            if (!$settings || !$user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }

            // Save each setting
            $stmt = $conn->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value)
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

            foreach ($settings as $key => $value) {
                $stmt->execute([$user_id, $key, json_encode($value)]);
            }

            // v5.1: Invalidate session cache after save
            invalidateUserSettingsCache($user_id);

            echo json_encode(['success' => true, 'message' => 'Settings saved']);
            exit;
        }

        if ($_POST['action'] === 'load_settings') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'settings' => [], 'message' => 'Server sync not available']);
                exit;
            }

            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
            }

            echo json_encode(['success' => true, 'settings' => $settings]);
            exit;
        }

        if ($_POST['action'] === 'reset_settings') {
            if ($can_sync && $user_id) {
                $stmt = $conn->prepare("DELETE FROM user_settings WHERE user_id = ?");
                $stmt->execute([$user_id]);
                // v5.1: Invalidate session cache after reset
                invalidateUserSettingsCache($user_id);
            }
            echo json_encode(['success' => true, 'message' => 'Settings reset']);
            exit;
        }

        // v7.0: Save custom AI persona
        if ($_POST['action'] === 'save_custom_persona') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'message' => 'Server sync not available for community role']);
                exit;
            }

            $personaName = trim($_POST['persona_name'] ?? '');
            $personaPrompt = trim($_POST['persona_prompt'] ?? '');

            // Validate name
            if (empty($personaName) || strlen($personaName) > 30) {
                echo json_encode(['success' => false, 'message' => 'Persona name must be 1–30 characters.']);
                exit;
            }

            // Validate prompt length
            if (empty($personaPrompt) || strlen($personaPrompt) > 500) {
                echo json_encode(['success' => false, 'message' => 'Persona prompt must be 1–500 characters.']);
                exit;
            }

            // Safety filter: block prompt injection patterns
            $injectionPatterns = [
                'ignore previous', 'ignore all previous', 'disregard previous',
                'forget your instructions', 'new instructions', 'system:', 'SYSTEM:',
                'you are now', 'act as if', 'pretend you are', 'jailbreak',
                'DAN', 'do anything now', 'bypass', 'override',
                'ignore safety', 'ignore guidelines', 'ignore rules',
                'reveal your prompt', 'show me your prompt', 'what is your system prompt',
            ];
            $lowerPrompt = strtolower($personaPrompt);
            foreach ($injectionPatterns as $pattern) {
                if (str_contains($lowerPrompt, strtolower($pattern))) {
                    echo json_encode(['success' => false, 'message' => 'Your prompt contains restricted phrases. Please rephrase.']);
                    exit;
                }
            }

            // Sanitize
            $personaName = htmlspecialchars($personaName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $personaPrompt = htmlspecialchars($personaPrompt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $personaData = json_encode(['name' => $personaName, 'prompt' => $personaPrompt]);

            $stmt = $conn->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value)
                                    VALUES (?, 'custom_persona', ?)
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$user_id, $personaData]);

            invalidateUserSettingsCache($user_id);
            echo json_encode(['success' => true, 'message' => 'Custom AI persona saved!']);
            exit;
        }

        // v7.0: Delete custom AI persona
        if ($_POST['action'] === 'delete_custom_persona') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'message' => 'Server sync not available for community role']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = 'custom_persona'");
            $stmt->execute([$user_id]);
            invalidateUserSettingsCache($user_id);
            echo json_encode(['success' => true, 'message' => 'Custom AI persona removed.']);
            exit;
        }

        // v5.1: Validate image URL by checking if it loads
        if ($_POST['action'] === 'validate_image_url') {
            if (!$can_sync) {
                echo json_encode(['success' => false, 'message' => 'Not available for community role']);
                exit;
            }

            $url = trim($_POST['url'] ?? '');

            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URL is required']);
                exit;
            }

            // SSRF validation: DNS resolution + private IP blocking
            $ssrfCheck = validateUrlSsrf($url);
            if (!$ssrfCheck['safe']) {
                echo json_encode(['success' => false, 'message' => 'Invalid or blocked URL: ' . $ssrfCheck['error']]);
                exit;
            }

            $resolvedIp = $ssrfCheck['resolved_ip'];
            $host = $ssrfCheck['host'];

            // Check file extension (common image formats)
            $parsed = parse_url($url);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
            $pathInfo = pathinfo($parsed['path'] ?? '');
            $extension = strtolower($pathInfo['extension'] ?? '');
            $hasValidExtension = empty($extension) || in_array($extension, $allowedExtensions);

            // Try to fetch headers to verify image — no redirects, IP pinned to resolved address
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SpencerWebsite/7.0)',
                CURLOPT_RESOLVE => [$host . ':443:' . $resolvedIp, $host . ':80:' . $resolvedIp],
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            // Redirects are not followed — treat 3xx as inaccessible
            if ($httpCode >= 300 && $httpCode < 400) {
                echo json_encode(['success' => false, 'message' => 'URL redirects are not permitted']);
                exit;
            }

            if ($httpCode !== 200) {
                echo json_encode(['success' => false, 'message' => "Image not accessible (HTTP $httpCode)"]);
                exit;
            }

            // Check content type if available
            $isImage = false;
            if ($contentType) {
                $isImage = strpos($contentType, 'image/') === 0;
            } else {
                $isImage = $hasValidExtension;
            }

            if (!$isImage && !$hasValidExtension) {
                echo json_encode(['success' => false, 'message' => 'URL does not appear to be an image']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Image URL is valid']);
            exit;
        }

    } catch (Exception $e) {
        error_log("Settings error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <link rel="icon" href="/assets/images/favicon.webp">
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <script src="common.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Settings - Spencer's Website">
    <title>Settings - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        /* v7.0 Two-Column Settings Layout */
        *{box-sizing:border-box;}
        .set-wrap{display:flex;max-width:1060px;margin:0 auto;min-height:calc(100vh - 52px);font-family:'Segoe UI',system-ui,sans-serif;}

        /* Left Sidebar Nav */
        .set-nav{width:220px;flex-shrink:0;background:rgba(15,23,42,0.7);backdrop-filter:blur(12px);border-right:1px solid rgba(255,255,255,0.06);padding:24px 0;position:sticky;top:52px;height:calc(100vh - 52px);overflow-y:auto;}
        .set-nav-item{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;font-size:0.88rem;font-weight:500;cursor:pointer;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none;}
        .set-nav-item:hover{color:#e2e8f0;background:rgba(255,255,255,0.04);}
        .set-nav-item.active{color:#4ECDC4;background:rgba(78,205,196,0.08);border-left-color:#4ECDC4;font-weight:600;}
        .set-nav-item i{width:18px;text-align:center;font-size:0.9rem;}
        .set-nav-title{padding:0 20px 16px;font-size:0.7rem;color:#475569;text-transform:uppercase;letter-spacing:1px;font-weight:700;}

        /* Right Content Panel */
        .set-content{flex:1;padding:28px 32px 60px;min-width:0;}
        .set-section{display:none;}
        .set-section.active{display:block;}
        .set-section-title{font-size:1.4rem;font-weight:700;color:#e2e8f0;margin:0 0 6px;}
        .set-section-desc{color:#64748b;font-size:0.88rem;margin-bottom:24px;}

        /* Setting Rows */
        .setting-row{display:flex;justify-content:space-between;align-items:center;padding:16px 0;border-bottom:1px solid rgba(255,255,255,0.06);}
        .setting-row:last-child{border-bottom:none;}
        .setting-label{flex:1;min-width:0;}
        .setting-name{display:block;color:#e2e8f0;font-weight:600;font-size:0.95rem;margin-bottom:3px;}
        .setting-desc{display:block;color:#64748b;font-size:0.82rem;line-height:1.5;}
        .setting-control{flex-shrink:0;margin-left:20px;}

        /* Reusable Controls */
        .setting-item{display:flex;justify-content:space-between;align-items:center;padding:15px 0;border-bottom:1px solid rgba(255,255,255,0.06);}
        .setting-item:last-child{border-bottom:none;}
        .setting-info{flex:1;}
        .setting-title{color:#e2e8f0;font-weight:600;font-size:0.95rem;margin-bottom:3px;}
        .setting-description{color:#64748b;font-size:0.82rem;line-height:1.5;}
        .setting-controls{flex-shrink:0;margin-left:16px;}

        /* Toggle Switch */
        .toggle-switch{position:relative;display:inline-block;width:48px;height:26px;}
        .toggle-switch input{opacity:0;width:0;height:0;}
        .toggle-slider{position:absolute;cursor:pointer;inset:0;background:#334155;transition:.3s;border-radius:26px;}
        .toggle-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:white;transition:.3s;border-radius:50%;}
        input:checked+.toggle-slider{background:#4ECDC4;}
        input:checked+.toggle-slider:before{transform:translateX(22px);}

        /* Select */
        .setting-select{background:rgba(15,23,42,0.8);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:#e2e8f0;padding:8px 12px;font-size:0.88rem;min-width:160px;outline:none;}
        .setting-select:focus{border-color:#4ECDC4;}

        /* Color Picker */
        .color-options{display:flex;gap:8px;flex-wrap:wrap;}
        .color-option{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all 0.2s;}
        .color-option.active{border-color:#fff;transform:scale(1.15);}

        /* Range Slider */
        .setting-range{width:100%;margin:8px 0;-webkit-appearance:none;height:6px;border-radius:3px;background:#334155;outline:none;}
        .setting-range::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:#4ECDC4;cursor:pointer;}
        .setting-range::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:#4ECDC4;cursor:pointer;border:none;}
        .range-value{color:#4ECDC4;font-weight:600;margin-left:8px;font-size:0.85rem;}

        /* Buttons */
        .set-btn{padding:8px 18px;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;}
        .set-btn-primary{background:linear-gradient(135deg,#4ECDC4,#6366f1);color:#fff;}
        .set-btn-primary:hover{opacity:0.9;transform:translateY(-1px);}
        .set-btn-danger{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);}
        .set-btn-danger:hover{background:rgba(239,68,68,0.25);}
        .set-btn-secondary{background:rgba(255,255,255,0.08);color:#94a3b8;border:1px solid rgba(255,255,255,0.1);}
        .set-btn-secondary:hover{color:#e2e8f0;background:rgba(255,255,255,0.12);}
        button:disabled{opacity:0.5;cursor:not-allowed;}

        /* Toast */
        .set-toast{position:fixed;bottom:24px;right:24px;padding:12px 22px;border-radius:10px;font-size:0.88rem;font-weight:600;color:#fff;z-index:10000;transform:translateY(80px);opacity:0;transition:all 0.3s ease;pointer-events:none;}
        .set-toast.show{transform:translateY(0);opacity:1;}
        .set-toast.ok{background:#10b981;}
        .set-toast.err{background:#ef4444;}

        /* Confirmation Dialog */
        .confirmation-dialog{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9999;justify-content:center;align-items:center;}
        .confirmation-content{background:#0f172a;border:1px solid rgba(239,68,68,0.4);border-radius:14px;padding:28px;max-width:420px;width:90%;text-align:center;color:#e2e8f0;}
        .confirmation-content h3{color:#ef4444;margin:0 0 12px;}
        .confirmation-buttons{display:flex;justify-content:center;gap:12px;margin-top:20px;}

        /* Misc */
        .bg-theme-override{pointer-events:none;}
        .sync-indicator{position:fixed;top:60px;right:20px;background:rgba(0,0,0,0.8);color:#4ECDC4;padding:8px 14px;border-radius:8px;font-size:0.82rem;z-index:1000;opacity:0;transition:opacity 0.3s;}.sync-indicator.show{opacity:1;}
        .loading-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.8s linear infinite;vertical-align:middle;margin-right:5px;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .import-file-input{display:none;}
        .data-divider{border:none;border-top:1px solid rgba(255,255,255,0.06);margin:8px 0;}
        .data-actions{display:flex;flex-direction:column;gap:14px;}
        .data-action-row{display:flex;align-items:center;gap:16px;}
        .data-action-row .setting-info{flex:1;}
        .data-action-row .setting-controls{flex-shrink:0;}
        .background-options{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}

        /* Designer Background Gallery */
        .bg-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:4px;}
        .bg-gallery-card{position:relative;border-radius:10px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .15s;background:rgba(15,23,42,0.6);}
        .bg-gallery-card:hover{border-color:rgba(78,205,196,0.5);transform:translateY(-2px);}
        .bg-gallery-card.active{border-color:#4ECDC4;box-shadow:0 0 0 1px rgba(78,205,196,0.3);}
        .bg-gallery-thumb{width:100%;height:80px;background-size:cover;background-position:center;background-color:rgba(30,41,59,0.9);}
        .bg-gallery-info{padding:7px 8px 6px;}
        .bg-gallery-name{font-size:0.78rem;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .bg-gallery-by{font-size:0.68rem;color:#64748b;margin-top:1px;}
        .bg-gallery-check{display:none;position:absolute;top:6px;right:6px;background:#4ECDC4;color:#0f172a;width:20px;height:20px;border-radius:50%;align-items:center;justify-content:center;font-size:0.6rem;font-weight:900;}
        .bg-gallery-card.active .bg-gallery-check{display:flex;}
        .bg-gallery-empty{color:#475569;font-size:0.85rem;padding:12px 0;}
        @media(max-width:640px){.bg-gallery{grid-template-columns:repeat(2,1fr);}}

        /* Mobile */
        @media(max-width:768px){
            .set-wrap{flex-direction:column;}
            .set-nav{width:100%;height:auto;position:relative;top:0;flex-shrink:0;display:flex;overflow-x:auto;padding:0;border-right:none;border-bottom:1px solid rgba(255,255,255,0.06);gap:0;}
            .set-nav-title{display:none;}
            .set-nav-item{padding:12px 16px;border-left:none;border-bottom:3px solid transparent;white-space:nowrap;font-size:0.82rem;gap:6px;}
            .set-nav-item.active{border-left:none;border-bottom-color:#4ECDC4;}
            .set-content{padding:20px 16px 60px;}
            .setting-row,.setting-item{flex-direction:column;align-items:flex-start;}
            .setting-control,.setting-controls{margin-left:0;margin-top:10px;width:100%;}
            .setting-select{width:100%;}
            .background-options{grid-template-columns:repeat(2,1fr);}
            .data-action-row{flex-direction:column;align-items:flex-start;}
        }
    </style>
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds ?? []), ENT_QUOTES, 'UTF-8'); ?>'
      data-active-background='<?php echo htmlspecialchars($active_designer_background['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
      data-user-id='<?php echo $user_id ?? 0; ?>'
      data-user-role='<?php echo htmlspecialchars($user_role); ?>'
      data-can-sync='<?php echo $can_sync ? "true" : "false"; ?>'>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="bg-theme-override" id="bgThemeOverride"></div>
    <div class="sync-indicator" id="syncIndicator">Syncing...</div>

    <?php
        $existingPersona = null;
        $db = null;
        try {
            $database = new Database();
            $db = $database->getConnection();
            if ($db && $user_id) {
                $pStmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'custom_persona'");
                $pStmt->execute([$user_id]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($pRow) $existingPersona = json_decode($pRow['setting_value'], true);
            }
        } catch (Exception $e) {}
    ?>

    <div class="set-wrap">
        <!-- Left Sidebar Nav -->
        <nav class="set-nav">
            <div class="set-nav-title">Settings</div>
            <a class="set-nav-item active" data-section="account" onclick="setSection('account',this)"><i class="fa-solid fa-user"></i> Account</a>
            <a class="set-nav-item" data-section="appearance" onclick="setSection('appearance',this)"><i class="fa-solid fa-palette"></i> Appearance</a>
            <a class="set-nav-item" data-section="notifications" onclick="setSection('notifications',this)"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a class="set-nav-item" data-section="chat-yap" onclick="setSection('chat-yap',this)"><i class="fa-solid fa-comments"></i> Chat & Yap</a>
            <a class="set-nav-item" data-section="privacy" onclick="setSection('privacy',this)"><i class="fa-solid fa-shield-halved"></i> Privacy</a>
            <a class="set-nav-item" data-section="security" onclick="setSection('security',this)"><i class="fa-solid fa-lock"></i> Security</a>
            <a class="set-nav-item" data-section="danger" onclick="setSection('danger',this)"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</a>
        </nav>

        <!-- Right Content Panel -->
        <div class="set-content">

            <!-- === ACCOUNT === -->
            <div class="set-section active" id="sec-account">
                <h2 class="set-section-title">Account</h2>
                <p class="set-section-desc">Your profile information and account data.</p>

                <div style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:20px;margin-bottom:24px;display:flex;align-items:center;gap:18px;">
                    <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#4ECDC4,#6366f1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0;"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <div>
                        <div style="font-size:1.05rem;font-weight:600;color:#e2e8f0;"><?php echo htmlspecialchars($username); ?></div>
                        <div style="font-size:0.8rem;color:#64748b;"><?php echo htmlspecialchars(ucfirst($user_role)); ?> Member</div>
                    </div>
                </div>

                <div class="data-actions">
                    <div class="data-action-row">
                        <div class="setting-info">
                            <div class="setting-title">Download My Data</div>
                            <div class="setting-description">Export all your account data as a JSON file</div>
                        </div>
                        <div class="setting-controls">
                            <button class="set-btn set-btn-secondary" id="downloadDataBtn" onclick="downloadMyData()"><i class="fa-solid fa-file-export"></i> Download</button>
                        </div>
                    </div>
                    <hr class="data-divider">
                    <div class="data-action-row">
                        <div class="setting-info">
                            <div class="setting-title">Export Settings</div>
                            <div class="setting-description">Download your current settings as a JSON file for backup</div>
                        </div>
                        <div class="setting-controls">
                            <button class="set-btn set-btn-secondary" id="exportSettingsBtn"><i class="fa-solid fa-download"></i> Export</button>
                        </div>
                    </div>
                    <hr class="data-divider">
                    <div class="data-action-row">
                        <div class="setting-info">
                            <div class="setting-title">Import Settings</div>
                            <div class="setting-description">Load settings from a previously exported JSON file</div>
                        </div>
                        <div class="setting-controls">
                            <input type="file" id="importSettingsFile" class="import-file-input" accept=".json">
                            <button class="set-btn set-btn-secondary" id="importSettingsBtn"><i class="fa-solid fa-upload"></i> Import</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === APPEARANCE === -->
            <div class="set-section" id="sec-appearance">
                <h2 class="set-section-title">Appearance</h2>
                <p class="set-section-desc">Customize colors, fonts, backgrounds, and themes.</p>

                <?php if ($user_role !== 'community'): ?>
                <div class="setting-item" style="flex-direction: column; align-items: stretch; gap: 14px;">
                    <div class="setting-info">
                        <div class="setting-title">Accent Color</div>
                        <div class="setting-description">Choose a preset or enter any hex color. Changes apply live.</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div class="color-options" id="accentPresets">
                            <div class="color-option" style="background-color: #4ECDC4;" data-color="4ECDC4" title="Teal"></div>
                            <div class="color-option" style="background-color: #FF6B6B;" data-color="FF6B6B" title="Coral"></div>
                            <div class="color-option" style="background-color: #45B7D1;" data-color="45B7D1" title="Sky"></div>
                            <div class="color-option" style="background-color: #9B59B6;" data-color="9B59B6" title="Purple"></div>
                            <div class="color-option" style="background-color: #F1C40F;" data-color="F1C40F" title="Gold"></div>
                            <div class="color-option" style="background-color: #10b981;" data-color="10b981" title="Green"></div>
                            <div class="color-option" style="background-color: #f97316;" data-color="f97316" title="Orange"></div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="color" id="accentColorPicker" value="#4ECDC4"
                                   style="width: 38px; height: 38px; border: none; border-radius: 8px; cursor: pointer; background: none; padding: 2px;"
                                   oninput="onAccentColorPicker(this.value)">
                            <input type="text" id="accentHexInput" value="#4ECDC4" maxlength="7"
                                   placeholder="#4ECDC4"
                                   style="width: 90px; padding: 8px 10px; background: rgba(15,23,42,0.7); border: 1px solid rgba(78,205,196,0.3); border-radius: 8px; color: white; font-family: monospace; font-size: 13px;"
                                   oninput="onAccentHexInput(this.value)">
                            <div id="accentLivePreview" style="width: 38px; height: 38px; border-radius: 8px; background: #4ECDC4; border: 2px solid rgba(255,255,255,0.2); transition: background 0.2s; flex-shrink: 0;"></div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="setting-item" style="opacity: 0.5;">
                    <div class="setting-info">
                        <div class="setting-title">Accent Color</div>
                        <div class="setting-description" style="color: #f59e0b;">Requires User role or higher. <a href="role_ranking.php" style="color: #4ECDC4;">Learn about roles</a></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Font Size</div>
                        <div class="setting-description">Adjust the text size for better readability</div>
                    </div>
                    <div class="setting-controls">
                        <input type="range" min="12" max="24" value="16" class="setting-range" id="fontSizeSlider">
                        <span class="range-value" id="fontSizeValue">16px</span>
                    </div>
                </div>

                <?php if ($user_role !== 'community'): ?>
                <!-- Random Background Button -->
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Random Background</div>
                        <div class="setting-description">Shuffle to a random approved designer background</div>
                    </div>
                    <div class="setting-controls">
                        <button type="button" id="randomBgBtn" class="game-button" onclick="applyRandomBackground()" style="padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-shuffle" id="shuffleIcon"></i> Shuffle
                        </button>
                    </div>
                </div>

                <!-- Designer Background Gallery -->
                <div class="setting-item" style="flex-direction:column;align-items:stretch;gap:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:20px;margin-top:4px;">
                    <div class="setting-info">
                        <div class="setting-title"><i class="fa-solid fa-images" style="color:#4ECDC4;margin-right:6px;"></i>Designer Background Gallery</div>
                        <div class="setting-description">Choose from community-approved backgrounds. Click one to apply it instantly.</div>
                    </div>
                    <?php if (!empty($available_backgrounds)): ?>
                    <div class="bg-gallery" id="bgGallery">
                        <?php foreach ($available_backgrounds as $bg): ?>
                        <div class="bg-gallery-card"
                             data-url="<?php echo htmlspecialchars($bg['image_url'], ENT_QUOTES); ?>"
                             data-title="<?php echo htmlspecialchars($bg['title'] ?? '', ENT_QUOTES); ?>"
                             onclick="selectGalleryBackground(this)">
                            <div class="bg-gallery-thumb" style="background-image:url('<?php echo htmlspecialchars($bg['image_url'], ENT_QUOTES); ?>')"></div>
                            <div class="bg-gallery-info">
                                <div class="bg-gallery-name" title="<?php echo htmlspecialchars($bg['title'] ?? '', ENT_QUOTES); ?>"><?php echo htmlspecialchars($bg['title'] ?? 'Untitled'); ?></div>
                                <div class="bg-gallery-by">by <?php echo htmlspecialchars($bg['designer_name'] ?? 'Unknown'); ?></div>
                            </div>
                            <div class="bg-gallery-check"><i class="fa-solid fa-check"></i></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:4px;">
                        <button type="button" class="set-btn set-btn-secondary" onclick="clearGalleryBackground()">
                            <i class="fa-solid fa-xmark"></i> Remove Background
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="bg-gallery-empty">No approved designer backgrounds available yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Custom Background (merged from Background Theme) -->
                <div class="setting-item" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:20px;">
                    <div class="setting-info">
                        <div class="setting-title">Custom Background Image</div>
                        <div class="setting-description">Enter a URL for your custom background image (leave empty for default)</div>
                    </div>
                </div>

                <div class="setting-item" style="flex-direction: column; align-items: stretch; gap: 15px;">
                    <div style="display: flex; gap: 10px; width: 100%;">
                        <input type="text" id="customBackgroundUrl" class="form-input"
                               placeholder="https://example.com/image.jpg"
                               style="flex: 1; padding: 12px; background: rgba(15, 23, 42, 0.8); border: 2px solid rgba(78, 205, 196, 0.3); border-radius: 10px; color: white; font-size: 14px;">
                        <button type="button" id="applyBackgroundBtn" class="game-button" style="padding: 12px 20px; white-space: nowrap;">
                            Apply
                        </button>
                        <button type="button" id="clearBackgroundBtn" class="game-button" style="padding: 12px 20px; white-space: nowrap; background: linear-gradient(45deg, #ef4444, #dc2626);">
                            Reset
                        </button>
                    </div>
                    <div id="backgroundPreview" style="width: 100%; height: 150px; border-radius: 10px; border: 2px solid rgba(78, 205, 196, 0.3); background-size: cover; background-position: center; background-color: rgba(15, 23, 42, 0.8); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <span id="previewText" style="color: #94a3b8; font-size: 14px;">Background preview will appear here</span>
                    </div>
                    <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                        Tip: Use direct image URLs (ending in .jpg, .png, .gif, .webp). For best results, use images at least 1920x1080 pixels.
                    </p>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Background Blur</div>
                        <div class="setting-description">Apply a blur effect to the background image</div>
                    </div>
                    <div class="setting-controls">
                        <input type="range" min="0" max="20" value="0" class="setting-range" id="backgroundBlurSlider">
                        <span class="range-value" id="backgroundBlurValue">0px</span>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Background Opacity</div>
                        <div class="setting-description">Adjust background image brightness</div>
                    </div>
                    <div class="setting-controls">
                        <input type="range" min="10" max="100" value="100" class="setting-range" id="backgroundOpacitySlider">
                        <span class="range-value" id="backgroundOpacityValue">100%</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Theme Presets -->
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
                    <div class="setting-title" style="margin-bottom:12px;">Theme Presets</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">
                        <button class="set-btn set-btn-secondary" onclick="applyThemePreset('dark')" style="padding:14px 10px;text-align:center;flex-direction:column;"><i class="fa-solid fa-moon"></i> Dark</button>
                        <button class="set-btn set-btn-secondary" onclick="applyThemePreset('midnight')" style="padding:14px 10px;text-align:center;flex-direction:column;"><i class="fa-solid fa-star"></i> Midnight</button>
                        <button class="set-btn set-btn-secondary" onclick="applyThemePreset('ocean')" style="padding:14px 10px;text-align:center;flex-direction:column;"><i class="fa-solid fa-water"></i> Ocean</button>
                        <button class="set-btn set-btn-secondary" onclick="applyThemePreset('forest')" style="padding:14px 10px;text-align:center;flex-direction:column;"><i class="fa-solid fa-tree"></i> Forest</button>
                    </div>
                </div>

                <!-- Animations -->
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Enable Animations</div>
                        <div class="setting-description">Toggle page animations and transitions</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="animationsEnabled" checked><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Layout Density</div>
                        <div class="setting-description">Compact or comfortable spacing</div>
                    </div>
                    <div class="setting-controls">
                        <select class="setting-select" id="layoutDensity"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Game Volume</div>
                        <div class="setting-description">Adjust the volume for game audio</div>
                    </div>
                    <div class="setting-controls">
                        <input type="range" min="0" max="100" value="80" class="setting-range" id="gameVolumeSlider">
                        <span class="range-value" id="gameVolumeValue">80%</span>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Autoplay Videos</div>
                        <div class="setting-description">Automatically play videos when page loads</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="autoplayToggle"><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Fullscreen Mode</div>
                        <div class="setting-description">Enable fullscreen for games by default</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="fullscreenToggle"><span class="toggle-slider"></span></label>
                    </div>
                </div>
            </div>

            <!-- === NOTIFICATIONS === -->
            <div class="set-section" id="sec-notifications">
                <h2 class="set-section-title">Notifications</h2>
                <p class="set-section-desc">Manage how you receive notifications.</p>
                <div style="text-align:center;padding:40px 20px;color:#475569;">
                    <i class="fa-solid fa-bell" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:10px;"></i>
                    <p>Notification settings coming soon.</p>
                </div>
            </div>

            <!-- === CHAT & YAP === -->
            <div class="set-section" id="sec-chat-yap">
                <h2 class="set-section-title">Chat & Yap</h2>
                <p class="set-section-desc">Customize your chat experience and AI persona.</p>

                <?php if ($user_role !== 'community'): ?>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Yap Session Nickname</div>
                        <div class="setting-description">This nickname appears during Yap sessions only. It has no effect on your username anywhere else on the platform.</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                    <button type="button" class="name-tag-preset set-btn set-btn-secondary" data-tag="" style="padding:6px 14px;">Default Role</button>
                    <button type="button" class="name-tag-preset set-btn set-btn-secondary" data-tag="Legend" style="padding:6px 14px;">Legend</button>
                    <button type="button" class="name-tag-preset set-btn set-btn-secondary" data-tag="VIP" style="padding:6px 14px;">VIP</button>
                    <button type="button" class="name-tag-preset set-btn set-btn-secondary" data-tag="Pro" style="padding:6px 14px;">Pro</button>
                    <button type="button" class="name-tag-preset set-btn set-btn-secondary" data-tag="Elite" style="padding:6px 14px;">Elite</button>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:8px;">
                    <input type="text" id="customNameTag" placeholder="Or enter custom tag (max 20 chars)" maxlength="20" style="flex:1;padding:10px 12px;background:rgba(15,23,42,0.8);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e2e8f0;font-size:0.88rem;">
                    <button type="button" id="saveNameTagBtn" class="set-btn set-btn-primary">Save Tag</button>
                </div>
                <p style="color:#64748b;font-size:0.8rem;margin:0 0 20px;">Preview: <span id="tagPreview" style="color:#f59e0b;font-weight:600;">&#11088; Your Tag</span></p>

                <!-- AI Persona -->
                <div style="padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
                    <div class="setting-title" style="margin-bottom:4px;">Custom AI Persona</div>
                    <div class="setting-description" style="margin-bottom:14px;">Create a custom AI persona for the AI Panel.</div>
                    <div class="setting-item" style="flex-direction:column;align-items:stretch;gap:8px;border-bottom:none;">
                        <input type="text" id="aiPersonaName" maxlength="30" value="<?php echo htmlspecialchars($existingPersona['name'] ?? ''); ?>" placeholder="Persona name (max 30 chars)" style="padding:10px 12px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e2e8f0;font-size:0.88rem;">
                        <textarea id="aiPersonaPrompt" maxlength="500" rows="3" placeholder="System prompt (max 500 chars)..." style="padding:10px 12px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e2e8f0;font-size:0.88rem;resize:vertical;font-family:inherit;min-height:70px;"><?php echo htmlspecialchars($existingPersona['prompt'] ?? ''); ?></textarea>
                        <div id="aiPromptCounter" style="text-align:right;font-size:0.72rem;color:#475569;"><?php echo strlen($existingPersona['prompt'] ?? ''); ?> / 500</div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px;">
                        <button class="set-btn set-btn-primary" id="btnSavePersona" onclick="saveCustomPersona()"><i class="fa-solid fa-save"></i> Save Persona</button>
                        <?php if ($existingPersona): ?>
                        <button class="set-btn set-btn-danger" id="btnDeletePersona" onclick="deleteCustomPersona()"><i class="fa-solid fa-trash"></i> Remove</button>
                        <?php endif; ?>
                    </div>
                    <div id="aiPersonaStatus" style="margin-top:8px;font-size:0.82rem;min-height:1.2em;"></div>
                </div>
                <?php else: ?>
                <p style="color:#64748b;">Chat customization requires a User role or higher.</p>
                <?php endif; ?>
            </div>

            <!-- === PRIVACY === -->
            <div class="set-section" id="sec-privacy">
                <h2 class="set-section-title">Privacy</h2>
                <p class="set-section-desc">Control your visibility and data preferences.</p>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Hide from User Directory</div>
                        <div class="setting-description">Your profile won't appear in the public user list. Admins can still see you.</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="hideProfileToggle" onchange="togglePrivacy(this.checked)"><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div id="privacyMsg" style="font-size:0.78rem;min-height:1.2em;margin:-6px 0 8px 0;"></div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Dark / Light Mode</div>
                        <div class="setting-description">Switch between dark and light theme</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="darkModeToggle" checked onchange="toggleDarkMode(this.checked)"><span class="toggle-slider"></span></label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Timezone</div>
                        <div class="setting-description">Set your local timezone for accurate timestamps</div>
                    </div>
                    <div class="setting-controls">
                        <select id="timezoneSelect" onchange="saveTimezone(this.value)" class="setting-select">
                            <option value="America/New_York">Eastern (ET)</option>
                            <option value="America/Chicago" selected>Central (CT)</option>
                            <option value="America/Denver">Mountain (MT)</option>
                            <option value="America/Los_Angeles">Pacific (PT)</option>
                            <option value="America/Anchorage">Alaska (AKT)</option>
                            <option value="Pacific/Honolulu">Hawaii (HT)</option>
                            <option value="Europe/London">London (GMT)</option>
                            <option value="Europe/Paris">Paris (CET)</option>
                            <option value="Europe/Berlin">Berlin (CET)</option>
                            <option value="Asia/Tokyo">Tokyo (JST)</option>
                            <option value="Asia/Shanghai">Shanghai (CST)</option>
                            <option value="Asia/Kolkata">India (IST)</option>
                            <option value="Australia/Sydney">Sydney (AEST)</option>
                        </select>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Active Sessions</div>
                        <div class="setting-description">Session expires after 8 hours of inactivity.</div>
                    </div>
                    <div class="setting-controls">
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.82rem;color:#22c55e;"><span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Active Now</span>
                    </div>
                </div>
            </div>

            <!-- === SECURITY === -->
            <div class="set-section" id="sec-security">
                <h2 class="set-section-title">Security</h2>
                <p class="set-section-desc">Login, session, and cache management.</p>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Remember Login</div>
                        <div class="setting-description">Keep me logged in for 7 days</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="rememberLoginToggle"><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Auto Logout</div>
                        <div class="setting-description">Automatically log out after 30 minutes of inactivity</div>
                    </div>
                    <div class="setting-controls">
                        <label class="toggle-switch"><input type="checkbox" id="autoLogoutToggle"><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Clear Browsing Data</div>
                        <div class="setting-description">Remove all stored data including login information</div>
                    </div>
                    <div class="setting-controls">
                        <button class="set-btn set-btn-secondary" id="clearDataBtn"><i class="fa-solid fa-eraser"></i> Clear Data</button>
                    </div>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Clear Browser Cache</div>
                        <div class="setting-description">Clear localStorage and sessionStorage for this site</div>
                    </div>
                    <div class="setting-controls">
                        <button class="set-btn set-btn-secondary" id="clearCacheBtn" onclick="clearBrowserCache()"><i class="fa-solid fa-broom"></i> Clear Cache</button>
                    </div>
                </div>
            </div>

            <!-- === DANGER ZONE === -->
            <div class="set-section" id="sec-danger">
                <h2 class="set-section-title" style="color:#ef4444;">Danger Zone</h2>
                <p class="set-section-desc">Irreversible actions. Proceed with caution.</p>

                <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:20px;">
                    <div class="setting-item" style="border-bottom:none;">
                        <div class="setting-info">
                            <div class="setting-title" style="color:#f87171;">Reset All Settings</div>
                            <div class="setting-description">Reset all settings to their default values. This cannot be undone.</div>
                        </div>
                        <div class="setting-controls">
                            <button class="set-btn set-btn-danger" id="resetSettingsBtn"><i class="fa-solid fa-trash-can"></i> Reset All</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.set-content -->
    </div><!-- /.set-wrap -->

    <!-- Confirmation Dialog -->
    <div class="confirmation-dialog" id="confirmationDialog">
        <div class="confirmation-content">
            <h3>Are you sure?</h3>
            <p id="confirmationMessage">This action cannot be undone.</p>
            <div class="confirmation-buttons">
                <button class="set-btn set-btn-secondary" id="confirmCancel">Cancel</button>
                <button class="set-btn set-btn-danger" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="set-toast" id="setToast"></div>

    <!-- Status Message (legacy compat) -->
    <div class="status-message" id="statusMessage" style="display:none;"></div>

    <script>
        // v7.0: Sidebar nav section switching
        function setSection(name, el) {
            document.querySelectorAll('.set-section').forEach(function(s) { s.classList.remove('active'); });
            document.querySelectorAll('.set-nav-item').forEach(function(n) { n.classList.remove('active'); });
            var sec = document.getElementById('sec-' + name);
            if (sec) sec.classList.add('active');
            if (el) el.classList.add('active');
        }

        // v7.0: Toast notification
        function showToast(message, isError) {
            var t = document.getElementById('setToast');
            if (!t) return;
            t.textContent = message;
            t.className = 'set-toast show ' + (isError ? 'err' : 'ok');
            clearTimeout(t._timer);
            t._timer = setTimeout(function() { t.className = 'set-toast'; }, 2500);
        }

        // Legacy: collapsible category toggle (kept for compat)
        function toggleCategory(header) {
            var body = header.nextElementSibling;
            header.classList.toggle('open');
            body.classList.toggle('open');
        }

        // Designer Background Gallery
        function selectGalleryBackground(card) {
            var url = card.dataset.url;
            var title = card.dataset.title || 'Background';
            if (!url) return;
            document.querySelectorAll('.bg-gallery-card').forEach(function(c) { c.classList.remove('active'); });
            card.classList.add('active');
            if (window.settingsManager) {
                window.settingsManager.settings.customBackground = url;
                window.settingsManager.applyBackgroundTheme();
                window.settingsManager.saveSettings();
                var urlInput = document.getElementById('customBackgroundUrl');
                if (urlInput) urlInput.value = url;
            }
            showToast('Background set: ' + title);
        }

        function clearGalleryBackground() {
            document.querySelectorAll('.bg-gallery-card').forEach(function(c) { c.classList.remove('active'); });
            if (window.settingsManager) {
                window.settingsManager.settings.customBackground = '';
                window.settingsManager.applyBackgroundTheme();
                window.settingsManager.saveSettings();
                var urlInput = document.getElementById('customBackgroundUrl');
                if (urlInput) urlInput.value = '';
            }
            showToast('Background removed');
        }

        function markActiveGalleryCard() {
            var current = '';
            if (window.settingsManager) {
                current = window.settingsManager.settings.customBackground || '';
            } else {
                try { current = (JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}')).customBackground || ''; } catch(e) {}
            }
            document.querySelectorAll('.bg-gallery-card').forEach(function(c) {
                c.classList.toggle('active', !!current && c.dataset.url === current);
            });
        }
        document.addEventListener('DOMContentLoaded', function() { setTimeout(markActiveGalleryCard, 700); });

        // v7.0: Privacy toggle - hide from user directory
        async function togglePrivacy(hidden) {
            const msg = document.getElementById('privacyMsg');
            msg.textContent = 'Saving...'; msg.style.color = '#94a3b8';
            try {
                const resp = await fetch('set.php', { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=toggle_privacy&is_hidden=' + (hidden ? '1' : '0') + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo htmlspecialchars($csrfToken ?? ''); ?>') });
                const data = await resp.json();
                msg.textContent = data.message || (data.success ? 'Saved' : 'Failed');
                msg.style.color = data.success ? '#10b981' : '#ef4444';
            } catch(e) { msg.textContent = 'Error'; msg.style.color = '#ef4444'; }
        }

        // v7.0: Dark/Light mode toggle
        function toggleDarkMode(isDark) {
            let s = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            s.darkMode = isDark;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(s));
            document.body.style.filter = isDark ? '' : 'invert(0.88) hue-rotate(180deg)';
            document.querySelectorAll('img, video, iframe').forEach(el => { el.style.filter = isDark ? '' : 'invert(1) hue-rotate(180deg)'; });
        }
        // Apply saved dark mode on load
        (function(){ var s = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}'); if (s.darkMode === false) { toggleDarkMode(false); document.getElementById('darkModeToggle').checked = false; } })();

        // v7.0: Timezone selector
        function saveTimezone(tz) {
            let s = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            s.timezone = tz;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(s));
        }
        (function(){ var s = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}'); if (s.timezone) { var sel = document.getElementById('timezoneSelect'); if (sel) sel.value = s.timezone; } })();

        // v7.0: Collapsible category toggle
        function toggleCategory(header) {
            const body = header.nextElementSibling;
            header.classList.toggle('open');
            body.classList.toggle('open');
        }

        // v7.0: AI Persona character counter
        const aiPromptEl = document.getElementById('aiPersonaPrompt');
        const aiCounterEl = document.getElementById('aiPromptCounter');
        if (aiPromptEl && aiCounterEl) {
            aiPromptEl.addEventListener('input', function() {
                const len = this.value.length;
                aiCounterEl.textContent = len + ' / 500';
                aiCounterEl.style.color = len > 450 ? (len >= 500 ? '#ef4444' : '#f59e0b') : '#6b7280';
            });
        }

        // v7.0: Save custom AI persona
        async function saveCustomPersona() {
            const nameEl = document.getElementById('aiPersonaName');
            const promptEl = document.getElementById('aiPersonaPrompt');
            const statusEl = document.getElementById('aiPersonaStatus');
            const btn = document.getElementById('btnSavePersona');

            const name = (nameEl?.value || '').trim();
            const prompt = (promptEl?.value || '').trim();

            if (!name || name.length > 30) {
                statusEl.textContent = 'Persona name must be 1–30 characters.';
                statusEl.style.color = '#ef4444';
                return;
            }
            if (!prompt || prompt.length > 500) {
                statusEl.textContent = 'System prompt must be 1–500 characters.';
                statusEl.style.color = '#ef4444';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            statusEl.textContent = '';

            try {
                const body = new URLSearchParams({
                    action: 'save_custom_persona',
                    csrf_token: csrfToken,
                    persona_name: name,
                    persona_prompt: prompt
                });
                const resp = await fetch('set.php', { method: 'POST', credentials: 'same-origin', body });
                const data = await resp.json();
                statusEl.textContent = data.message || (data.success ? 'Saved!' : 'Error');
                statusEl.style.color = data.success ? '#10b981' : '#ef4444';
            } catch (err) {
                statusEl.textContent = 'Network error. Please try again.';
                statusEl.style.color = '#ef4444';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-save"></i> Save Persona';
        }

        // v7.0: Delete custom AI persona
        async function deleteCustomPersona() {
            if (!confirm('Remove your custom AI persona?')) return;
            const statusEl = document.getElementById('aiPersonaStatus');
            try {
                const body = new URLSearchParams({
                    action: 'delete_custom_persona',
                    csrf_token: csrfToken
                });
                const resp = await fetch('set.php', { method: 'POST', credentials: 'same-origin', body });
                const data = await resp.json();
                if (data.success) {
                    document.getElementById('aiPersonaName').value = '';
                    document.getElementById('aiPersonaPrompt').value = '';
                    const deleteBtn = document.getElementById('btnDeletePersona');
                    if (deleteBtn) deleteBtn.style.display = 'none';
                    statusEl.textContent = 'Persona removed.';
                    statusEl.style.color = '#10b981';
                }
            } catch (err) {
                statusEl.textContent = 'Network error.';
                statusEl.style.color = '#ef4444';
            }
        }

        // v5.1: CSRF Token for secure requests
        const csrfToken = '<?php echo htmlspecialchars(generateCsrfToken()); ?>';

        // Settings Manager - Enhanced with Server Sync
        class SettingsManager {
            constructor() {
                this.defaultSettings = {
                    accentColor: '4ECDC4',
                    fontSize: 16,
                    rememberLogin: true,
                    autoLogout: false,
                    gameVolume: 80,
                    fullscreenMode: false,
                    autoplayVideos: false,
                    customBackground: '',
                    backgroundBlur: 0,
                    backgroundOpacity: 100,
                    nameTag: ''
                };

                this.settings = { ...this.defaultSettings };
                this.syncTimeout = null;
                this.userId = document.body.dataset.userId || 0;
                this.userRole = document.body.dataset.userRole || 'community';
                this.canSync = document.body.dataset.canSync === 'true';
                this.isLoading = false; // v5.1: Loading state

                this.init();
            }

            async init() {
                // Load from localStorage first (instant)
                this.loadFromLocal();
                this.applySettings();
                this.setupEventListeners();

                // Then sync with server (async)
                await this.loadFromServer();
                this.applySettings();
            }

            // Load settings from localStorage (instant)
            loadFromLocal() {
                try {
                    const saved = localStorage.getItem('spencerWebsiteSettings');
                    if (saved) {
                        this.settings = { ...this.defaultSettings, ...JSON.parse(saved) };
                    }
                } catch (e) {
                    console.error('Error loading local settings:', e);
                }
            }

            // Save to localStorage
            saveToLocal() {
                try {
                    localStorage.setItem('spencerWebsiteSettings', JSON.stringify(this.settings));
                } catch (e) {
                    console.error('Error saving local settings:', e);
                }
            }

            // Load settings from server (only for non-community roles)
            async loadFromServer() {
                if (!this.canSync) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'load_settings');
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('set.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });

                    const data = await response.json();
                    if (data.success && data.settings && Object.keys(data.settings).length > 0) {
                        this.settings = { ...this.defaultSettings, ...data.settings };
                        this.saveToLocal(); // Update local cache
                    }
                } catch (e) {
                    console.error('Error loading server settings:', e);
                }
            }

            // Save to server (debounced, only for non-community roles)
            saveToServer() {
                if (!this.canSync) return;

                // Debounce server saves
                clearTimeout(this.syncTimeout);
                this.syncTimeout = setTimeout(async () => {
                    this.showSyncIndicator(true);

                    try {
                        const formData = new FormData();
                        formData.append('action', 'save_settings');
                        formData.append('settings', JSON.stringify(this.settings));
                        formData.append('csrf_token', csrfToken);

                        const response = await fetch('set.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        });

                        const data = await response.json();
                        if (!data.success) {
                            console.error('Server save failed:', data.message);
                        }
                    } catch (e) {
                        console.error('Error saving to server:', e);
                    } finally {
                        this.showSyncIndicator(false);
                    }
                }, 1000);
            }

            // Save settings (local + server)
            saveSettings() {
                this.saveToLocal();
                this.saveToServer();
                this.showStatusMessage('Settings saved!');
                this.applyAccentColor();
                this.applyBackgroundTheme();
            }

            // Apply all settings to page
            applySettings() {
                // Apply font size
                document.documentElement.style.fontSize = `${this.settings.fontSize}px`;

                // Apply accent color
                this.applyAccentColor();

                // Apply background theme
                this.applyBackgroundTheme();

                // Update UI controls
                const fontSlider = document.getElementById('fontSizeSlider');
                const fontValue = document.getElementById('fontSizeValue');
                if (fontSlider) fontSlider.value = this.settings.fontSize;
                if (fontValue) fontValue.textContent = `${this.settings.fontSize}px`;

                const rememberLogin = document.getElementById('rememberLoginToggle');
                if (rememberLogin) rememberLogin.checked = this.settings.rememberLogin;

                const autoLogout = document.getElementById('autoLogoutToggle');
                if (autoLogout) autoLogout.checked = this.settings.autoLogout;

                const volumeSlider = document.getElementById('gameVolumeSlider');
                const volumeValue = document.getElementById('gameVolumeValue');
                if (volumeSlider) volumeSlider.value = this.settings.gameVolume;
                if (volumeValue) volumeValue.textContent = `${this.settings.gameVolume}%`;

                const fullscreen = document.getElementById('fullscreenToggle');
                if (fullscreen) fullscreen.checked = this.settings.fullscreenMode;

                const autoplay = document.getElementById('autoplayToggle');
                if (autoplay) autoplay.checked = this.settings.autoplayVideos;

                // Update custom background controls
                const bgUrl = document.getElementById('customBackgroundUrl');
                if (bgUrl) bgUrl.value = this.settings.customBackground || '';

                const bgBlurSlider = document.getElementById('backgroundBlurSlider');
                const bgBlurValue = document.getElementById('backgroundBlurValue');
                if (bgBlurSlider) bgBlurSlider.value = this.settings.backgroundBlur;
                if (bgBlurValue) bgBlurValue.textContent = `${this.settings.backgroundBlur}px`;

                const bgOpacitySlider = document.getElementById('backgroundOpacitySlider');
                const bgOpacityValue = document.getElementById('backgroundOpacityValue');
                if (bgOpacitySlider) bgOpacitySlider.value = this.settings.backgroundOpacity;
                if (bgOpacityValue) bgOpacityValue.textContent = `${this.settings.backgroundOpacity}%`;

                // Update background preview
                this.updateBackgroundPreview();

                // Update color options
                document.querySelectorAll('.color-option').forEach(opt => {
                    opt.classList.toggle('active', opt.dataset.color === this.settings.accentColor);
                });
            }

            // Update background preview
            updateBackgroundPreview() {
                const preview = document.getElementById('backgroundPreview');
                const previewText = document.getElementById('previewText');

                if (preview && this.settings.customBackground) {
                    preview.style.backgroundImage = `url(${this.settings.customBackground})`;
                    preview.style.filter = `blur(${this.settings.backgroundBlur}px) brightness(${this.settings.backgroundOpacity / 100})`;
                    if (previewText) previewText.style.display = 'none';
                } else if (preview) {
                    preview.style.backgroundImage = '';
                    preview.style.filter = '';
                    if (previewText) previewText.style.display = 'block';
                }
            }

            // Apply accent color
            applyAccentColor() {
                const color = `#${this.settings.accentColor}`;
                document.documentElement.style.setProperty('--accent-color', color);

                let styleEl = document.getElementById('accent-color-styles');
                if (!styleEl) {
                    styleEl = document.createElement('style');
                    styleEl.id = 'accent-color-styles';
                    document.head.appendChild(styleEl);
                }

                styleEl.textContent = `
                    .game-button, .move-button, .info-button {
                        background: linear-gradient(45deg, #FF6B6B, ${color}) !important;
                    }
                    .game-button:hover, .move-button:hover, .info-button:hover {
                        background: linear-gradient(45deg, ${color}, #FF6B6B) !important;
                    }
                    .centered-box { border-color: ${color} !important; }
                    .centered-box:hover { border-color: #FF6B6B !important; }
                    .settings-category { border-color: rgba(${parseInt(color.slice(1,3),16)}, ${parseInt(color.slice(3,5),16)}, ${parseInt(color.slice(5,7),16)}, 0.2) !important; }
                    .settings-category:hover { border-color: rgba(${parseInt(color.slice(1,3),16)}, ${parseInt(color.slice(3,5),16)}, ${parseInt(color.slice(5,7),16)}, 0.4) !important; }
                    .category-header h2 { color: ${color} !important; }
                    .toggle-switch input:checked + .toggle-slider { background-color: ${color} !important; }
                    .setting-range::-webkit-slider-thumb { background: ${color} !important; }
                    .setting-range::-moz-range-thumb { background: ${color} !important; }
                    .range-value { color: ${color} !important; }
                    .page-header h1 {
                        background: linear-gradient(45deg, #FF6B6B, ${color}) !important;
                        -webkit-background-clip: text !important;
                        -webkit-text-fill-color: transparent !important;
                        background-clip: text !important;
                    }
                `;
            }

            // Apply background theme
            applyBackgroundTheme() {
                const bgOverride = document.getElementById('bgThemeOverride');
                if (!bgOverride) return;

                if (this.settings.customBackground) {
                    bgOverride.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: -1;
                        background-image: url(${this.settings.customBackground});
                        background-size: cover;
                        background-position: center;
                        background-attachment: fixed;
                        filter: blur(${this.settings.backgroundBlur}px) brightness(${this.settings.backgroundOpacity / 100});
                    `;
                } else {
                    bgOverride.style.cssText = '';
                }

                // Update preview
                this.updateBackgroundPreview();
            }

            // Reset all settings
            async resetSettings() {
                this.settings = { ...this.defaultSettings };
                this.saveToLocal();

                if (this.canSync) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'reset_settings');
                        formData.append('csrf_token', csrfToken);
                        await fetch('set.php', { method: 'POST', credentials: 'same-origin', body: formData });
                    } catch (e) {
                        console.error('Error resetting server settings:', e);
                    }
                }

                this.applySettings();
            }

            // v7.0: Export settings as JSON file
            exportSettings() {
                const dataStr = JSON.stringify(this.settings, null, 2);
                const blob = new Blob([dataStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `spencer-settings-${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showStatusMessage('Settings exported!');
            }

            // v7.0: Import settings from JSON file
            importSettings(file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const imported = JSON.parse(e.target.result);

                        // Validate that imported data has at least some known keys
                        const knownKeys = Object.keys(this.defaultSettings);
                        const importedKeys = Object.keys(imported);
                        const validKeys = importedKeys.filter(k => knownKeys.includes(k));

                        if (validKeys.length === 0) {
                            this.showStatusMessage('Invalid settings file. No recognized settings found.', true);
                            return;
                        }

                        // Merge imported settings with defaults (only known keys)
                        const merged = { ...this.defaultSettings };
                        validKeys.forEach(key => {
                            merged[key] = imported[key];
                        });

                        this.settings = merged;
                        this.saveSettings();
                        this.applySettings();
                        this.showStatusMessage(`Imported ${validKeys.length} settings successfully!`);
                    } catch (err) {
                        console.error('Error importing settings:', err);
                        this.showStatusMessage('Failed to import settings. Invalid JSON file.', true);
                    }
                };
                reader.readAsText(file);
            }

            // v5.1: Validate image URL on server
            async validateImageUrl(url) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'validate_image_url');
                    formData.append('url', url);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('set.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });

                    return await response.json();
                } catch (e) {
                    console.error('Error validating URL:', e);
                    return { success: false, message: 'Network error' };
                }
            }

            // v5.1: Set loading state on a button
            setButtonLoading(btn, isLoading, originalText = 'Apply') {
                if (isLoading) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading-spinner"></span> Checking...';
                } else {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }

            // Setup all event listeners
            setupEventListeners() {
                // Font size slider
                const fontSlider = document.getElementById('fontSizeSlider');
                if (fontSlider) {
                    fontSlider.addEventListener('input', (e) => {
                        this.settings.fontSize = parseInt(e.target.value);
                        document.getElementById('fontSizeValue').textContent = `${this.settings.fontSize}px`;
                        document.documentElement.style.fontSize = `${this.settings.fontSize}px`;
                        this.saveSettings();
                    });
                }

                // Color options
                document.querySelectorAll('.color-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        this.settings.accentColor = opt.dataset.color;
                        this.saveSettings();
                        this.applySettings();
                    });
                });

                // Toggle switches
                const toggles = [
                    { id: 'rememberLoginToggle', key: 'rememberLogin' },
                    { id: 'autoLogoutToggle', key: 'autoLogout' },
                    { id: 'fullscreenToggle', key: 'fullscreenMode' },
                    { id: 'autoplayToggle', key: 'autoplayVideos' }
                ];

                toggles.forEach(({ id, key }) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.addEventListener('change', (e) => {
                            this.settings[key] = e.target.checked;
                            this.saveSettings();
                        });
                    }
                });

                // Game volume slider
                const volumeSlider = document.getElementById('gameVolumeSlider');
                if (volumeSlider) {
                    volumeSlider.addEventListener('input', (e) => {
                        this.settings.gameVolume = parseInt(e.target.value);
                        document.getElementById('gameVolumeValue').textContent = `${this.settings.gameVolume}%`;
                        this.saveSettings();
                    });
                }

                // Background blur slider
                const bgBlurSlider = document.getElementById('backgroundBlurSlider');
                if (bgBlurSlider) {
                    bgBlurSlider.addEventListener('input', (e) => {
                        this.settings.backgroundBlur = parseInt(e.target.value);
                        document.getElementById('backgroundBlurValue').textContent = `${this.settings.backgroundBlur}px`;
                        this.applyBackgroundTheme();
                        this.saveSettings();
                    });
                }

                // Background opacity slider
                const bgOpacitySlider = document.getElementById('backgroundOpacitySlider');
                if (bgOpacitySlider) {
                    bgOpacitySlider.addEventListener('input', (e) => {
                        this.settings.backgroundOpacity = parseInt(e.target.value);
                        document.getElementById('backgroundOpacityValue').textContent = `${this.settings.backgroundOpacity}%`;
                        this.applyBackgroundTheme();
                        this.saveSettings();
                    });
                }

                // Apply background button - v5.1: Enhanced with URL validation and loading state
                const applyBgBtn = document.getElementById('applyBackgroundBtn');
                if (applyBgBtn) {
                    applyBgBtn.addEventListener('click', async () => {
                        const url = document.getElementById('customBackgroundUrl').value.trim();
                        if (!url) {
                            this.showStatusMessage('Please enter an image URL.', true);
                            return;
                        }

                        // Basic URL format check first
                        try {
                            new URL(url);
                        } catch (e) {
                            this.showStatusMessage('Invalid URL format.', true);
                            return;
                        }

                        // Show loading state
                        this.setButtonLoading(applyBgBtn, true);
                        this.showStatusMessage('Validating image...');

                        // First, try to load the image in the browser for preview
                        const img = new Image();
                        img.crossOrigin = 'anonymous';

                        const loadPromise = new Promise((resolve) => {
                            img.onload = () => resolve({ loaded: true });
                            img.onerror = () => resolve({ loaded: false });
                            setTimeout(() => resolve({ loaded: false, timeout: true }), 10000);
                        });

                        img.src = url;
                        const result = await loadPromise;

                        if (result.loaded) {
                            // Image loaded successfully
                            this.settings.customBackground = url;
                            this.applyBackgroundTheme();
                            this.saveSettings();
                            this.showStatusMessage('Background applied!');
                        } else {
                            // Image failed to load in browser, but might still work
                            // Apply anyway but warn user
                            this.settings.customBackground = url;
                            this.applyBackgroundTheme();
                            this.saveSettings();
                            this.showStatusMessage('Background set. If it doesn\'t display, the URL may be blocked or invalid.');
                        }

                        this.setButtonLoading(applyBgBtn, false, 'Apply');
                    });
                }

                // Clear/Reset background button
                const clearBgBtn = document.getElementById('clearBackgroundBtn');
                if (clearBgBtn) {
                    clearBgBtn.addEventListener('click', () => {
                        this.settings.customBackground = '';
                        this.settings.backgroundBlur = 0;
                        this.settings.backgroundOpacity = 100;
                        document.getElementById('customBackgroundUrl').value = '';
                        document.getElementById('backgroundBlurSlider').value = 0;
                        document.getElementById('backgroundBlurValue').textContent = '0px';
                        document.getElementById('backgroundOpacitySlider').value = 100;
                        document.getElementById('backgroundOpacityValue').textContent = '100%';
                        this.applyBackgroundTheme();
                        this.saveSettings();
                        this.showStatusMessage('Background reset to default!');
                    });
                }

                // Clear data button
                const clearBtn = document.getElementById('clearDataBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', () => {
                        this.showConfirmDialog(
                            'This will clear all your browsing data including login information. This action cannot be undone.',
                            () => {
                                localStorage.clear();
                                this.showStatusMessage('All data cleared!');
                                setTimeout(() => window.location.href = 'index.php', 1500);
                            }
                        );
                    });
                }

                // Reset settings button
                const resetBtn = document.getElementById('resetSettingsBtn');
                if (resetBtn) {
                    resetBtn.addEventListener('click', () => {
                        this.showConfirmDialog(
                            'This will reset all your settings to their default values.',
                            async () => {
                                await this.resetSettings();
                                this.showStatusMessage('Settings reset!');
                            }
                        );
                    });
                }

                // v7.0: Export settings button
                const exportBtn = document.getElementById('exportSettingsBtn');
                if (exportBtn) {
                    exportBtn.addEventListener('click', () => {
                        this.exportSettings();
                    });
                }

                // v7.0: Import settings button and file input
                const importBtn = document.getElementById('importSettingsBtn');
                const importFile = document.getElementById('importSettingsFile');
                if (importBtn && importFile) {
                    importBtn.addEventListener('click', () => {
                        importFile.click();
                    });
                    importFile.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        if (file) {
                            this.importSettings(file);
                            // Reset file input so same file can be re-imported
                            importFile.value = '';
                        }
                    });
                }

                // Confirmation dialog buttons
                const cancelBtn = document.getElementById('confirmCancel');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', () => {
                        document.getElementById('confirmationDialog').style.display = 'none';
                    });
                }

                const dialog = document.getElementById('confirmationDialog');
                if (dialog) {
                    dialog.addEventListener('click', (e) => {
                        if (e.target === dialog) dialog.style.display = 'none';
                    });
                }

                // Name tag preset buttons
                document.querySelectorAll('.name-tag-preset').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const tag = btn.dataset.tag;
                        const tagInput = document.getElementById('customNameTag');
                        if (tagInput) tagInput.value = tag;
                        this.updateTagPreview(tag);
                    });
                });

                // Name tag input live preview
                const tagInput = document.getElementById('customNameTag');
                if (tagInput) {
                    tagInput.addEventListener('input', (e) => {
                        this.updateTagPreview(e.target.value);
                    });
                    // Load saved tag
                    const savedTag = this.settings.nameTag || '';
                    tagInput.value = savedTag;
                    this.updateTagPreview(savedTag);
                }

                // Save name tag button
                const saveTagBtn = document.getElementById('saveNameTagBtn');
                if (saveTagBtn) {
                    saveTagBtn.addEventListener('click', () => {
                        const tagInput = document.getElementById('customNameTag');
                        if (tagInput) {
                            this.settings.nameTag = tagInput.value.trim();
                            this.saveSettings();
                            this.showStatusMessage('Name tag saved!');
                        }
                    });
                }
            }

            // Update tag preview
            updateTagPreview(tag) {
                const preview = document.getElementById('tagPreview');
                if (preview) {
                    if (tag && tag.trim()) {
                        preview.textContent = `\u2B50 ${tag}`;
                    } else {
                        preview.textContent = '(Default role tag)';
                    }
                }
            }

            // Show confirmation dialog
            showConfirmDialog(message, callback) {
                const msgEl = document.getElementById('confirmationMessage');
                const dialog = document.getElementById('confirmationDialog');
                const confirmBtn = document.getElementById('confirmAction');

                if (msgEl) msgEl.textContent = message;
                if (dialog) dialog.style.display = 'flex';
                if (confirmBtn) {
                    confirmBtn.onclick = () => {
                        dialog.style.display = 'none';
                        callback();
                    };
                }
            }

            // Show status message (also fires toast for v7.0 layout)
            showStatusMessage(message, isError = false) {
                showToast(message, isError);
                const el = document.getElementById('statusMessage');
                if (!el) return;

                el.textContent = message;
                el.style.background = isError
                    ? 'linear-gradient(45deg, #e74c3c, #c0392b)'
                    : 'linear-gradient(45deg, #4ECDC4, #FF6B6B)';

                el.classList.add('show');
                setTimeout(() => el.classList.remove('show'), 3000);
            }

            // Show sync indicator
            showSyncIndicator(show) {
                const el = document.getElementById('syncIndicator');
                if (el) el.classList.toggle('show', show);
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.settingsManager = new SettingsManager();
        });

        // Accent color picker — live preview handlers
        function onAccentColorPicker(hex) {
            const hexInput = document.getElementById('accentHexInput');
            const preview  = document.getElementById('accentLivePreview');
            if (hexInput) hexInput.value = hex;
            if (preview)  preview.style.background = hex;
            if (window.settingsManager) {
                window.settingsManager.settings.accentColor = hex.replace('#', '');
                window.settingsManager.applyAccentColor();
                window.settingsManager.saveSettings();
            }
            // Deselect presets
            document.querySelectorAll('.color-option').forEach(o => o.classList.remove('active'));
            // Highlight if matches a preset
            document.querySelectorAll('.color-option').forEach(o => {
                if (('#' + o.dataset.color).toLowerCase() === hex.toLowerCase()) o.classList.add('active');
            });
        }

        function onAccentHexInput(val) {
            if (!/^#[0-9a-fA-F]{6}$/.test(val)) return;
            const picker  = document.getElementById('accentColorPicker');
            const preview = document.getElementById('accentLivePreview');
            if (picker)  picker.value = val;
            if (preview) preview.style.background = val;
            if (window.settingsManager) {
                window.settingsManager.settings.accentColor = val.replace('#', '');
                window.settingsManager.applyAccentColor();
                window.settingsManager.saveSettings();
            }
        }

        // Random background — shuffle approved designer backgrounds
        function applyRandomBackground() {
            const btn  = document.getElementById('randomBgBtn');
            const icon = document.getElementById('shuffleIcon');
            const allBgs = JSON.parse(document.body.dataset.backgrounds || '[]');
            if (!allBgs.length) {
                if (window.settingsManager) window.settingsManager.showStatusMessage('No approved backgrounds available.', true);
                return;
            }
            // Spin animation
            if (icon) { icon.style.transition = 'transform 0.5s ease'; icon.style.transform = 'rotate(360deg)'; }
            if (btn)  btn.disabled = true;
            setTimeout(() => {
                if (icon) { icon.style.transform = ''; icon.style.transition = ''; }
                if (btn)  btn.disabled = false;
            }, 600);

            const pick = allBgs[Math.floor(Math.random() * allBgs.length)];
            const url  = pick.image_url || '';
            if (!url) return;

            if (window.settingsManager) {
                window.settingsManager.settings.customBackground = url;
                const bgInput = document.getElementById('customBackgroundUrl');
                if (bgInput) bgInput.value = url;
                window.settingsManager.applyBackgroundTheme();
                window.settingsManager.saveSettings();
                window.settingsManager.showStatusMessage(`Random background: ${pick.title || 'Applied'}!`);
            }
        }

        // Theme presets
        function applyThemePreset(preset) {
            const presets = {
                dark:     { accent: '4ECDC4', blur: 0, opacity: 100 },
                midnight: { accent: '6366f1', blur: 2, opacity: 90 },
                ocean:    { accent: '06b6d4', blur: 3, opacity: 85 },
                forest:   { accent: '22c55e', blur: 2, opacity: 90 }
            };
            const p = presets[preset];
            if (!p) return;

            // Set accent color
            const colorOpt = document.querySelector('.color-option[data-color="' + p.accent + '"]');
            if (colorOpt) colorOpt.click();

            // Set blur
            const blurSlider = document.getElementById('backgroundBlurSlider');
            if (blurSlider) { blurSlider.value = p.blur; blurSlider.dispatchEvent(new Event('input')); }

            // Set opacity
            const opacitySlider = document.getElementById('backgroundOpacitySlider');
            if (opacitySlider) { opacitySlider.value = p.opacity; opacitySlider.dispatchEvent(new Event('input')); }

            // Show feedback
            const el = document.getElementById('statusMessage');
            if (el) {
                el.textContent = preset.charAt(0).toUpperCase() + preset.slice(1) + ' theme applied!';
                el.style.background = 'linear-gradient(45deg, #4ECDC4, #FF6B6B)';
                el.classList.add('show');
                setTimeout(() => el.classList.remove('show'), 2500);
            }
        }

        // Clear browser cache
        function clearBrowserCache() {
            try {
                localStorage.clear();
                sessionStorage.clear();
                const el = document.getElementById('statusMessage');
                if (el) {
                    el.textContent = 'Browser cache cleared!';
                    el.style.background = 'linear-gradient(45deg, #4ECDC4, #FF6B6B)';
                    el.classList.add('show');
                    setTimeout(() => el.classList.remove('show'), 2500);
                }
            } catch (e) {
                console.error('Clear cache failed:', e);
            }
        }

        // Download my data
        function downloadMyData() {
            const data = {
                exportDate: new Date().toISOString(),
                username: <?php echo json_encode($username); ?>,
                role: <?php echo json_encode($user_role); ?>,
                settings: {},
                localStorage: {}
            };

            // Gather localStorage
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                data.localStorage[key] = localStorage.getItem(key);
            }

            // Gather current settings
            if (window.settingsManager && window.settingsManager.settings) {
                data.settings = window.settingsManager.settings;
            }

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'spencers-website-data-' + new Date().toISOString().slice(0,10) + '.json';
            a.click();
            URL.revokeObjectURL(url);

            const el = document.getElementById('statusMessage');
            if (el) {
                el.textContent = 'Data downloaded!';
                el.style.background = 'linear-gradient(45deg, #4ECDC4, #FF6B6B)';
                el.classList.add('show');
                setTimeout(() => el.classList.remove('show'), 2500);
            }
        }

        // Animations toggle
        document.addEventListener('DOMContentLoaded', function() {
            const animToggle = document.getElementById('animationsEnabled');
            if (animToggle) {
                const savedAnim = localStorage.getItem('animationsEnabled');
                if (savedAnim === 'false') {
                    animToggle.checked = false;
                    document.documentElement.style.setProperty('--transition-speed', '0s');
                }
                animToggle.addEventListener('change', function() {
                    localStorage.setItem('animationsEnabled', this.checked);
                    document.documentElement.style.setProperty('--transition-speed', this.checked ? '0.3s' : '0s');
                });
            }

            const densitySelect = document.getElementById('layoutDensity');
            if (densitySelect) {
                const savedDensity = localStorage.getItem('layoutDensity');
                if (savedDensity) densitySelect.value = savedDensity;
                densitySelect.addEventListener('change', function() {
                    localStorage.setItem('layoutDensity', this.value);
                });
            }
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
