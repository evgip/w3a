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

        <div class="admin-form-group">
            <label>Категория Lobsters:</label>
            <select name="category">
                <option value="languages">languages (Языки программирования)</option>
                <option value="practices">practices (Практики и технологии)</option>
                <option value="format">format (Форматы контента)</option>
                <option value="compsci">compsci (Компьютерные науки)</option>
                <option value="tools">tools (Инструменты разработки)</option>
                <option value="os">os (Операционные системы)</option>
                <option value="platforms">platforms (Платформы)</option>
                <option value="culture">culture (Культура и сообщество)</option>
            </select>
            <small class="text-muted">Управляет многоколоночным распределением тега на странице общего каталога.</small>
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

