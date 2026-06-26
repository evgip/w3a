<h1>Настройки аккаунта</h1>

<p class="hint">
    Вы можете изменить личные контактные данные, рассказать о себе и загрузить графический аватар.
    <strong>150×150px</strong> — идеальный размер.
</p>

<form action="<?= route('account.settings.submit') ?>" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label><strong>Ваш аватар</strong></label>
        <div class="avatar-upload-container">
            <?php if (!empty($user['avatar'])): ?>
                <img src="/uploads/avatars/<?= substr($user['avatar'], 0, 2) ?>/<?= e($user['avatar']) ?>" 
                     class="avatar-preview" alt="Avatar">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?= e(mb_substr($user['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <div>
                <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/gif" class="form-input-file">
                <p class="hint">
                    Рекомендуется квадратное изображение JPG, PNG или GIF. Максимум 5 МБ.
                </p>
            </div>
        </div>
    </div>

    <div class="form-field-group">
        <label for="username"><strong>Имя пользователя</strong></label>
        <input type="text" id="username" class="form-input-wide" value="<?= e($user['username']) ?>" disabled>
        <p class="hint">
            Имя пользователя является уникальным идентификатором и не может быть изменено самостоятельно.
        </p>
    </div>

    <div class="form-field-group">
        <label for="email"><strong>Email адрес</strong></label>
        <input type="email" id="email" name="email" class="form-input-wide" value="<?= e($user['email']) ?>" required>
    </div>

    <div class="form-field-group">
        <label for="bio"><strong>О себе</strong></label>
        <textarea id="bio" name="bio" rows="4" 
                  placeholder="Расскажите немного о себе, ваших интересах или проектах..."><?= e($user['bio'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">💾 Сохранить изменения</button>
    </div>
</form>

<hr>

<!-- УПРАВЛЕНИЕ УВЕДОМЛЕНИЯМИ -->
<h2>Настройки уведомлений</h2>
<p class="hint">
    Выберите, о каких событиях вы хотите получать уведомления.
</p>

<form action="<?= route('account.settings.submit') ?>" method="POST">
    <?= csrf_field() ?>
    
    <!-- Скрываем поля email/bio/avatar, чтобы они не потерялись при отправке -->
    <input type="hidden" name="email" value="<?= e($user['email']) ?>">
    <input type="hidden" name="bio" value="<?= e($user['bio'] ?? '') ?>">
    
    <div class="notification-settings">
        
        <!-- Уведомления об ответах -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <input type="checkbox" 
                       name="notify_on_reply" 
                       value="1"
                       <?= !empty($settings['notify_on_reply']) ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления об ответах на мои комментарии</strong>
            </label>
            <p class="hint">Получать уведомления, когда кто-то отвечает на ваш комментарий</p>
        </div>
        
        <!-- Уведомления о комментариях к подписанным историям -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <input type="checkbox" 
                       name="notify_on_story_comment" 
                       value="1"
                       <?= !empty($settings['notify_on_story_comment']) ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Уведомления о комментариях к моим историям</strong>
            </label>
            <p class="hint">Получать уведомления о новых комментариях к историям, на которые вы подписаны</p>
        </div>
        
        <!-- Email-уведомления -->
        <div class="form-field-group">
            <label class="checkbox-label">
                <input type="checkbox" 
                       name="email_notifications" 
                       value="1"
                       <?= !empty($settings['email_notifications']) ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <strong>Дублировать уведомления на email</strong>
            </label>
            <p class="hint">Отправлять копии уведомлений на ваш email (<?= e($user['email']) ?>)</p>
        </div>
        
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Сохранить настройки уведомлений</button>
    </div>
</form>

<hr>

 

<!-- ИЗМЕНЕНИЕ ПАРОЛЯ -->
<h2>Изменение пароля</h2>

<p class="hint">
    Для повышения безопасности вашего профиля рекомендуется использовать сложный пароль из букв, цифр и спецсимволов.
 
 <br>
<a href="/password/recovery" class="form-field-hint-inline">
	Не помните текущий пароль?
</a>

</p>

<form action="<?= route('account.password.submit') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="current_password"><strong>Текущий пароль</strong></label>
        <input type="password" id="current_password" name="current_password" class="form-input-wide" required placeholder="Введите ваш действующий пароль">
    </div>

    <div class="form-field-group">
        <label for="new_password"><strong>Новый пароль</strong></label>
        <input type="password" id="new_password" name="new_password" class="form-input-wide" required minlength="6" placeholder="Минимум 6 символов">
    </div>

    <div class="form-field-group">
        <label for="confirm_password"><strong>Подтвердите новый пароль</strong></label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-input-wide" required minlength="6" placeholder="Повторите новый пароль">
    </div>

    <div class="form-actions">
        <button type="submit">🔑 Обновить пароль</button>
    </div>
</form>