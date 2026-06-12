<?php $request = new \App\Core\Request(); ?>


<div class="panel-card auth-recovery-container">


    <h2>Регистрация аккаунта</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Clean, class-based layout instead of inline styling -->
    <form action="/register" method="POST" class="auth-form">
        <?= $request->csrfField() ?>

        <div class="form-group-field">
            <label>Имя пользователя:</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group-field">
            <label>Email адрес:</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group-field">
            <label>Пароль (минимум 6 символов):</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group captcha-form-row">
            <?= \App\Core\Captcha::render() ?>
        </div>

        <button type="submit" class="btn btn-primary">
            Зарегистрироваться
        </button>
    </form>

    <p class="auth-footer">
        Уже есть аккаунт? <a href="/login">Войти</a>
    </p>
</div>