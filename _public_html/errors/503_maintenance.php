<?php
/**
 * 503 Maintenance Error Page
 * Shown when the site is under maintenance
 */

http_response_code(503);

// Get maintenance settings
$maintenanceMessage = "We are currently performing scheduled maintenance. Please check back soon.";
$maintenanceToggledAt = null;

try {
    require_once __DIR__ . '/../includes/maintenance.php';
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $settings = getMaintenanceSettings($db);
    $maintenanceMessage = $settings['maintenance_message'];
    $maintenanceToggledAt = $settings['maintenance_toggled_at'];
} catch (Exception $e) {
    error_log("Error getting maintenance settings: " . $e->getMessage());
}

$pageTitle = "Maintenance Mode";
$errorCode = 503;
$errorTitle = "Under Maintenance";
$errorDescription = $maintenanceMessage;
$showBackButton = false;
$showHomeButton = false;

// Add custom styling for maintenance page
$customStyles = '
    .maintenance-icon {
        animation: wrench 2s ease-in-out infinite;
    }
    
    @keyframes wrench {
        0%, 100% { transform: rotate(-15deg); }
        50% { transform: rotate(15deg); }
    }
    
    .maintenance-progress {
        background: rgba(255, 255, 255, 0.1);
        height: 4px;
        border-radius: 2px;
        overflow: hidden;
        margin: 20px 0;
    }
    
    .maintenance-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #1DFFC4, #7B6EF6);
        animation: progress 3s ease-in-out infinite;
    }
    
    @keyframes progress {
        0% { width: 0%; }
        50% { width: 70%; }
        100% { width: 100%; }
    }
';

// Include the error template
require_once __DIR__ . '/error_template.php';
?>

<script>
// Auto-refresh every 5 minutes
setTimeout(() => {
    window.location.reload();
}, 300000);

// Show countdown to next refresh
let countdown = 300;
const countdownEl = document.createElement('p');
countdownEl.style.cssText = 'margin-top: 20px; color: var(--text-secondary); font-size: 14px;';
countdownEl.innerHTML = 'Auto-refreshing in <span id="countdown">5:00</span>...';

const errorActions = document.querySelector('.error-actions');
if (errorActions) {
    errorActions.appendChild(countdownEl);
}

setInterval(() => {
    countdown--;
    const minutes = Math.floor(countdown / 60);
    const seconds = countdown % 60;
    document.getElementById('countdown').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}, 1000);
</script>
