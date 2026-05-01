<?php
/**
 * Game Template Styles - Spencer's Website v5.0
 * CSS styles for the game template
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
?>
<style>
/* Control buttons */
.logout-btn, .setting {
    position: fixed;
    right: 25px;
    background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
    color: #ffffff;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    z-index: 1001;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.logout-btn { top: 25px; }
.setting { top: 85px; background: linear-gradient(135deg, #667eea, #764ba2); }
.backgrounds-btn { top: 145px; background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important; }

.logout-btn:hover, .setting:hover, .backgrounds-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.5);
    color: #ffffff;
}

/* Background Modal */
.background-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10000;
    backdrop-filter: blur(10px);
}

.background-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
}

.background-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.background-modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.background-modal-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.background-modal-close:hover { color: white; }

.backgrounds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.background-item {
    background: rgba(15, 23, 42, 0.7);
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.background-item:hover {
    transform: translateY(-5px);
    border-color: #8b5cf6;
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
}

.background-item.active {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
}

.background-preview {
    width: 100%;
    height: 150px;
    background-size: cover;
    background-position: center;
}

.background-info { padding: 1rem; }
.background-title { font-weight: 600; color: white; margin-bottom: 0.5rem; font-size: 0.95rem; }
.background-designer { color: #94a3b8; font-size: 0.85rem; }

.btn-set-background {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
}

.btn-remove-background {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

/* Game Player */
.game-player {
    max-width: 900px;
    margin: 30px auto;
    padding: 20px;
    text-align: center;
}

.game-container {
    position: relative;
    background: #000;
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    overflow: hidden;
}

.game-iframe {
    width: 100%;
    height: 600px;
    border: none;
    display: block;
}

.fullscreen-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    border-radius: 5px;
    padding: 8px 15px;
    cursor: pointer;
    font-size: 14px;
    z-index: 10;
    transition: all 0.3s ease;
}

.fullscreen-btn:hover {
    background: rgba(0, 0, 0, 0.9);
    transform: scale(1.05);
}

/* Game Details */
.game-details {
    background: rgba(0, 0, 0, 0.9);
    border: 3px solid #FF6B6B;
    padding: 35px;
    border-radius: 15px;
    margin: 30px 0;
    box-shadow: 0 10px 35px rgba(0,0,0,0.4);
    text-align: left;
    color: #ffffff;
}

.game-details h2 {
    color: #FF6B6B;
    border-bottom: 3px solid #FF6B6B;
    padding-bottom: 15px;
    margin-bottom: 25px;
    font-size: 28px;
    font-weight: 700;
}

.game-details h3 {
    color: #4ECDC4;
    margin-top: 25px;
    margin-bottom: 15px;
    font-size: 22px;
    font-weight: 700;
}

.game-details p {
    color: #f8f9fa;
    margin-bottom: 15px;
    font-size: 17px;
    font-weight: 500;
    line-height: 1.7;
}

.game-details strong { color: #FF6B6B; font-weight: 700; }

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.feature-item {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #4ECDC4;
}

/* Controls Box */
.controls-box {
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(78,205,196,0.1) 100%);
    padding: 20px;
    border-radius: 10px;
    border-left: 5px solid #FF6B6B;
    margin: 20px 0;
}

.controls-box ul {
    padding-left: 20px;
    margin: 10px 0;
}

.controls-box li {
    margin-bottom: 8px;
    color: #f8f9fa;
}

/* Tips List */
.tips-list {
    color: #f8f9fa;
    font-size: 16px;
    font-weight: 500;
    line-height: 1.7;
    padding-left: 20px;
}

.tips-list li { margin-bottom: 8px; }

/* Pro Tip Box */
.pro-tip-box {
    background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2));
    border: 2px solid #4ECDC4;
    border-radius: 10px;
    padding: 20px;
    margin-top: 25px;
    text-align: center;
}

.pro-tip-box h4 {
    color: #4ECDC4;
    margin-bottom: 10px;
    font-weight: 700;
}

.pro-tip-box p { color: #f8f9fa; font-weight: 500; margin: 0; }

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 3em;
    margin-bottom: 10px;
    background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 800;
}

.page-header p {
    font-size: 1.3em;
    color: #ffffff;
    font-weight: 600;
}

.game-badge {
    background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-weight: 700;
    font-size: 12px;
    display: inline-block;
    margin-left: 10px;
}

/* Game Nav */
.game-nav {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 30px 0;
    flex-wrap: wrap;
}

.game-nav a {
    background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
    color: white;
    padding: 15px 30px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.game-nav a:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
}

/* Fullscreen */
.game-container.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    border-radius: 0;
}

.game-container.fullscreen .game-iframe {
    width: 100%;
    height: 100%;
}

.game-container.fullscreen .fullscreen-btn {
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    background: #10b981;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 10000;
    transform: translateX(400px);
    transition: all 0.3s ease;
}

.notification.show { transform: translateX(0); }
.notification.error { background: #ef4444; }

/* Responsive */
@media (max-width: 768px) {
    .logout-btn, .setting, .backgrounds-btn {
        position: relative;
        display: block;
        margin: 10px auto;
        width: 200px;
        text-align: center;
        right: auto;
        top: 0;
    }

    .backgrounds-grid { grid-template-columns: 1fr; }

    .page-header h1 { font-size: 2em; }

    .game-details { padding: 20px; }
    .game-details h2 { font-size: 22px; }
    .game-details h3 { font-size: 18px; }
}
</style>
