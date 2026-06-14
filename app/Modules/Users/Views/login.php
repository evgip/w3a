<h1>Вход в систему</h1>

<?php if (\App\Core\Session::hasFlash('error')): ?>
    <div class="flash-error">
        <?= e(\App\Core\Session::getFlash('error')) ?>
    </div>
<?php endif; ?>

<?php if (\App\Core\Session::hasFlash('success')): ?>
    <div class="flash-success">
        <?= e(\App\Core\Session::getFlash('success')) ?>
    </div>
<?php endif; ?>

<p class="hint">Пожалуйста, укажите ваши данные для авторизации.</p>

<form action="/login" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="login-email"><strong>Email</strong></label>
        <input type="email" id="login-email" name="email" required autofocus class="form-input-wide" placeholder="name@example.com">
    </div>

    <div class="form-field-group">
        <label for="login-password">
            <strong>Пароль</strong>
            <a href="/password/recovery" class="form-field-hint-inline">(забыли пароль?)</a>
        </label>
        <input type="password" id="login-password" name="password" required class="form-input-wide">
    </div>

    <div class="form-field-group">
        <label>
            <input type="checkbox" name="remember" value="1">
            Запомнить меня на этом компьютере
        </label>
    </div>

    <div class="form-actions">
        <button type="submit">Войти</button>
    </div>
</form>

<hr>

<p>
    Нет аккаунта? <a href="/register">Зарегистрироваться</a>
</p>