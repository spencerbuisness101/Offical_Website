function toggleAnn(cardId, btn) {
    var body = document.getElementById(cardId + '-body');
    if (!body) return;
    var isCollapsed = body.classList.contains('collapsed');
    var label = btn.querySelector('span');
    if (isCollapsed) {
        body.classList.remove('collapsed');
        body.style.maxHeight = body.scrollHeight + 'px';
        if (label) label.textContent = 'Show less';
        btn.setAttribute('aria-expanded', 'true');
    } else {
        body.style.maxHeight = body.scrollHeight + 'px';
        void body.offsetHeight;
        body.classList.add('collapsed');
        body.style.maxHeight = '';
        if (label) label.textContent = 'Show more';
        btn.setAttribute('aria-expanded', 'false');
    }
}

function dismissMainAnn(id) {
    var card = document.getElementById('ann-' + id);
    if (!card) return;
    card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
    card.style.opacity = '0';
    card.style.transform = 'translateX(20px)';
    setTimeout(function() {
        card.style.height = card.offsetHeight + 'px';
        card.style.overflow = 'hidden';
        void card.offsetHeight;
        card.style.height = '0';
        card.style.margin = '0';
        card.style.padding = '0';
        card.style.borderWidth = '0';
        setTimeout(function() { card.remove(); }, 300);
    }, 250);
    if (typeof fetch !== 'undefined') {
        fetch('mark_announcement_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'announcement_id=' + encodeURIComponent(id)
        }).catch(function() {});
    }
}

function filterMainAnnouncements(type, btn) {
    document.querySelectorAll('.ann-filter').forEach(function(f) { f.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    try {
        document.cookie = 'ann_filter=' + type + ';path=/;max-age=86400';
    } catch(e) {}
    document.querySelectorAll('.mp-ann-card').forEach(function(c) {
        if (type === 'all' || c.getAttribute('data-type') === type) {
            c.style.display = '';
        } else {
            c.style.display = 'none';
        }
    });
}

(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.mp-ann-body.collapsed').forEach(function(el) {
            el.style.maxHeight = '4.8em';
        });
    });
})();
