/**
 * Premium Alert System
 * A modern, glassmorphism-style replacement for SweetAlert2
 */

class PremiumAlert {
    constructor() {
        this.overlay = null;
        this.toastContainer = null;
        this.init();
    }

    init() {
        // Create Overlay if not exists
        if (!document.querySelector('.pa-overlay')) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'pa-overlay';
            document.body.appendChild(this.overlay);
        } else {
            this.overlay = document.querySelector('.pa-overlay');
        }

        // Create Toast Container if not exists
        if (!document.querySelector('.pa-toast-container')) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.className = 'pa-toast-container';
            document.body.appendChild(this.toastContainer);
        } else {
            this.toastContainer = document.querySelector('.pa-toast-container');
        }
    }

    /**
     * Main Alert Method (Compatible with Swal.fire)
     * @param {Object|String} options - Configuration object or title string
     * @param {String} [text] - Text content (if first arg is title)
     * @param {String} [icon] - Icon type (if first arg is title)
     */
    fire(options, text, icon) {
        // Handle shorthand: fire('Title', 'Text', 'icon')
        if (typeof options === 'string') {
            options = {
                title: options,
                text: text,
                icon: icon
            };
        }

        return new Promise((resolve) => {
            const {
                title = '',
                text = '',
                html = '',
                icon = 'info',
                showCancelButton = false,
                confirmButtonText = 'ตกลง',
                cancelButtonText = 'ยกเลิก',
                confirmButtonColor, // Ignored, handled by CSS
                cancelButtonColor,  // Ignored, handled by CSS
                timer = 0
            } = options;

            // Icons mapping
            const icons = {
                success: '<i class="fas fa-check-circle pa-icon pa-success"></i>',
                error: '<i class="fas fa-times-circle pa-icon pa-error"></i>',
                warning: '<i class="fas fa-exclamation-triangle pa-icon pa-warning"></i>',
                info: '<i class="fas fa-info-circle pa-icon pa-info"></i>',
                question: '<i class="fas fa-question-circle pa-icon pa-question"></i>'
            };

            const contentHtml = html || text.replace(/\n/g, '<br>');

            const modalHtml = `
                <div class="pa-modal">
                    ${icons[icon] || icons.info}
                    <div class="pa-title">${title}</div>
                    <div class="pa-content">${contentHtml}</div>
                    <div class="pa-actions">
                        ${showCancelButton ? `<button class="pa-btn pa-btn-cancel">${cancelButtonText}</button>` : ''}
                        <button class="pa-btn pa-btn-confirm">${confirmButtonText}</button>
                    </div>
                </div>
            `;

            this.overlay.innerHTML = modalHtml;

            // Show Overlay
            requestAnimationFrame(() => {
                this.overlay.classList.add('pa-show');
            });

            // Event Listeners
            const confirmBtn = this.overlay.querySelector('.pa-btn-confirm');
            const cancelBtn = this.overlay.querySelector('.pa-btn-cancel');

            const close = (isConfirmed) => {
                this.overlay.classList.remove('pa-show');
                setTimeout(() => {
                    this.overlay.innerHTML = '';
                    resolve({ isConfirmed: isConfirmed, isDismissed: !isConfirmed });
                }, 300);
            };

            confirmBtn.addEventListener('click', () => close(true));
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => close(false));
            }

            // Timer
            if (timer > 0) {
                setTimeout(() => close(false), timer);
            }
        });
    }

    /**
     * Toast Notification (Compatible with Swal.mixin({toast: true}).fire)
     */
    toast(options) {
        const {
            title = '',
            icon = 'info',
            timer = 3000
        } = options;

        const toast = document.createElement('div');
        toast.className = `pa-toast pa-${icon}`;

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = `
            <i class="fas ${icons[icon] || icons.info} pa-toast-icon"></i>
            <div class="pa-toast-content">
                <div class="pa-toast-title">${title}</div>
            </div>
        `;

        this.toastContainer.appendChild(toast);

        // Animate In
        requestAnimationFrame(() => toast.classList.add('pa-show'));

        // Remove after timer
        setTimeout(() => {
            toast.classList.remove('pa-show');
            setTimeout(() => toast.remove(), 400);
        }, timer);
    }

    // Mock mixin for backward compatibility
    mixin(config) {
        if (config.toast) {
            return {
                fire: (opts) => this.toast({ ...config, ...opts })
            };
        }
        return this; // Return self for chaining if needed
    }
}

// Initialize Global Instance
const premiumAlert = new PremiumAlert();

// Backward Compatibility with SweetAlert2
// Backward Compatibility with SweetAlert2
const Swal = premiumAlert;
window.Swal = premiumAlert;

// Global Helper for simplified usage (matches topup.php usage)
window.showAlert = function (icon, title, text, timer = 0) {
    return premiumAlert.fire({
        icon: icon,
        title: title,
        text: text,
        timer: timer
    });
};
