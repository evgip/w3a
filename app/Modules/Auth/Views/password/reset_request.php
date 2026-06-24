<h1>Восстановление пароля</h1>

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

<p class="hint">Введите email, указанный при регистрации. Мы отправим ссылку для восстановления пароля.</p>

<form action="<?= route('password.request.submit') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-field-group">
        <label for="reset-email"><strong>Email</strong></label>
        <input 
            type="email" 
            id="reset-email" 
            name="email" 
            required 
            autofocus 
            class="form-input-wide" 
            placeholder="name@example.com"
        >
        <small class="form-field-hint">На этот email будет отправлена ссылка для сброса пароля</small>
    </div>

	<div class="form-input-wide">
			<?= \App\Modules\Captcha\Core\Captcha::getHtml() ?>
	</div>

    <div class="form-actions">
        <button type="submit">Отправить ссылку</button>
    </div>
</form>

<hr>

<p>
    Вспомнили пароль? <a href="<?= route('auth.login') ?>">Вернуться ко входу</a>
</p>