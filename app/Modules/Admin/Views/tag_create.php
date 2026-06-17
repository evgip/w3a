<?php
// Получаем список категорий
$categoryModel = new App\Modules\Tags\Models\Category();
$categoriesList = $categoryModel->getAllOrdered();
?>
<div class="admin-edit-panel-card">
    <h3>✨ Добавление новой темы/тега</h3>
    <p class="admin-subtitle-desc">Создание нового тега для классификации обсуждений. Укажите имя тега в нижнем регистре и выберите подходящую категорию.</p>
    
    <form action="<?= route('admin.tags.create.submit') ?>" method="POST" class="admin-form-container">
        <?= csrf_field() ?>

        <div class="admin-form-group">
            <label>Имя тега (Слуг):</label>
            <input type="text" name="tag" required placeholder="например: laravel">
            <small class="text-muted">Только латиница в нижнем регистре, без пробелов.</small>
        </div>

		<div class="form-group">
			<label for="category_id">Категория:</label>
			<select id="category_id" name="category_id" required>
				<option value="">— Выберите категорию —</option>
				<?php foreach ($categoriesList as $cat): ?>
					<option value="<?= (int)$cat['id'] ?>" 
							<?= ($request->getParams('category_id', $tagItem['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
						<?= e($cat['name']) ?> (<?= e($cat['slug']) ?>)
					</option>
				<?php endforeach; ?>
			</select>
			<small>Определяет колонку на странице общего каталога тегов.</small>
		</div>

        <div class="admin-form-group">
            <label>Описание назначения:</label>
            <input type="text" name="description" placeholder="например: Обсуждение экосистемы фреймворка Laravel">
        </div>

        <!-- CLEAN CLASS-DRIVEN CHECKBOX ROW -->
        <div class="admin-form-group admin-checkbox-row">
            <input type="checkbox" name="is_media" id="chk-is-media">
            <label Security-policy-label-target for="chk-is-media">Этот тег обозначает Медиа-контент (видео, подкаст, pdf)</label>
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-admin-submit">💾 Создать тег</button>
            <a href="/admin/tags" class="btn-cancel-reply-node btn-admin-cancel">Отмена</a>
        </div>
    </form>
</div>

