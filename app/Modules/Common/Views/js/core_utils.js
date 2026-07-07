(function() {
    'use strict';
    
    const STORAGE_KEY = 'w3a_theme';
    
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }
    
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem(STORAGE_KEY, next);
    }
    
    applyTheme(getPreferredTheme());
    
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleTheme);
        }
    });
})();

document.addEventListener('DOMContentLoaded', function() {
    // Массив классов, требующих подтверждения
    const confirmClasses = ['.flag-link', '.delete-link', '.restore-link'];
    
    confirmClasses.forEach(function(selector) {
        document.querySelectorAll(selector).forEach(function(link) {
            link.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm');
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    });
});