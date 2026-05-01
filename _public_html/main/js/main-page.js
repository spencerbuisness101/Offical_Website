/**
 * Spencer's Website — Main page v8.0 behaviors
 * Reveal animations, quick-card cursor glow, upgrade plan selector, checkout starter.
 */
(function() {
    'use strict';

    // ===== Reveal on intersect =====
    const revealEls = document.querySelectorAll('.mp-reveal');
    if (revealEls.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(el => io.observe(el));
    }

    // ===== Quick-card cursor glow =====
    document.querySelectorAll('[data-cursor]').forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - rect.left) / rect.width * 100) + '%');
            card.style.setProperty('--my', ((e.clientY - rect.top) / rect.height * 100) + '%');
        });
        card.addEventListener('mouseleave', () => {
            card.style.setProperty('--mx', '50%');
            card.style.setProperty('--my', '0%');
        });
    });

    // ===== Upgrade panel plan selector =====
    let selectedPlan = 'yearly';
    window.selectUpgradePlan = function(plan) {
        selectedPlan = plan;
        ['Monthly', 'Yearly', 'Lifetime'].forEach(p => {
            const el = document.getElementById('upgPlan' + p);
            if (el) el.classList.toggle('selected', p.toLowerCase() === plan);
        });
    };

    window.startUpgradeCheckout = async function(provider) {
        const btn = document.getElementById('upgradeStripeBtn');
        const errBox = document.getElementById('upgradeError');
        if (!btn) return;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Redirecting…';
        if (errBox) errBox.style.display = 'none';

        try {
            const resp = await fetch('api/create_checkout_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    plan: selectedPlan,
                    provider: provider || 'stripe'
                })
            });
            const data = await resp.json();
            if (data && data.url) {
                window.location.href = data.url;
            } else {
                throw new Error(data.error || 'Unable to start checkout');
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = original;
            if (errBox) {
                errBox.style.display = 'block';
                errBox.textContent = err.message || 'Payment error. Please try again.';
            }
        }
    };

    // ===== v7.1: Stat count-up tween =====
    // Animates [data-target] numbers from 0 → target via rAF, but only when
    // the tile becomes visible. Honours prefers-reduced-motion (snaps).
    const reduceMotion = window.matchMedia &&
                         window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function tweenCount(el) {
        const target = parseInt(el.getAttribute('data-target') || '0', 10);
        if (!isFinite(target) || target <= 0) {
            el.textContent = '0';
            return;
        }
        if (reduceMotion) {
            el.textContent = target.toLocaleString();
            return;
        }
        const duration = Math.min(1400, 600 + Math.log10(Math.max(10, target)) * 240);
        const start = performance.now();
        function step(now) {
            const t = Math.min(1, (now - start) / duration);
            // easeOutQuart
            const eased = 1 - Math.pow(1 - t, 4);
            const value = Math.floor(eased * target);
            el.textContent = value.toLocaleString();
            if (t < 1) requestAnimationFrame(step);
            else el.textContent = target.toLocaleString();
        }
        requestAnimationFrame(step);
    }

    const countNumbers = document.querySelectorAll('.mp-stat-num[data-target]');
    if (countNumbers.length) {
        const numIo = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    tweenCount(e.target);
                    numIo.unobserve(e.target);
                }
            });
        }, { threshold: 0.4 });
        countNumbers.forEach(el => numIo.observe(el));
    }

    // ===== v7.1: Notifications "Mark all read" =====
    const markAllBtn = document.querySelector('.mp-notif-mark-all');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', async () => {
            const csrf = markAllBtn.getAttribute('data-csrf') || '';
            const original = markAllBtn.textContent;
            markAllBtn.disabled = true;
            markAllBtn.textContent = 'Marking…';
            try {
                const resp = await fetch('api/notifications.php?action=mark_all_read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrf
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({ csrf_token: csrf }).toString()
                });
                if (resp.ok) {
                    // Visually update without a full page reload
                    document.querySelectorAll('.mp-notif-item.is-unread').forEach(item => {
                        item.classList.remove('is-unread');
                        item.classList.add('is-read');
                    });
                    const badge = document.querySelector('.mp-notif-badge');
                    if (badge) badge.remove();
                    markAllBtn.style.display = 'none';
                } else {
                    markAllBtn.disabled = false;
                    markAllBtn.textContent = 'Try again';
                    setTimeout(() => { markAllBtn.textContent = original; }, 1800);
                }
            } catch (err) {
                markAllBtn.disabled = false;
                markAllBtn.textContent = 'Network error';
                setTimeout(() => { markAllBtn.textContent = original; }, 1800);
            }
        });
    }
})();
