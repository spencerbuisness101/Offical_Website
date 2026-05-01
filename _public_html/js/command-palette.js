/**
 * Command Palette v1.0 - Spencer's Website
 * Quick navigation with Ctrl+K or Cmd+K
 * Usage: Include this file and call initCommandPalette()
 */

class CommandPalette {
    constructor() {
        this.commands = [
            // Navigation
            { id: 'nav-home', label: 'Home / Dashboard', icon: 'fa-home', keywords: 'home dashboard main start', action: () => window.location.href = '/main.php' },
            { id: 'nav-games', label: 'Games', icon: 'fa-gamepad', keywords: 'games play fun entertainment', action: () => window.location.href = '/game.php' },
            { id: 'nav-chat', label: 'Chat / Yaps', icon: 'fa-comments', keywords: 'chat yaps talk message', action: () => window.location.href = '/yaps.php' },
            { id: 'nav-users', label: 'User Directory', icon: 'fa-users', keywords: 'users people directory community', action: () => window.location.href = '/userlist.php' },
            { id: 'nav-community', label: 'Community Hub', icon: 'fa-people-group', keywords: 'community hub social', action: () => window.location.href = '/community.php' },
            
            // User Actions
            { id: 'user-profile', label: 'My Profile', icon: 'fa-user', keywords: 'profile me account', action: () => window.location.href = '/userprofile.php' },
            { id: 'user-settings', label: 'Settings', icon: 'fa-cog', keywords: 'settings preferences config options', action: () => window.location.href = '/set.php' },
            { id: 'user-panel', label: 'User Panel', icon: 'fa-user-cog', keywords: 'panel dashboard account', action: () => window.location.href = '/user_panel.php' },
            { id: 'user-mail', label: 'Smail / Messages', icon: 'fa-envelope', keywords: 'mail messages smail inbox', action: () => window.location.href = '/smail.php' },
            { id: 'user-subscription', label: 'My Subscription', icon: 'fa-credit-card', keywords: 'subscription billing payment plan manage', action: () => window.location.href = '/manage_subscription.php' },
            
            // Features
            { id: 'feature-ai', label: 'AI Assistant', icon: 'fa-robot', keywords: 'ai assistant chatbot help', action: () => window.location.href = '/ai_panel.php' },
            { id: 'feature-background', label: 'Change Background', icon: 'fa-image', keywords: 'background theme wallpaper customize', action: () => { if (typeof openBackgroundSelector === 'function') openBackgroundSelector(); } },
            { id: 'feature-shop', label: 'Shop / Premium', icon: 'fa-shopping-cart', keywords: 'shop premium upgrade buy', action: () => window.location.href = '/shop.php' },
            { id: 'feature-donate', label: 'Donate / Support', icon: 'fa-heart', keywords: 'donate support tip money contribution', action: () => window.location.href = '/donate.php' },
            { id: 'feature-help', label: 'Help & Support', icon: 'fa-question-circle', keywords: 'help support faq assistance', action: () => window.location.href = '/supporthelp.php' },
            { id: 'feature-feedback', label: 'Send Feedback', icon: 'fa-comment-dots', keywords: 'feedback suggestion bug report idea', action: () => window.location.href = '/feedback.php' },
            { id: 'feature-updates', label: 'Updates', icon: 'fa-newspaper', keywords: 'updates news changelog', action: () => window.location.href = '/up.php' },
            
            // Information
            { id: 'info-about', label: 'About / Info', icon: 'fa-info-circle', keywords: 'about info information', action: () => window.location.href = '/info.php' },
            { id: 'info-policies', label: 'Policies', icon: 'fa-shield-alt', keywords: 'policies terms privacy rules', action: () => window.location.href = '/policiesandshowing.php' },
            { id: 'info-security', label: 'Security Brief', icon: 'fa-lock', keywords: 'security safety protection', action: () => window.location.href = '/security_brief.php' },
            { id: 'info-halloffame', label: 'Hall of Fame', icon: 'fa-trophy', keywords: 'hall fame contributors top ranking', action: () => window.location.href = '/role_ranking.php' },
            { id: 'info-health', label: 'System Health', icon: 'fa-heartbeat', keywords: 'health status system check status', action: () => window.location.href = '/health.php' },
            
            // Utilities
            { id: 'util-copy-username', label: 'Copy Username', icon: 'fa-copy', keywords: 'copy username clipboard', action: () => this.copyToClipboard(document.body.dataset.username, 'Username copied!') },
            { id: 'util-copy-id', label: 'Copy User ID', icon: 'fa-fingerprint', keywords: 'copy id user identifier clipboard', action: () => this.copyToClipboard(document.body.dataset.userId, 'User ID copied!') },
            { id: 'util-fullscreen', label: 'Toggle Fullscreen', icon: 'fa-expand', keywords: 'fullscreen expand maximize screen', action: () => this.toggleFullscreen() },
            { id: 'util-reload', label: 'Reload Page', icon: 'fa-rotate-right', keywords: 'reload refresh page', action: () => window.location.reload() },
            
            // Admin (only shown if user is admin)
            { id: 'admin-panel', label: 'Admin Panel', icon: 'fa-shield-halved', keywords: 'admin dashboard control', action: () => window.location.href = '/admin/index.php', adminOnly: true },
            { id: 'admin-users', label: 'Admin - Users', icon: 'fa-users-cog', keywords: 'admin users manage', action: () => window.location.href = '/admin/index.php?tab=users', adminOnly: true },
            { id: 'admin-security', label: 'Admin - Security', icon: 'fa-shield', keywords: 'admin security dashboard', action: () => window.location.href = '/admin/security_dashboard.php', adminOnly: true },
            { id: 'admin-dashboard', label: 'Admin - Dashboard', icon: 'fa-chart-line', keywords: 'admin dashboard stats', action: () => window.location.href = '/admin/index.php', adminOnly: true },
        ];
        
        this.filteredCommands = [];
        this.selectedIndex = 0;
        this.isOpen = false;
        this.userRole = null;
        
        this.init();
    }
    
    async init() {
        // Get user role from body data attribute or make an API call
        this.userRole = document.body.dataset.userRole || await this.fetchUserRole();
        
        this.createElements();
        this.bindEvents();
        this.filterCommands('');
    }
    
    async fetchUserRole() {
        try {
            const response = await fetch('/api/get_user_role.php', {
                credentials: 'same-origin'
            });
            const data = await response.json();
            return data.role || 'community';
        } catch (e) {
            return 'community';
        }
    }
    
    createElements() {
        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'command-palette';
        this.modal.innerHTML = `
            <div class="command-palette__backdrop"></div>
            <div class="command-palette__container">
                <div class="command-palette__input-wrapper">
                    <i class="fas fa-search command-palette__search-icon"></i>
                    <input 
                        type="text" 
                        class="command-palette__input" 
                        placeholder="Search commands... (e.g., 'games', 'settings', 'profile')"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <kbd class="command-palette__shortcut">ESC</kbd>
                </div>
                <div class="command-palette__results"></div>
                <div class="command-palette__footer">
                    <span><kbd>↑</kbd> <kbd>↓</kbd> to navigate</span>
                    <span><kbd>Enter</kbd> to select</span>
                    <span><kbd>Ctrl+K</kbd> to toggle</span>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.modal);
        
        // Cache elements
        this.backdrop = this.modal.querySelector('.command-palette__backdrop');
        this.input = this.modal.querySelector('.command-palette__input');
        this.results = this.modal.querySelector('.command-palette__results');
    }
    
    bindEvents() {
        // Keyboard shortcut to open (Ctrl+K or Cmd+K)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.toggle();
            }
            
            // ESC to close
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
            
            // Navigation when open
            if (this.isOpen) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigate(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigate(-1);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.executeSelected();
                }
            }
        });
        
        // Input handling
        this.input.addEventListener('input', (e) => {
            this.filterCommands(e.target.value);
        });
        
        // Click outside to close
        this.backdrop.addEventListener('click', () => this.close());
        
        // Prevent modal clicks from closing
        this.modal.querySelector('.command-palette__container').addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
    
    filterCommands(query) {
        const q = query.toLowerCase().trim();
        
        this.filteredCommands = this.commands.filter(cmd => {
            // Filter out admin commands for non-admins
            if (cmd.adminOnly && this.userRole !== 'admin') {
                return false;
            }
            
            if (!q) return true;
            
            // Search in label and keywords
            const searchText = (cmd.label + ' ' + (cmd.keywords || '')).toLowerCase();
            return searchText.includes(q);
        });
        
        // Sort: exact matches first, then starts with, then includes
        if (q) {
            this.filteredCommands.sort((a, b) => {
                const aLabel = a.label.toLowerCase();
                const bLabel = b.label.toLowerCase();
                
                if (aLabel === q) return -1;
                if (bLabel === q) return 1;
                
                if (aLabel.startsWith(q)) return -1;
                if (bLabel.startsWith(q)) return 1;
                
                return 0;
            });
        }
        
        this.selectedIndex = 0;
        this.renderResults();
    }
    
    renderResults() {
        if (this.filteredCommands.length === 0) {
            this.results.innerHTML = `
                <div class="command-palette__empty">
                    <i class="fas fa-search"></i>
                    <p>No commands found</p>
                </div>
            `;
            return;
        }
        
        this.results.innerHTML = this.filteredCommands.map((cmd, index) => `
            <div class="command-palette__item ${index === this.selectedIndex ? 'selected' : ''}" 
                 data-index="${index}"
                 onclick="commandPalette.selectItem(${index})"
                 onmouseenter="commandPalette.hoverItem(${index})">
                <div class="command-palette__item-icon">
                    <i class="fas ${cmd.icon}"></i>
                </div>
                <div class="command-palette__item-content">
                    <div class="command-palette__item-label">${this.highlightMatch(cmd.label)}</div>
                    ${cmd.keywords ? `<div class="command-palette__item-keywords">${cmd.keywords}</div>` : ''}
                </div>
                <div class="command-palette__item-action">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        `).join('');
    }
    
    highlightMatch(text) {
        const query = this.input.value.trim();
        if (!query) return text;
        
        const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    navigate(direction) {
        const newIndex = this.selectedIndex + direction;
        if (newIndex >= 0 && newIndex < this.filteredCommands.length) {
            this.selectedIndex = newIndex;
            this.renderResults();
            this.scrollSelectedIntoView();
        }
    }
    
    hoverItem(index) {
        this.selectedIndex = index;
        this.renderResults();
    }
    
    selectItem(index) {
        this.selectedIndex = index;
        this.executeSelected();
    }
    
    executeSelected() {
        const cmd = this.filteredCommands[this.selectedIndex];
        if (cmd && cmd.action) {
            this.close();
            cmd.action();
        }
    }
    
    scrollSelectedIntoView() {
        const selected = this.results.querySelector('.command-palette__item.selected');
        if (selected) {
            selected.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
    
    open() {
        this.isOpen = true;
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Clear previous search and focus input
        setTimeout(() => {
            this.input.value = '';
            this.filterCommands('');
            this.input.focus();
        }, 10);
    }
    
    close() {
        this.isOpen = false;
        this.modal.classList.remove('active');
        document.body.style.overflow = '';
        this.input.blur();
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    copyToClipboard(text, successMessage) {
        if (!text) {
            if (typeof Toast !== 'undefined') Toast.show('Nothing to copy', 'warning');
            return;
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof Toast !== 'undefined') Toast.show(successMessage || 'Copied!', 'success');
            }).catch(() => this.fallbackCopy(text, successMessage));
        } else {
            this.fallbackCopy(text, successMessage);
        }
    }

    fallbackCopy(text, successMessage) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (typeof Toast !== 'undefined') Toast.show(successMessage || 'Copied!', 'success');
        } catch (e) {
            if (typeof Toast !== 'undefined') Toast.show('Copy failed', 'error');
        }
        document.body.removeChild(ta);
    }

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {
                if (typeof Toast !== 'undefined') Toast.show('Fullscreen not available', 'warning');
            });
        } else {
            document.exitFullscreen();
        }
    }
}

// Escape special regex characters
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Initialize globally
let commandPalette;

function initCommandPalette() {
    commandPalette = new CommandPalette();
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCommandPalette);
} else {
    initCommandPalette();
}

// Expose to global scope for debugging
window.commandPalette = commandPalette;
window.initCommandPalette = initCommandPalette;
