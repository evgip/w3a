<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { text-align: center; padding: 50px; font-family: sans-serif; background: #f7f9fa; color: #333; }
        .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: #e74c3c; font-size: 48px; margin-bottom: 10px; }
        a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>⚠️ Ошибка</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <a href="/">Вернуться на главную страницу</a>
    </div>
</body>
</html>
