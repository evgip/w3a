/**
 * Асинхронный AJAX-движок голосования в стиле Lobsters
 * Перехватывает submit на формах голосования и обновляет UI без перезагрузки
 */
document.addEventListener('DOMContentLoaded', function() {

    // Делегируем обработчик на document, чтобы работали динамически добавленные формы
    document.addEventListener('submit', function(event) {
        const form = event.target;

        // Проверяем, что это форма голосования (URL содержит /vote/)
        if (!form.action || form.action.indexOf('/vote/') === -1) {
            return;
        }

        event.preventDefault();

        // Защита от двойного клика
        if (form.dataset.ajaxSubmitting === 'true') {
            return;
        }

        // Ищем обёртку голосования (.voters — как в Lobsters)
        const wrapper = form.closest('.voters');
        if (!wrapper) {
            return;
        }

        // Блокируем форму на время запроса
        form.dataset.ajaxSubmitting = 'true';

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.status === 401) {
                alert('Пожалуйста, войдите в аккаунт, чтобы голосовать.');
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                return response.json().then(errData => {
                    alert(errData.message || 'Ошибка обработки голоса.');
                    throw new Error('Server error');
                });
            }

            return response.json();
        })
        .then(data => {
            if (data.status !== 'success') {
                return;
            }

            // 1. Обновляем счётчик (.score — как в Lobsters)
            const scoreEl = wrapper.querySelector('.score');
            if (scoreEl) {
                scoreEl.textContent = data.new_score;
            }

            // 2. Сбрасываем все активные состояния (.upvoted)
            const buttons = wrapper.querySelectorAll('.upvoter');
            buttons.forEach(btn => btn.classList.remove('upvoted'));

            // 3. Подсвечиваем нужную кнопку
            // vote_state: 1 = upvote, -1 = downvote, 0 = отмена голоса
            if (data.vote_state === 1) {
                const upBtn = wrapper.querySelector('form[action*="/up"] .upvoter');
                if (upBtn) upBtn.classList.add('upvoted');
            } else if (data.vote_state === -1) {
                const downBtn = wrapper.querySelector('form[action*="/down"] .upvoter');
                if (downBtn) downBtn.classList.add('upvoted');
            }
        })
        .catch(error => {
            console.warn('Voting error:', error);
        })
        .finally(() => {
            // Всегда разблокируем форму после запроса
            form.dataset.ajaxSubmitting = 'false';
        });
    });

});