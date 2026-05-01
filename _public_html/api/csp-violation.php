<?php
/**
 * CSP Violation Report Endpoint — Spencer's Website v7.0
 *
 * Accepts POST with JSON body containing CSP violation reports.
 * Logs to error_log for admin review. Returns 204 No Content.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain');
    exit;
}

// Reject oversized payloads to prevent DoS via error_log flooding
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 65536) {
    http_response_code(413);
    header('Content-Type: text/plain');
    exit;
}

$raw = file_get_contents('php://input');
if ($raw && strlen($raw) <= 65536) {
    $report = json_decode($raw, true, 5); // depth limit
    if (isset($report['csp-report'])) {
        error_log('CSP Violation: ' . ($report['csp-report']['violated-directive'] ?? 'unknown')
            . ' | blocked: ' . ($report['csp-report']['blocked-uri'] ?? 'unknown')
            . ' | page: ' . ($report['csp-report']['document-uri'] ?? 'unknown'));
    } else {
        error_log('CSP Violation: ' . substr($raw, 0, 500));
    }
}

http_response_code(204);
