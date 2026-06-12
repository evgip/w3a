<div class="submit-form auth-recovery-container">
    <h3>🔑 Восстановление доступа</h3>
    <p class="field-sub-hint">Укажите ваш Email, зарегистрированный в системе. Мы вышлем вам временную защищенную ссылку для сброса пароля.</p>

    <form action="<?= route('password.request.submit') ?>" method="POST" class="auth-form">
        <?= $request->csrfField() ?>

        <div class="form-group form-group-field-spacing">
            <label>Ваш Email адрес:</label>
            <input type="email" name="email" required placeholder="name@example.com" class="form-input-text">
        </div>

        <div class="form-group captcha-form-row">
            <?= \App\Core\Captcha::render() ?>
        </div>

        <button type="submit" class="btn btn-primary btn-submit-recovery">
            📨 Выслать секретную ссылку
        </button>
    </form>
</div>