<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/css/app.min.css">
</head>
<body>
    <main>
	  <div class="card">
        <h1>⚠️ Ошибка</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <hr>
        <a href="/">Вернуться на главную страницу</a>
		</div>
    </main>
</body>
</html>
