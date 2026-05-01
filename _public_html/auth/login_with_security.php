<?php
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['identifier']) && isset($_POST['username'])) {
    $_POST['identifier'] = $_POST['username'];
}

// Delegate all login logic to login.php — the canonical auth handler.
// Phase 4 enhancements (device fingerprinting, ban detection) should be
// integrated into login.php in a future update. The duplicate code below
// (lines 21-265) is preserved as a reference implementation but is dead code.
require __DIR__ . '/login.php';
exit;
