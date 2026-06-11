<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель | <?= htmlspecialchars($title ?? '') ?></title>
	
	<link rel="stylesheet" href="/css/admin.min.css">
	<script src="<?= \App\Core\Asset::js() ?>"></script>

</head>
<body>

    <aside class="sidebar">
        <h3><?= htmlspecialchars(\App\Core\Config::get('app.name', 'Панель')) ?></h3>
        <nav>
            <a href="/admin">📊 Главная панель</a>
            <a href="/admin/users">👥 Пользователи</a>
			<a href="/admin/tags">🏷️ Управление тегами</a>
			<a href="/admin/audit">🔒 Журнал аудита</a>
			<a href="/admin/firewall">🧱 Сетевой экран (Firewall)</a>
			<a href="/admin/tools">🛠️ Инструменты</a>
            <a href="/" target="_blank">🌐 Перейти на сайт</a>
        </nav>
    </aside>

    <div class="main-content">
        <header class="navbar">
            <div class="page-title"><strong><?= htmlspecialchars($title ?? '') ?></strong></div>
            <div class="user-meta">
                Добро пожаловать, <b><?= htmlspecialchars($_SESSION['user_name'] ?? 'Администратор') ?></b> | 
                <a href="/logout" class="gray">Выйти</a>
            </div>
        </header>

 
	<main class="container">
		
		        <!-- Success Alerts Container Notification Layout -->
        <?php if (\App\Core\Session::hasFlash('success')): ?>
            <div class="ui-alert-banner ui-alert-success">
                <strong>Успех:</strong> <?= htmlspecialchars(\App\Core\Session::getFlash('success')) ?>
            </div>
        <?php endif; ?>

        <!-- Error Alerts Container Notification Layout -->
        <?php if (\App\Core\Session::hasFlash('error')): ?>
            <div class="ui-alert-banner ui-alert-error">
                <strong>Ошибка:</strong> <?= htmlspecialchars(\App\Core\Session::getFlash('error')) ?>
            </div>
        <?php endif; ?>


		<?= $content ?>
	</main>

        <?= \App\Core\Benchmark::renderStats() ?>
    </div>

</body>
</html>
