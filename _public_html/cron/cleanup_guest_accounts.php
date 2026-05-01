<?php
/**
 * Cron: Cleanup Guest Accounts - Spencer's Website v7.0
 *
 * Deletes guest accounts (is_guest = 1) that have been inactive for more than 24 hours.
 * Per Community Standards: guest accounts are ephemeral and must not persist beyond
 * active session or 24h of inactivity.
 *
 * Schedule: hourly — 0 * * * *
 */

$db = require __DIR__ . '/bootstrap.php';

$results = ['deleted' => 0, 'guest_rows' => 0];

try {
    // Identify stale guests: no activity in 24h. Use last_login as the activity signal
    // (existing column — updated on every login and by init.php touch, if added).
    // Also fall back to guest_created_at if last_login is NULL (user never came back after signup).
    $staleSql = "
        SELECT id FROM users
        WHERE is_guest = 1
          AND (
              (last_login IS NOT NULL AND last_login < DATE_SUB(NOW(), INTERVAL 24 HOUR))
              OR (last_login IS NULL AND guest_created_at IS NOT NULL AND guest_created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
              OR (last_login IS NULL AND guest_created_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
          )
    ";
    $stmt = $db->query($staleSql);
    $staleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if (!empty($staleIds)) {
        $placeholders = implode(',', array_fill(0, count($staleIds), '?'));

        // Clean up supporting tables in dependency-safe order
        $cleanupTables = [
            'guest_accounts'       => 'user_id',
            'ai_chat_messages'     => 'user_id',   // ignore if table doesn't exist
            'ai_chat_conversations'=> 'user_id',
            'user_announcements'   => 'user_id',
            'smail_messages'       => 'receiver_id',
        ];
        foreach ($cleanupTables as $table => $col) {
            try {
                $sql = "DELETE FROM `{$table}` WHERE `{$col}` IN ({$placeholders})";
                $s = $db->prepare($sql);
                $s->execute($staleIds);
                if ($table === 'guest_accounts') {
                    $results['guest_rows'] = $s->rowCount();
                }
            } catch (PDOException $e) {
                // Ignore missing tables — cleanup is best-effort
            }
        }

        // Finally delete the users rows
        $del = $db->prepare("DELETE FROM users WHERE id IN ({$placeholders}) AND is_guest = 1");
        $del->execute($staleIds);
        $results['deleted'] = $del->rowCount();
    }

    echo date('Y-m-d H:i:s') . " Guest cleanup: "
        . "{$results['deleted']} guest users deleted, "
        . "{$results['guest_rows']} tracking rows removed\n";
} catch (Exception $e) {
    error_log("Cron cleanup_guest_accounts error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
