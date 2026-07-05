<h1>Создание публикации</h1>

<p class="hint">
Поделитесь интересной ссылкой или начните обсуждение с сообществом.
</p>

<?php if (!empty($error)): ?>
<div class="flash-error">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form action="/stories/create" method="POST" id="story-form">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="story-title"><strong>Заголовок</strong></label>
        <input type="text" id="story-title" name="title"
               value="<?= e($old['title'] ?? '') ?>"
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
                   value="<?= e($old['url'] ?? '') ?>"
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
            $isBound = isset($old['tags']) && in_array((int)$tagItem['id'], $old['tags']);
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
            'value' => $old['description'] ?? '',
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
        <button type="submit">Опубликовать</button>
        <a href="/">Отмена</a>
    </div>
</form>

<script nonce="<?= csp_nonce(); ?>">
// ... (код для извлечения заголовка остаётся здесь) ...
</script>