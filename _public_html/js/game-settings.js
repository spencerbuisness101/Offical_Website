/**
 * Game Settings Helper
 * Applies fullscreen and volume settings from user preferences to game pages
 */

const GameSettings = {
    settings: null,

    // Initialize and load settings
    init: function() {
        this.loadSettings();
        this.applySettings();
        this.createVolumeControl();
        this.setupKeyboardShortcuts();
    },

    // Load settings from localStorage
    loadSettings: function() {
        try {
            const saved = localStorage.getItem('spencerWebsiteSettings');
            if (saved) {
                this.settings = JSON.parse(saved);
            } else {
                this.settings = {
                    gameVolume: 80,
                    fullscreenMode: false
                };
            }
        } catch (e) {
            console.error('Error loading game settings:', e);
            this.settings = { gameVolume: 80, fullscreenMode: false };
        }
    },

    // Apply settings to the game
    applySettings: function() {
        // Apply fullscreen mode if enabled
        if (this.settings.fullscreenMode) {
            this.autoEnterFullscreen();
        }

        // Try to apply volume to game iframe
        this.applyVolume(this.settings.gameVolume);
    },

    // Auto-enter fullscreen mode
    autoEnterFullscreen: function() {
        const gameContainer = document.getElementById('gameContainer');
        if (!gameContainer) return;

        // Small delay to ensure page is fully loaded
        setTimeout(() => {
            // Only auto-enter if user has fullscreen enabled in settings
            if (this.settings.fullscreenMode) {
                // Show notification that auto-fullscreen is available
                this.showNotification('Press F or click the Fullscreen button to enter fullscreen mode', 'info');
            }
        }, 500);
    },

    // Apply volume to game (attempt to communicate with iframe)
    applyVolume: function(volume) {
        const gameIframe = document.getElementById('gameIframe');
        if (!gameIframe) return;

        const normalizedVolume = volume / 100;

        // Store volume for reference
        window.gameVolume = normalizedVolume;

        // Try to send volume to iframe via postMessage (for compatible games)
        try {
            if (gameIframe.contentWindow) {
                gameIframe.contentWindow.postMessage({
                    type: 'setVolume',
                    volume: normalizedVolume
                }, '*');
            }
        } catch (e) {
            console.log('Could not send volume to game iframe (cross-origin)');
        }

        // Update volume control if it exists
        const volumeSlider = document.getElementById('gameVolumeControl');
        if (volumeSlider) {
            volumeSlider.value = volume;
        }
        const volumeDisplay = document.getElementById('volumeDisplay');
        if (volumeDisplay) {
            volumeDisplay.textContent = `${volume}%`;
        }

        // Apply volume to any audio elements on the page
        document.querySelectorAll('audio, video').forEach(media => {
            media.volume = normalizedVolume;
        });
    },

    // Create floating volume control
    createVolumeControl: function() {
        // Check if control already exists
        if (document.getElementById('volumeControlContainer')) return;

        const container = document.createElement('div');
        container.id = 'volumeControlContainer';
        container.innerHTML = `
            <style>
                #volumeControlContainer {
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    background: rgba(15, 23, 42, 0.95);
                    border: 2px solid rgba(78, 205, 196, 0.5);
                    border-radius: 12px;
                    padding: 15px 20px;
                    z-index: 9998;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                    backdrop-filter: blur(10px);
                    transition: all 0.3s ease;
                }
                #volumeControlContainer:hover {
                    border-color: rgba(78, 205, 196, 0.8);
                    transform: translateY(-2px);
                }
                #volumeControlContainer .volume-icon {
                    font-size: 20px;
                    cursor: pointer;
                }
                #gameVolumeControl {
                    width: 100px;
                    height: 6px;
                    -webkit-appearance: none;
                    appearance: none;
                    background: rgba(255,255,255,0.2);
                    border-radius: 3px;
                    cursor: pointer;
                }
                #gameVolumeControl::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #4ECDC4, #44A08D);
                    cursor: pointer;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                }
                #gameVolumeControl::-moz-range-thumb {
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #4ECDC4, #44A08D);
                    cursor: pointer;
                    border: none;
                }
                #volumeDisplay {
                    color: #4ECDC4;
                    font-weight: 600;
                    min-width: 40px;
                    text-align: center;
                    font-size: 14px;
                }
                #volumeControlContainer .volume-tip {
                    color: #94a3b8;
                    font-size: 11px;
                    position: absolute;
                    bottom: -18px;
                    left: 50%;
                    transform: translateX(-50%);
                    white-space: nowrap;
                }
            </style>
            <span class="volume-icon" onclick="GameSettings.toggleMute()" title="Click to mute/unmute">🔊</span>
            <input type="range" id="gameVolumeControl" min="0" max="100" value="${this.settings.gameVolume || 80}">
            <span id="volumeDisplay">${this.settings.gameVolume || 80}%</span>
        `;

        document.body.appendChild(container);

        // Add event listener for volume slider
        document.getElementById('gameVolumeControl').addEventListener('input', (e) => {
            const volume = parseInt(e.target.value);
            this.settings.gameVolume = volume;
            this.applyVolume(volume);
            this.saveSettings();

            // Update icon
            const icon = container.querySelector('.volume-icon');
            if (volume === 0) {
                icon.textContent = '🔇';
            } else if (volume < 50) {
                icon.textContent = '🔉';
            } else {
                icon.textContent = '🔊';
            }
        });
    },

    // Toggle mute
    toggleMute: function() {
        const slider = document.getElementById('gameVolumeControl');
        const icon = document.querySelector('#volumeControlContainer .volume-icon');

        if (parseInt(slider.value) > 0) {
            // Store current volume and mute
            this.previousVolume = parseInt(slider.value);
            slider.value = 0;
            icon.textContent = '🔇';
        } else {
            // Restore previous volume
            slider.value = this.previousVolume || 80;
            icon.textContent = '🔊';
        }

        this.settings.gameVolume = parseInt(slider.value);
        this.applyVolume(parseInt(slider.value));
        this.saveSettings();

        document.getElementById('volumeDisplay').textContent = `${slider.value}%`;
    },

    // Setup keyboard shortcuts
    setupKeyboardShortcuts: function() {
        document.addEventListener('keydown', (e) => {
            // M key for mute toggle
            if (e.key === 'm' || e.key === 'M') {
                if (!e.ctrlKey && !e.altKey && document.activeElement.tagName !== 'INPUT') {
                    e.preventDefault();
                    this.toggleMute();
                }
            }

            // Arrow up/down for volume
            if (e.key === 'ArrowUp' && e.shiftKey) {
                e.preventDefault();
                this.adjustVolume(10);
            }
            if (e.key === 'ArrowDown' && e.shiftKey) {
                e.preventDefault();
                this.adjustVolume(-10);
            }
        });
    },

    // Adjust volume by delta
    adjustVolume: function(delta) {
        let newVolume = (this.settings.gameVolume || 80) + delta;
        newVolume = Math.max(0, Math.min(100, newVolume));

        this.settings.gameVolume = newVolume;
        this.applyVolume(newVolume);
        this.saveSettings();

        const slider = document.getElementById('gameVolumeControl');
        if (slider) slider.value = newVolume;

        const display = document.getElementById('volumeDisplay');
        if (display) display.textContent = `${newVolume}%`;

        const icon = document.querySelector('#volumeControlContainer .volume-icon');
        if (icon) {
            if (newVolume === 0) icon.textContent = '🔇';
            else if (newVolume < 50) icon.textContent = '🔉';
            else icon.textContent = '🔊';
        }

        this.showNotification(`Volume: ${newVolume}%`, 'info');
    },

    // Save settings back to localStorage
    saveSettings: function() {
        try {
            const allSettings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            allSettings.gameVolume = this.settings.gameVolume;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(allSettings));
        } catch (e) {
            console.error('Error saving settings:', e);
        }
    },

    // Show notification
    showNotification: function(message, type = 'success') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: ${type === 'info' ? 'linear-gradient(135deg, #3b82f6, #1d4ed8)' : 'linear-gradient(135deg, #10b981, #059669)'};
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 10001;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.transform = 'translateX(-50%) translateY(0)';
        }, 10);

        setTimeout(() => {
            notification.style.transform = 'translateX(-50%) translateY(-100px)';
            setTimeout(() => notification.remove(), 300);
        }, 2500);
    },

    previousVolume: 80
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => GameSettings.init());
} else {
    GameSettings.init();
}
