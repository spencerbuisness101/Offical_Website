<?php
/**
 * Main page — Status banners (guest warning, subscription bar, upgrade flash)
 * Expects: $role, $is_guest, $username, $db, $user_id
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }
?>

<?php if ($is_guest): ?>
<div class="mp-banner warn mp-reveal">
    <div class="mp-banner-inner">
        <div class="mp-banner-icon">⚠️</div>
        <div class="mp-banner-text">
            <strong>Temporary Guest Account</strong> — You are signed in as <strong><?php echo htmlspecialchars($username); ?></strong>.
            This account will be deleted when you sign out or after 24 hours of inactivity. Create a permanent account to keep your progress.
        </div>
        <a href="auth/register_with_security.php" class="mp-banner-cta">Create Permanent Account</a>
    </div>
</div>
<?php endif; ?>

<?php if ($role === 'user'):
    $subStatus = $_SESSION['_sub_status'] ?? 'active';
    $userPremium = null;
    try {
        if (function_exists('getUserPremium') && isset($db) && $db) {
            $userPremium = getUserPremium($db, $user_id);
        }
    } catch (Exception $e) { /* non-critical */ }
    $subPlanType = $userPremium['plan_type'] ?? 'lifetime';
    $nextBilling = $userPremium['current_period_end'] ?? null;
    $graceWarning = !empty($_SESSION['_sub_grace_warning']);
?>
<div class="mp-banner <?php echo $graceWarning ? 'warn' : 'success'; ?> mp-reveal">
    <div class="mp-banner-inner">
        <div class="mp-banner-icon"><?php echo $subPlanType === 'lifetime' ? '✨' : '↻'; ?></div>
        <div class="mp-banner-text">
            <strong><?php echo $subPlanType === 'lifetime' ? 'Lifetime Access' : 'Monthly Subscription'; ?></strong>
            <?php if ($graceWarning): ?>
                — <span style="color:#FBBF24;">Payment overdue, please update your payment method.</span>
            <?php elseif ($subPlanType === 'monthly' && $nextBilling): ?>
                — Next billing on <?php echo date('M j, Y', strtotime($nextBilling)); ?>
            <?php else: ?>
                — Active
            <?php endif; ?>
        </div>
        <a href="manage_subscription.php" class="mp-banner-cta">Manage Plan</a>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['upgrade']) && $_GET['upgrade'] === 'success'): ?>
<div class="mp-banner success mp-reveal">
    <div class="mp-banner-inner">
        <div class="mp-banner-icon">🎉</div>
        <div class="mp-banner-text">Upgrade successful! Refresh the page to see your new role and perks.</div>
    </div>
</div>
<?php elseif (isset($_GET['upgrade']) && $_GET['upgrade'] === 'cancelled'): ?>
<div class="mp-banner warn mp-reveal">
    <div class="mp-banner-inner">
        <div class="mp-banner-icon">↩</div>
        <div class="mp-banner-text">Payment was cancelled. You can try again anytime.</div>
    </div>
</div>
<?php endif; ?>
