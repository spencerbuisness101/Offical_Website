<?php
/**
 * Admin API: Strike Operations
 * 
 * Endpoints for applying strikes, getting user strike counts, and strike statistics.
 */

session_start();
header('Content-Type: application/json');

define('APP_RUNNING', true);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/StrikeManager.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF validation for state-changing actions
$stateChanging = ['apply', 'remove'];
if (in_array($action, $stateChanging, true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    $strikeManager = new StrikeManager();
    
    switch ($action) {
        case 'apply':
            // Apply a new strike
            $userId = intval($_POST['user_id'] ?? 0);
            $ruleId = $_POST['rule_id'] ?? '';
            $evidence = $_POST['evidence'] ?? '';
            $customDuration = !empty($_POST['custom_duration']) ? intval($_POST['custom_duration']) : null;
            
            if ($userId <= 0 || empty($ruleId) || empty($evidence)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $result = $strikeManager->applyStrike($userId, $ruleId, $evidence, $_SESSION['user_id'], $customDuration);
            echo json_encode($result);
            break;
            
        case 'preview':
            // Preview punishment before applying
            $userId = intval($_GET['user_id'] ?? 0);
            $ruleId = $_GET['rule_id'] ?? '';
            
            if ($userId <= 0 || empty($ruleId)) {
                echo json_encode(['success' => false, 'message' => 'Missing user_id or rule_id']);
                exit;
            }
            
            $activeStrikes = $strikeManager->countActiveStrikes($userId);
            $punishment = $strikeManager->determinePunishment($ruleId, $activeStrikes);
            
            echo json_encode([
                'success' => true,
                'active_strikes' => $activeStrikes,
                'punishment' => $punishment,
                'rule' => StrikeManager::getRule($ruleId)
            ]);
            break;
            
        case 'get_user_strikes':
            // Get strike history for a user
            $userId = intval($_GET['user_id'] ?? 0);
            
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Missing user_id']);
                exit;
            }
            
            $strikes = $strikeManager->getUserStrikes($userId);
            $activeCount = $strikeManager->countActiveStrikes($userId);
            
            echo json_encode([
                'success' => true,
                'strikes' => $strikes,
                'active_count' => $activeCount,
                'total_count' => count($strikes)
            ]);
            break;
            
        case 'get_all_strikes':
            // Get all strikes (paginated)
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            
            $strikes = $strikeManager->getAllStrikes($limit, $offset);
            
            echo json_encode([
                'success' => true,
                'strikes' => $strikes,
                'count' => count($strikes)
            ]);
            break;
            
        case 'get_rules':
            // Get all rule definitions
            echo json_encode([
                'success' => true,
                'rules' => StrikeManager::getRules()
            ]);
            break;
            
        case 'get_statistics':
            // Get strike statistics
            $days = intval($_GET['days'] ?? 30);
            $stats = $strikeManager->getStatistics($days);
            
            echo json_encode([
                'success' => true,
                'statistics' => $stats
            ]);
            break;
            
        case 'reactivate_expired':
            // Manually trigger reactivation of expired suspensions
            // This is usually done by cron, but can be triggered manually
            $result = $strikeManager->reactivateExpiredSuspensions();
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Strike API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
