<?php
/**
 * 403 Banned Error Page
 * Shown when a user's fingerprint is banned
 */

define('ERROR_PAGE', true);
http_response_code(403);

// Get ban details if available
$banReason = "Your device has been banned due to suspicious activity.";
$banPermanent = true;
$banExpiresAt = null;

$fingerprintHash = $_SESSION['fingerprint_hash'] ?? $_POST['fingerprint_hash'] ?? null;
if ($fingerprintHash) {
    try {
        require_once __DIR__ . '/../includes/fingerprint_ban.php';
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $banStatus = isFingerprintBanned($db, $fingerprintHash);
        if ($banStatus['banned']) {
            $banReason = $banStatus['reason'];
            $banPermanent = $banStatus['permanent'];
            $banExpiresAt = $banStatus['expires_at'];
        }
    } catch (Exception $e) {
        error_log("Error getting ban details: " . $e->getMessage());
    }
}

$error_code = 403;
$error_title = 'Access Denied';
$error_message = $banReason;
$error_icon = '&#x1F6AB;';
$error_description = $banPermanent ? 'This ban is permanent.' : ($banExpiresAt ? 'Expires: ' . $banExpiresAt : '');

// Include the error template
require_once __DIR__ . '/error_template.php';
?>
