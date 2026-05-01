<?php
/**
 * Feature Flags Helper — Spencer's Website v7.0
 * Checks if a feature is enabled via site_settings table
 */

function featureEnabled($key) {
    global $db;
    static $cache = [];

    if (isset($cache[$key])) return $cache[$key];

    $defaultFlags = [
        'feature_ai_chat' => true,
        'feature_yaps_chat' => true,
        'feature_registration' => true,
        'feature_donations' => true,
        'feature_feedback' => true,
    ];

    if (!$db) {
        $cache[$key] = $defaultFlags[$key] ?? true;
        return $cache[$key];
    }

    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $cache[$key] = $r['setting_value'] === '1';
        } else {
            $cache[$key] = $defaultFlags[$key] ?? true;
        }
    } catch (Exception $e) {
        $cache[$key] = $defaultFlags[$key] ?? true;
    }

    return $cache[$key];
}
