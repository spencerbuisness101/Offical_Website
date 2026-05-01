<?php
/**
 * API Endpoint: Set User Background
 * POST /api/set_background.php
 * Body: { background_id: int }
 */

header('Content-Type: application/json');

// Prevent direct access
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$backgroundId = isset($input['background_id']) ? (int)$input['background_id'] : 0;

if (!$backgroundId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Background ID required']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify background exists and user has access
    $stmt = $db->prepare("
        SELECT b.*, u.id as designer_id
        FROM backgrounds b
        LEFT JOIN users u ON b.designer_id = u.id
        WHERE b.id = ? 
        AND (b.status = 'approved' OR (b.designer_id = ? AND b.status IN ('approved', 'pending')))
    ");
    $stmt->execute([$backgroundId, $userId]);
    $background = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$background) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Background not found or not accessible']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Clear any existing active backgrounds for this user
    $stmt = $db->prepare("
        UPDATE user_backgrounds 
        SET is_active = FALSE 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    
    // Check if user already has this background
    $stmt = $db->prepare("
        SELECT id FROM user_backgrounds 
        WHERE user_id = ? AND background_id = ?
    ");
    $stmt->execute([$userId, $backgroundId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing to active
        $stmt = $db->prepare("
            UPDATE user_backgrounds 
            SET is_active = TRUE, updated_at = NOW()
            WHERE user_id = ? AND background_id = ?
        ");
        $stmt->execute([$userId, $backgroundId]);
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO user_backgrounds (user_id, background_id, is_active, created_at, updated_at)
            VALUES (?, ?, TRUE, NOW(), NOW())
        ");
        $stmt->execute([$userId, $backgroundId]);
    }
    
    // Update user's current background reference
    $stmt = $db->prepare("
        UPDATE users 
        SET current_background_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$backgroundId, $userId]);
    
    $db->commit();
    
    // Log the action
    error_log("User {$userId} changed background to {$backgroundId}");
    
    echo json_encode([
        'success' => true,
        'background_id' => $backgroundId,
        'background_url' => $background['image_url'],
        'message' => 'Background updated successfully'
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Set background error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
