<?php
/**
 * Identity Bar Compatibility Shim
 * Redirects to identity_bar_v2.php
 * Many pages reference this file, so we keep it as a bridge.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
require_once __DIR__ . '/identity_bar_v2.php';
