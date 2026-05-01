<?php
/**
 * Subscription Helpers - Spencer's Website v7.0
 * Manages subscription lifecycle: queries, renewals, cancellations, history.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Get the active subscription for a user.
 * @param PDO $db
 * @param int $userId
 * @return array|false
 */
function getActiveSubscription($db, $userId) {
    $stmt = $db->prepare("
        SELECT id, user_id, provider_subscription_id, status, current_period_end, provider, created_at, updated_at FROM subscriptions
        WHERE user_id = ? AND status IN ('active','past_due')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get the most recent subscription of any status for a user.
 * @param PDO $db
 * @param int $userId
 * @return array|false
 */
function getLatestSubscription($db, $userId) {
    $stmt = $db->prepare("
        SELECT id, user_id, provider_subscription_id, status, current_period_end, provider, created_at, updated_at FROM subscriptions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update the subscription period end after a successful payment.
 * @param PDO $db
 * @param int $subscriptionId
 * @param string $periodEnd  MySQL TIMESTAMP string
 * @return bool
 */
function updateSubscriptionPeriod($db, $subscriptionId, $periodEnd) {
    $stmt = $db->prepare("
        UPDATE subscriptions
        SET current_period_end = ?, status = 'active', updated_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$periodEnd, $subscriptionId]);
}

/**
 * Cancel a subscription in the database.
 * @param PDO $db
 * @param int $userId
 * @param string $reason
 * @return bool
 */
function cancelSubscription($db, $userId, $reason = 'User cancelled') {
    try {
        $db->prepare("
            UPDATE subscriptions
            SET status = 'cancelled', cancelled_at = NOW(), ended_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND status IN ('active','past_due','suspended')
        ")->execute([$userId]);

        $db->prepare("
            UPDATE user_premium
            SET subscription_status = 'cancelled'
            WHERE user_id = ?
        ")->execute([$userId]);

        return true;
    } catch (Exception $e) {
        error_log("Cancel subscription failed for user $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Admin manual renewal: extend subscription by N days and clear suspension.
 * @param PDO $db
 * @param int $userId
 * @param int $daysToAdd
 * @return bool
 */
function renewSubscription($db, $userId, $daysToAdd = 30, $planType = 'monthly') {
    try {
        $db->beginTransaction();

        // Update or create subscription
        $sub = getActiveSubscription($db, $userId);
        if ($sub) {
            $newEnd = max(strtotime($sub['current_period_end'] ?? 'now'), time()) + ($daysToAdd * 86400);
            $db->prepare("
                UPDATE subscriptions
                SET current_period_end = ?, status = 'active', updated_at = NOW()
                WHERE id = ?
            ")->execute([date('Y-m-d H:i:s', $newEnd), $sub['id']]);
        } else {
            // Create a new subscription entry
            $newEnd = time() + ($daysToAdd * 86400);
            $db->prepare("
                INSERT INTO subscriptions (user_id, plan_type, provider, status, amount_cents, current_period_start, current_period_end)
                VALUES (?, 'monthly', 'admin_manual', 'active', 200, NOW(), ?)
            ")->execute([$userId, date('Y-m-d H:i:s', $newEnd)]);
        }

        // Update user_premium
        $db->prepare("
            INSERT INTO user_premium (user_id, is_premium, premium_since, plan_type, provider, subscription_status, current_period_end, last_payment_at)
            VALUES (?, TRUE, NOW(), 'monthly', 'admin_manual', 'active', ?, NOW())
            ON DUPLICATE KEY UPDATE
                is_premium = TRUE,
                subscription_status = 'active',
                current_period_end = VALUES(current_period_end),
                last_payment_at = NOW(),
                provider = 'admin_manual'
        ")->execute([$userId, date('Y-m-d H:i:s', time() + ($daysToAdd * 86400))]);

        // Ensure user has 'user' role and is not suspended
        $db->prepare("UPDATE users SET role = 'user', is_suspended = FALSE, suspended_at = NULL, suspension_reason = NULL WHERE id = ? AND role IN ('community','user')")->execute([$userId]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Renew subscription failed for user $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get subscription/payment history for a user.
 * @param PDO $db
 * @param int $userId
 * @param int $limit
 * @return array
 */
function getSubscriptionHistory($db, $userId, $limit = 20) {
    try {
        $stmt = $db->prepare("
            SELECT ps.id, ps.provider, ps.plan_type, ps.amount_cents, ps.status, ps.created_at, ps.completed_at
            FROM payment_sessions ps
            WHERE ps.user_id = ? AND ps.status IN ('paid','refunded')
            ORDER BY ps.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Subscription history error for user $userId: " . $e->getMessage());
        return [];
    }
}

/**
 * Get the user_premium record for a user.
 * @param PDO $db
 * @param int $userId
 * @return array|false
 */
function getUserPremium($db, $userId) {
    $stmt = $db->prepare("SELECT id, user_id, plan_type, provider, subscription_status, premium_since, current_period_end FROM user_premium WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Find a subscription by provider subscription ID.
 * @param PDO $db
 * @param string $providerSubId
 * @return array|false
 */
function getSubscriptionByProviderId($db, $providerSubId) {
    $stmt = $db->prepare("SELECT id, user_id, provider_subscription_id, status, current_period_end, provider, created_at, updated_at FROM subscriptions WHERE provider_subscription_id = ? LIMIT 1");
    $stmt->execute([$providerSubId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update subscription status by provider subscription ID.
 * @param PDO $db
 * @param string $providerSubId
 * @param string $status
 * @param string|null $periodEnd
 * @return bool
 */
function updateSubscriptionByProviderId($db, $providerSubId, $status, $periodEnd = null) {
    try {
        $sql = "UPDATE subscriptions SET status = ?, updated_at = NOW()";
        $params = [$status];

        if ($periodEnd !== null) {
            $sql .= ", current_period_end = ?";
            $params[] = $periodEnd;
        }

        if ($status === 'cancelled') {
            $sql .= ", cancelled_at = NOW()";
        }

        $sql .= " WHERE provider_subscription_id = ?";
        $params[] = $providerSubId;

        $db->prepare($sql)->execute($params);
        return true;
    } catch (Exception $e) {
        error_log("Update subscription by provider ID failed: " . $e->getMessage());
        return false;
    }
}
