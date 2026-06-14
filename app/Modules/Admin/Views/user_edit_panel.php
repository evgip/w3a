<div class="admin-edit-panel-card">
    <h3>🔧 Панель модерации пользователя # <?= e($userItem['username']) ?></h3>
    
    <!-- AVATAR MODERATION INTERACTION CONTROL CARD -->
    <div class="admin-avatar-moderation-row">
        <div class="admin-avatar-meta-group">
            <?php if (!empty($userItem['avatar'])): ?>
			
				<img src="/uploads/avatars/<?= substr($user['avatar'], 0, 2) ?>/<?= e($user['avatar']) ?>" class="profile-avatar-render-img" alt="Avatar">
				
                <div class="admin-avatar-text-block">
                    <strong>Пользовательский аватар установлен</strong>
                    <small>Файл: <?= e($userItem['avatar']) ?></small>
                </div>
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?= e(mb_substr($userItem['username'], 0, 1)) ?>
                </div>
                <div class="admin-avatar-text-block">
                    <strong>Стандартный аватар-заглушка</strong>
                    <small>Пользователь еще не загрузил личную фотографию.</small>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($userItem['avatar'])): ?>
            <form action="<?= route('admin.users.avatar.delete', ['id' => $userItem['id']]) ?>" method="POST" class="js-comment-delete-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn-action btn-action-danger">
                    🗑️ Удалить аватар
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- FIELD METADATA RE-PERSISTENCE FORM OVERRIDES -->
    <form action="<?= route('admin.users.edit.submit', ['id' => $userItem['id']]) ?>" method="POST" class="admin-form-container">
        <?= csrf_field() ?>

        <div class="admin-form-group">
            <label>Email адрес пользователя:</label>
            <input type="email" name="email" required value="<?= e($userItem['email']) ?>">
        </div>

        <div class="admin-form-group">
            <label>Уровень прав доступа (Роль):</label>
            <select name="role">
                <option value="user" <?= $userItem['role'] === 'user' ? 'selected' : '' ?>>user (Обычный пользователь)</option>
                <option value="admin" <?= $userItem['role'] === 'admin' ? 'selected' : '' ?>>admin (Администратор системы)</option>
            </select>
        </div>

        <div class="admin-form-group">
            <label>Биография пользователя (О себе):</label>
            <textarea name="bio"><?= e($userItem['bio'] ?? '') ?></textarea>
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
            <a href="/admin/users" class="btn-cancel-reply-node">Назад к списку</a>
        </div>
    </form>
</div>
