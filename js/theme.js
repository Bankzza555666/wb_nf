/**
 * Theme Switcher
 * Handles Light/Dark mode toggling and persistence
 */

const ThemeManager = {
    init() {
        // Check saved preference or system preference
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            this.setTheme('dark');
        } else {
            this.setTheme('light');
        }

        // Add event listener to toggle button (if exists)
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            this.updateButtonIcon(toggleBtn);
            toggleBtn.addEventListener('click', () => this.toggleTheme());
        }
    },

    setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        }

        // Update button icon if it exists
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            this.updateButtonIcon(toggleBtn);
        }

        // Update Premium Alert theme
        if (window.premiumAlert) {
            // Re-initialize or update styles if needed (CSS variables handle most of it)
        }
    },

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        if (currentTheme === 'dark') {
            this.setTheme('light');
        } else {
            this.setTheme('dark');
        }
    },

    updateButtonIcon(btn) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            btn.innerHTML = '<i class="fas fa-sun"></i>';
            btn.setAttribute('title', 'สลับเป็นโหมดสว่าง');
        } else {
            btn.innerHTML = '<i class="fas fa-moon"></i>';
            btn.setAttribute('title', 'สลับเป็นโหมดมืด');
        }
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
});
