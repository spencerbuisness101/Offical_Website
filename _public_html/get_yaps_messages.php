<?php
require_once __DIR__ . '/includes/init.php';

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get messages with user_id for name tag lookup
    $stmt = $db->query("
        SELECT m.user_id, m.username, m.user_role, m.message, m.timestamp
        FROM yaps_chat_messages m
        WHERE m.is_active = TRUE
        ORDER BY m.timestamp ASC
        LIMIT 100
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch custom name tags for each user
    foreach ($messages as &$msg) {
        $msg['custom_tag'] = null;

        if ($msg['user_id'] && $msg['user_role'] !== 'community') {
            // Check user_settings for nameTag
            try {
                $tagStmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'nameTag'");
                $tagStmt->execute([$msg['user_id']]);
                $setting = $tagStmt->fetch(PDO::FETCH_ASSOC);
                if ($setting && $setting['setting_value']) {
                    $tagValue = json_decode($setting['setting_value'], true);
                    if ($tagValue && trim($tagValue) !== '') {
                        $msg['custom_tag'] = htmlspecialchars($tagValue);
                    }
                }
            } catch (Exception $e) {
                // Table might not exist - silently fail
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($messages);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Yaps messages error: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>