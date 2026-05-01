<?php
/**
 * Shared Role Configuration - Spencer's Website v7.0
 * Defines role hierarchy, colors, icons, and permissions used across all pages.
 *
 * Role Levels:
 *   0 = community (lowest, default)
 *   1 = user
 *   2 = contributor / designer (same privileges, separate panels)
 *   3 = admin (highest)
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

$ROLE_CONFIG = [
    'community' => [
        'level'         => 0,
        'name'          => 'Community',
        'color'         => '#10b981',
        'gradient'      => 'linear-gradient(135deg, #10b981, #059669)',
        'icon'          => 'fa-users',
        'can_customize' => false,
        'description'   => 'Most basic privileges and benefits. Default role for new users. (FREE)',
        'privileges'    => ['Basic site access', 'Community features', 'Yaps chat'],
        'obtainable'    => true,
        'panel'         => null,
    ],
    'user' => [
        'level'         => 1,
        'name'          => 'User',
        'color'         => '#3b82f6',
        'gradient'      => 'linear-gradient(135deg, #3b82f6, #2563eb)',
        'icon'          => 'fa-user',
        'can_customize' => true,
        'description'   => 'Full access to all member benefits. $2/month, $30/year, or $100 lifetime.',
        'privileges'    => ['Custom backgrounds', 'Chat tags', 'AI assistant access', 'Full site features', 'Accent color customization', 'Server-synced settings'],
        'obtainable'    => true,
        'panel'         => 'user_panel.php',
    ],
    'contributor' => [
        'level'         => 2,
        'name'          => 'Contributor',
        'color'         => '#f59e0b',
        'gradient'      => 'linear-gradient(135deg, #f59e0b, #d97706)',
        'icon'          => 'fa-lightbulb',
        'can_customize' => true,
        'description'   => 'Unobtainable unless very helpful to the owner. Can submit feature ideas.',
        'privileges'    => ['Submit feature ideas', 'Priority feedback', 'Contributor panel access', 'All User privileges'],
        'obtainable'    => false,
        'panel'         => 'contributor_panel.php',
    ],
    'designer' => [
        'level'         => 2,
        'name'          => 'Designer',
        'color'         => '#ec4899',
        'gradient'      => 'linear-gradient(135deg, #ec4899, #db2777)',
        'icon'          => 'fa-palette',
        'can_customize' => true,
        'description'   => 'Unobtainable unless very helpful to the owner. Can submit custom backgrounds.',
        'privileges'    => ['Submit custom backgrounds', 'Design contributions', 'Designer panel access', 'All User privileges'],
        'obtainable'    => false,
        'panel'         => 'designer_panel.php',
    ],
    'admin' => [
        'level'         => 3,
        'name'          => 'Admin',
        'color'         => '#ef4444',
        'gradient'      => 'linear-gradient(135deg, #ef4444, #dc2626)',
        'icon'          => 'fa-crown',
        'can_customize' => true,
        'description'   => 'Highest rank. Permanently unobtainable. Full site control. (UNOBTAINABLE!)',
        'privileges'    => ['Full admin panel access', 'User management', 'Site configuration', 'All features unlocked'],
        'obtainable'    => false,
        'panel'         => 'admin.php',
    ],
];

/**
 * Get the numeric level for a role.
 * @param string $role
 * @return int
 */
function getRoleLevel($role) {
    global $ROLE_CONFIG;
    return isset($ROLE_CONFIG[$role]) ? $ROLE_CONFIG[$role]['level'] : 0;
}

/**
 * Check if $currentRole is at or above $requiredRole in hierarchy.
 * @param string $currentRole
 * @param string $requiredRole
 * @return bool
 */
function hasRoleOrHigher($currentRole, $requiredRole) {
    return getRoleLevel($currentRole) >= getRoleLevel($requiredRole);
}

/**
 * Check if a role can customize accent colors, backgrounds, etc.
 * @param string $role
 * @return bool
 */
function canCustomize($role) {
    global $ROLE_CONFIG;
    return isset($ROLE_CONFIG[$role]) ? $ROLE_CONFIG[$role]['can_customize'] : false;
}

/**
 * Get role display info (color, icon, name).
 * @param string $role
 * @return array
 */
function getRoleInfo($role) {
    global $ROLE_CONFIG;
    if (isset($ROLE_CONFIG[$role])) {
        return $ROLE_CONFIG[$role];
    }
    return $ROLE_CONFIG['community'];
}
