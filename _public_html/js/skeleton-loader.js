/**
 * Skeleton Loader v1.0 - Spencer's Website
 * Progressive loading with skeleton states
 * Usage: SkeletonLoader.show('element-id'), SkeletonLoader.hide('element-id')
 */

class SkeletonLoader {
    constructor() {
        this.loaders = new Map();
    }
    
    /**
     * Show skeleton loading state for an element
     * @param {string} elementId - ID of the container element
     * @param {string} type - Type of skeleton (card, text, list, etc.)
     */
    show(elementId, type = 'default') {
        const container = document.getElementById(elementId);
        if (!container) {
            console.warn(`SkeletonLoader: Container "${elementId}" not found`);
            return;
        }
        
        // Store original content
        if (!this.loaders.has(elementId)) {
            this.loaders.set(elementId, {
                originalContent: container.innerHTML,
                type: type
            });
        }
        
        // Generate and show skeleton
        const skeletonHTML = this.generateSkeleton(type);
        container.innerHTML = skeletonHTML;
        container.classList.add('skeleton-loading');
    }
    
    /**
     * Hide skeleton and restore/insert content
     * @param {string} elementId - ID of the container element
     * @param {string} content - HTML content to insert (optional, restores original if not provided)
     */
    hide(elementId, content = null) {
        const container = document.getElementById(elementId);
        if (!container) return;
        
        const loader = this.loaders.get(elementId);
        
        // Fade out skeleton
        container.style.opacity = '0';
        
        setTimeout(() => {
            // Insert new content or restore original
            container.innerHTML = content || (loader ? loader.originalContent : '');
            container.classList.remove('skeleton-loading');
            
            // Fade in content
            container.style.opacity = '1';
            
            // Remove from tracking
            this.loaders.delete(elementId);
        }, 200);
    }
    
    /**
     * Generate skeleton HTML based on type
     */
    generateSkeleton(type) {
        const skeletons = {
            default: `<div class="skeleton skeleton-text skeleton-text--lg"></div>`,
            
            card: `
                <div class="skeleton-card">
                    <div class="skeleton skeleton-card__image"></div>
                    <div class="skeleton skeleton-card__title"></div>
                    <div class="skeleton skeleton-card__meta"></div>
                </div>
            `,
            
            'game-card': `
                <div class="skeleton-game-card">
                    <div class="skeleton skeleton-game-card__image"></div>
                    <div class="skeleton-game-card__content">
                        <div class="skeleton skeleton-game-card__title"></div>
                        <div class="skeleton skeleton-game-card__badge"></div>
                    </div>
                </div>
            `,
            
            list: `
                <div class="skeleton-list-item">
                    <div class="skeleton skeleton-list-item__avatar"></div>
                    <div class="skeleton-list-item__content">
                        <div class="skeleton skeleton-list-item__title"></div>
                        <div class="skeleton skeleton-list-item__subtitle"></div>
                    </div>
                </div>
            `,
            
            text: `
                <div class="skeleton-text-wrapper">
                    <div class="skeleton skeleton-text skeleton-text--lg"></div>
                    <div class="skeleton skeleton-text skeleton-text--md"></div>
                    <div class="skeleton skeleton-text skeleton-text--sm"></div>
                </div>
            `,
            
            stats: `
                <div class="skeleton-stats">
                    <div class="skeleton-stat">
                        <div class="skeleton skeleton-stat__value"></div>
                        <div class="skeleton skeleton-stat__label"></div>
                    </div>
                    <div class="skeleton-stat">
                        <div class="skeleton skeleton-stat__value"></div>
                        <div class="skeleton skeleton-stat__label"></div>
                    </div>
                    <div class="skeleton-stat">
                        <div class="skeleton skeleton-stat__value"></div>
                        <div class="skeleton skeleton-stat__label"></div>
                    </div>
                </div>
            `,
            
            announcement: `
                <div class="skeleton-announcement">
                    <div class="skeleton-announcement__header">
                        <div class="skeleton skeleton-announcement__badge"></div>
                        <div class="skeleton skeleton-announcement__title"></div>
                    </div>
                    <div class="skeleton skeleton-announcement__body"></div>
                </div>
            `,
            
            chat: `
                <div class="skeleton-chat">
                    <div class="skeleton skeleton-chat__avatar"></div>
                    <div class="skeleton-chat__content">
                        <div class="skeleton-chat__header">
                            <div class="skeleton skeleton-chat__username"></div>
                            <div class="skeleton skeleton-chat__time"></div>
                        </div>
                        <div class="skeleton skeleton-chat__message"></div>
                    </div>
                </div>
            `,
            
            profile: `
                <div class="skeleton-profile">
                    <div class="skeleton skeleton-profile__avatar"></div>
                    <div class="skeleton skeleton-profile__name"></div>
                    <div class="skeleton skeleton-profile__role"></div>
                    <div class="skeleton-profile__stats">
                        <div class="skeleton-profile__stat">
                            <div class="skeleton skeleton-profile__stat-value"></div>
                            <div class="skeleton skeleton-profile__stat-label"></div>
                        </div>
                        <div class="skeleton-profile__stat">
                            <div class="skeleton skeleton-profile__stat-value"></div>
                            <div class="skeleton skeleton-profile__stat-label"></div>
                        </div>
                        <div class="skeleton-profile__stat">
                            <div class="skeleton skeleton-profile__stat-value"></div>
                            <div class="skeleton skeleton-profile__stat-label"></div>
                        </div>
                    </div>
                </div>
            `,
            
            table: `
                <div class="skeleton-table">
                    <div class="skeleton-table__row">
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--wide"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                    </div>
                    <div class="skeleton-table__row">
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--wide"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                    </div>
                    <div class="skeleton-table__row">
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--wide"></div>
                        <div class="skeleton skeleton-table__cell skeleton-table__cell--narrow"></div>
                    </div>
                </div>
            `,
            
            'admin-stat': `
                <div class="skeleton-stat-card">
                    <div class="skeleton-stat-card__header">
                        <div class="skeleton skeleton-stat-card__value"></div>
                        <div class="skeleton skeleton-stat-card__icon"></div>
                    </div>
                    <div class="skeleton skeleton-stat-card__label"></div>
                </div>
            `
        };
        
        return skeletons[type] || skeletons.default;
    }
    
    /**
     * Show multiple skeletons of the same type
     * @param {string} elementId - ID of container
     * @param {string} type - Skeleton type
     * @param {number} count - Number of skeletons to show
     */
    showMultiple(elementId, type, count = 3) {
        const container = document.getElementById(elementId);
        if (!container) return;
        
        const skeletonHTML = Array(count).fill(this.generateSkeleton(type)).join('');
        
        this.loaders.set(elementId, {
            originalContent: container.innerHTML,
            type: type
        });
        
        container.innerHTML = skeletonHTML;
        container.classList.add('skeleton-loading');
    }
    
    /**
     * Show page-level skeleton loading
     * @param {string} layout - Layout type (dashboard, list, grid, etc.)
     */
    showPage(layout = 'default') {
        const body = document.body;
        
        // Store current content
        body.dataset.originalContent = body.innerHTML;
        
        const layouts = {
            default: `
                <div class="skeleton-page">
                    <div class="skeleton-page__header">
                        <div class="skeleton skeleton-page__title"></div>
                        <div class="skeleton skeleton-page__subtitle"></div>
                    </div>
                    <div class="skeleton-text-wrapper">
                        <div class="skeleton skeleton-text skeleton-text--lg"></div>
                        <div class="skeleton skeleton-text skeleton-text--md"></div>
                    </div>
                </div>
            `,
            
            dashboard: `
                <div class="skeleton-page">
                    <div class="skeleton-page__header">
                        <div class="skeleton skeleton-page__title"></div>
                    </div>
                    <div class="skeleton-stats" style="margin-bottom: 32px;">
                        <div class="skeleton-stat"><div class="skeleton skeleton-stat__value"></div><div class="skeleton skeleton-stat__label"></div></div>
                        <div class="skeleton-stat"><div class="skeleton skeleton-stat__value"></div><div class="skeleton skeleton-stat__label"></div></div>
                        <div class="skeleton-stat"><div class="skeleton skeleton-stat__value"></div><div class="skeleton skeleton-stat__label"></div></div>
                        <div class="skeleton-stat"><div class="skeleton skeleton-stat__value"></div><div class="skeleton skeleton-stat__label"></div></div>
                    </div>
                    <div class="skeleton-page__grid">
                        ${Array(6).fill('<div class="skeleton-card"><div class="skeleton skeleton-card__image"></div><div class="skeleton skeleton-card__title"></div><div class="skeleton skeleton-card__meta"></div></div>').join('')}
                    </div>
                </div>
            `,
            
            grid: `
                <div class="skeleton-page">
                    <div class="skeleton-page__header">
                        <div class="skeleton skeleton-page__title"></div>
                    </div>
                    <div class="skeleton-page__grid">
                        ${Array(8).fill('<div class="skeleton-card"><div class="skeleton skeleton-card__image"></div><div class="skeleton skeleton-card__title"></div></div>').join('')}
                    </div>
                </div>
            `,
            
            list: `
                <div class="skeleton-page">
                    <div class="skeleton-page__header">
                        <div class="skeleton skeleton-page__title"></div>
                    </div>
                    ${Array(5).fill('<div class="skeleton-list-item"><div class="skeleton skeleton-list-item__avatar"></div><div class="skeleton-list-item__content"><div class="skeleton skeleton-list-item__title"></div><div class="skeleton skeleton-list-item__subtitle"></div></div></div>').join('')}
                </div>
            `
        };
        
        body.innerHTML = layouts[layout] || layouts.default;
        body.classList.add('skeleton-page-loading');
    }
    
    /**
     * Hide page-level skeleton and restore content
     */
    hidePage() {
        const body = document.body;
        const originalContent = body.dataset.originalContent;
        
        if (originalContent) {
            body.style.opacity = '0';
            
            setTimeout(() => {
                body.innerHTML = originalContent;
                body.classList.remove('skeleton-page-loading');
                body.style.opacity = '1';
                delete body.dataset.originalContent;
            }, 200);
        }
    }
    
    /**
     * Check if an element is currently showing skeleton
     */
    isLoading(elementId) {
        return this.loaders.has(elementId);
    }
    
    /**
     * Clear all skeletons
     */
    clearAll() {
        this.loaders.forEach((loader, elementId) => {
            this.hide(elementId);
        });
    }
}

// Create global instance
const skeletonLoader = new SkeletonLoader();

// Expose to global scope
window.SkeletonLoader = SkeletonLoader;
window.skeletonLoader = skeletonLoader;

// Auto-initialize data-skeleton attributes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-skeleton]').forEach(el => {
        const type = el.dataset.skeleton;
        const autoHide = parseInt(el.dataset.skeletonAutohide) || 0;
        
        skeletonLoader.show(el.id, type);
        
        if (autoHide > 0) {
            setTimeout(() => {
                skeletonLoader.hide(el.id);
            }, autoHide);
        }
    });
});
