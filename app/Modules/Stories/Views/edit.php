<h1>Редактирование публикации</h1>

<p class="hint">
    Вы можете откорректировать заголовок, описание и изменить привязанные к теме теги.
</p>

<?php if (!empty($error)): ?>
    <div class="flash-error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="/stories/<?= (int)$story['id'] ?>/edit" method="POST">
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
        <input type="url" id="story-url" name="url" 
               value="<?= e($story['url'] ?? '') ?>"
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
            
			<div class="tag">
                <input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>" 
                       <?= $isBound ? 'checked' : '' ?>>
                <span><?= e($tagItem['tag']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-field-group">
        <label for="story-description"><strong>Текст обсуждения</strong></label>
        <p class="hint">Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`</p>
        <textarea id="story-description" name="description" rows="8" 
                  placeholder="Сопроводительный текст, комментарии или дополнительный контекст..."><?= e($story['description'] ?? '') ?></textarea>
    </div>

	<div class="form-group">
		<label>
			<input type="checkbox" name="user_is_following" value="1" 
				<?= !empty($story['user_is_following']) ? 'checked' : '' ?>>
			Получать уведомления о новых комментариях к этой истории.
		</label><br>
		<small class="form-text text-muted">
			Вы будете получать уведомления о всех новых комментариях в этой истории.
		</small>
	</div>


    <div class="form-actions">
        <button type="submit">Сохранить изменения</button>
        <a href="<?= route('story.show', ['id' => $story['id']]) ?>">Отмена</a>
    </div>
</form>