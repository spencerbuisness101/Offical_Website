/**
 * Auto-Save v1.0 - Spencer's Website
 * Automatically saves form data to localStorage
 * Usage: new AutoSave('#form-selector', { key: 'unique_key', interval: 30000 })
 */

class AutoSave {
    constructor(formSelector, options = {}) {
        this.form = document.querySelector(formSelector);
        if (!this.form) {
            console.warn(`AutoSave: Form not found for selector "${formSelector}"`);
            return;
        }
        
        this.key = options.key || `autosave_${window.location.pathname}_${this.form.id || 'form'}`;
        this.interval = options.interval || 30000; // Default 30 seconds
        this.fields = options.fields || this.getAutoSaveableFields();
        this.maxAge = options.maxAge || 7 * 24 * 60 * 60 * 1000; // 7 days
        this.showIndicator = options.showIndicator !== false; // Default true
        
        this.saveTimeout = null;
        this.lastSaveTime = null;
        this.indicator = null;
        
        this.init();
    }
    
    init() {
        this.restore();
        this.bindEvents();
        
        // Auto-save on interval
        setInterval(() => this.save(), this.interval);
        
        console.log(`AutoSave initialized for "${this.key}"`);
    }
    
    getAutoSaveableFields() {
        // Get all text inputs, textareas, and select elements
        return Array.from(this.form.querySelectorAll(
            'input[type="text"], input[type="email"], input[type="url"], ' +
            'input[type="search"], input[type="tel"], ' +
            'textarea, select'
        )).map(field => field.name || field.id).filter(Boolean);
    }
    
    bindEvents() {
        // Save on input (debounced)
        this.form.addEventListener('input', (e) => {
            if (this.shouldAutoSaveField(e.target)) {
                this.debouncedSave();
            }
        });
        
        // Save on change (for selects, checkboxes, etc.)
        this.form.addEventListener('change', (e) => {
            if (this.shouldAutoSaveField(e.target)) {
                this.save();
            }
        });
        
        // Clear on successful submit
        this.form.addEventListener('submit', () => {
            this.clear();
            this.showStatus('Saved!', 'success');
        });
        
        // Warn before leaving if unsaved
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges()) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }
    
    shouldAutoSaveField(field) {
        // Don't auto-save password fields, hidden fields, or submit buttons
        if (field.type === 'password' || field.type === 'hidden' || field.type === 'submit') {
            return false;
        }
        
        // Don't auto-save fields with data-no-autosave attribute
        if (field.dataset.noAutosave) {
            return false;
        }
        
        return true;
    }
    
    debouncedSave() {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => this.save(), 1000);
    }
    
    save() {
        const data = {};
        let hasData = false;
        
        this.fields.forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"], #${fieldName}`);
            if (field && this.shouldAutoSaveField(field)) {
                const value = this.getFieldValue(field);
                if (value) {
                    data[fieldName] = value;
                    hasData = true;
                }
            }
        });
        
        if (hasData) {
            const saveData = {
                data,
                timestamp: Date.now(),
                url: window.location.href
            };
            
            try {
                localStorage.setItem(this.key, JSON.stringify(saveData));
                this.lastSaveTime = Date.now();
                
                if (this.showIndicator) {
                    this.showStatus('Auto-saved', 'info');
                }
                
                console.log(`AutoSave: Data saved for "${this.key}"`);
            } catch (e) {
                console.error('AutoSave: Failed to save to localStorage', e);
            }
        }
    }
    
    getFieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked;
        } else if (field.type === 'radio') {
            const radioGroup = this.form.querySelectorAll(`[name="${field.name}"]`);
            const checked = Array.from(radioGroup).find(r => r.checked);
            return checked ? checked.value : null;
        } else if (field.tagName === 'SELECT' && field.multiple) {
            return Array.from(field.selectedOptions).map(o => o.value);
        }
        return field.value;
    }
    
    restore() {
        try {
            const saved = localStorage.getItem(this.key);
            if (!saved) return;
            
            const { data, timestamp, url } = JSON.parse(saved);
            
            // Check if data is too old
            if (Date.now() - timestamp > this.maxAge) {
                console.log(`AutoSave: Data for "${this.key}" is too old, clearing`);
                this.clear();
                return;
            }
            
            // Check if URL matches (prevents restoring data on wrong page)
            if (url && !window.location.href.includes(new URL(url).pathname)) {
                console.log(`AutoSave: URL mismatch, not restoring`);
                return;
            }
            
            // Restore data
            let restoredCount = 0;
            Object.entries(data).forEach(([fieldName, value]) => {
                const field = this.form.querySelector(`[name="${fieldName}"], #${fieldName}`);
                if (field && !field.value) { // Only restore if field is empty
                    this.setFieldValue(field, value);
                    restoredCount++;
                }
            });
            
            if (restoredCount > 0) {
                console.log(`AutoSave: Restored ${restoredCount} fields for "${this.key}"`);
                this.showStatus('Draft restored', 'warning');
            }
        } catch (e) {
            console.error('AutoSave: Failed to restore', e);
        }
    }
    
    setFieldValue(field, value) {
        if (field.type === 'checkbox') {
            field.checked = value;
        } else if (field.type === 'radio') {
            const radio = this.form.querySelector(`[name="${field.name}"][value="${value}"]`);
            if (radio) radio.checked = true;
        } else if (field.tagName === 'SELECT' && field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach(option => {
                option.selected = value.includes(option.value);
            });
            // Trigger change event
            field.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            field.value = value;
            // Trigger input event for any listeners
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    
    clear() {
        try {
            localStorage.removeItem(this.key);
            console.log(`AutoSave: Cleared data for "${this.key}"`);
        } catch (e) {
            console.error('AutoSave: Failed to clear', e);
        }
    }
    
    hasUnsavedChanges() {
        // Check if there's saved data that differs from current form state
        try {
            const saved = localStorage.getItem(this.key);
            if (!saved) return false;
            
            const { data, timestamp } = JSON.parse(saved);
            
            // If saved within last 5 seconds, consider it synced
            if (Date.now() - timestamp < 5000) {
                return false;
            }
            
            // Check if any field differs
            for (const [fieldName, savedValue] of Object.entries(data)) {
                const field = this.form.querySelector(`[name="${fieldName}"], #${fieldName}`);
                if (field) {
                    const currentValue = this.getFieldValue(field);
                    if (currentValue !== savedValue) {
                        return true;
                    }
                }
            }
        } catch (e) {
            return false;
        }
        
        return false;
    }
    
    showStatus(message, type = 'info') {
        if (!this.showIndicator) return;
        
        // Remove existing indicator
        if (this.indicator) {
            this.indicator.remove();
        }
        
        // Create new indicator
        this.indicator = document.createElement('div');
        this.indicator.className = `autosave-indicator autosave-indicator--${type}`;
        this.indicator.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add styles if not already added
        if (!document.getElementById('autosave-styles')) {
            const styles = document.createElement('style');
            styles.id = 'autosave-styles';
            styles.textContent = `
                .autosave-indicator {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    z-index: 9999;
                    animation: slideIn 0.3s ease;
                    backdrop-filter: blur(10px);
                }
                
                .autosave-indicator--info {
                    background: rgba(59, 130, 246, 0.9);
                    color: white;
                }
                
                .autosave-indicator--success {
                    background: rgba(34, 197, 94, 0.9);
                    color: white;
                }
                
                .autosave-indicator--warning {
                    background: rgba(245, 158, 11, 0.9);
                    color: white;
                }
                
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                
                .autosave-indicator.hiding {
                    animation: fadeOut 0.3s ease forwards;
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(this.indicator);
        
        // Auto-hide after delay
        setTimeout(() => {
            if (this.indicator) {
                this.indicator.classList.add('hiding');
                setTimeout(() => {
                    if (this.indicator) {
                        this.indicator.remove();
                        this.indicator = null;
                    }
                }, 300);
            }
        }, 3000);
    }
    
    // Public API methods
    forceSave() {
        this.save();
    }
    
    getSavedData() {
        try {
            const saved = localStorage.getItem(this.key);
            return saved ? JSON.parse(saved) : null;
        } catch (e) {
            return null;
        }
    }
    
    destroy() {
        clearTimeout(this.saveTimeout);
        if (this.indicator) {
            this.indicator.remove();
        }
    }
}

// Helper function to initialize auto-save on all forms with data-autosave attribute
function initAutoSaveForms() {
    document.querySelectorAll('form[data-autosave]').forEach(form => {
        const key = form.dataset.autosave;
        const interval = parseInt(form.dataset.autosaveInterval) || 30000;
        
        new AutoSave(form, {
            key,
            interval,
            showIndicator: true
        });
    });
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAutoSaveForms);
} else {
    initAutoSaveForms();
}

// Expose to global scope
window.AutoSave = AutoSave;
window.initAutoSaveForms = initAutoSaveForms;
