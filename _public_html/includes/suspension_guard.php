<?php
/**
 * Suspension Guard — Phase 3 Time Removal Enforcement
 *
 * Reusable guard for API endpoints and interactive pages.
 * Call requireNotSuspended() at the top of any endpoint that should
 * be blocked during an active Time Removal punishment.
 *
 * Community Accounts are never suspended (they have no user record),
 * so the check is skipped when user_id = 0.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

/**
 * Terminate the request with a 403 if the user is currently serving
 * a Time Removal punishment.  Safe to call from both API (JSON) and
 * page (HTML) contexts.
 *
 * @param bool $jsonResponse  Return JSON error instead of HTML redirect
 */
function requireNotSuspended(bool $jsonResponse = true): void {
    if (!empty($_SESSION['is_suspended_punishment'])) {
        $until = $_SESSION['restriction_until'] ?? null;
        $formatted = $until ? date('F j, Y \a\t g:i A', strtotime($until)) : 'a future date';

        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Your account is currently suspended due to a community standards violation.',
                'suspended_until' => $until,
                'suspended_until_formatted' => $formatted,
            ]);
            exit;
        } else {
            header('Location: /main.php?suspended=1');
            exit;
        }
    }
}

/**
 * Convenience wrapper that also blocks Community Accounts from
 * interactive endpoints (belt-and-suspenders alongside page-level checks).
 *
 * @param bool $jsonResponse  Return JSON error instead of redirect
 */
function requirePaidAndActive(bool $jsonResponse = true): void {
    // Community Account sentinel
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 0) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This feature is not available for Community Accounts.']);
            exit;
        } else {
            header('Location: /main.php');
            exit;
        }
    }

    requireNotSuspended($jsonResponse);
}
