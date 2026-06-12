<?php $config = require dirname(__DIR__, 3) . '/Config/config.php'; ?>
<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? 'Лента историй') ?> | <?= htmlspecialchars($config['app']['name']); ?> форум</title>
	<link rel="stylesheet" href="/css/app.min.css">
</head>

<body>

	<header>
		<div class="navbar-container">
			<a href="<?= route('home') ?>" class="navbar-logo">🌐 <?= htmlspecialchars($config['app']['name']); ?></a>

			<nav class="navbar-links">
				<!-- Глобальные публичные ссылки, видимые всегда -->
				<a href="<?= route('home') ?>">📋 Лента</a>
				<a href="<?= route('tags.index') ?>">🏷️ Теги</a>
				<a href="/search">🔍 Поиск</a>

				<?php if (\App\Core\Auth::check()): ?>
					<?php
					// Instantiating the model on the fly to count unread indicators
					$convModel = new \App\Modules\Messages\Models\Conversation();
					$unreadCount = $convModel->getUnreadCount((int)$_SESSION['user_id']);
					?>
					<div class="navbar-user-dropdown-container" id="user-dropdown-wrapper">

						<button class="dropdown-trigger-btn" id="user-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
							<div class="dropdown-avatar-badge-wrapper">
								<?php if (!empty($_SESSION['user_avatar'] ?? '')): ?>
									<img src="/uploads/avatars/<?= substr($_SESSION['user_avatar'], 0, 2) ?>/<?= htmlspecialchars($_SESSION['user_avatar']) ?>" class="mini-avatar-img" alt="avatar">
								<?php else: ?>
									<span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($_SESSION['user_name'], 0, 1)) ?></span>
								<?php endif; ?>

								<!-- Display a micro warning red dot indicator over the avatar if any unread messages exist -->
								<?php if ($unreadCount > 0): ?>
									<span class="nav-trigger-alert-dot"></span>
								<?php endif; ?>
							</div>
							<span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
							<span class="dropdown-arrow-icon">▼</span>
						</button>

						<!-- Dropdown Interactive Links List Menu Stack Box -->
						<div class="navbar-dropdown-menu" id="user-dropdown-menu">
							<a href="<?= route('story.create') ?>" class="dropdown-menu-item">➕ Поделиться</a>

							<a href="<?= route('messages.index') ?>" class="dropdown-menu-item">
								<span>✉️ Сообщения</span>
								<!-- Append a red numerical bubble counter inside the dropdown item layout -->
								<?php if ($unreadCount > 0): ?>
									<span class="nav-badge-counter"><?= $unreadCount ?></span>
								<?php endif; ?>


								<?php
								// Initial page-load count optimization to prevent interface flickers
								$initialUnreadCount = (new \App\Modules\Messages\Models\Message())->getUnreadCount((int)$_SESSION['user_id']);
								?>
								<span id="unread-messages-badge" class="nav-notification-badge <?= $initialUnreadCount === 0 ? 'badge-hidden' : '' ?>">
									<?= $initialUnreadCount ?>
								</span>
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

	<?php if (\App\Core\Session::hasFlash('success')): ?>
		<div class="alert alert-success">
			<strong>Успех!</strong> <?= htmlspecialchars(\App\Core\Session::getFlash('success')) ?>
		</div>
	<?php endif; ?>
	<?php if (\App\Core\Session::hasFlash('error')): ?>
		<div class="alert alert-danger">
			<strong>Ошибка!</strong> <?= htmlspecialchars(\App\Core\Session::getFlash('error')) ?>
		</div>
	<?php endif; ?>

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
			<div>
	</footer>

	<script src="<?= \App\Core\Asset::js() ?>"></script>
</body>

</html>