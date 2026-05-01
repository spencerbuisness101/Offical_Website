<?php
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$background_id = isset($input['background_id']) ? (int)$input['background_id'] : null;
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown User';

try {
    if ($background_id === null) {
        // Remove preference
        $stmt = $db->prepare("DELETE FROM user_background_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Background preference removed',
            'background_url' => null
        ]);
        exit;
    }

    // Verify that the background exists and is approved
    $query = $db->prepare("SELECT id, image_url, title FROM designer_backgrounds WHERE id = ? AND (status = 'approved' OR status IS NULL)");
    $query->execute([$background_id]);
    $background = $query->fetch(PDO::FETCH_ASSOC);

    if (!$background) {
        echo json_encode(['success' => false, 'message' => 'Background not found or not approved']);
        exit;
    }

    // Ensure only one active background per user
    $db->beginTransaction();

    $db->prepare("DELETE FROM user_background_preferences WHERE user_id = ?")->execute([$user_id]);

    $insert = $db->prepare("
        INSERT INTO user_background_preferences (user_id, background_id, is_active)
        VALUES (?, ?, TRUE)
    ");
    $insert->execute([$user_id, $background_id]);

    $db->commit();

    // Log this action
    $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
    $logStmt->execute(["{$username} set background to '{$background['title']}' (ID: {$background_id})", $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Background preference saved successfully',
        'background_url' => $background['image_url'],
        'title' => $background['title']
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Background preference error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
?>
