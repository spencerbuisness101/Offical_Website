<?php
/**
 * Game Template Scripts - Spencer's Website v5.0
 * JavaScript for the game template
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
?>
<script>
// URL validation for background images
function isValidImageUrl(url) {
    return url && /^https?:\/\/.+/i.test(url);
}

// Fullscreen toggle
function toggleFullscreen() {
    const container = document.getElementById('gameContainer');
    const btn = container.querySelector('.fullscreen-btn');

    if (container.classList.contains('fullscreen')) {
        container.classList.remove('fullscreen');
        btn.textContent = 'Fullscreen';
        document.body.style.overflow = '';
    } else {
        container.classList.add('fullscreen');
        btn.textContent = 'Exit Fullscreen';
        document.body.style.overflow = 'hidden';
    }
}

// ESC to exit fullscreen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const container = document.getElementById('gameContainer');
        if (container && container.classList.contains('fullscreen')) {
            toggleFullscreen();
        }
    }
});

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        const logoutBtn = document.querySelector('.logout-btn');
        if (logoutBtn) {
            const originalText = logoutBtn.innerHTML;
            logoutBtn.innerHTML = 'Logging out...';
            logoutBtn.disabled = true;
        }

        const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch('auth/logout.php?csrf_token=' + encodeURIComponent(_csrfToken))
            .then(response => {
                if (response.ok) {
                    window.location.href = 'index.php';
                } else {
                    throw new Error('Logout failed');
                }
            })
            .catch(error => {
                console.error('Logout error:', error);
                if (logoutBtn) {
                    logoutBtn.innerHTML = originalText;
                    logoutBtn.disabled = false;
                }
                alert('Logout failed. Please try again.');
            });
    }
}

// Background Modal Functions
function openBackgroundModal() {
    const modal = document.getElementById('backgroundModal');
    if (modal) {
        modal.style.display = 'block';
        populateBackgrounds();
    }
}

function closeBackgroundModal() {
    const modal = document.getElementById('backgroundModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function populateBackgrounds() {
    const grid = document.getElementById('backgroundsGrid');
    if (!grid) return;

    const backgrounds = JSON.parse(document.body.dataset.backgrounds || '[]');
    const savedSettings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
    const currentBg = savedSettings.customBackground || '';

    grid.innerHTML = backgrounds.filter(bg => isValidImageUrl(bg.image_url)).map(bg => `
        <div class="background-item ${currentBg === bg.image_url ? 'active' : ''}"
             onclick="setBackground('${bg.image_url.replace(/'/g, "\\'")}')">
            <div class="background-preview" style="background-image: url('${bg.image_url}')"></div>
            <div class="background-info">
                <div class="background-title">${bg.title}</div>
                <div class="background-designer">by ${bg.designer_name || 'Unknown'}</div>
            </div>
        </div>
    `).join('');
}

function setBackground(url) {
    if (!isValidImageUrl(url)) return;
    const bgOverride = document.getElementById('bgThemeOverride');
    if (bgOverride) {
        bgOverride.style.backgroundImage = `url('${url}')`;
        bgOverride.classList.add('designer-bg');
    }

    // Save to localStorage
    const settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
    settings.customBackground = url;
    localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

    showNotification('Background updated!');
    closeBackgroundModal();
    populateBackgrounds();
}

function removeCustomBackground() {
    const bgOverride = document.getElementById('bgThemeOverride');
    if (bgOverride) {
        bgOverride.style.backgroundImage = '';
        bgOverride.classList.remove('designer-bg');
    }

    // Clear from localStorage
    const settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
    delete settings.customBackground;
    localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

    showNotification('Custom background removed');
    closeBackgroundModal();
    populateBackgrounds();
}

function showNotification(message, isError = false) {
    let notification = document.querySelector('.notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'notification';
        document.body.appendChild(notification);
    }

    notification.textContent = message;
    notification.classList.toggle('error', isError);
    notification.classList.add('show');

    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Apply saved settings
    const savedSettings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');

    // Check for server settings first (for background persistence fix)
    if (window.serverUserSettings && window.serverUserSettings.loaded && window.serverUserSettings.settings) {
        const serverSettings = window.serverUserSettings.settings;
        if (serverSettings.customBackground) {
            savedSettings.customBackground = serverSettings.customBackground;
        }
    }

    // Apply custom background
    if (savedSettings.customBackground && isValidImageUrl(savedSettings.customBackground)) {
        const bgOverride = document.getElementById('bgThemeOverride');
        if (bgOverride) {
            bgOverride.style.backgroundImage = `url('${savedSettings.customBackground}')`;
            bgOverride.classList.add('designer-bg');
        }
    } else {
        // Use active designer background from server
        const activeBg = document.body.dataset.activeBackground;
        if (activeBg && isValidImageUrl(activeBg)) {
            const bgOverride = document.getElementById('bgThemeOverride');
            if (bgOverride) {
                bgOverride.style.backgroundImage = `url('${activeBg}')`;
                bgOverride.classList.add('designer-bg');
            }
        }
    }

    // Apply accent color
    if (savedSettings.accentColor) {
        applyAccentColor(savedSettings.accentColor);
    }

    // Apply font size
    if (savedSettings.fontSize) {
        document.documentElement.style.fontSize = `${savedSettings.fontSize}px`;
    }

    // Close modal on outside click
    const modal = document.getElementById('backgroundModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeBackgroundModal();
            }
        });
    }
});

function applyAccentColor(color) {
    const accentColor = color.startsWith('#') ? color : `#${color}`;
    let styleElement = document.getElementById('accent-color-styles');
    if (!styleElement) {
        styleElement = document.createElement('style');
        styleElement.id = 'accent-color-styles';
        document.head.appendChild(styleElement);
    }

    styleElement.textContent = `
        .game-badge { background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important; }
        .game-nav a { background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important; }
        .feature-item { border-left-color: ${accentColor} !important; }
    `;
}
</script>
