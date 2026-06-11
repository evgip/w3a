<!-- app/Modules/Errors/Views/errors/404.php -->
<?php $this->layout('errors/layout', compact('uri', 'statusCode')) ?>
<h1>404 — Страница не найдена</h1>
<p>Запрошенный путь <strong><?= $uri ?></strong> не существует.</p>
<p><a href="/">← Вернуться на главную</a></p>