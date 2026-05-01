<?php
/**
 * Maintenance Mode Check - Spencer's Website v7.0
 *
 * Include this file early in pages that should respect maintenance mode.
 * Admin users will bypass maintenance mode and see a banner instead.
 *
 * Usage: require_once __DIR__ . '/includes/maintenance_check.php';
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Don't check maintenance mode for these pages
$maintenance_exempt_pages = [
    'index.php',
    'auth/login.php',
    'auth/logout.php',
    'admin.php',
    'errors/503.php'
];

$current_script = basename($_SERVER['SCRIPT_FILENAME']);
$current_path = $_SERVER['SCRIPT_NAME'] ?? '';

// Check if current page is exempt
$is_exempt = false;
foreach ($maintenance_exempt_pages as $exempt) {
    if ($current_script === basename($exempt) || strpos($current_path, $exempt) !== false) {
        $is_exempt = true;
        break;
    }
}

// Only check maintenance mode if not on exempt page
if (!$is_exempt) {
    $maintenance_mode = false;
    $maintenance_message = 'We are currently performing scheduled maintenance. Please check back soon.';
    $maintenance_title = 'Under Maintenance';
    $maintenance_description = '';
    $maintenance_eta = '';

    try {
        $dbConfigPath = __DIR__ . '/../config/database.php';
        if (file_exists($dbConfigPath)) {
            require_once $dbConfigPath;

            if (class_exists('Database')) {
                $__maintenance_db = new Database();
                $__maintenance_conn = $__maintenance_db->getConnection();

                if ($__maintenance_conn) {
                    // Check if site_settings table exists
                    $tableCheck = $__maintenance_conn->query("SHOW TABLES LIKE 'site_settings'");
                    if ($tableCheck->rowCount() > 0) {
                        // Get maintenance mode status
                        $stmt = $__maintenance_conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");

                        $stmt->execute(['maintenance_mode']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && $result['setting_value'] === '1') {
                            $maintenance_mode = true;
                        }

                        // Get custom maintenance message if set
                        $stmt->execute(['maintenance_message']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $maintenance_message = $result['setting_value'];
                        }

                        // v5.2: Get custom maintenance fields
                        $stmt->execute(['maintenance_title']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $maintenance_title = $result['setting_value'];
                        }

                        $stmt->execute(['maintenance_description']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $maintenance_description = $result['setting_value'];
                        }

                        $stmt->execute(['maintenance_eta']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $maintenance_eta = $result['setting_value'];
                        }

                        // v7.0: Check scheduled maintenance window
                        $scheduled_start = '';
                        $scheduled_end = '';

                        $stmt->execute(['maintenance_start']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $scheduled_start = $result['setting_value'];
                        }

                        $stmt->execute(['maintenance_end']);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['setting_value'])) {
                            $scheduled_end = $result['setting_value'];
                        }

                        // Auto-enable/disable based on schedule
                        if (!empty($scheduled_start) && !empty($scheduled_end)) {
                            $now = time();
                            $startTime = strtotime($scheduled_start);
                            $endTime = strtotime($scheduled_end);

                            if ($now >= $startTime && $now < $endTime && !$maintenance_mode) {
                                // Auto-enable
                                $updateStmt = $__maintenance_conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
                                $updateStmt->execute();
                                $maintenance_mode = true;
                            } elseif ($now >= $endTime && $maintenance_mode) {
                                // Auto-disable
                                $updateStmt = $__maintenance_conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0') ON DUPLICATE KEY UPDATE setting_value = '0'");
                                $updateStmt->execute();
                                $maintenance_mode = false;
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Maintenance check error: " . $e->getMessage());
        // On error, don't enable maintenance mode
    }

    // If maintenance mode is enabled
    if ($maintenance_mode) {
        // Check if user is admin (bypass maintenance)
        $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

        if (!$is_admin) {
            // Show maintenance page for non-admins
            http_response_code(503);

            // Use custom maintenance page or fallback
            $maintenance_page = __DIR__ . '/../errors/503.php';
            if (file_exists($maintenance_page)) {
                define('ERROR_PAGE', true);
                $error_code = 503;
                $error_title = $maintenance_title ?? 'Under Maintenance';
                $error_message = $maintenance_message;
                $error_description = $maintenance_description;
                $error_eta = $maintenance_eta;
                $error_icon = '&#x1F6A7;';
                require_once __DIR__ . '/../errors/error_template.php';
                exit;
            } else {
                // Branded v7.0 Evolution maintenance page
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>7.0 Evolution in Progress - Spencer's Website</title>
                    <link rel="icon" type="image/webp" href="/assets/images/favicon.webp">
                    <style>
                        *{margin:0;padding:0;box-sizing:border-box;}
                        body{font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#0a0e1a;color:#e2e8f0;overflow:hidden;}
                        body::before{content:'';position:fixed;inset:0;background:linear-gradient(135deg,#0f172a 0%,#1a0a2e 25%,#0f172a 50%,#0a1628 75%,#0f172a 100%);background-size:400% 400%;animation:bgShift 15s ease infinite;z-index:-2;}
                        @keyframes bgShift{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}
                        body::after{content:'';position:fixed;inset:0;background:radial-gradient(circle at 30% 40%,rgba(78,205,196,0.06) 0%,transparent 50%),radial-gradient(circle at 70% 60%,rgba(99,102,241,0.06) 0%,transparent 50%);z-index:-1;}
                        .maint-wrap{text-align:center;max-width:520px;width:100%;}
                        .maint-badge{display:inline-block;padding:6px 18px;border-radius:20px;font-size:0.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;background:linear-gradient(135deg,rgba(78,205,196,0.15),rgba(99,102,241,0.15));border:1px solid rgba(78,205,196,0.3);color:#4ECDC4;margin-bottom:24px;}
                        .maint-icon{font-size:4rem;margin-bottom:16px;filter:drop-shadow(0 0 20px rgba(78,205,196,0.3));}
                        .maint-title{font-size:2rem;font-weight:800;margin-bottom:8px;background:linear-gradient(135deg,#4ECDC4,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
                        .maint-sub{font-size:1.05rem;color:#94a3b8;line-height:1.6;margin-bottom:20px;}
                        .maint-desc{font-size:0.9rem;color:#64748b;line-height:1.5;margin-bottom:16px;padding:14px 18px;background:rgba(0,0,0,0.3);border-radius:10px;border:1px solid rgba(255,255,255,0.05);}
                        .maint-eta{display:inline-flex;align-items:center;gap:8px;font-size:0.85rem;color:#f59e0b;padding:8px 16px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;}
                        .maint-pulse{width:8px;height:8px;border-radius:50%;background:#4ECDC4;animation:pulse 2s ease infinite;display:inline-block;margin-right:6px;}
                        @keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.4;transform:scale(0.8);}}
                        .maint-footer{margin-top:32px;font-size:0.75rem;color:#334155;}
                    </style>
                </head>
                <body>
                    <div class="maint-wrap">
                        <div class="maint-badge"><span class="maint-pulse"></span>v7.0 Evolution in Progress</div>
                        <div class="maint-icon">&#x1F680;</div>
                        <h1 class="maint-title"><?php echo htmlspecialchars($maintenance_title ?? 'Evolution in Progress'); ?></h1>
                        <p class="maint-sub"><?php echo htmlspecialchars($maintenance_message); ?></p>
                        <?php if (!empty($maintenance_description)): ?>
                            <div class="maint-desc"><?php echo nl2br(htmlspecialchars($maintenance_description)); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($maintenance_eta)): ?>
                            <div class="maint-eta">&#x23F0; <?php echo htmlspecialchars($maintenance_eta); ?></div>
                        <?php endif; ?>
                        <div class="maint-footer">Spencer's Website &mdash; Zero-Trust Architecture v7.0</div>
                    </div>
                </body>
                </html>
                <?php
                exit;
            }
        } else {
            // Admin users see a banner instead
            // This will be shown by JavaScript in the page
            if (!defined('MAINTENANCE_BANNER_SHOWN')) {
                define('MAINTENANCE_BANNER_SHOWN', true);
                $GLOBALS['show_maintenance_banner'] = true;
            }
        }
    }
}

/**
 * Helper function to output maintenance banner for admins
 * Call this in the <body> of your page after including this file
 */
function outputMaintenanceBanner() {
    if (!empty($GLOBALS['show_maintenance_banner'])) {
        echo '<div id="maintenance-banner" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(90deg, #f59e0b, #d97706);
            color: white;
            padding: 10px 20px;
            text-align: center;
            font-weight: 600;
            z-index: 99999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        ">
            &#x26A0; MAINTENANCE MODE ACTIVE - Only admins can access the site.
            <a href="admin.php#maintenance" style="color: white; margin-left: 15px;">Disable</a>
        </div>';
    }
}

/**
 * Check if maintenance mode is active
 */
function isMaintenanceMode() {
    return !empty($GLOBALS['show_maintenance_banner']) || (isset($maintenance_mode) && $maintenance_mode);
}
