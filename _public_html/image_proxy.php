<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

// Only allow designers and admins to use the proxy
if (!in_array($_SESSION['role'], ['designer', 'admin'])) {
    http_response_code(403);
    exit('Access denied');
}

// CREV-03: Rate limit image proxy to prevent App-Layer DoS (10 requests/min/IP)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rate_limit_ip.php';
try {
    $proxyDb = (new Database())->getConnection();
    enforceIpRateLimit($proxyDb, 'image_proxy', 10, 60);
} catch (Exception $e) {
    error_log("Image proxy rate limit error: " . $e->getMessage());
}

if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    exit('No URL provided');
}

$imageUrl = $_GET['url'];

// Enhanced URL validation
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL');
}

// Additional security checks
$parsedUrl = parse_url($imageUrl);
if (!$parsedUrl) {
    http_response_code(400);
    exit('Invalid URL format');
}

// Only allow specific protocols
$allowedSchemes = ['http', 'https'];
if (!in_array(strtolower($parsedUrl['scheme'] ?? ''), $allowedSchemes)) {
    http_response_code(400);
    exit('Invalid protocol');
}

// Block suspicious domains
$blockedDomains = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
$host = strtolower($parsedUrl['host'] ?? '');
if (in_array($host, $blockedDomains) || str_ends_with($host, '.local')) {
    http_response_code(400);
    exit('Blocked domain');
}

// Check for suspicious patterns
$suspiciousPatterns = [
    '/../',
    '<script',
    'javascript:',
    'data:',
    'blob:',
    'file:',
    'ftp:'
];

foreach ($suspiciousPatterns as $pattern) {
    if (stripos($imageUrl, $pattern) !== false) {
        http_response_code(400);
        exit('Suspicious URL detected');
    }
}

// Check if URL points to an image
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$path = parse_url($imageUrl, PHP_URL_PATH);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    exit('URL does not point to a valid image');
}

// Security: Block potentially dangerous URLs
$blockedHosts = [
    'localhost',
    '127.0.0.1',
    '192.168.',
    '10.',
    '172.16.',
    '169.254.',
    '::1'
];

$host = parse_url($imageUrl, PHP_URL_HOST);
foreach ($blockedHosts as $blocked) {
    if (strpos($host, $blocked) === 0) {
        http_response_code(403);
        exit('Access to internal resources blocked');
    }
}

// SECURITY: Resolve DNS and validate IP is not private/reserved (prevents DNS rebinding SSRF)
$resolvedIp = gethostbyname($host);
if ($resolvedIp === $host) {
    // DNS resolution failed
    http_response_code(400);
    exit('Could not resolve hostname');
}
if (!filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    http_response_code(403);
    exit('Access to internal network resources is blocked');
}

// Fetch the image
// Collect response headers for validation
$responseContentType = '';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $imageUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false, // SECURITY: Disable redirects to prevent SSRF bypass
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'SpencerWebsite/1.0',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_RESOLVE => [$host . ':443:' . $resolvedIp, $host . ':80:' . $resolvedIp], // Pin resolved IP
    CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseContentType) {
        $len = strlen($header);
        $parts = explode(':', $header, 2);
        if (count($parts) === 2 && strtolower(trim($parts[0])) === 'content-type') {
            $responseContentType = strtolower(trim($parts[1]));
        }
        return $len;
    }
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle redirects: validate the redirect target against blocklist instead of following blindly
if (in_array($httpCode, [301, 302, 303, 307, 308])) {
    http_response_code(403);
    exit('Redirects are not allowed for security reasons');
}

if ($httpCode !== 200 || !$imageData) {
    http_response_code(404);
    exit('Image not found or inaccessible');
}

// SECURITY: Validate response Content-Type is actually an image
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
$isImage = false;
foreach ($allowedMimes as $mime) {
    if (str_starts_with($responseContentType, $mime)) {
        $isImage = true;
        break;
    }
}
if (!$isImage) {
    http_response_code(403);
    exit('Response is not a valid image');
}

// Output with validated content type
header('Content-Type: ' . $responseContentType);
header('Content-Length: ' . strlen($imageData));
header('X-Content-Type-Options: nosniff');
echo $imageData;
?>