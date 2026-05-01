<?php
/**
 * 503 Service Unavailable / Maintenance Error Page - Spencer's Website v7.0
 */

define('ERROR_PAGE', true);
http_response_code(503);
header('Retry-After: 3600');

$error_code  = 503;
$error_title = 'Under Maintenance';
$error_icon  = '&#x1F6A7;';

// Try to load maintenance message from DB
$error_message     = 'The site is currently undergoing scheduled maintenance.';
$error_description = 'We\'ll be back shortly. Thanks for your patience.';
$error_eta         = '';

try {
    $dbCfg = dirname(__DIR__) . '/config/database.php';
    if (file_exists($dbCfg)) {
        require_once $dbCfg;
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('maintenance_message','maintenance_eta')");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['setting_key'] === 'maintenance_message' && !empty($row['setting_value'])) {
                $error_message = htmlspecialchars($row['setting_value']);
            }
            if ($row['setting_key'] === 'maintenance_eta' && !empty($row['setting_value'])) {
                $error_eta = htmlspecialchars($row['setting_value']);
            }
        }
    }
} catch (Exception $e) {
    // silently fall back to defaults
}

require_once __DIR__ . '/error_template.php';
?>
<script>
// Auto-refresh every 5 minutes
setTimeout(() => location.reload(), 300000);
</script>
