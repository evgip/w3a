<!-- app/Modules/Errors/Views/errors/403.php -->
<?php $this->layout('errors/layout', compact('message', 'statusCode')) ?>
<h1>403 — Доступ запрещён</h1>
<p><?= $message ?></p>
<p><a href="/">← Вернуться на главную</a></p>