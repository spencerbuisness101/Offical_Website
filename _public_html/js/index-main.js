const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // === FEATURE DETAILS LOGIC ===
    const featureData = {
        games: {
            title: 'Exclusive Gaming Universe',
            icon: '<i class="fas fa-gamepad"></i>',
            body: 'Dive into a curated collection of HTML5 and WebGL experiences. From retro-inspired arcade games to high-fidelity 3D puzzles, our library is built for performance and instant fun. No downloads, no waiting—just click and play.'
        },
        ai: {
            title: 'The AI Singularity',
            icon: '<i class="fas fa-robot"></i>',
            body: 'Our AI assistants aren\'t just chatbots. They feature memory persistence, emotional intelligence, and specialized knowledge bases. Whether you need a coding partner, a creative writer, or a casual conversationalist, they adapt to your specific needs.'
        },
        themes: {
            title: 'Visual Sovereignty',
            icon: '<i class="fas fa-paint-brush"></i>',
            body: 'Express yourself with our deep customization engine. Change every aspect of your experience, from the blur intensity of the glass panels to the specific hue of your UI glow. Your profile, your rules.'
        },
        community: {
            title: 'Vibrant Ecosystem',
            icon: '<i class="fas fa-users"></i>',
            body: 'Join a global network of creators and enthusiasts. Share your high scores, collaborate on creative projects, and participate in exclusive community events. This isn\'t just a platform; it\'s a home.'
        },
        cloud: {
            title: 'Seamless Continuity',
            icon: '<i class="fas fa-cloud"></i>',
            body: 'Never lose a save state again. Our real-time cloud synchronization ensures your game progress, settings, and AI conversation history are available on every device. Start on desktop, continue on mobile.'
        },
        rankings: {
            title: 'The Global Arena',
            icon: '<i class="fas fa-trophy"></i>',
            body: 'Prove your dominance in our real-time leaderboard system. Earn badges, unlock exclusive profile frames, and climb the ranks across all games. The world is watching—where will you stand?'
        }
    };

    function openFeatureDetail(featureId) {
        const data = featureData[featureId];
        const modal = document.getElementById('featureDetailModal');
        if (!data || !modal) return;

        document.getElementById('detailIcon').innerHTML = data.icon;
        document.getElementById('detailTitle').textContent = data.title;
        document.getElementById('detailBody').textContent = data.body;

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Ensure modal content is scrollable if long
        const content = modal.querySelector('.s-modal');
        if (content) content.scrollTop = 0;
    }

    function closeFeatureDetail() {
        const modal = document.getElementById('featureDetailModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Delegation for clicks
    document.addEventListener('click', e => {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');
        const feature = target.getAttribute('data-feature');

        if (action === 'open-feature-detail') {
            e.preventDefault();
            openFeatureDetail(feature);
        } else if (action === 'close-feature-detail') {
            closeFeatureDetail();
        }
    });

    // === MOUSE TRACKER (used by multiple layers) ===
    const mouse = { x: -9999, y: -9999, nx: 0, ny: 0 };
    document.addEventListener('mousemove', e => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.nx = (e.clientX / window.innerWidth) - 0.5;
        mouse.ny = (e.clientY / window.innerHeight) - 0.5;
    });
    document.addEventListener('mouseleave', () => { mouse.x = -9999; mouse.y = -9999; });

    // === NAVBAR SCROLL ===
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 60);
        });
    }

    // === MOBILE MENU ===
    function toggleMobileMenu() {
        const el = document.getElementById('mobileMenu');
        if (el) el.classList.toggle('open');
    }
    function closeMobileMenu() {
        const el = document.getElementById('mobileMenu');
        if (el) el.classList.remove('open');
    }

    // === NAV SECTION JUMP (works even when login overlay is open) ===
    // If the login overlay is active, transition back to landing first, then scroll.
    function navToSection(e, hash) {
        if (e && e.preventDefault) e.preventDefault();
        const loginPageEl = document.getElementById('loginPage');
        const scrollToTarget = () => {
            if (!hash || hash === '#' || hash === '#!') return; // Safety check for empty hashes
            try {
                const target = document.querySelector(hash);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (err) {
                console.warn('Invalid scroll target:', hash);
            }
        };
        if (loginPageEl && loginPageEl.classList.contains('active')) {
            // Return to landing, then scroll after transition finishes
            if (typeof transitionToLanding === 'function') transitionToLanding();
            setTimeout(scrollToTarget, 850);
        } else {
            scrollToTarget();
        }
    }

    // === HERO HEADLINE — typewriter reveal ===
    (function initTypewriter() {
        const el = document.getElementById('heroHeadline');
        if (!el) return;
        const text = el.getAttribute('data-text') || '';
        let i = 0;
        el.textContent = '';
        el.style.opacity = '1';
        el.style.transform = 'none';

        function type() {
            if (i < text.length) {
                // Special handling for "Universe" to add accent class later or mid-stream
                // For simplicity, we type it all and then wrap the word
                el.textContent += text.charAt(i);
                i++;
                setTimeout(type, 80 + Math.random() * 40);
            } else {
                // Typing finished, wrap "Universe" in accent span
                const currentText = el.textContent;
                const idx = currentText.indexOf('Universe');
                if (idx >= 0) {
                    el.innerHTML = currentText.slice(0, idx) + '<span class="accent-word">Universe</span>' + currentText.slice(idx + 8);
                }
                el.classList.add('typing-done');
            }
        }
        // Start typing after a short delay
        setTimeout(type, 600);
    })();

    // === INTERSECTION OBSERVER — REVEAL ===
    let revealObserver;
    if ('IntersectionObserver' in window) {
        revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
    }

    // Animate yearly savings counter ($0 → $4) when yearly tile scrolls in
    const yearlyTile = document.querySelector('.pricing-card.card-yearly');
    if (yearlyTile) {
        const savingsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    savingsObserver.unobserve(entry.target);
                    const savingsEl = document.getElementById('yearlySavings');
                    if (savingsEl) {
                        const duration = 1200;
                        const start = performance.now();
                        function tick(now) {
                            const progress = Math.min((now - start) / duration, 1);
                            const val = Math.floor(easeOutQuart(progress) * 6);
                            savingsEl.textContent = '$' + val;
                            if (progress < 1) requestAnimationFrame(tick);
                        }
                        requestAnimationFrame(tick);
                    }
                }
            });
        }, { threshold: 0.3 });
        savingsObserver.observe(yearlyTile);
    }

    if (revealObserver) {
        document.querySelectorAll('.reveal').forEach((el, index) => {
            // Stagger reveal for pricing cards specifically - speed up to 0.05s
            if (el.classList.contains('pricing-card')) {
                el.style.transitionDelay = (index * 0.05) + 's';
            }
            revealObserver.observe(el);
        });
    } else {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
    }

    // === STATS COUNTER ===
    let statsAnimated = false;
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !statsAnimated) {
                statsAnimated = true;
                loadAndAnimateStats();
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });
    const statsEl = document.getElementById('stats');
    if (statsEl) statsObserver.observe(statsEl);

    function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

    function animateCounter(element, target, duration, suffix) {
        if (!element) return;
        const start = performance.now();
        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const value = Math.floor(easeOutQuart(progress) * target);
            element.textContent = value.toLocaleString() + (suffix || '');
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    function loadAndAnimateStats() {
        fetch('api/public_stats.php')
            .then(r => r.json())
            .then(data => {
                const members = parseInt(data.members || 0, 10);
                const games = parseInt(data.games || 0, 10);
                const online = parseInt(data.online_now || 0, 10);

                // Members (include "+" suffix when >= 1000 for growth feel)
                const memberSuffix = members >= 1000 ? '+' : '';
                animateCounter(document.getElementById('statMembers'), members, 2000, memberSuffix);

                // Games (Enforce 50+ as requested)
                const targetGames = Math.max(games, 50);
                animateCounter(document.getElementById('statGames'), targetGames, 2000, '+');

                // Update community section member count
                const communityEl = document.getElementById('communityMemberCount');
                if (communityEl) {
                    if (members >= 1000) {
                        communityEl.textContent = (Math.floor(members / 100) / 10).toFixed(1).replace(/\.0$/, '') + 'K+ Members';
                    } else if (members > 0) {
                        communityEl.textContent = members.toLocaleString() + ' Members';
                    } else {
                        communityEl.textContent = 'Our Growing';
                    }
                }
            })
            .catch(() => {
                const m = document.getElementById('statMembers');
                const g = document.getElementById('statGames');
                if (m) m.textContent = '—';
                if (g) g.textContent = '—';
            });
    }

    // === RIPPLE EFFECT ===
    document.querySelectorAll('.ripple-container').forEach(el => {
        el.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // === PAGE TRANSITIONS ===
    const wipeOverlay = document.getElementById('wipeOverlay');
    const landingPage = document.getElementById('landingPage');
    const loginPage = document.getElementById('loginPage');
    if (!wipeOverlay || !landingPage || !loginPage) {
        console.warn('[index] page transition elements missing — skipping transition setup');
        window.transitionToLogin = window.transitionToLanding = function() {};
    } else {

    function transitionToLogin(e) {
        if (e) e.preventDefault();
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduced) {
            landingPage.style.display = 'none';
            loginPage.classList.add('active');
            loginPage.setAttribute('aria-hidden', 'false');
            const firstInput = document.getElementById('username');
            if (firstInput) firstInput.focus();
            return;
        }

        const cx = ((e ? e.clientX : window.innerWidth / 2) / window.innerWidth * 100);
        const cy = ((e ? e.clientY : window.innerHeight / 2) / window.innerHeight * 100);
        wipeOverlay.style.setProperty('--cx', cx + '%');
        wipeOverlay.style.setProperty('--cy', cy + '%');
        wipeOverlay.classList.remove('collapsing');
        wipeOverlay.classList.add('expanding');

        setTimeout(() => {
            landingPage.style.display = 'none';
            loginPage.classList.add('active');
            loginPage.setAttribute('aria-hidden', 'false');
            const firstInput = document.getElementById('username');
            if (firstInput) firstInput.focus();
            wipeOverlay.classList.remove('expanding');
            wipeOverlay.style.clipPath = 'circle(150% at ' + cx + '% ' + cy + '%)';
            setTimeout(() => { wipeOverlay.style.clipPath = ''; }, 50);
        }, 800);
    }

    function transitionToLanding() {
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduced) {
            loginPage.classList.remove('active');
            loginPage.setAttribute('aria-hidden', 'true');
            landingPage.style.display = '';
            return;
        }

        define('SITE_VERSION', '7.4');
        wipeOverlay.style.setProperty('--cx', '50%');
        wipeOverlay.style.setProperty('--cy', '50%');
        wipeOverlay.style.clipPath = 'circle(150% at 50% 50%)';
        wipeOverlay.classList.remove('expanding');
        wipeOverlay.classList.add('collapsing');

        setTimeout(() => {
            loginPage.classList.remove('active');
            loginPage.setAttribute('aria-hidden', 'true');
            landingPage.style.display = '';
            wipeOverlay.classList.remove('collapsing');
            wipeOverlay.style.clipPath = '';
        }, 800);
    }
    } // end else (transition elements present)

    // === PASSWORD TOGGLE ===
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (toggleBtn && passwordInput && eyeOpen && eyeClosed) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeOpen.style.display = isPassword ? 'none' : '';
            eyeClosed.style.display = isPassword ? '' : 'none';
        });
    }

    // === reCAPTCHA v3 TOKEN HELPER (invisible, fail-OPEN) ===
    // v7.1: Expose the site key for js/recaptcha-helper.js (loaded via defer).
    // That helper defines window.getRecaptchaToken — the same function we used
    // to duplicate inline. If the helper hasn't loaded yet (unlikely since
    // form submit is user-triggered), fall back to a minimal inline version.
    var recaptchaSiteKeyMeta = document.querySelector('meta[name="recaptcha-site-key"]');
    window.__RECAPTCHA_SITE_KEY = recaptchaSiteKeyMeta ? recaptchaSiteKeyMeta.content : '';
    // Thin wrapper: delegate to the helper if available, else inline fallback.
    function getRecaptchaToken(action) {
        if (typeof window.getRecaptchaToken === 'function' && window.getRecaptchaToken !== getRecaptchaToken) {
            return window.getRecaptchaToken(action);
        }
        // Inline fallback (only used if recaptcha-helper.js hasn't loaded yet)
        if (!window.__RECAPTCHA_SITE_KEY) return Promise.resolve('');
        return new Promise(function (resolve) {
            var start = Date.now();
            var tick = function () {
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute && grecaptcha.ready) {
                    try { grecaptcha.ready(function () { try { grecaptcha.execute(window.__RECAPTCHA_SITE_KEY, { action: action }).then(function (t) { resolve(typeof t === 'string' ? t : ''); }).catch(function () { resolve(''); }); } catch (_) { resolve(''); } }); } catch (_) { resolve(''); }
                    return;
                }
                if (Date.now() - start > 5000) return resolve('');
                setTimeout(tick, 100);
            };
            tick();
        });
    }

    // === LOGIN FORM ===
    const loginFormEl = document.getElementById('loginForm');
    if (loginFormEl) loginFormEl.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const usernameErr = document.getElementById('usernameError');
        const passwordErr = document.getElementById('passwordError');
        const networkBanner = document.getElementById('networkBanner');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const csrfInput = document.getElementById('csrf_token');

        if (!btn || !usernameErr || !passwordErr || !networkBanner || !usernameInput || !passwordInput || !csrfInput) {
            console.error('[login] required form elements missing');
            return;
        }

        // Clear errors
        usernameErr.classList.remove('visible');
        passwordErr.classList.remove('visible');
        networkBanner.classList.remove('visible');

        const identifier = usernameInput.value.trim();
        const password = passwordInput.value;
        const csrfToken = csrfInput.value;
        const rememberMeEl = document.getElementById('rememberMe');
        const rememberMe = (rememberMeEl && rememberMeEl.checked) ? '1' : '0';

        // Inline validation
        let hasError = false;
        if (!identifier) {
            usernameErr.textContent = 'Email or username is required';
            usernameErr.classList.add('visible');
            hasError = true;
        }
        if (!password) {
            passwordErr.textContent = 'Password is required';
            passwordErr.classList.add('visible');
            hasError = true;
        }
        if (hasError) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="login-spinner"></span> Verifying...';

        // Get reCAPTCHA v3 token (invisible, non-blocking)
        const recaptchaToken = await getRecaptchaToken('login');
        const tokenInput = document.getElementById('loginRecaptchaToken');
        if (tokenInput) tokenInput.value = recaptchaToken;

        btn.innerHTML = '<span class="login-spinner"></span> Signing in...';

        const body = new URLSearchParams({
            identifier: identifier,
            password: password,
            csrf_token: csrfToken,
            recaptcha_token: recaptchaToken,
            remember_me: rememberMe
        });

        try {
            const r = await fetch('/auth/login_with_security.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            });
            const data = await r.json();

            if (data.success && data.terms_required) {
                btn.disabled = false;
                btn.textContent = 'Sign In';
                window.location.href = data.redirect || 'main.php';
            } else if (data.success) {
                btn.innerHTML = '<span class="login-spinner"></span> Redirecting...';
                window.location.href = data.redirect || 'main.php';
            } else {
                btn.disabled = false;
                btn.textContent = 'Sign In';

                const msg = (data.message || '').toLowerCase();
                if (msg.includes('username') || msg.includes('email') || msg.includes('identifier')) {
                    usernameErr.textContent = data.message;
                    usernameErr.classList.add('visible');
                } else if (msg.includes('password') || msg.includes('locked') || msg.includes('attempt')) {
                    passwordErr.textContent = data.message;
                    passwordErr.classList.add('visible');
                } else if (msg.includes('captcha') || msg.includes('verification') || msg.includes('security') || msg.includes('recaptcha')) {
                    networkBanner.textContent = data.message;
                    networkBanner.classList.add('visible');
                } else {
                    passwordErr.textContent = data.message || 'An error occurred. Please try again later.';
                    passwordErr.classList.add('visible');
                }
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Sign In';
            passwordErr.textContent = 'Connection error. Please try again.';
            passwordErr.classList.add('visible');
        }
    });

    // === GUEST ACCOUNT CREATION (reCAPTCHA v3 invisible) ===
    async function createGuestAccount(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = e && e.target;
        if (!btn) return;
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Verifying...';

        try {
            // Get reCAPTCHA v3 token (invisible, fast)
            const recaptchaToken = await getRecaptchaToken('create_guest');

            btn.textContent = 'Creating account...';

            const response = await fetch('api/create_guest_account.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: (document.getElementById('csrf_token') || {}).value || '',
                    recaptcha_token: recaptchaToken
                })
            });

            const data = await response.json();

            if (data.success) {
                btn.textContent = 'Welcome!';
                window.location.href = data.redirect || 'main.php';
            } else {
                btn.disabled = false;
                btn.textContent = originalText;
                alert(data.error || 'Unable to create guest account. Please try again.');
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            alert('Connection error. Please try again.');
        }
    }

    // === 3D TILT ON PRICING CARDS ===
    (function() {
        if (prefersReducedMotion) return;
        document.querySelectorAll('.pricing-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const mx = (x / rect.width) * 100;
                const my = (y / rect.height) * 100;
                card.style.setProperty('--mx', mx + '%');
                card.style.setProperty('--my', my + '%');
            });
            card.addEventListener('mouseleave', () => {
                card.style.setProperty('--mx', '50%');
                card.style.setProperty('--my', '0%');
            });
        });
    })();

    // === SPOTLIGHT ON FEATURE CARDS ===
    (function() {
        if (prefersReducedMotion) return;
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const mx = ((e.clientX - rect.left) / rect.width) * 100;
                const my = ((e.clientY - rect.top) / rect.height) * 100;
                card.style.setProperty('--mx', mx + '%');
                card.style.setProperty('--my', my + '%');
            });
            card.addEventListener('mouseleave', () => {
                card.style.setProperty('--mx', '50%');
                card.style.setProperty('--my', '0%');
            });
        });
    })();

    // === MAGNETIC BUTTONS (Surprise Polish) ===
    (function() {
        if (prefersReducedMotion) return;
        const magneticElements = document.querySelectorAll('.btn-primary, .pricing-cta, .feat-cta-primary');
        
        magneticElements.forEach(el => {
            el.addEventListener('mousemove', e => {
                const rect = el.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                el.style.transform = `translate(${x * 0.3}px, ${y * 0.5}px) scale(1.05)`;
                if (el.querySelector('i')) {
                    el.querySelector('i').style.transform = `translate(${x * 0.1}px, ${y * 0.1}px)`;
                }
            });
            
            el.addEventListener('mouseleave', () => {
                el.style.transform = '';
                if (el.querySelector('i')) {
                    el.querySelector('i').style.transform = '';
                }
            });
        });
    })();

    // === PREVENT ACCIDENTAL DOUBLE-CLICK MENU ===
    document.addEventListener('dblclick', e => {
        if (!e.target.closest('input, textarea, [contenteditable="true"]')) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, { capture: true });
    (function() {
        if (prefersReducedMotion) return;
        
        const dot = document.createElement('div');
        const ring = document.createElement('div');
        
        dot.id = 'cursor-dot';
        ring.id = 'cursor-ring';
        
        dot.style.cssText = 'position:fixed;width:6px;height:6px;background:var(--accent);border-radius:50%;pointer-events:none;z-index:100000;transform:translate(-50%,-50%);transition:opacity 0.3s, transform 0.1s;';
        ring.style.cssText = 'position:fixed;width:30px;height:30px;border:1.5px solid var(--accent);border-radius:50%;pointer-events:none;z-index:99999;transform:translate(-50%,-50%);transition:opacity 0.3s, width 0.3s, height 0.3s, border-color 0.3s;';
        
        document.body.appendChild(dot);
        document.body.appendChild(ring);
        
        let mouseX = -100, mouseY = -100;
        let ringX = -100, ringY = -100;
        let isHovering = false;
        
        document.addEventListener('mousemove', e => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            dot.style.left = mouseX + 'px';
            dot.style.top = mouseY + 'px';
            dot.style.opacity = '1';
            ring.style.opacity = '1';
            
            const target = e.target.closest('a, button, .pricing-card, .feature-card, [data-action]');
            if (target && !isHovering) {
                isHovering = true;
                ring.style.width = '50px';
                ring.style.height = '50px';
                ring.style.borderColor = 'rgba(123, 110, 246, 0.8)';
                dot.style.transform = 'translate(-50%,-50%) scale(1.5)';
            } else if (!target && isHovering) {
                isHovering = false;
                ring.style.width = '30px';
                ring.style.height = '30px';
                ring.style.borderColor = 'var(--accent)';
                dot.style.transform = 'translate(-50%,-50%) scale(1)';
            }
        });
        
        // Smooth ring follow (lag effect)
        function animateRing() {
            ringX += (mouseX - ringX) * 0.15;
            ringY += (mouseY - ringY) * 0.15;
            ring.style.left = ringX + 'px';
            ring.style.top = ringY + 'px';
            requestAnimationFrame(animateRing);
        }
        animateRing();
        
        document.addEventListener('mouseleave', () => {
            dot.style.opacity = '0';
            ring.style.opacity = '0';
        });
    })();

    // === FEATURE CARD EXPAND (v7.1: single-open, dim-others, keyboard, ESC) ===
    window.toggleFeature = function(el, e) {
        // Don't toggle when the click came from a CTA button/link inside the card
        if (e && e.target && e.target.closest('.feat-cta, .feat-link, a, button')) {
            // But allow normal nav. If a button has its own onclick (e.g. transitionToLogin),
            // it has already handled the event. Just stop the toggle.
            return;
        }
        const grid = el.parentElement;
        if (!grid) return;
        const wasExpanded = el.classList.contains('expanded');
        // Close all siblings first (single-open accordion)
        grid.querySelectorAll('.feature-card.expanded').forEach(function(c) {
            c.classList.remove('expanded');
            c.setAttribute('aria-expanded', 'false');
        });
        if (!wasExpanded) {
            el.classList.add('expanded');
            el.setAttribute('aria-expanded', 'true');
            grid.classList.add('has-expanded');
        } else {
            grid.classList.remove('has-expanded');
        }
    };
    window.featureKeyHandler = function(e, el) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.toggleFeature(el, e);
        } else if (e.key === 'Escape') {
            const grid = el.parentElement;
            if (grid) grid.classList.remove('has-expanded');
            el.classList.remove('expanded');
            el.setAttribute('aria-expanded', 'false');
        }
    };
    // Global ESC and outside-click close any expanded feature card
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.features-grid .feature-card.expanded').forEach(function(c) {
            c.classList.remove('expanded');
            c.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.features-grid.has-expanded').forEach(function(g) {
            g.classList.remove('has-expanded');
        });
    });
    document.addEventListener('click', function(e) {
        const inCard = e.target.closest && e.target.closest('.feature-card');
        if (inCard) return;
        document.querySelectorAll('.features-grid .feature-card.expanded').forEach(function(c) {
            c.classList.remove('expanded');
            c.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.features-grid.has-expanded').forEach(function(g) {
            g.classList.remove('has-expanded');
        });
    }, true);

    // === LOGIN BRAND ROTATING QUOTE ===
    (function rotateQuote() {
        const el = document.getElementById('clRotatorText');
        if (!el) return;
        const quotes = [
            '"Where every click bends light."',
            '"A galaxy of games, AI, and good company."',
            '"Sign in. The universe is patient."',
            '"Built for the curious. Designed for the cinematic."',
            '"Your stars are waiting."'
        ];
        let i = 0;
        setInterval(function() {
            i = (i + 1) % quotes.length;
            el.style.opacity = '0';
            el.style.transform = 'translateY(-4px)';
            setTimeout(function() {
                el.textContent = quotes[i];
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 500);
        }, 5500);
    })();

    // === SHOWCASE TAB SWITCHING ===
    window.switchShowcase = function(tab, btn) {
        document.querySelectorAll('.showcase-tab').forEach(function(t) { t.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        document.querySelectorAll('.showcase-panel').forEach(function(p) { p.classList.remove('active'); });
        var target = document.getElementById('showcase' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (target) target.classList.add('active');
    };

    // === SHOWCASE ACCOUNT PROMPT ===
    window.showcasePrompt = function(feature, e) {
        if (e) e.preventDefault();
        var titles = { games:'Browse All Games', ai:'Try AI Chat', chat:'Join the Chat', profile:'Customize Your Profile' };
        var apTitle = document.getElementById('apTitle');
        var apModal = document.getElementById('accountPromptModal');
        if (apTitle) apTitle.textContent = titles[feature] || 'Choose Your Path';
        if (apModal) { apModal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    };
    window.closeAccountPrompt = function() {
        var apModal = document.getElementById('accountPromptModal');
        if (apModal) { apModal.classList.remove('open'); document.body.style.overflow = ''; }
    };

    // === STARTUP SOUND (Web Audio API — no file needed) ===
    function playStartupSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            // Soft pad chord
            [261.63, 329.63, 392.00].forEach(function(freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.1);
                gain.gain.linearRampToValueAtTime(0.08, ctx.currentTime + i * 0.1 + 0.15);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.1 + 1.2);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + i * 0.1);
                osc.stop(ctx.currentTime + i * 0.1 + 1.2);
            });
            // Sparkle
            var osc2 = ctx.createOscillator();
            var gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.value = 880;
            gain2.gain.setValueAtTime(0, ctx.currentTime + 0.3);
            gain2.gain.linearRampToValueAtTime(0.06, ctx.currentTime + 0.45);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.0);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(ctx.currentTime + 0.3);
            osc2.stop(ctx.currentTime + 1.0);
        } catch(e) { /* Audio not available */ }
    }

    // === GUIDED TOUR ===
    var tourStep = -1;
    var tourSteps = [
        { title: 'Welcome to Spencer\'s Website', desc: 'Let us show you around. You\'ll see what makes this place different.' },
        { title: 'Browse Our Games', desc: '70+ browser games — action, puzzle, strategy, and more. All playable instantly, no downloads.' },
        { title: 'Meet the AI', desc: 'Chat with 8 unique AI personas. Each has a distinct personality and knowledge area.' },
        { title: 'Join the Community', desc: 'Real-time chat, custom profiles, and a growing community of gamers and creators.' }
    ];
    window.startTour = function() {
        if (typeof playStartupSound === 'function') playStartupSound();
        tourStep = -1;
        var tourOverlay = document.getElementById('tourOverlay');
        if (tourOverlay) tourOverlay.classList.add('active');
        nextTourStep();
    };
    window.nextTourStep = function() {
        tourStep++;
        if (tourStep >= tourSteps.length) { skipTour(); return; }
        var tourTitle = document.getElementById('tourTitle');
        var tourDesc = document.getElementById('tourDesc');
        if (tourTitle) tourTitle.textContent = tourSteps[tourStep].title;
        if (tourDesc) tourDesc.textContent = tourSteps[tourStep].desc;
        document.querySelectorAll('.tour-dot').forEach(function(d, i) {
            d.classList.toggle('active', i === tourStep);
        });
        var btn = document.getElementById('tourBtn');
        if (btn) {
            if (tourStep === tourSteps.length - 1) {
                btn.innerHTML = 'Get Started <i class="fas fa-arrow-right"></i>';
            } else {
                btn.innerHTML = 'Continue <i class="fas fa-arrow-right"></i>';
            }
        }
    };
    window.skipTour = function() {
        var tourOverlay = document.getElementById('tourOverlay');
        if (tourOverlay) tourOverlay.classList.remove('active');
        if (typeof transitionToLogin === 'function') transitionToLogin();
    };

    // === BIND START EXPLORING BUTTON ===
    (function() {
        var btn = document.getElementById('startExploringBtn');
        if (btn) btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.startTour === 'function') window.startTour();
        });
    })();

    // === CSP-COMPLIANT EVENT WIRING (replaces all inline onclick handlers) ===
    (function() {
        // --- data-action delegation ---
        document.body.addEventListener('click', function(e) {
            var el = e.target.closest('[data-action]');
            if (!el) return;
            var action = el.getAttribute('data-action');
            if (!action) return;

            switch (action) {
                case 'landing':
                    e.preventDefault();
                    if (typeof transitionToLanding === 'function') transitionToLanding();
                    break;
                case 'login':
                    e.preventDefault();
                    if (typeof transitionToLogin === 'function') transitionToLogin(e);
                    break;
                case 'login-close-prompt':
                    e.preventDefault();
                    if (typeof closeAccountPrompt === 'function') closeAccountPrompt();
                    if (typeof transitionToLogin === 'function') transitionToLogin(e);
                    break;
                case 'login-close-community':
                    e.preventDefault();
                    if (typeof closeCommunityModal === 'function') closeCommunityModal();
                    if (typeof transitionToLogin === 'function') transitionToLogin(e);
                    break;
                case 'community-modal':
                    e.preventDefault();
                    if (typeof openCommunityModal === 'function') openCommunityModal(e);
                    break;
                case 'community-modal-close-prompt':
                    e.preventDefault();
                    if (typeof closeAccountPrompt === 'function') closeAccountPrompt();
                    if (typeof openCommunityModal === 'function') openCommunityModal(e);
                    break;
                case 'community-modal-close-compare':
                    e.preventDefault();
                    if (typeof closeCompareModal === 'function') closeCompareModal();
                    if (typeof openCommunityModal === 'function') openCommunityModal(e);
                    break;
                case 'compare-modal':
                case 'open-compare-modal':
                    e.preventDefault();
                    if (typeof openCompareModal === 'function') openCompareModal(e);
                    break;
                case 'close-compare-modal':
                    e.preventDefault();
                    if (typeof closeCompareModal === 'function') closeCompareModal();
                    break;
                case 'create-guest':
                    e.preventDefault();
                    if (typeof createGuestAccount === 'function') createGuestAccount(e);
                    break;
                case 'show-guest-disclosure':
                    e.preventDefault();
                    if (typeof showGuestDisclosure === 'function') showGuestDisclosure();
                    break;
                case 'toggle-dropdown':
                    e.preventDefault();
                    var parent = el.parentElement;
                    if (parent) parent.classList.toggle('open');
                    break;
                case 'nav-section':
                    e.preventDefault();
                    var hash = el.getAttribute('href') || '';
                    if (typeof navToSection === 'function' && hash) navToSection(e, hash);
                    // close mobile menu if inside it
                    var inMobile = el.closest('.mobile-menu');
                    if (inMobile && typeof closeMobileMenu === 'function') closeMobileMenu();
                    break;
            }
        });

        // --- data-href navigation ---
        document.body.addEventListener('click', function(e) {
            var el = e.target.closest('[data-href]');
            if (!el) return;
            var href = el.getAttribute('data-href');
            if (href) window.location.href = href;
        });

        // --- data-showcase handlers ---
        document.body.addEventListener('click', function(e) {
            var el = e.target.closest('[data-showcase]');
            if (!el) return;
            var feature = el.getAttribute('data-showcase');
            if (feature && typeof showcasePrompt === 'function') showcasePrompt(feature, e);
        });

        // --- Showcase tabs ---
        document.querySelectorAll('.showcase-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = this.getAttribute('data-tab');
                if (target && typeof switchShowcase === 'function') switchShowcase(target, this);
            });
        });

        // --- Feature cards ---
        document.querySelectorAll('.feature-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (typeof toggleFeature === 'function') toggleFeature(this, e);
            });
            card.addEventListener('keydown', function(e) {
                if (typeof featureKeyHandler === 'function') featureKeyHandler(e, this);
            });
        });

        // --- Feature card Sign In buttons ---
        document.querySelectorAll('.feat-cta-primary').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (typeof transitionToLogin === 'function') transitionToLogin(e);
            });
        });

        // --- Hamburger ---
        var hamburger = document.getElementById('hamburger');
        if (hamburger && typeof toggleMobileMenu === 'function') {
            hamburger.addEventListener('click', toggleMobileMenu);
        }

        // --- Tour controls ---
        var tourSkip = document.querySelector('.tour-skip');
        if (tourSkip && typeof skipTour === 'function') {
            tourSkip.addEventListener('click', skipTour);
        }
        var tourBtn = document.getElementById('tourBtn');
        if (tourBtn && typeof nextTourStep === 'function') {
            tourBtn.addEventListener('click', function(e) {
                e.preventDefault();
                nextTourStep();
            });
        }

        // --- Modal close buttons ---
        document.querySelectorAll('.s-modal-close').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var overlay = this.closest('.s-modal-overlay');
                if (!overlay) return;
                var id = overlay.id;
                if (id === 'accountPromptModal' && typeof closeAccountPrompt === 'function') closeAccountPrompt();
                if (id === 'communityModal' && typeof closeCommunityModal === 'function') closeCommunityModal();
                if (id === 'compareModal' && typeof closeCompareModal === 'function') closeCompareModal();
            });
        });

        // --- Modal overlay click-to-close ---
        document.querySelectorAll('.s-modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target !== this) return;
                var id = this.id;
                if (id === 'accountPromptModal' && typeof closeAccountPrompt === 'function') closeAccountPrompt();
                if (id === 'communityModal' && typeof closeCommunityModal === 'function') closeCommunityModal();
                if (id === 'compareModal' && typeof closeCompareModal === 'function') closeCompareModal();
            });
        });

        // --- Guest disclosure back ---
        var btnCancelDisclosure = document.getElementById('btnCancelDisclosure');
        if (btnCancelDisclosure && typeof hideGuestDisclosure === 'function') {
            btnCancelDisclosure.addEventListener('click', hideGuestDisclosure);
        }

        // --- Compare modal -> community modal ---
        var btnCompareToCommunity = document.getElementById('btnCompareToCommunity');
        if (btnCompareToCommunity) {
            btnCompareToCommunity.addEventListener('click', function() {
                if (typeof closeCompareModal === 'function') closeCompareModal();
                if (typeof openCommunityModal === 'function') openCommunityModal();
            });
        }

        // --- Nav hash links without data-action ---
        document.querySelectorAll('.nav-links a[href^="#"], .hero-ctas a[href^="#"]').forEach(function(link) {
            if (link.hasAttribute('data-action')) return;
            link.addEventListener('click', function(e) {
                if (typeof navToSection === 'function') navToSection(e, this.getAttribute('href'));
            });
        });
    })();
