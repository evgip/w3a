<?php 
    $request = new \App\Core\Request(); 
?>

<div class="admin-edit-panel-card">
    <h3>📝 Корректировка параметров тега</h3>
    <p class="admin-subtitle-desc">
        Изменение настроек тега <strong># <?= e($tagItem['tag']) ?></strong>. 
        Перенос в другую категорию мгновенно перестроит его положение на странице каталога.
    </p>
    
    <form action="<?= route('admin.tags.edit.submit', ['id' => $tagItem['id']]) ?>" method="POST" class="admin-form-container">
        <?= $request->csrfField() ?>

        <div class="admin-form-group">
            <label>Имя тега (Слуг):</label>
            <input type="text" name="tag" required value="<?= e($tagItem['tag']) ?>">
            <small class="text-muted">Только латиница в нижнем регистре, без пробелов (например: php).</small>
        </div>

        <div class="admin-form-group">
            <label>Категория Lobsters:</label>
            <select name="category">
                <option value="languages" <?= $tagItem['category'] === 'languages' ? 'selected' : '' ?>>languages (Языки программирования)</option>
                <option value="practices" <?= $tagItem['category'] === 'practices' ? 'selected' : '' ?>>practices (Практики и технологии)</option>
                <option value="format" <?= $tagItem['category'] === 'format' ? 'selected' : '' ?>>format (Форматы контента)</option>
                <option value="compsci" <?= $tagItem['category'] === 'compsci' ? 'selected' : '' ?>>compsci (Компьютерные науки)</option>
                <option value="tools" <?= $tagItem['category'] === 'tools' ? 'selected' : '' ?>>tools (Инструменты разработки)</option>
                <option value="os" <?= $tagItem['category'] === 'os' ? 'selected' : '' ?>>os (Операционные системы)</option>
                <option value="platforms" <?= $tagItem['category'] === 'platforms' ? 'selected' : '' ?>>platforms (Платформы)</option>
                <option value="culture" <?= $tagItem['category'] === 'culture' ? 'selected' : '' ?>>culture (Культура и сообщество)</option>
            </select>
            <small class="text-muted">Управляет многоколоночным распределением тега на странице общего каталога.</small>
        </div>

        <div class="admin-form-group">
            <label>Описание назначения:</label>
            <input type="text" name="description" value="<?= e($tagItem['description'] ?? '') ?>" placeholder="Короткое описание темы...">
        </div>

        <!-- CLEAN CLASS-DRIVEN CHECKBOX ROW -->
        <div class="admin-form-group admin-checkbox-row">
            <input type="checkbox" name="is_media" id="chk-is-media-edit" <?= (int)$tagItem['is_media'] === 1 ? 'checked' : '' ?>>
            <label for="chk-is-media-edit">Этот тег обозначает Медиа-контент (видео, подкаст, pdf)</label>
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-admin-submit">💾 Сохранить изменения</button>
            <a href="/admin/tags" class="btn-cancel-reply-node btn-admin-cancel">Отмена</a>
        </div>
    </form>
</div>

