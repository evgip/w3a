(function() {
    'use strict';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; padding: 15px 20px;
            background: ${type === 'error' ? '#f44336' : '#4CAF50'};
            color: white; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000; animation: slideIn 0.3s ease-out; font-family: sans-serif;
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        `;
        document.head.appendChild(style);
    }

    // Универсальная функция POST-запроса
    async function postJson(endpoint, data) {
        const csrfToken = getCsrfToken();
        if (!csrfToken) throw new Error('CSRF-токен не найден. Обновите страницу.');

        // ✅ Используем абсолютный URL без слэша в конце (стандарт для w3a)
        const url = window.location.origin + endpoint;

        const formData = new URLSearchParams();
        formData.append('csrf_token', csrfToken);
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString(),
            // ✅ Запрещаем браузеру следовать за редиректом автоматически, чтобы мы увидели ошибку
            redirect: 'manual' 
        });

        // Если сервер вернул редирект (302), мы это перехватим
        if (response.status === 302 || response.status === 301) {
            const redirectUrl = response.headers.get('location');
            throw new Error(`Сервер попытался сделать редирект на: ${redirectUrl}. Проверьте CSRF или авторизацию.`);
        }

        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Сервер вернул не JSON:', text);
            throw new Error('Некорректный ответ сервера: ' + text.substring(0, 100));
        }
    }

    // Обработчик добавления
    document.getElementById('btn-add-filter')?.addEventListener('click', async function() {
        const tagId = document.getElementById('tag-select').value;
        if (!tagId) {
            showNotification('Выберите тег из списка', 'error');
            return;
        }

        const button = this;
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = 'Добавление...';

        try {
            const result = await postJson('/filters/add', { tag_id: tagId });
            if (result.success) {
                showNotification(result.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Ошибка', 'error');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            showNotification(error.message, 'error');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });

    // Обработчик удаления
    document.querySelectorAll('.btn-remove-filter').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Удалить этот тег из фильтров?')) return;

            const tagId = this.closest('.filter-item').dataset.tagId;
            const button = this;
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '...';

            try {
                const result = await postJson('/filters/remove', { tag_id: tagId });
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showNotification(result.message || 'Ошибка', 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showNotification(error.message, 'error');
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });
})();