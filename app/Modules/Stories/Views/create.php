<?php $request = new \App\Core\Request(); ?>

<div class="submit-form">
    <h3 style="margin-top: 0; color: #2c3e50; margin-bottom: 5px;">Поделиться историей или ссылкой</h3>
    <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 25px;">Отправьте интересную ссылку в духе Lobsters/HackerNews или начните текстовое обсуждение.</p>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="color: #e74c3c; background: #fdf2f2; padding: 12px; border: 1px solid #f8b4b4; margin-bottom: 20px; border-radius: 4px; font-size: 14px;">
            <strong>Ошибка:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="/stories/create" method="POST">
        <?= $request->csrfField() ?>

        <div class="form-group-field">
            <label>Заголовок публикации:</label>
            <input type="text" name="title" required placeholder="О чем ссылка или обсуждение?" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-group-field">
            <label>Ссылка (URL):</label>
            <input type="url" name="url" placeholder="https://example.com" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
            <span class="form-field-hint">Заполните, если хотите отправить внешнюю ссылку на материал.</span>
        </div>

		<div class="form-group-field">
			<label>Выберите соответствующие теги (темы):</label>
			<div class="checkbox-matrix-grid">
				<?php foreach ($tags as $tagItem): ?>
					<label class="checkbox-item-wrapper">
						<input type="checkbox" name="tags[]" value="<?= (int)$tagItem['id'] ?>">
						<span class="tag-badge-link tag-checkbox-badge">
							<?= htmlspecialchars($tagItem['tag']) ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

        <div class="form-group-field">
            <label>Текст обсуждения:</label>
            <textarea name="description" placeholder="Ваш сопроводительный текст или заметка для обсуждения..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            <span class="form-field-hint">Заполните, если это чисто текстовый пост, либо как дополнение к ссылке.</span>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 14px; width: auto; cursor: pointer;">
            🚀 Опубликовать на главную
        </button>
    </form>
</div>
