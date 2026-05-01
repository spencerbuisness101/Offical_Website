<?php
/**
 * Game Template Controls
 *
 * Common control buttons and background modal for game pages.
 * Include this after game_template_header.php
 */
?>

<!-- Control Buttons -->
<div class="button-container">
    <a href="main.php" class="control-button">
        <span class="button-icon">&#127968;</span>
        <span>Main Menu</span>
    </a>
    <a href="games.php" class="control-button">
        <span class="button-icon">&#127918;</span>
        <span>Games</span>
    </a>
    <button onclick="openBackgroundModal()" class="control-button">
        <span class="button-icon">&#127912;</span>
        <span>Backgrounds</span>
    </button>
    <button onclick="openSettingsModal()" class="control-button">
        <span class="button-icon">&#9881;</span>
        <span>Settings</span>
    </button>
    <button onclick="confirmLogout()" class="control-button logout-button">
        <span class="button-icon">&#128682;</span>
        <span>Logout</span>
    </button>
</div>

<!-- Background Selection Modal -->
<div id="backgroundModal" class="modal-overlay" style="display: none;" onclick="closeBackgroundModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>Choose Background</h2>
            <button class="modal-close" onclick="closeBackgroundModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="backgrounds-grid">
                <?php if (!empty($available_backgrounds)): ?>
                    <?php foreach ($available_backgrounds as $bg): ?>
                        <div class="background-option" onclick="selectBackground('<?php echo htmlspecialchars($bg['image_url']); ?>')">
                            <div class="background-preview" style="background-image: url('<?php echo htmlspecialchars($bg['image_url']); ?>')"></div>
                            <div class="background-info">
                                <span class="background-title"><?php echo htmlspecialchars($bg['title']); ?></span>
                                <span class="background-designer">by <?php echo htmlspecialchars($bg['designer_name'] ?? 'Unknown'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-backgrounds">No backgrounds available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="modal-overlay" style="display: none;" onclick="closeSettingsModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>Settings</h2>
            <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="setting-item">
                <label for="volumeSlider">Sound Volume</label>
                <input type="range" id="volumeSlider" min="0" max="100" value="50" onchange="updateVolume(this.value)">
            </div>
            <div class="setting-item">
                <label>
                    <input type="checkbox" id="fullscreenOnPlay" onchange="updateFullscreenPref(this.checked)">
                    Auto-fullscreen on game start
                </label>
            </div>
        </div>
    </div>
</div>

<!-- User Info Display -->
<div class="user-info-display">
    <span class="user-name"><?php echo $username; ?></span>
    <span class="user-role role-<?php echo $role; ?>"><?php echo ucfirst($role); ?></span>
</div>
