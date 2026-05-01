<?php
/**
 * Health Check Endpoint — Spencer's Website v7.0
 *
 * Returns JSON health status for monitoring tools (UptimeRobot, Pingdom, etc.)
 * Checks: database connectivity, disk space, PHP version, critical extensions
 *
 * Usage: GET /health.php
 *        GET /health.php?checks=all (detailed checks)
 *        GET /health.php?format=prometheus (Prometheus metrics)
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';

// CORS for monitoring tools
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$startTime = microtime(true);
$checks = [];
$overallStatus = 'healthy'; // healthy, degraded, unhealthy

// Basic checks always run

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$checks['php_version'] = [
    'status' => $phpOk ? 'pass' : 'fail',
    'value' => $phpVersion,
    'required' => '>= 8.1.0'
];
if (!$phpOk) $overallStatus = 'unhealthy';

// 2. Critical Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}
$checks['extensions'] = [
    'status' => empty($missingExtensions) ? 'pass' : 'fail',
    'missing' => $missingExtensions,
    'required' => $requiredExtensions
];
if (!empty($missingExtensions)) $overallStatus = 'unhealthy';

// 3. Database Connectivity
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Test query
    $stmt = $db->query("SELECT 1 as health_check");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $checks['database'] = [
        'status' => 'pass',
        'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ];
} catch (Exception $e) {
    $checks['database'] = [
        'status' => 'fail',
        'error' => $e->getMessage()
    ];
    $overallStatus = 'unhealthy';
}

// 3b. Migration Status
try {
    if (isset($db) && $db) {
        $stmt = $db->query("SELECT COUNT(*) FROM schema_migrations");
        $applied = (int)$stmt->fetchColumn();

        $migrationDir = __DIR__ . '/migrations';
        $total = count(glob($migrationDir . '/*.sql') ?: []);
        $pending = $total - $applied;

        $checks['migrations'] = [
            'status' => $pending === 0 ? 'pass' : 'degraded',
            'applied' => $applied,
            'total' => $total,
            'pending' => $pending
        ];
        if ($pending > 0 && $overallStatus === 'healthy') {
            $overallStatus = 'degraded';
        }
    }
} catch (Exception $e) {
    $checks['migrations'] = [
        'status' => 'degraded',
        'error' => 'schema_migrations table not found'
    ];
}

// 4. Disk Space
$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskPercentUsed = (($diskTotal - $diskFree) / $diskTotal) * 100;
$diskOk = $diskPercentUsed < 90 && $diskFree > 100 * 1024 * 1024; // 100MB free minimum

$checks['disk_space'] = [
    'status' => $diskOk ? 'pass' : ($diskPercentUsed < 95 ? 'degraded' : 'fail'),
    'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
    'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
    'percent_used' => round($diskPercentUsed, 1)
];
if ($diskPercentUsed > 95) $overallStatus = 'unhealthy';
elseif ($diskPercentUsed > 90 && $overallStatus === 'healthy') $overallStatus = 'degraded';

// 5. Session Storage
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$sessionWritable = is_writable($sessionPath);
$checks['session_storage'] = [
    'status' => $sessionWritable ? 'pass' : 'fail',
    'path' => $sessionPath,
    'writable' => $sessionWritable
];
if (!$sessionWritable && $overallStatus === 'healthy') $overallStatus = 'degraded';

// 6. Cache Directory
$cacheDir = __DIR__ . '/cache';
$cacheWritable = is_dir($cacheDir) && is_writable($cacheDir);
$checks['cache_storage'] = [
    'status' => $cacheWritable ? 'pass' : 'degraded',
    'path' => $cacheDir,
    'writable' => $cacheWritable
];
if (!$cacheWritable && $overallStatus === 'healthy') $overallStatus = 'degraded';

// Response time
$responseTime = round((microtime(true) - $startTime) * 1000, 2);

// Detailed checks (optional)
if (isset($_GET['checks']) && $_GET['checks'] === 'all') {
    // Check external APIs (Groq)
    $groqKey = getenv('GROQ_API_KEY') ?: '';
    $checks['external_apis'] = [
        'groq_configured' => !empty($groqKey),
        'status' => !empty($groqKey) ? 'pass' : 'degraded'
    ];
    if (empty($groqKey) && $overallStatus === 'healthy') $overallStatus = 'degraded';
    
    // Memory usage
    $checks['memory'] = [
        'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        'limit_mb' => ini_get('memory_limit') === '-1' ? 'unlimited' : ini_get('memory_limit')
    ];
    
    // OpCache status
    $checks['opcache'] = [
        'enabled' => extension_loaded('Zend OPcache') && opcache_get_status(false) !== false
    ];
}

// Prometheus format
if (isset($_GET['format']) && $_GET['format'] === 'prometheus') {
    header('Content-Type: text/plain');
    $output = "# HELP spencer_health_check Site health check status\n";
    $output .= "# TYPE spencer_health_check gauge\n";
    $output .= "spencer_health_check{status=\"{$overallStatus}\"} 1\n";
    $output .= "# HELP spencer_health_response_time_ms Response time in milliseconds\n";
    $output .= "# TYPE spencer_health_response_time_ms gauge\n";
    $output .= "spencer_health_response_time_ms{$responseTime}\n";
    $output .= "# HELP spencer_disk_usage_percent Disk usage percentage\n";
    $output .= "# TYPE spencer_disk_usage_percent gauge\n";
    $output .= "spencer_disk_usage_percent " . round($diskPercentUsed, 1) . "\n";
    $output .= "# HELP spencer_db_latency_ms Database latency in milliseconds\n";
    $output .= "# TYPE spencer_db_latency_ms gauge\n";
    $output .= "spencer_db_latency_ms " . ($checks['database']['latency_ms'] ?? 0) . "\n";
    echo $output;
    exit;
}

// JSON response
$response = [
    'status' => $overallStatus,
    'timestamp' => date('c'),
    'version' => defined('SITE_VERSION') ? SITE_VERSION : '7.0',
    'response_time_ms' => $responseTime,
    'checks' => $checks
];

// HTTP status code
$httpStatus = $overallStatus === 'healthy' ? 200 : ($overallStatus === 'degraded' ? 200 : 503);
http_response_code($httpStatus);

echo json_encode($response, JSON_PRETTY_PRINT);
