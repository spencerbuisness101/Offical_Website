<?php
/**
 * Image Serve Proxy - Spencer's Website v7.0
 * Serves images from /pending_uploads/ directory through PHP.
 * Only accessible to the owning user or admins.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';

$filename = $_GET['file'] ?? '';

if (empty($filename) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $filename)) {
    http_response_code(400);
    die('Invalid file request.');
}

$filePath = __DIR__ . '/pending_uploads/' . $filename;

if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    die('File not found.');
}

// Check access: must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    die('Access denied.');
}

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

// If not admin, verify this file belongs to the requesting user
if (!$isAdmin) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $relativePath = 'pending_uploads/' . $filename;
        $stmt = $db->prepare("SELECT id FROM users WHERE (pfp_pending_path = ? OR profile_picture_url LIKE ?) AND id = ?");
        $stmt->execute([$relativePath, '%' . $filename . '%', $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die('Access denied.');
        }
    } catch (Exception $e) {
        http_response_code(500);
        die('Server error.');
    }
}

// Determine content type
$ext = pathinfo($filename, PATHINFO_EXTENSION);
$mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Serve the file
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
