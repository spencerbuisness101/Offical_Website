<?php
/**
 * Cache Action API — Spencer's Website v7.0
 */
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
}

$action = $_POST['action'] ?? '';
$cacheDir = __DIR__ . '/../../cache';

switch ($action) {
    case 'clear_all':
        $cleared = 0;
        if (is_dir($cacheDir)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iter as $file) {
                if ($file->isFile()) { @unlink($file->getRealPath()); $cleared++; }
                elseif ($file->isDir()) { @rmdir($file->getRealPath()); }
            }
        }
        echo json_encode(['success' => true, 'message' => "Cleared $cleared files"]);
        break;

    case 'view':
        $files = [];
        if (is_dir($cacheDir)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir));
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $files[] = ['name' => $file->getFilename(), 'size' => $file->getSize(), 'modified' => date('Y-m-d H:i:s', $file->getMTime())];
                }
            }
        }
        echo json_encode(['success' => true, 'files' => $files]);
        break;

    case 'delete':
        $filename = basename($_POST['file'] ?? '');
        $path = $cacheDir . '/' . $filename;
        if ($filename && file_exists($path) && is_file($path)) {
            @unlink($path);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
