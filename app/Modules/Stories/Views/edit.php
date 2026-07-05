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

    <?php
    // Подключаем универсальный Markdown-редактор через partial()
    partial('Common::_markdown_editor', [
        'editor' => [
            'name' => 'description',
            'value' => $story['description'] ?? '',
            'placeholder' => 'Сопроводительный текст, комментарии или дополнительный контекст...',
            'rows' => 10,
            'textarea_id' => 'story-description',
            'preview_url' => '/stories/preview',
            'allow_images' => true,
            'label' => 'Текст обсуждения',
            'hint' => 'Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`',
        ]
    ]);
    ?>

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
});
</script>