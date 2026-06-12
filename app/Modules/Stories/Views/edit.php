<div class="submit-form">
    <h3 style="margin-top: 0; color: #2c3e50; margin-bottom: 5px;">Редактирование публикации</h3>
    <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 25px;">Вы можете откорректировать заголовок, описание и изменить привязанные к теме теги.</p>

    <form action="/stories/<?= (int)$story['id'] ?>/edit" method="POST">
        <?= $request->csrfField() ?>

        <div class="form-group-field">
            <label>Заголовок публикации:</label>
            <input type="text" name="title" required value="<?= htmlspecialchars($story['title']) ?>">
        </div>

        <div class="form-group-field">
            <label>Ссылка (URL):</label>
            <input type="url" name="url" value="<?= htmlspecialchars($story['url'] ?? '') ?>">
        </div>

		<div class="form-group-field">
			<label>Выберите соответствующие теги (темы):</label>
			<div class="checkbox-matrix-grid">
				<?php foreach ($availableTags as $tagItem): ?>
					<?php $isBound = in_array((int)$tagItem['id'], $activeTagIds); ?>
					<label class="checkbox-item-wrapper">
						<input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>" <?= $isBound ? 'checked' : '' ?>>
						<span class="tag-badge-link tag-checkbox-badge">
							<?= htmlspecialchars($tagItem['tag']) ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

        <div class="form-group-field">
            <label>Текст обсуждения:</label>
            <textarea name="description" placeholder="Сопроводительный текст..."><?= htmlspecialchars($story['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 14px; width: auto; cursor: pointer;">
            💾 Сохранить изменения
        </button>
    </form>
</div>
