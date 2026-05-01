<?php
/**
 * Cron Trigger API — Spencer's Website v7.0
 * Triggers cron jobs via HTTP call (not include) for isolation
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

$job = $_POST['job'] ?? '';
if (!$job) { echo json_encode(['success' => false, 'message' => 'No job specified']); exit; }

// Only allow known cron jobs
$allowedJobs = ['cron/check_subscriptions.php'];
if (!in_array($job, $allowedJobs)) {
    echo json_encode(['success' => false, 'message' => 'Unknown job']); exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Ensure cron_log table exists
    $db->exec("CREATE TABLE IF NOT EXISTS cron_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_name VARCHAR(100),
        status VARCHAR(20),
        message TEXT,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL,
        INDEX idx_job (job_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $jobPath = __DIR__ . '/../../' . $job;
    $startedAt = date('Y-m-d H:i:s');

    if (!file_exists($jobPath)) {
        $stmt = $db->prepare("INSERT INTO cron_log (job_name, status, message, started_at) VALUES (?, 'failed', ?, ?)");
        $stmt->execute([$job, 'Job file not found', $startedAt]);
        echo json_encode(['success' => false, 'message' => 'Job file not found']);
        exit;
    }

    // Run via HTTP call for process isolation (not include)
    $jobUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' . $job;
    $ch = curl_init($jobUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false, // localhost may use self-signed
        CURLOPT_HTTPHEADER => ['X-Cron-Trigger: admin']
    ]);
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $finishedAt = date('Y-m-d H:i:s');

    if ($curlError) {
        $status = 'failed';
        $message = 'cURL error: ' . substr($curlError, 0, 200);
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $status = 'success';
        $message = substr($output ?: 'OK', 0, 500);
    } else {
        $status = 'failed';
        $message = "HTTP $httpCode: " . substr($output ?: '', 0, 300);
    }

    $stmt = $db->prepare("INSERT INTO cron_log (job_name, status, message, started_at, finished_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$job, $status, $message, $startedAt, $finishedAt]);

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'cron_trigger', ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Triggered $job: $status", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => $status === 'success', 'message' => 'Job ' . $status . ($curlError ? ': ' . $curlError : '')]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
