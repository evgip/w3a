/**
 * Асинхронный AJAX-движок голосования в стиле Lobsters (Полный перехват форм)
 */
/**
 * Асинхронный AJAX-движок голосования в стиле Lobsters с временным троттлингом кликов
 */
document.addEventListener('DOMContentLoaded', function () {
    
    document.addEventListener('submit', function (event) {
        const form = event.target;
        
        if (!form.action || form.action.indexOf('/vote/') === -1) {
            return;
        }

        event.preventDefault();

        // Защита от спам-кликов: Если форма уже отправляется в данный момент — игнорируем клик
        if (form.dataset.ajaxSubmitting === 'true') {
            return false;
        }

        const wrapper = form.closest('.story-voting-wrapper') || form.closest('.comment-vote-form-group');
        if (!wrapper) return;

        // Ищем активную кнопку, на которую нажали, и временно замораживаем её
        const currentBtn = form.querySelector('button[type="submit"]');
        if (currentBtn) {
            form.dataset.ajaxSubmitting = 'true';
            currentBtn.classList.add('btn-processing-disabled'); // Делаем её полупрозрачной на время запроса
        }

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
                    throw new Error('Forbidden');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // 1. Обновляем счетчик
                const counter = wrapper.querySelector('.story-counter-value') || wrapper.closest('.comment-node').querySelector('.story-counter-value');
                if (counter) {
                    if (counter.innerText.indexOf('(') !== -1) {
                        counter.innerText = `(${data.new_score})`;
                    } else {
                        counter.innerText = data.new_score;
                    }
                }

                // 2. Сбрасываем стили подсветки у стрелочек
                const upBtn = wrapper.querySelector('form[action*="/up"] button');
                const downBtn = wrapper.querySelector('form[action*="/down"] button');

                if (upBtn) upBtn.classList.remove('btn-vote-arrow-active');
                if (downBtn) downBtn.classList.remove('btn-vote-down-active');

                // 3. Красим нужную стрелочку
                if (data.vote_state === 1 && upBtn) {
                    upBtn.classList.add('btn-vote-arrow-active');
                } else if (data.vote_state === -1 && downBtn) {
                    downBtn.classList.add('btn-vote-down-active');
                }
            }
        })
        .catch(error => console.warn('Voting engine communication log:', error))
        .finally(() => {
            // КРИТИЧЕСКИЙ РАЗБЛОКИРОВЩИК: Всегда снимаем статус отправки и возвращаем кнопке полную кликабельность!
            form.dataset.ajaxSubmitting = 'false';
            if (currentBtn) {
                currentBtn.classList.remove('btn-processing-disabled');
            }
        });
    });
});
