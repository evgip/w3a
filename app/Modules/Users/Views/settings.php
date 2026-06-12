<?php $request = new \App\Core\Request(); ?>

<div class="submit-form container">

    <!-- NEW SYSTEM NOTIFICATIONS PRESENTATION LAYER BLOCK -->
    <div class="notif-section-wrapper">
        <div class="notif-header-row">
            <h4 class="notif-header-title">🔔 Центр системных уведомлений</h4>
            <?php
            $hasUnreadNotif = false;
            if (!empty($notifications)) {
                foreach ($notifications as $n) {
                    if ((int)$n['is_read'] === 0) {
                        $hasUnreadNotif = true;
                        break;
                    }
                }
            }
            ?>
            <?php if ($hasUnreadNotif): ?>
                <form action="<?= route('account.notifications.read') ?>" method="POST" style="margin:0;">
                    <?= $request->csrfField() ?>
                    <button type="submit" class="tag-badge-link" style="border:none; cursor:pointer; background:#cbd5e1; color:#334155!important; padding:4px 10px;">
                        ✓ Прочитать все
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notif-stream-list">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $item): ?>
                    <?php
                    $unreadClass = ((int)$item['is_read'] === 0) ? 'notif-unread-card' : '';
                    $severityClass = 'notif-type-' . htmlspecialchars($item['type']);
                    ?>
                    <div class="notif-alert-card <?= $unreadClass ?> <?= $severityClass ?>">
                        <span><?= htmlspecialchars($item['message']) ?></span>
                        <span class="notif-time-stamp"><?= htmlspecialchars(date('d.m H:i', strtotime($item['created_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="comment-empty-text notif-empty-text">У вас нет системных уведомлений. Здесь будут появляться важные сообщения о безопасности вашего аккаунта.</p>
            <?php endif; ?>
        </div>
    </div>

    <h3>🛠️ Настройки моего профиля</h3>
    <p class="field-sub-hint">Вы можете изменить личные контактные данные, рассказать о себе и загрузить графический аватар. 150 на 150px идеальный размер.</p>

    <form action="<?= route('account.settings.submit') ?>" method="POST" enctype="multipart/form-data" class="auth-form form-input-text">
        <?= $request->csrfField() ?>

        <!-- БЛОК ЗАГРУЗКИ АВАТАРА -->
        <div class="form-group-field">
            <label>Ваш текущий аватар:</label>
            <div class="profile-avatar-row-layout">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($user['avatar'], 0, 2) ?>/<?= htmlspecialchars($user['avatar']) ?>" class="profile-avatar-render-img" alt="Avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder form-avatar-wrapper">
                        <?= htmlspecialchars(mb_substr($user['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/gif">
                    <br>
                    <small>Рекомендуется квадратное изображение JPG, PNG или GIF. Максимум 5 МБ.</small>
                </div>
            </div>
        </div>

        <!-- БЛОК ЛОГИНА (ЗАБЛОКИРОВАН ДЛЯ РЕДАКТИРОВАНИЯ) -->
        <div class="form-group-field">
            <label>Имя пользователя (Логин):</label>
            <input type="text" disabled value="<?= htmlspecialchars($user['name']) ?>"><br>
            <small class="">Имя пользователя является уникальным идентификатором и не может быть изменено самостоятельно.</small>
        </div>

        <!-- БЛОК EMAIL -->
        <div class="form-group-field">
            <label>Email адрес:</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <!-- БЛОК БИОГРАФИИ -->
        <div class="form-group-field">
            <label>О себе (Био):</label>
            <textarea name="bio" placeholder="Расскажите немного о себе, ваших интересах или проектах..." class="comment-input-textarea form-textarea-bio"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <!-- КНОПКА ОТПРАВКИ -->
        <button type="submit" class="btn btn-success">
            💾 Сохранить изменения
        </button>
    </form>

    <div class="settings-section-divider">
        <h3 class="settings-section-title">🔒 Изменение пароля аккаунта</h3>
        <p class="field-sub-hint">Для повышения безопасности вашего профиля рекомендуется использовать сложный пароль из букв, цифр и спецсимволов.</p>

        <form action="<?= route('account.password.submit') ?>" method="POST" class="auth-form form-input-text">
            <?= $request->csrfField() ?>

            <div class="form-group-field">
                <label>Текущий пароль:</label>
                <input type="password" name="current_password" required placeholder="Введите ваш действующий пароль">
            </div>

            <div class="form-group-field">
                <label>Новый пароль (минимум 6 символов):</label>
                <input type="password" name="new_password" required placeholder="Придумайте надежный пароль">
            </div>

            <div class="form-group-field">
                <label>Подтвердите новый пароль:</label>
                <input type="password" name="confirm_password" required placeholder="Повторите новый пароль еще раз">
            </div>

            <button type="submit" class="btn btn-success">
                🔑 Обновить пароль
            </button>
        </form>
    </div>


</div>