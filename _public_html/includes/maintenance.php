<?php
/**
 * Maintenance Mode Helper Functions
 * Phase 2.2 - Maintenance Heartbeat
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Check if maintenance mode is enabled
 */
function isMaintenanceMode(PDO $db): bool {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['setting_value'] ?? 'false') === 'true';
    } catch (Exception $e) {
        error_log("Error checking maintenance mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Get maintenance settings
 */
function getMaintenanceSettings(PDO $db): array {
    try {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('maintenance_mode', 'maintenance_toggled_at', 'maintenance_message', 'maintenance_bypass_key')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [
            'maintenance_mode' => 'false',
            'maintenance_toggled_at' => null,
            'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back soon.',
            'maintenance_bypass_key' => null
        ];
        
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting maintenance settings: " . $e->getMessage());
        return [
            'maintenance_mode' => 'false',
            'maintenance_toggled_at' => null,
            'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back soon.',
            'maintenance_bypass_key' => null
        ];
    }
}

/**
 * Enable maintenance mode
 */
function enableMaintenanceMode(PDO $db, string $message = null, int $userId = null): bool {
    try {
        $db->beginTransaction();
        
        // Update maintenance mode
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = 'true', updated_by = ? WHERE setting_key = 'maintenance_mode'");
        $stmt->execute([$userId]);
        
        // Update timestamp
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = NOW(), updated_by = ? WHERE setting_key = 'maintenance_toggled_at'");
        $stmt->execute([$userId]);
        
        // Update message if provided
        if ($message) {
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'maintenance_message'");
            $stmt->execute([$message, $userId]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error enabling maintenance mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Disable maintenance mode
 */
function disableMaintenanceMode(PDO $db, int $userId = null): bool {
    try {
        $db->beginTransaction();
        
        // Update maintenance mode
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = 'false', updated_by = ? WHERE setting_key = 'maintenance_mode'");
        $stmt->execute([$userId]);
        
        // Clear timestamp
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = NULL, updated_by = ? WHERE setting_key = 'maintenance_toggled_at'");
        $stmt->execute([$userId]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error disabling maintenance mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has maintenance bypass
 */
function hasMaintenanceBypass(): bool {
    // Admins always have bypass
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Check bypass key in session or URL parameter
    $bypassKey = $_SESSION['maintenance_bypass'] ?? $_GET['bypass'] ?? null;
    
    if ($bypassKey) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_bypass_key' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && hash_equals($result['setting_value'], $bypassKey)) {
                $_SESSION['maintenance_bypass'] = $bypassKey;
                return true;
            }
        } catch (Exception $e) {
            error_log("Error checking maintenance bypass: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Show maintenance page if maintenance mode is enabled
 */
function checkMaintenanceMode(): void {
    // Skip check for login page, static assets, and API endpoints
    $exemptPaths = [
        '/auth/login.php',
        '/errors/',
        '/css/',
        '/js/',
        '/images/',
        '/fonts/',
        '/api/',
        '/webhook-stripe',
        '/webhook-paypal'
    ];
    
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    foreach ($exemptPaths as $exemptPath) {
        if (str_starts_with($path, $exemptPath)) {
            return;
        }
    }
    
    // Check if maintenance mode is enabled
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (isMaintenanceMode($db) && !hasMaintenanceBypass()) {
            $settings = getMaintenanceSettings($db);
            
            http_response_code(503);
            header('Retry-After: 3600'); // Suggest retry in 1 hour
            
            // Include maintenance page
            require_once __DIR__ . '/../errors/503_maintenance.php';
            exit;
        }
    } catch (Exception $e) {
        error_log("Error checking maintenance mode: " . $e->getMessage());
    }
}
?>
