<?php
$request = new \App\Core\Request();
?>

<h1>Редактирование публикации</h1>

<p class="hint">
    Вы можете откорректировать заголовок, описание и изменить привязанные к теме теги.
</p>

<?php if (!empty($error)): ?>
    <div class="flash-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form action="/stories/<?= (int)$story['id'] ?>/edit" method="POST">
    <?= $request->csrfField() ?>

    <div class="form-field-group">
        <label for="story-title"><strong>Заголовок</strong></label>
        <input type="text" id="story-title" name="title" 
               value="<?= htmlspecialchars($story['title']) ?>" 
               required placeholder="Введите заголовок публикации"
               class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label for="story-url">
            <strong>Ссылка (URL)</strong>
            <span class="form-field-hint-inline">— необязательно</span>
        </label>
        <input type="url" id="story-url" name="url" 
               value="<?= htmlspecialchars($story['url'] ?? '') ?>"
               placeholder="https://example.com/article"
               class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label><strong>Теги</strong></label>
        <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>
        
        <?php foreach ($availableTags as $tagItem): ?>
            <?php 
            $isBound = in_array((int)$tagItem['id'], $activeTagIds); 
            ?>
            <label class="tag-checkbox-item">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>" 
                       <?= $isBound ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($tagItem['tag']) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="form-field-group">
        <label for="story-description"><strong>Текст обсуждения</strong></label>
        <p class="hint">Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`</p>
        <textarea id="story-description" name="description" rows="8" 
                  placeholder="Сопроводительный текст, комментарии или дополнительный контекст..."><?= htmlspecialchars($story['description'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">Сохранить изменения</button>
        <a href="<?= route('story.show', ['id' => $story['id']]) ?>">Отмена</a>
    </div>
</form>