<?php 
    // Instantiate core utilities once for cryptographic token validation pairs
    $request = new \App\Core\Request(); 
?>

<div class="container">

<!-- Reusing existing common architecture classes from the Users/Common modules -->
<div class="panel-card auth-recovery-container">
    <h3>🔑 Вход в систему</h3>
    <p class="field-sub-hint">Пожалуйста, укажите ваши данные для авторизации.</p>

    <!-- Reusing your existing decoupled error components -->
    <?php if (!empty($error)): ?>
        <div class="notif-alert-card notif-unread-card notif-type-danger">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="/login" method="POST" class="auth-form">
        <?= $request->csrfField() ?>

        <div class="form-group-field">
            <label>Email:</label>
            <input type="email" name="email" required placeholder="name@example.com" class="form-field">
        </div>

        <div class="form-group-field">
            <label>Пароль:</label>
            <input type="password" name="password" required placeholder="Введите ваш пароль" class="form-field">
        </div>

        <!-- Using the full width submission utility class established in previous recovery steps -->
        <button type="submit" class="btn btn-primary submit-recovery">
            Войти
        </button>
    </form>
</div>
</div>