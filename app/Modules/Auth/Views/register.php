<h1>Регистрация</h1>

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

<p class="hint">Создайте аккаунт, чтобы присоединиться к сообществу.</p>

<form action="/register" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="register-username"><strong>Имя пользователя</strong></label>
        <input 
            type="text" 
            id="register-username" 
            name="username" 
            required 
            autofocus 
            minlength="3" 
            maxlength="20" 
            pattern="[a-zA-Z0-9_]+" 
            class="form-input-wide" 
            placeholder="Например: john_doe"
            value="<?= e($old['username'] ?? '') ?>"
        >
        <p class="hint">Только латинские буквы, цифры и символ подчёркивания (3-20 символов).</p>
    </div>

    <div class="form-field-group">
        <label for="register-email"><strong>Email</strong></label>
        <input 
            type="email" 
            id="register-email" 
            name="email" 
            required 
            class="form-input-wide" 
            placeholder="name@example.com"
            value="<?= e($old['email'] ?? '') ?>"
        >
    </div>

    <div class="form-field-group">
        <label for="register-password"><strong>Пароль</strong></label>
        <input 
            type="password" 
            id="register-password" 
            name="password" 
            required 
            minlength="6" 
            class="form-input-wide" 
            placeholder="Минимум 6 символов"
        >
        <p class="hint">Используйте буквы, цифры и специальные символы для надёжности.</p>
    </div>

    <div class="form-field-group">
        <label for="register-password-confirm"><strong>Подтверждение пароля</strong></label>
        <input 
            type="password" 
            id="register-password-confirm" 
            name="password_confirmation" 
            required 
            minlength="6" 
            class="form-input-wide" 
            placeholder="Повторите пароль"
        >
    </div>
<div class="form-input-wide">
		<?= \App\Modules\Captcha\Core\Captcha::getHtml() ?>
</div>
    <div class="form-actions">
        <button type="submit">Зарегистрироваться</button>
    </div>
</form>

<hr>

<p>
    Уже есть аккаунт? <a href="/login">Войти</a>
</p>
 

  