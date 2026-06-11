document.addEventListener('DOMContentLoaded', function () {
	
	/**
	 * Defensive Global Form Submission Guard & Double-Click Throttle
	 */
	document.addEventListener('submit', function (event) {
		const form = event.target;
		
		// ИСКЛЮЧЕНИЕ: Если эта форма является асинхронным голосованием, пропускаем её защиту,
		// так как её разблокировкой управляет сам AJAX-движок в voting.js
		if (form.action && form.action.indexOf('/vote/') !== -1) {
			return true;
		}
		
		// 1. Skip if this form is already marked as processing
		if (form.dataset.isSubmitting === 'true') {
			event.preventDefault();
			return false;
		}

		// 2. Safely apply native browser confirm prompts to comment deletion workflows
		if (form.classList.contains('js-comment-delete-form')) {
			const confirmed = confirm('Вы уверены, что хотите удалить этот комментарий?');
			if (!confirmed) {
				event.preventDefault();
				return false;
			}
		}

		// 3. Mark the form as active to completely throttle secondary click requests
		form.dataset.isSubmitting = 'true';

		// 4. Find the button inside the submitted form and disable it visually
		const submitBtn = form.querySelector('button[type="submit"]');
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.classList.add('btn-processing-disabled');
		}
	});
	
	
    const replyButtons = document.querySelectorAll('.comment-reply-link');
    const editButtons  = document.querySelectorAll('.comment-edit-trigger');
    
    const parentIdInput = document.getElementById('form-parent-id');
    const formTitle     = document.getElementById('comment-form-title');
    const cancelBtn     = document.getElementById('btn-cancel-reply');
    const commentForm   = document.getElementById('main-comment-form');
    const textarea      = document.getElementById('form-comment-textarea');

    // 1. ИНТЕРАКТИВНЫЙ ОТВЕТ НА КОММЕНТАРИЙ (REPLY)
    if (commentForm) {
        replyButtons.forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                
                // Закрываем открытые формы редактирования, если они есть
                document.querySelectorAll('.comment-dynamic-edit-form').forEach(f => f.remove());
                document.querySelectorAll('.comment-text').forEach(t => t.style.display = 'block');

                const commentId = this.getAttribute('href').replace('#reply-to-', '');
                const authorName = this.closest('.comment-wrapper').querySelector('.comment-meta strong').innerText;

                parentIdInput.value = commentId;
                formTitle.innerText = `Ответ пользователю ${authorName}`;
                cancelBtn.style.display = 'inline-block';

                this.closest('.comment-node').insertBefore(commentForm, this.nextSibling);
                textarea.focus();
            });
        });

        cancelBtn.addEventListener('click', function () {
            parentIdInput.value = '';
            formTitle.innerText = 'Оставить комментарий';
            cancelBtn.style.display = 'none';
            document.querySelector('.comments-section').appendChild(commentForm);
            textarea.value = '';
        });
    }

    // 2. ДИНАМИЧЕСКОЕ РЕДАКТИРОВАНИЕ КОММЕНТАРИЯ (EDIT)
    editButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            
            const commentId = this.getAttribute('data-id');
            const wrapper = this.closest('.comment-wrapper');
            const textBlock = document.getElementById(`comment-text-content-${commentId}`);
            
            // Если форма редактирования для этого коммента уже открыта — выходим
            if (wrapper.querySelector('.comment-dynamic-edit-form')) return;

            // Скрываем текущий текст
            textBlock.style.display = 'none';

            // Извлекаем чистый текст для формы
            const currentText = textBlock.innerHTML.replace(/<br\s*\/?>/mg, "\n").trim();

            // Извлекаем CSRF токен из основной формы
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;

            // Создаем динамическую форму редактирования
            const editForm = document.createElement('form');
            editForm.action = `/comments/${commentId}/edit`;
            editForm.method = 'POST';
            editForm.className = 'comment-dynamic-edit-form comment-edit-form-node';

            editForm.innerHTML = `
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <textarea name="comment_text" required class="comment-input-textarea" style="min-height:70px; margin-bottom:5px;">${currentText}</textarea>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-submit-comment" style="padding:5px 10px; font-size:12px;">Сохранить</button>
                    <button type="button" class="btn-cancel-reply-node comment-edit-cancel" style="padding:5px 10px; font-size:12px;">Отмена</button>
                </div>
            `;

            // Вставляем форму прямо под скрытый блок текста
            textBlock.parentNode.insertBefore(editForm, textBlock.nextSibling);

            // Обработка кнопки "Отмена" внутри формы редактирования
            editForm.querySelector('.comment-edit-cancel').addEventListener('click', function() {
                editForm.remove();
                textBlock.style.display = 'block';
            });
        });
    });
	
	
    // 3. SECURE CSP CONFIRMATION INTERCEPTORS
    document.addEventListener('submit', function (event) {
        // Intercept comment soft-deletion form triggers safely
        if (event.target && event.target.classList.contains('js-comment-delete-form')) {
            const confirmed = confirm('Вы уверены, что хотите удалить этот комментарий?');
            if (!confirmed) {
                event.preventDefault(); // Halt the submission process if the user cancels
            }
        }
    });
	
});

 