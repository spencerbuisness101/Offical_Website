<?php
// Check if user can access background features (non-community roles only)
$user_role = $_SESSION['role'] ?? 'community';
$can_access_backgrounds = in_array($user_role, ['admin', 'designer', 'contributor', 'user']);
?>

<!-- Background Theme Override Element -->
<div class="bg-theme-override" id="bgThemeOverride"></div>

<?php if ($can_access_backgrounds): ?>
<!-- Background Selection Modal - Only for non-community roles -->
<div id="backgroundModal" class="background-modal">
    <div class="background-modal-content">
        <div class="background-modal-header">
            <h2 class="background-modal-title">🎨 Choose Your Background</h2>
            <button class="background-modal-close" onclick="closeBackgroundModal()">&times;</button>
        </div>

        <div class="user-background-info" style="background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #8b5cf6;">
            <h4 style="color: #8b5cf6; margin-bottom: 0.5rem;">Your Current Background</h4>
            <?php if (isset($user_background_preference) && $user_background_preference): ?>
                <p style="color: #94a3b8; margin: 0;">
                    <strong>"<?php echo htmlspecialchars($user_background_preference['title']); ?>"</strong>
                    <br><small>Selected on: <?php echo date('M j, Y', strtotime($user_background_preference['created_at'] ?? 'now')); ?></small>
                </p>
            <?php elseif (isset($active_designer_background) && $active_designer_background): ?>
                <p style="color: #94a3b8; margin: 0;">
                    <strong>Community Background: "<?php echo htmlspecialchars($active_designer_background['title']); ?>"</strong>
                </p>
            <?php else: ?>
                <p style="color: #94a3b8; margin: 0;">No background selected</p>
            <?php endif; ?>
        </div>

        <div class="backgrounds-grid" id="backgroundsGrid">
            <!-- Background items will be populated by JavaScript -->
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <button class="btn-remove-background" onclick="removeUserBackground()" style="padding: 12px 24px; font-size: 1rem;">
                🗑️ Remove My Background Choice
            </button>

            <?php if (isset($can_access_designer_features) && $can_access_designer_features): ?>
            <div style="margin-top: 1rem;">
                <!-- v7.0: Designer panel removed -->
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Control Buttons -->
<button class="logout-btn" onclick="logout()" aria-label="Logout from website">
    🚪 Logout (<?php echo $username; ?>)
</button>

<button class="setting-btn" onclick="window.location.href='set.php'">⚙️ Settings</button>

<?php if ($can_access_backgrounds): ?>
<button class="setting backgrounds-btn" onclick="openBackgroundModal()">🎨 My Background</button>
<?php endif; ?>

<!-- v7.0: Designer Panel button removed -->

<!-- Active Background Info - Only for non-community roles -->
<?php if ($can_access_backgrounds && isset($active_designer_background) && $active_designer_background && (!isset($user_background_preference) || !$user_background_preference)): ?>
<div class="background-info-section">
    <h3>🎨 Active Community Background</h3>
    <p><strong>"<?php echo htmlspecialchars($active_designer_background['title']); ?>"</strong></p>
    <p>Designed by: <?php echo htmlspecialchars($active_designer_background['designer_name']); ?></p>
    <button class="btn-set-background" onclick="setUserBackground(<?php
        // Find the background ID for the active background
        $active_bg_id = null;
        if (isset($available_backgrounds)) {
            foreach ($available_backgrounds as $bg) {
                if ($bg['image_url'] === $active_designer_background['image_url']) {
                    $active_bg_id = $bg['id'];
                    break;
                }
            }
        }
        echo $active_bg_id ?: 'null';
    ?>, '<?php echo $active_designer_background['image_url']; ?>', '<?php echo htmlspecialchars($active_designer_background['title']); ?>')" style="margin-top: 10px;">
        Use This Background
    </button>
</div>
<?php endif; ?>