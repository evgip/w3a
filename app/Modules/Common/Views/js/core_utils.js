/**
 * CSRF Protection - автоматическая отправка токена для AJAX-запросов
 * Double-Submit Cookie Pattern
 */
const CsrfProtection = {
    cookieName: 'XSRF-TOKEN',
    headerName: 'X-XSRF-TOKEN',

    /**
     * Получает токен из cookie
     */
    getToken() {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === this.cookieName) {
                return decodeURIComponent(value);
            }
        }
        return null;
    },

    /**
     * Инициализация - перехватываем все AJAX-запросы
     */
    init() {
        this.interceptFetch();
        this.interceptXMLHttpRequest();
    },

    /**
     * Перехватываем fetch API
     */
    interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = function(url, options = {}) {
            // Инициализируем headers если их нет
            options.headers = options.headers || {};
            
            // Если headers - это объект (не FormData), добавляем токен
            if (!(options.headers instanceof Headers)) {
                const token = CsrfProtection.getToken();
                if (token) {
                    options.headers[CsrfProtection.headerName] = token;
                }
            }

            return originalFetch(url, options);
        };
    },

    /**
     * Перехватываем XMLHttpRequest
     */
    interceptXMLHttpRequest() {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function(method, url) {
            this._csrfMethod = method;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function(data) {
            // Добавляем токен только для изменяющих методов
            if (this._csrfMethod && !['GET', 'HEAD', 'OPTIONS'].includes(this._csrfMethod.toUpperCase())) {
                const token = CsrfProtection.getToken();
                if (token) {
                    this.setRequestHeader(CsrfProtection.headerName, token);
                }
            }
            return originalSend.apply(this, arguments);
        };
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    CsrfProtection.init();
});


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