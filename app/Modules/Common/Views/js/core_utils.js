/**
 * Глобальная функция для ручного получения CSRF-токена
 * Используется в особых случаях (WebSocket, кастомные библиотеки)
 */
window.getCsrfToken = function() {
    const name = 'XSRF-TOKEN=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(name) === 0) {
            return c.substring(name.length, c.length);
        }
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;

    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;

    return '';
};

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
        const self = this;
        
        window.fetch = function(url, options = {}) {
            options.headers = options.headers || {};
            
            // Проверяем, что headers — это обычный объект (не массив, не Headers)
            if (options.headers.constructor === Object) {
                const token = self.getToken();
                if (token) {
                    options.headers[self.headerName] = token;
                    options.headers['X-Requested-With'] = 'XMLHttpRequest';
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
        const self = this;

        XMLHttpRequest.prototype.open = function(method, url) {
            this._csrfMethod = method;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function(data) {
            if (this._csrfMethod && !['GET', 'HEAD', 'OPTIONS'].includes(this._csrfMethod.toUpperCase())) {
                const token = self.getToken();
                if (token) {
                    this.setRequestHeader(self.headerName, token);
                    this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
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

/**
 * Управление темой (светлая/тёмная)
 */
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

/**
 * Подтверждение действий для ссылок с классами .flag-link, .delete-link, .restore-link
 */
document.addEventListener('DOMContentLoaded', function() {
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