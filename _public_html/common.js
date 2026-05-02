/**
 * Spencer's Website Common JavaScript - v5.0
 * Activity tracking, settings management, and utility functions
 */

class ActivityTracker {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.userId = this.getUserId();
        this.startTime = Date.now();
        this.init();
    }

    generateSessionId() {
        return 'session_' + Math.random().toString(36).substr(2, 9);
    }

    getUserId() {
        return null;
    }

    init() {
        this.trackPageView();
        this.trackPerformance();
        this.setupActivityListeners();
        this.heartbeat();
        this.setupGameTracking();
    }

    trackPageView() {
        const data = {
            page_url: window.location.pathname,
            session_id: this.sessionId,
            user_id: this.userId,
            referrer: document.referrer
        };
        this.sendData('api/track_pageview.php', data);
    }

    trackPerformance() {
        window.addEventListener('load', () => {
            const loadTime = Date.now() - this.startTime;
            const data = {
                page_url: window.location.pathname,
                load_time: loadTime,
                session_id: this.sessionId,
                user_id: this.userId
            };
            this.sendData('api/track_performance.php', data);
        });
    }

    setupActivityListeners() {
        document.addEventListener('click', (e) => {
            const heatmapData = {
                x: e.clientX,
                y: e.clientY,
                page_url: window.location.pathname,
                session_id: this.sessionId,
                user_id: this.userId,
                element: e.target.tagName,
                interaction_type: 'click'
            };
            this.sendData('api/track_interaction.php', heatmapData);
        });
        this.trackContentInteractions();
    }

    setupGameTracking() {
        const gameLinks = document.querySelectorAll('a[href*=".php"]');
        gameLinks.forEach(link => {
            if (link.href.includes('game') ||
                ['tomb.php', 'block.php', 'slope.php', 'cookie.php', 'fnaf.php', 'retro.php', 'bit.php', 'time.php', 'roof.php'].some(game => link.href.includes(game))) {
                link.addEventListener('click', () => {
                    this.trackGameStart(link.href);
                });
            }
        });
    }

    trackGameStart(gameUrl) {
        const data = {
            game: gameUrl,
            session_id: this.sessionId,
            user_id: this.userId,
            action: 'game_start'
        };
        this.sendData('api/track_game.php', data);
    }


    trackContentInteractions() {
        const settings = document.querySelector('.settings-container');
        if (settings) {
            settings.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox' || e.target.type === 'range' || e.target.tagName === 'SELECT') {
                    this.trackSettingChange(e.target.name, e.target.value);
                }
            });
        }
        const comingSoonButtons = document.querySelectorAll('[onclick*="comingSoon"]');
        comingSoonButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.trackFeatureAttempt('coming_soon_feature');
            });
        });
    }

    trackSettingChange(setting, value) {
        const data = {
            setting: setting,
            value: value,
            session_id: this.sessionId,
            user_id: this.userId,
            action: 'setting_change'
        };
        this.sendData('api/track_setting.php', data);
    }

    trackFeatureAttempt(featureName) {
        const data = {
            feature: featureName,
            session_id: this.sessionId,
            user_id: this.userId,
            action: 'feature_attempt'
        };
        this.sendData('api/track_feature.php', data);
    }

    heartbeat() {
        setInterval(() => {
            this.sendData('api/session_heartbeat.php', {
                session_id: this.sessionId,
                user_id: this.userId,
                page_url: window.location.pathname,
                current_page: document.title
            });
        }, 30000);
    }

    sendData(url, data) {
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).catch(() => {});
        }
    }
}

// Coming Soon notification
window.comingSoon = function(featureName = 'this feature') {
    console.log('User attempted to access:', featureName);
    if (window.activityTracker) {
        window.activityTracker.trackFeatureAttempt(featureName);
    }
    const notification = document.createElement('div');
    notification.style.cssText = `
        position:fixed;top:30px;right:30px;background:linear-gradient(45deg,#FF6B6B,#4ECDC4);
        color:white;padding:20px 25px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,0.3);
        z-index:10000;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-weight:600;
        animation:slideInRight 0.4s ease-out;max-width:300px;border-left:5px solid rgba(255,255,255,0.3);
    `;
    notification.innerHTML = `
        <div style="font-size:18px;margin-bottom:5px;">Coming Soon!</div>
        <div style="font-size:14px;opacity:0.9;">We're working hard to bring you ${featureName}!</div>
    `;
    document.body.appendChild(notification);

    if (!document.getElementById('enhanced-notification-styles')) {
        const style = document.createElement('style');
        style.id = 'enhanced-notification-styles';
        style.textContent = `
            @keyframes slideInRight{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
            @keyframes slideOutRight{from{transform:translateX(0);opacity:1;}to{transform:translateX(100%);opacity:0;}}
        `;
        document.head.appendChild(style);
    }

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.4s ease-in';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 400);
    }, 4000);
};

/**
 * v5.0: Merge server settings with localStorage
 * Server settings take precedence for logged-in users
 */
function getEffectiveSettings() {
    // Start with localStorage settings
    let settings = {};
    const savedSettings = localStorage.getItem('spencerWebsiteSettings');
    if (savedSettings) {
        try {
            settings = JSON.parse(savedSettings);
        } catch (e) {
            console.error('Failed to parse localStorage settings:', e);
        }
    }

    // Merge with server settings if available (v5.0 background fix)
    if (window.serverUserSettings && window.serverUserSettings.loaded) {
        const serverSettings = window.serverUserSettings.settings || {};

        // Server settings override localStorage for synced values
        if (serverSettings.customBackground) {
            settings.customBackground = serverSettings.customBackground;
        }
        if (serverSettings.accentColor) {
            settings.accentColor = serverSettings.accentColor;
        }
        if (serverSettings.fontSize) {
            settings.fontSize = serverSettings.fontSize;
        }

        // Check for active designer background
        if (window.serverUserSettings.activeDesignerBackground && !settings.customBackground) {
            settings.activeDesignerBackground = window.serverUserSettings.activeDesignerBackground.image_url;
        }
    }

    return settings;
}

/**
 * Validate that a URL is a safe image URL (http/https only)
 */
function isValidImageUrl(url) {
    return url && /^https?:\/\/.+/i.test(url);
}

/**
 * Apply settings to current page
 */
function applySettingsToPage(settings) {
    // Apply accent color
    if (settings.accentColor) {
        const accentColor = settings.accentColor.startsWith('#') ? settings.accentColor : `#${settings.accentColor}`;
        let styleElement = document.getElementById('accent-color-styles');
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'accent-color-styles';
            document.head.appendChild(styleElement);
        }
        styleElement.textContent = `
            .game-button,.move-button,.info-button{background:linear-gradient(45deg,#FF6B6B,${accentColor})!important;}
            .game-button:hover,.move-button:hover,.info-button:hover{background:linear-gradient(45deg,${accentColor},#FF6B6B)!important;}
        `;
    }

    // Apply font size
    if (settings.fontSize) {
        document.documentElement.style.fontSize = `${settings.fontSize}px`;
    }

    // Apply background (v5.0 fix - check for both custom and active designer backgrounds)
    const bgOverride = document.getElementById('bgThemeOverride');
    if (bgOverride) {
        if (settings.customBackground && isValidImageUrl(settings.customBackground)) {
            bgOverride.style.backgroundImage = `url('${settings.customBackground}')`;
            bgOverride.classList.add('designer-bg');
        } else if (settings.activeDesignerBackground && isValidImageUrl(settings.activeDesignerBackground)) {
            bgOverride.style.backgroundImage = `url('${settings.activeDesignerBackground}')`;
            bgOverride.classList.add('designer-bg');
        }
    }

    if (window.activityTracker) {
        window.activityTracker.trackInteraction && window.activityTracker.trackInteraction('settings_applied');
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize activity tracker
    if (typeof ActivityTracker !== 'undefined') {
        window.activityTracker = new ActivityTracker();
    }

    // Load and apply settings (with v5.0 server merge)
    const settings = getEffectiveSettings();
    if (Object.keys(settings).length > 0) {
        applySettingsToPage(settings);
    }
});
