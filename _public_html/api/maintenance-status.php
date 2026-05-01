<?php
/**
 * API Endpoint: Maintenance Status
 * Returns current maintenance status for heartbeat system
 */

// Prevent direct access to this file
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

// Only allow AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    require_once __DIR__ . '/../includes/maintenance.php';
    $settings = getMaintenanceSettings($db);
    
    // If maintenance mode is enabled and user doesn't have bypass, return 503
    if ($settings['maintenance_mode'] === 'true' && !hasMaintenanceBypass()) {
        http_response_code(503);
        echo json_encode([
            'maintenance_mode' => 'true',
            'maintenance_toggled_at' => $settings['maintenance_toggled_at'],
            'maintenance_message' => $settings['maintenance_message']
        ]);
        exit;
    }
    
    // Return current status
    echo json_encode([
        'maintenance_mode' => $settings['maintenance_mode'],
        'maintenance_toggled_at' => $settings['maintenance_toggled_at'],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    error_log("Error getting maintenance status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
