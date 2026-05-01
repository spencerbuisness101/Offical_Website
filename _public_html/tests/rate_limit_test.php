<?php
/**
 * Smoke test: includes/RateLimit.php
 *
 * Verifies the v7.1 fix:
 *   1. check() does NOT log when over the limit (no sliding-window self-extension).
 *   2. check() does log when allowed AND $logAttempt is true.
 *   3. log() always records an attempt.
 *   4. After the window passes, count resets.
 *
 * Run from CLI:
 *     php tests/rate_limit_test.php
 *
 * NOT a phpunit test — keeps zero deps so QA can run it on a fresh checkout.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/RateLimit.php';

$db = db();

// Use a synthetic action so we never touch real login data
$action = 'smoketest_' . bin2hex(random_bytes(4));
$identifier = 'tester_' . bin2hex(random_bytes(4));
$max = 3;
$window = 5; // 5-second window keeps the test fast

function assertEq($expected, $actual, string $message): void {
    if ($expected === $actual) {
        echo "  PASS  $message\n";
    } else {
        echo "  FAIL  $message — expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . "\n";
        global $failed; $failed = true;
    }
}

$failed = false;
$rl = new RateLimit();

echo "RateLimit smoke test (action=$action, max=$max, window={$window}s)\n";

// 1) Empty state → check() returns true and does NOT log unless asked
$ok = $rl->check($action, $identifier, $max, $window);
assertEq(true, $ok, 'first check() with empty state returns true');
$count = (int) $db->prepare("SELECT COUNT(*) FROM rate_limit_log WHERE action = ? AND identifier = ?")
    ->execute([$action, $identifier]) ? null : null;
$stmt = $db->prepare("SELECT COUNT(*) FROM rate_limit_log WHERE action = ? AND identifier = ?");
$stmt->execute([$action, $identifier]);
assertEq(0, (int)$stmt->fetchColumn(), 'check() with logAttempt=false (default) does NOT insert a row');

// 2) check() with logAttempt=true inserts a row when allowed
$rl->check($action, $identifier, $max, $window, true);
$stmt->execute([$action, $identifier]);
assertEq(1, (int)$stmt->fetchColumn(), 'check(..., true) inserts a row when allowed');

// 3) Saturate the limit using log() directly
for ($i = 0; $i < $max - 1; $i++) {
    $rl->log($action, $identifier);
}
$stmt->execute([$action, $identifier]);
assertEq($max, (int)$stmt->fetchColumn(), 'log() inserts each call');

// 4) Now over the limit — check() should return false WITHOUT inserting a new row
$rowsBefore = (int)$stmt->fetchColumn();
$stmt->execute([$action, $identifier]);
$rowsBefore = (int)$stmt->fetchColumn();
$blocked = !$rl->check($action, $identifier, $max, $window);
assertEq(true, $blocked, 'check() returns false when count >= max');
$stmt->execute([$action, $identifier]);
$rowsAfter = (int)$stmt->fetchColumn();
assertEq($rowsBefore, $rowsAfter, 'check() did NOT insert when blocked (the bug fix)');

// 5) Even with logAttempt=true, blocked check() must not insert
$rl->check($action, $identifier, $max, $window, true);
$stmt->execute([$action, $identifier]);
assertEq($rowsBefore, (int)$stmt->fetchColumn(), 'check(..., true) still does NOT insert when blocked');

// 6) Wait for window to expire, then check() recovers
echo "  ...waiting " . ($window + 1) . "s for window to pass...\n";
sleep($window + 1);
$ok = $rl->check($action, $identifier, $max, $window);
assertEq(true, $ok, 'check() returns true after window expires (auto-cleanup)');

// Cleanup
$db->prepare("DELETE FROM rate_limit_log WHERE action = ?")->execute([$action]);

if ($failed) {
    echo "\nFAIL\n";
    exit(1);
}
echo "\nALL PASS\n";
exit(0);
