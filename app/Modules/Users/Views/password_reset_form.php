<div class="submit-form auth-recovery-container">
    <h3>🔒 Новый пароль аккаунта</h3>
    <p class="field-sub-hint">Введите надежный пароль из букв и цифр длиной не менее 6 символов.</p>
    
    <form action="<?= route('password.reset.submit') ?>" method="POST" class="auth-form">
        <?= $request->csrfField() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group form-group-field-spacing">
            <label>Придумайте новый пароль:</label>
            <input type="password" name="password" required placeholder="Минимум 6 знаков" class="form-input-text">
        </div>

        <div class="form-group form-group-textarea-spacing">
            <label>Повторите новый пароль еще раз:</label>
            <input type="password" name="confirm_password" required placeholder="Подтверждение" class="form-input-text">
        </div>

        <button type="submit" class="btn btn-primary btn-submit-save-password">
            💾 Сохранить новый пароль
        </button>
    </form>
</div>

