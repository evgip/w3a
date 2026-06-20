/**
 * AJAX-голосование.
 * Делегирует уведомления существующей системе (если есть).
 */
(function() {
    'use strict';

    document.addEventListener('submit', function(event) {
        const form = event.target;
        
        // Только формы голосования
        if (!form.hasAttribute('data-vote-form')) return;
        
        event.preventDefault();
        
        // Защита от двойного клика
        if (form.dataset.submitting === 'true') return;
        
        const wrapper = form.closest('.voters');
        if (!wrapper) return;
        
        form.dataset.submitting = 'true';
        const buttons = wrapper.querySelectorAll('button');
        buttons.forEach(btn => btn.disabled = true);
        
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => handleResponse(response))
        .then(data => {
            if (!data || data.status !== 'success') return;
            updateUI(wrapper, data);
        })
        .catch(error => {
            if (error.message !== 'CSRF_EXPIRED' && error.message !== 'REDIRECT') {
                console.warn('[Votes] Error:', error);
            }
        })
        .finally(() => {
            form.dataset.submitting = 'false';
            buttons.forEach(btn => btn.disabled = false);
        });
    });

    /**
     * Обработка HTTP-ответа.
     */
    async function handleResponse(response) {
        // CSRF истёк — перезагрузка
        if (response.status === 419) {
            showNotice('Сессия истекла. Обновите страницу.', 'warning');
            setTimeout(() => location.reload(), 2000);
            throw new Error('CSRF_EXPIRED');
        }

        // Не авторизован — редирект
        if (response.status === 401) {
            showNotice('Необходима авторизация.', 'info');
            setTimeout(() => {
                window.location.href = '/login?redirect=' + encodeURIComponent(location.pathname);
            }, 1500);
            throw new Error('REDIRECT');
        }

        // Ошибки с сообщением
        if (response.status === 400 || response.status === 403) {
            const data = await response.json().catch(() => ({}));
            showNotice(data.message || 'Ошибка', 'error');
            throw new Error(data.message || 'Error');
        }

        // Ошибки сервера
        if (response.status >= 500) {
            showNotice('Ошибка сервера. Попробуйте позже.', 'error');
            throw new Error('Server error');
        }

        if (!response.ok) {
            throw new Error('Unknown error');
        }

        return await response.json();
    }

    /**
     * Обновление UI после голосования.
     */
    function updateUI(wrapper, data) {
        // Обновляем счётчик
        const scoreEl = wrapper.querySelector('.score');
        if (scoreEl && typeof data.new_score === 'number') {
            const old = parseInt(scoreEl.textContent, 10) || 0;
            scoreEl.textContent = data.new_score;
            
            // Простая анимация
            scoreEl.style.transition = 'transform 0.3s';
            scoreEl.style.transform = 'scale(1.3)';
            setTimeout(() => scoreEl.style.transform = 'scale(1)', 300);
        }

        // Сбрасываем активные кнопки
        wrapper.querySelectorAll('.upvoter').forEach(btn => btn.classList.remove('upvoted'));

        // Подсвечиваем нужную
        if (data.vote_state === 1) {
            const btn = wrapper.querySelector('form[data-direction="up"] .upvoter');
            if (btn) btn.classList.add('upvoted');
        } else if (data.vote_state === -1) {
            const btn = wrapper.querySelector('form[data-direction="down"] .upvoter');
            if (btn) btn.classList.add('upvoted');
        }
    }

    /**
     * Показать уведомление.
     * Использует существующую систему, если есть.
     */
    function showNotice(message, type) {
        // Если есть глобальная функция — используем её
        if (typeof window.showFlashMessage === 'function') {
            window.showFlashMessage(message, type);
            return;
        }
        
        // Если есть Session flash — используем alert как fallback
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        
        // Fallback — простой alert
        alert(message);
    }

})();