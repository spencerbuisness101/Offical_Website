<?php
/**
 * Settings Loader - Spencer's Website v5.0
 *
 * Server-side settings fetcher to fix background persistence bug.
 * Include this in pages that need user settings (backgrounds, accent colors, etc.)
 *
 * Usage:
 * 1. Include this file after session start
 * 2. The $serverUserSettings variable will be available
 * 3. Add to page: <script>window.serverUserSettings = <?php echo json_encode($serverUserSettings); ?>;</script>
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Initialize empty settings
$serverUserSettings = [
    'loaded' => false,
    'source' => 'none',
    'settings' => []
];

// Only load settings if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['role'] ?? 'community';

    // Community users use localStorage only - don't fetch from server
    if ($user_role === 'community') {
        $serverUserSettings['source'] = 'localStorage';
        $serverUserSettings['loaded'] = true;
    } elseif ($user_id) {
        // Non-community users can have server-side settings
        try {
            $dbConfigPath = __DIR__ . '/../config/database.php';
            if (file_exists($dbConfigPath)) {
                require_once $dbConfigPath;

                if (class_exists('Database')) {
                    $database = new Database();
                    $conn = $database->getConnection();

                    if ($conn) {
                        // Check if user_settings table exists
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_settings'");
                        if ($tableCheck->rowCount() > 0) {
                            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $settings = [];
                            foreach ($rows as $row) {
                                $decoded = json_decode($row['setting_value'], true);
                                $settings[$row['setting_key']] = $decoded !== null ? $decoded : $row['setting_value'];
                            }

                            $serverUserSettings['settings'] = $settings;
                            $serverUserSettings['source'] = 'database';
                            $serverUserSettings['loaded'] = true;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Settings loader error: " . $e->getMessage());
            $serverUserSettings['source'] = 'error';
        }
    }

    // Also try to get active designer background for all users
    try {
        if (isset($conn) && $conn) {
            $bgStmt = $conn->query("
                SELECT db.image_url, db.title, u.username as designer_name
                FROM designer_backgrounds db
                LEFT JOIN users u ON db.user_id = u.id
                WHERE db.is_active = 1 AND db.status = 'approved'
                LIMIT 1
            ");
            $activeBg = $bgStmt->fetch(PDO::FETCH_ASSOC);
            if ($activeBg) {
                $serverUserSettings['activeDesignerBackground'] = $activeBg;
            }
        }
    } catch (Exception $e) {
        // Silently fail - designer background is optional
    }
}

/**
 * Helper function to output the settings script tag
 * Call this in the <head> section of your page
 */
function outputServerSettingsScript($settings = null) {
    global $serverUserSettings;
    $settingsToOutput = $settings ?? $serverUserSettings;
    echo '<script>window.serverUserSettings = ' . json_encode($settingsToOutput) . ';</script>';
}

/**
 * Get a specific setting value
 */
function getServerSetting($key, $default = null) {
    global $serverUserSettings;
    return $serverUserSettings['settings'][$key] ?? $default;
}
