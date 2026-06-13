/**
 * Интерактивные функции комментариев в стиле Lobsters
 * - Ответ на комментарий (Reply)
 * - Редактирование комментария (Edit)
 * - Защита от двойной отправки форм
 */
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // ЗАЩИТА ОТ ДВОЙНОЙ ОТПРАВКИ ФОРМ
    // ============================================
    document.addEventListener('submit', function(event) {
        const form = event.target;

        // Исключаем формы голосования (ими управляет voting.js)
        if (form.action && form.action.indexOf('/vote/') !== -1) {
            return true;
        }

        // Защита от повторной отправки
        if (form.dataset.isSubmitting === 'true') {
            event.preventDefault();
            return false;
        }

        // Подтверждение удаления комментария
        if (form.classList.contains('js-comment-delete-form')) {
            const confirmed = confirm('Вы уверены, что хотите удалить этот комментарий?');
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }

        // Блокируем форму на время отправки
        form.dataset.isSubmitting = 'true';

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    });

    // ============================================
    // СЕЛЕКТОРЫ ЭЛЕМЕНТОВ
    // ============================================
    const replyButtons = document.querySelectorAll('.comment-reply-link');
    const editButtons = document.querySelectorAll('.comment-edit-trigger');
    const parentIdInput = document.getElementById('form-parent-id');
    const cancelBtn = document.getElementById('btn-cancel-reply');
    const commentForm = document.getElementById('main-comment-form');
    const textarea = document.getElementById('form-comment-textarea');
    const formContainer = document.getElementById('comment-form-container');

    // ============================================
    // 1. ОТВЕТ НА КОММЕНТАРИЙ (REPLY)
    // ============================================
    if (commentForm && replyButtons.length > 0) {

        replyButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                // Закрываем все открытые формы редактирования
                document.querySelectorAll('.comment-dynamic-edit-form').forEach(f => f.remove());
                document.querySelectorAll('.comment_text').forEach(t => t.style.display = 'block');

                // Извлекаем ID комментария из href (#reply-to-{id})
                const commentId = this.getAttribute('href').replace('#reply-to-', '');

                // Находим родительский комментарий (li.comment)
                const parentComment = this.closest('li.comment');
                if (!parentComment) return;

                // Извлекаем имя автора из comment_meta
                const authorLink = parentComment.querySelector('.comment_meta a');
                const authorName = authorLink ? authorLink.innerText : '';

                // Устанавливаем parent_id
                if (parentIdInput) {
                    parentIdInput.value = commentId;
                }

                // Показываем кнопку отмены
                if (cancelBtn) {
                    cancelBtn.style.display = 'inline-block';
                }

                // Перемещаем форму под комментарий
                parentComment.parentNode.insertBefore(commentForm, parentComment.nextSibling);

                // Фокус на textarea
                if (textarea) {
                    textarea.focus();
                }
            });
        });

        // Обработка кнопки "Отмена" для ответа
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                // Сбрасываем parent_id
                if (parentIdInput) {
                    parentIdInput.value = '';
                }

                // Скрываем кнопку отмены
                cancelBtn.style.display = 'none';

                // Возвращаем форму в исходный контейнер
                if (formContainer) {
                    formContainer.appendChild(commentForm);
                }

                // Очищаем textarea
                if (textarea) {
                    textarea.value = '';
                }
            });
        }
    }

    // ============================================
    // 2. ДИНАМИЧЕСКОЕ РЕДАКТИРОВАНИЕ КОММЕНТАРИЯ (EDIT)
    // ============================================
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const commentId = this.getAttribute('data-id');

            // Находим родительский комментарий (li.comment)
            const commentLi = this.closest('li.comment');
            if (!commentLi) return;

            // Находим блок текста комментария
            const textBlock = document.getElementById(`comment-text-content-${commentId}`);
            if (!textBlock) return;

            // Если форма редактирования уже открыта — выходим
            if (commentLi.querySelector('.comment-dynamic-edit-form')) return;

            // Скрываем текущий текст
            textBlock.style.display = 'none';

            // Извлекаем исходный Markdown из data-raw
            const currentText = textBlock.getAttribute('data-raw') || '';

            // Извлекаем CSRF токен из основной формы
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

            // Создаём динамическую форму редактирования
            const editForm = document.createElement('form');
            editForm.action = `/comments/${commentId}/edit`;
            editForm.method = 'POST';
            editForm.className = 'comment-dynamic-edit-form';

            editForm.innerHTML = `
                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                <textarea name="comment_text" required>${escapeHtml(currentText)}</textarea>
                <div class="comment_actions">
                    <button type="submit">Сохранить</button>
                    <span class="divider">|</span>
                    <button type="button" class="comment-edit-cancel btn-link">Отмена</button>
                </div>
            `;

            // Вставляем форму после блока текста
            textBlock.parentNode.insertBefore(editForm, textBlock.nextSibling);

            // Фокус на textarea
            const editTextarea = editForm.querySelector('textarea');
            if (editTextarea) {
                editTextarea.focus();
            }

            // Обработка кнопки "Отмена"
            editForm.querySelector('.comment-edit-cancel').addEventListener('click', function() {
                editForm.remove();
                textBlock.style.display = 'block';
            });
        });
    });

    // ============================================
    // ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ: Экранирование HTML
    // ============================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

	// Обработка форм с подтверждением удаления
		document.querySelectorAll('.js-confirm-delete').forEach(function(form) {
			form.addEventListener('submit', function(e) {
				const message = this.getAttribute('data-confirm-message') || 'Вы уверены?';
				if (!confirm(message)) {
					e.preventDefault();
					return false;
				}
			});
		});

});
