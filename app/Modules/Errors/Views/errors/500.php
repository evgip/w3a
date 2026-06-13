<!-- app/Modules/Errors/Views/errors/500.php -->
<?php $this->layout('errors/layout', compact('message', 'trace', 'statusCode')) ?>
<h1>500 — Внутренняя ошибка сервера</h1>
<p><?= $message ?></p>

<?php if ($trace): ?>
    <details>
        <summary>Детали ошибки (для разработчиков)</summary>
        <pre><?= e($trace) ?></pre>
    </details>
<?php endif; ?>

<p><a href="/">← Вернуться на главную</a></p>