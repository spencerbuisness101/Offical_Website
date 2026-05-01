/**
 * Toast Notification System — Spencer's Website v7.0
 * Global toast stack. Usage: Toast.show('Saved!', 'success');
 */

class Toast {
    static container = null;
    static maxVisible = 5;
    static defaultDuration = 4000;

    static init() {
        if (Toast.container) return;
        Toast.container = document.createElement('div');
        Toast.container.className = 'toast-container';
        document.body.appendChild(Toast.container);
    }

    static show(message, type = 'info', duration = Toast.defaultDuration) {
        Toast.init();

        // Trim oldest if over limit
        while (Toast.container.children.length >= Toast.maxVisible) {
            const oldest = Toast.container.firstElementChild;
            if (oldest) Toast.dismiss(oldest);
        }

        const icons = {
            info:    'fa-circle-info',
            success: 'fa-circle-check',
            warning: 'fa-triangle-exclamation',
            error:   'fa-circle-xmark'
        };

        const el = document.createElement('div');
        el.className = `toast toast--${type}`;
        el.innerHTML = `
            <i class="fas ${icons[type] || icons.info} toast__icon"></i>
            <div class="toast__content"><div class="toast__message">${this.escapeHtml(message)}</div></div>
            <button class="toast__close" aria-label="Dismiss"><i class="fas fa-xmark"></i></button>
            <div class="toast__progress" style="width:100%"></div>
        `;

        Toast.container.appendChild(el);

        // Animate in
        requestAnimationFrame(() => {
            requestAnimationFrame(() => el.classList.add('show'));
        });

        // Dismiss button
        el.querySelector('.toast__close').addEventListener('click', () => Toast.dismiss(el));

        // Progress bar + auto-dismiss
        const progress = el.querySelector('.toast__progress');
        const start = performance.now();
        let rafId;

        const tick = (now) => {
            const elapsed = now - start;
            const remaining = Math.max(0, duration - elapsed);
            const pct = (remaining / duration) * 100;
            progress.style.width = pct + '%';

            if (remaining <= 0) {
                Toast.dismiss(el);
            } else {
                rafId = requestAnimationFrame(tick);
            }
        };
        rafId = requestAnimationFrame(tick);

        el._toastRaf = rafId;

        // Pause on hover
        el.addEventListener('mouseenter', () => cancelAnimationFrame(rafId));
        el.addEventListener('mouseleave', () => {
            // Resume from current width
            const currentPct = parseFloat(progress.style.width) || 0;
            const remaining = (currentPct / 100) * duration;
            const newStart = performance.now() - (duration - remaining);
            const resumeTick = (now) => {
                const elapsed = now - newStart;
                const rem = Math.max(0, duration - elapsed);
                progress.style.width = (rem / duration) * 100 + '%';
                if (rem <= 0) {
                    Toast.dismiss(el);
                } else {
                    el._toastRaf = requestAnimationFrame(resumeTick);
                }
            };
            el._toastRaf = requestAnimationFrame(resumeTick);
        });

        return el;
    }

    static dismiss(el) {
        if (!el || el._dismissing) return;
        el._dismissing = true;
        if (el._toastRaf) cancelAnimationFrame(el._toastRaf);
        el.classList.remove('show');
        el.classList.add('hiding');
        setTimeout(() => {
            if (el.parentElement) el.parentElement.removeChild(el);
        }, 350);
    }

    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Auto-init on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Toast.init());
} else {
    Toast.init();
}

window.Toast = Toast;
