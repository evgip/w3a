<h1>Создание публикации</h1>

<p class="hint">
    Поделитесь интересной ссылкой или начните обсуждение с сообществом.
</p>

<?php if (!empty($error)): ?>
    <div class="flash-error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/stories/create" method="POST">
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
        <input type="url" id="story-url" name="url" 
               value="<?= e($old['url'] ?? '') ?>"
               placeholder="https://example.com/article"
               class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>
	
        <?php foreach ($availableTags as $tagItem): ?>
            <?php 
            $isBound = isset($old['tags']) && in_array((int)$tagItem['id'], $old['tags']); 
            ?>
            <label class="tag-checkbox-item">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>" 
                       <?= $isBound ? 'checked' : '' ?>>
                <span><?= e($tagItem['tag']) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="form-field-group">
        <label for="story-description"><strong>Текст обсуждения</strong></label>
        <p class="hint">Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`</p>
        <textarea id="story-description" name="description" rows="8" 
                  placeholder="Сопроводительный текст, комментарии или дополнительный контекст..."><?= e($old['description'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">Опубликовать</button>
        <a href="/">Отмена</a>
    </div>
</form>