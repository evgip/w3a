<h1>Редактирование публикации</h1>

<p class="hint">
    Вы можете откорректировать заголовок, описание и изменить привязанные к теме теги.
</p>

<?php if (!empty($error)): ?>
    <div class="flash-error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/stories/<?= (int)$story['id'] ?>/edit" method="POST" id="story-form">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="story-title"><strong>Заголовок</strong></label>
        <input type="text" id="story-title" name="title"
            value="<?= e($story['title']) ?>"
            required placeholder="Введите заголовок публикации"
            class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label for="story-url">
            <strong>Ссылка (URL)</strong>
            <span class="form-field-hint-inline">— необязательно</span>
        </label>
        <div style="display: flex; gap: 8px; align-items: center;">
            <input type="url" id="story-url" name="url"
                value="<?= e($story['url'] ?? '') ?>"
                placeholder="https://example.com/article"
                class="form-input-wide" style="flex: 1;">
            <button type="button" id="fetch-title-btn" class="btn-secondary" style="white-space: nowrap;">
                Извлечь заголовок
            </button>
        </div>
        <div id="fetch-status" style="margin-top: 5px; font-size: 0.85em; color: var(--opacity-fg-contrast-5);"></div>
    </div>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>

        <?php foreach ($availableTags as $tagItem): ?>
            <?php
            $isBound = in_array((int)$tagItem['id'], $activeTagIds);
            ?>

            <div class="tag">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>"
                    <?= $isBound ? 'checked' : '' ?>>
                <span><?= e($tagItem['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-field-group">
        <label for="story-description"><strong>Текст обсуждения</strong></label>
        <p class="hint">Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`</p>
        
        <!-- Панель инструментов Markdown -->
        <div class="markdown-toolbar">
            <button type="button" class="btn-markdown" data-action="bold" title="Жирный (Ctrl+B)">
                <strong>B</strong>
            </button>
            <button type="button" class="btn-markdown" data-action="italic" title="Курсив (Ctrl+I)">
                <em>I</em>
            </button>
            <button type="button" class="btn-markdown" data-action="code" title="Код">
                &lt;/&gt;
            </button>
            <span class="toolbar-separator"></span>
            <button type="button" class="btn-markdown" data-action="link" title="Ссылка">
                🔗
            </button>
            <button type="button" class="btn-markdown" data-action="quote" title="Цитата">
                ❝
            </button>
            <button type="button" class="btn-markdown" data-action="list" title="Список">
                ☰
            </button>
            
            <span class="toolbar-spacer"></span>
            
            <button type="button" id="preview-btn" class="btn-markdown" title="Предпросмотр (Ctrl+Enter)">
                👁 Предпросмотр
            </button>
        </div>
        
        <textarea id="story-description" name="description" rows="10"
            placeholder="Сопроводительный текст, комментарии или дополнительный контекст..."><?= e($story['description'] ?? '') ?></textarea>
        
        <!-- Область предпросмотра -->
        <div id="preview-area" class="preview-area" style="display: none;">
            <div class="preview-header">
                <strong>Предпросмотр</strong>
                <button type="button" id="close-preview" class="btn-close-preview" title="Закрыть">×</button>
            </div>
            <div id="preview-content" class="preview-content"></div>
        </div>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="user_is_following" value="1"
                <?= !empty($story['user_is_following']) ? 'checked' : '' ?>>
            Получать уведомления о новых комментариях к этой истории.
        </label><br>
        <small class="form-text text-muted hint">
            Вы будете получать уведомления о всех новых комментариях в этой истории.
        </small>
    </div>

    <div class="form-actions">
        <button type="submit">Сохранить изменения</button>
        <a href="<?= route('story.show', ['id' => $story['id']]) ?>">Отмена</a>
    </div>
</form>

<script nonce="<?= csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('story-url');
    const titleInput = document.getElementById('story-title');
    const fetchBtn = document.getElementById('fetch-title-btn');
    const statusDiv = document.getElementById('fetch-status');
    const form = document.getElementById('story-form');
    const descriptionInput = document.getElementById('story-description');
    const previewBtn = document.getElementById('preview-btn');
    const previewArea = document.getElementById('preview-area');
    const previewContent = document.getElementById('preview-content');
    const closePreview = document.getElementById('close-preview');

    // ==================== УТИЛИТЫ ====================
    
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        
        const input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        
        return '';
    }

    function setStatus(message, type) {
        statusDiv.textContent = message;
        if (type === 'success') {
            statusDiv.style.color = 'var(--color-fg-affirmative)';
        } else if (type === 'error') {
            statusDiv.style.color = 'var(--color-fg-negative)';
        } else {
            statusDiv.style.color = 'var(--opacity-fg-contrast-5)';
        }
    }

    // ==================== ИЗВЛЕЧЕНИЕ ЗАГОЛОВКА ====================
    
    function fetchTitle() {
        const url = urlInput.value.trim();
        
        if (!url) {
            setStatus('Введите URL', 'error');
            return;
        }

        try {
            new URL(url);
        } catch (e) {
            setStatus('Некорректный URL', 'error');
            return;
        }

        const csrfToken = getCsrfToken();
        
        if (!csrfToken) {
            setStatus('CSRF токен не найден. Обновите страницу.', 'error');
            return;
        }

        fetchBtn.disabled = true;
        fetchBtn.textContent = 'Загрузка...';
        setStatus('Извлекаем заголовок...', 'loading');

        fetch('/stories/fetch-url-title', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'url=' + encodeURIComponent(url) + '&csrf_token=' + encodeURIComponent(csrfToken),
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.status === 419) {
                throw new Error('Сессия истекла. Обновите страницу.');
            }
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                setStatus(data.error, 'error');
                return;
            }

            if (data.title) {
                titleInput.value = data.title;
                setStatus('✓ Заголовок извлечен', 'success');
                
                if (data.url && data.url !== url) {
                    urlInput.value = data.url;
                }
            } else {
                setStatus('Не удалось извлечь заголовок', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            setStatus('Ошибка: ' + error.message, 'error');
        })
        .finally(() => {
            fetchBtn.disabled = false;
            fetchBtn.textContent = 'Извлечь заголовок';
        });
    }

    fetchBtn.addEventListener('click', fetchTitle);

    urlInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            fetchTitle();
        }
    });

    // ==================== ПРЕДПРОСМОТР MARKDOWN ====================
    
    function showPreview() {
        const text = descriptionInput.value.trim();
        
        if (!text) {
            previewArea.style.display = 'none';
            return;
        }

        const csrfToken = getCsrfToken();
        
        if (!csrfToken) {
            alert('CSRF токен не найден. Обновите страницу.');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.textContent = '⏳ Загрузка...';

        fetch('/stories/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'text=' + encodeURIComponent(text) + '&csrf_token=' + encodeURIComponent(csrfToken) + '&allow_images=1',
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.status === 419) {
                throw new Error('Сессия истекла. Обновите страницу.');
            }
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert('Ошибка: ' + data.error);
                return;
            }

            if (data.html) {
                previewContent.innerHTML = data.html;
                previewArea.style.display = 'block';
                previewArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при предпросмотре: ' + error.message);
        })
        .finally(() => {
            previewBtn.disabled = false;
            previewBtn.textContent = '👁 Предпросмотр';
        });
    }

    previewBtn.addEventListener('click', showPreview);

    closePreview.addEventListener('click', function() {
        previewArea.style.display = 'none';
    });

    descriptionInput.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            showPreview();
        }
    });

    // ==================== ПАНЕЛЬ ИНСТРУМЕНТОВ MARKDOWN ====================
    
    function insertMarkdown(action) {
        const textarea = descriptionInput;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        let replacement = '';
        let cursorOffset = 0;

        switch(action) {
            case 'bold':
                replacement = `**${selectedText || 'жирный текст'}**`;
                cursorOffset = selectedText ? replacement.length : 2;
                break;
            case 'italic':
                replacement = `_${selectedText || 'курсив'}_`;
                cursorOffset = selectedText ? replacement.length : 1;
                break;
            case 'code':
                if (selectedText.includes('\n')) {
                    replacement = `\n\`\`\`\n${selectedText}\n\`\`\`\n`;
                } else {
                    replacement = `\`${selectedText || 'код'}\``;
                }
                cursorOffset = selectedText ? replacement.length : 1;
                break;
            case 'link':
                const url = prompt('Введите URL:', 'https://');
                if (url) {
                    const linkText = selectedText || 'ссылка';
                    replacement = `[${linkText}](${url})`;
                    cursorOffset = replacement.length;
                }
                break;
            case 'quote':
                if (selectedText) {
                    replacement = selectedText.split('\n').map(line => `> ${line}`).join('\n');
                } else {
                    replacement = `\n> цитата\n`;
                }
                cursorOffset = replacement.length;
                break;
            case 'list':
                if (selectedText) {
                    const lines = selectedText.split('\n');
                    replacement = lines.map(line => `- ${line}`).join('\n');
                } else {
                    replacement = `\n- элемент списка\n`;
                }
                cursorOffset = replacement.length;
                break;
        }

        if (replacement) {
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            textarea.focus();
            const newPos = start + cursorOffset;
            textarea.selectionStart = newPos;
            textarea.selectionEnd = newPos;
        }
    }

    document.querySelectorAll('.btn-markdown[data-action]').forEach(btn => {
        btn.addEventListener('click', function() {
            insertMarkdown(this.dataset.action);
        });
    });

    // Горячие клавиши
    descriptionInput.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'b') {
                e.preventDefault();
                insertMarkdown('bold');
            } else if (e.key === 'i') {
                e.preventDefault();
                insertMarkdown('italic');
            }
        }
    });
});
</script>