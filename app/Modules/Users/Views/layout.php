<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= e($title ?? 'Лента историй') ?> | <?= e(app_name()); ?> форум</title>
	<link rel="stylesheet" href="/css/app.min.css">

	<meta name="csrf-token" content="<?= \App\Core\Security::getNonce(); ?>">
</head>

<body>

	<header>
		<div class="navbar-container">
			<a href="<?= route('home') ?>" class="navbar-logo">🌐 <?= e(app_name()); ?></a>

			<nav class="navbar-links">
				<!-- Глобальные публичные ссылки, видимые всегда -->
				<a href="<?= route('home') ?>">📋 Лента</a>
				<a href="<?= route('tags.index') ?>">🏷️ Теги</a>
				<a href="/search">🔍 Поиск</a>


<?php if (\App\Core\Auth::check()): ?>
    <?php
    $notifModel = new \App\Modules\Notifications\Models\Notification();
    $unreadCount = $notifModel->getUnreadCount((int)$_SESSION['user_id']);
    ?>
    
    
    <a href="/notifications" class="header-notification-link" id="header-notifications-link" aria-label="Уведомления">
    <!-- SVG иконка колокольчика -->
    <svg class="header-notification-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
    </svg>
    
    <!-- Бейдж с количеством -->
    <span id="header-notification-badge" class="header-notification-badge">0</span>
</a>
    
<?php endif; ?>

				<?php if (\App\Core\Auth::check()): ?>
					<div class="navbar-user-dropdown-container" id="user-dropdown-wrapper">

						<button class="dropdown-trigger-btn" id="user-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
							<div class="dropdown-avatar-badge-wrapper">
								<?php if (!empty($_SESSION['user_avatar'] ?? '')): ?>
									<img src="/uploads/avatars/<?= substr($_SESSION['user_avatar'], 0, 2) ?>/<?= e($_SESSION['user_avatar']) ?>" class="mini-avatar-img" alt="avatar">
								<?php else: ?>
									<span class="mini-avatar-placeholder"><?= e(mb_substr($_SESSION['user_name'], 0, 1)) ?></span>
								<?php endif; ?>

								<!-- Display a micro warning red dot indicator over the avatar if any unread messages exist -->
								<?php if ($unreadCount > 0): ?>
									<span class="nav-trigger-alert-dot"></span>
								<?php endif; ?>
							</div>
							<span><?= e($_SESSION['user_name']) ?></span>
							<span class="dropdown-arrow-icon">▼</span>
						</button>

						<!-- Dropdown Interactive Links List Menu Stack Box -->
						<div class="navbar-dropdown-menu" id="user-dropdown-menu">
							<a href="<?= route('story.create') ?>" class="dropdown-menu-item">➕ Поделиться</a>

							<a href="<?= route('messages.index') ?>" class="dropdown-menu-item">
								<span>✉️ Сообщения</span>
							</a>

							<a href="<?= route('account.settings') ?>" class="dropdown-menu-item">⚙️ Настройки</a>

							<?php if (\App\Core\Auth::isAdmin()): ?>
								<div class="dropdown-divider"></div>
								<a href="/admin" class="dropdown-menu-item dropdown-item-admin">📊 Админка</a>
							<?php endif; ?>

							<div class="dropdown-divider"></div>
							<a href="<?= route('auth.logout') ?>" class="dropdown-menu-item navbar-logout-link">🚪 Выйти из системы</a>
						</div>

					</div>
				<?php else: ?>
					<a href="<?= route('auth.login') ?>">Войти</a>
					<a href="<?= route('auth.register') ?>" class="btn-nav-create">Регистрация</a>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<?= render_flashes() ?>

	<main>
		<?= $content ?>
	</main>

	<footer>
		<div></div>
		<div>
			<nav>
				<a href="<?= route('home') ?>">Главная</a>
				<a href="<?= route('page.about') ?>">О проекте</a>
				<a href="<?= route('stats.index') ?>">Статистика</a>
			</nav>
			<hr>
			<?= \App\Core\Benchmark::renderStats() ?>
		</div>
	</footer>

	<script src="<?= \App\Core\Asset::js() ?>"></script>
</body>

</html>