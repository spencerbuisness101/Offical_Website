/* Identity Bar v3.0 JS */
(function(){
    'use strict';

    const userBtn = document.querySelector('.identity-bar__user-btn');
    const userDropdown = document.getElementById('userDropdown');

    function toggleUserMenu() {
        const expanded = userBtn.getAttribute('aria-expanded') === 'true';
        userBtn.setAttribute('aria-expanded', !expanded);
        if (userDropdown) userDropdown.classList.toggle('active');
    }

    if (userBtn) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserMenu();
        });
    }

    document.addEventListener('click', function(e) {
        if (userBtn && !userBtn.contains(e.target) && userDropdown && !userDropdown.contains(e.target)) {
            userBtn.setAttribute('aria-expanded', 'false');
            if (userDropdown) userDropdown.classList.remove('active');
        }
    });

    // Background selector
    window.openBackgroundSelector = function() {
        const el = document.getElementById('bgSelector');
        if (el) { el.classList.add('active'); document.body.style.overflow = 'hidden'; }
    };
    window.closeBackgroundSelector = function() {
        const el = document.getElementById('bgSelector');
        if (el) { el.classList.remove('active'); document.body.style.overflow = ''; }
    };

    // Filter backgrounds
    (function() {
        const filters = document.querySelectorAll('.bg-selector__filter');
        filters.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var filter = btn.getAttribute('data-filter') || 'all';
                filters.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                document.querySelectorAll('.bg-card').forEach(function(card) {
                    var cat = card.getAttribute('data-category') || 'all';
                    card.style.display = (filter === 'all' || cat === filter) ? '' : 'none';
                });
            });
        });
    })();

    window.selectBackground = function(bgId) {
        var btn = event.target.closest('.bg-card').querySelector('.bg-card__btn');
        if (!btn) return;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Applying...';
        btn.disabled = true;

        fetch('/api/set_background.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (document.querySelector('meta[name=\"csrf-token\"]') || {}).content || '' },
            body: JSON.stringify({ background_id: bgId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                document.querySelectorAll('.bg-card').forEach(function(c) {
                    c.classList.remove('bg-card--active');
                    var b = c.querySelector('.bg-card__btn');
                    if (b) { b.disabled = false; b.innerHTML = '<i class=\"fas fa-check\"></i> Select'; }
                });
                var activeCard = document.querySelector('[data-id=\"' + bgId + '\"]');
                if (activeCard) {
                    activeCard.classList.add('bg-card--active');
                    var ab = activeCard.querySelector('.bg-card__btn');
                    if (ab) { ab.disabled = true; ab.innerHTML = '<i class=\"fas fa-check\"></i> Active'; }
                }
                var bgThumb = document.querySelector('.identity-bar__bg-thumb');
                if (bgThumb && activeCard) bgThumb.src = activeCard.querySelector('img').src;
                var bgUrl = data.background_url;
                if (!bgUrl && activeCard) bgUrl = activeCard.querySelector('img').src.replace('_thumb', '');
                if (bgUrl) {
                    var override = document.getElementById('bgThemeOverride');
                    if (override) override.style.backgroundImage = 'url(' + bgUrl + ')';
                }
                setTimeout(closeBackgroundSelector, 500);
            } else {
                alert('Error: ' + (data.error || 'Failed'));
                btn.innerHTML = original; btn.disabled = false;
            }
        })
        .catch(function() {
            alert('Network error');
            btn.innerHTML = original; btn.disabled = false;
        });
    };

    // Notifications
    window.toggleNotifications = function() {
        var panel = document.getElementById('notificationsPanel');
        if (panel) panel.classList.toggle('active');
    };
    window.markAllRead = function() {
        fetch('/api/mark_notifications_read.php', { method: 'POST', headers: { 'X-CSRF-Token': (document.querySelector('meta[name=\"csrf-token\"]') || {}).content || '' } })
        .then(function() {
            var badge = document.querySelector('.identity-bar__notifications-btn .identity-bar__badge');
            if (badge) badge.remove();
        });
    };

    // ESC to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBackgroundSelector();
            var notif = document.getElementById('notificationsPanel');
            if (notif) notif.classList.remove('active');
            if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
            var dd = document.getElementById('userDropdown');
            if (dd) dd.classList.remove('active');
        }
    });
})();
