window.addEventListener('load', function() {
    initializeAnimations();
});
function initializeAnimations() {
    var elements = document.querySelectorAll('.game-card,.infol,.nav-card');
    elements.forEach(function(el, i) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease,transform 0.6s ease';
        setTimeout(function() {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, 100 + (i * 100));
    });
}
function comingSoon() {
    var notification = document.createElement('div');
    notification.style.cssText = 'position:fixed;top:30px;right:30px;background:linear-gradient(45deg,#FF6B6B,#4ECDC4);color:white;padding:20px 25px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,0.3);z-index:10000;font-family:var(--font);font-weight:600;animation:slideInRight 0.4s ease-out;max-width:300px;border-left:5px solid rgba(255,255,255,0.3);';
    notification.innerHTML = '<div style="font-size:18px;margin-bottom:5px;">Coming Soon!</div><div style="font-size:14px;opacity:0.9;">We\'re working hard to bring you this feature!</div>';
    document.body.appendChild(notification);
    if (!document.getElementById('enhanced-notification-styles')) {
        var style = document.createElement('style');
        style.id = 'enhanced-notification-styles';
        style.textContent = '@keyframes slideInRight{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}@keyframes slideOutRight{from{transform:translateX(0);opacity:1;}to{transform:translateX(100%);opacity:0;}}';
        document.head.appendChild(style);
    }
    setTimeout(function() {
        notification.style.animation = 'slideOutRight 0.4s ease-in';
        setTimeout(function() { if (document.body.contains(notification)) document.body.removeChild(notification); }, 400);
    }, 4000);
}
function logout() {
    window.location.href = 'index.php';
}
function smoothScrollTo(element) {
    if (element && typeof element.scrollIntoView === 'function') {
        try { element.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) {}
    }
}
function initializeExternalLinks() {
    document.querySelectorAll('a[href^="http"]').forEach(function(link) {
        if (!link.getAttribute('rel')) link.setAttribute('rel', 'noopener noreferrer');
        if (!link.getAttribute('aria-label')) link.setAttribute('aria-label', (link.textContent || 'external link') + ' (opens in new window)');
    });
}
document.addEventListener('DOMContentLoaded', function() {
    initializeExternalLinks();
});
window.websiteUtils = { comingSoon: comingSoon, logout: logout, smoothScrollTo: smoothScrollTo };
