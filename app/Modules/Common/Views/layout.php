<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($data['csrf_token'] ?? '') ?>">
	<title><?= e($title ?? 'Лента историй') ?> | <?= e(app_name()); ?> форум</title>
	<link rel="stylesheet" href="/css/app.min.css">
</head>

<body>

	<header>
		<div class="navbar-container">
			<a href="<?= route('home') ?>" class="navbar-logo">🌐 <?= e(app_name()); ?></a>

			<nav class="navbar-links">
				<a href="<?= route('home') ?>">📋 Лента</a>
				<a href="<?= route('tags.index') ?>">🏷️ Теги</a>
				<a href="/search">🔍 Поиск</a>

				<?php if (\App\Modules\Auth\Services\Auth::check()): ?>
					<?php
					$notifModel = new \App\Modules\Notifications\Models\Notification();
					$unreadCount = $notifModel->getUnreadCount(\App\Modules\Auth\Services\Auth::id());
					?>

					<a href="/notifications" class="header-notification-link" id="header-notifications-link" aria-label="Уведомления">

					<svg class="header-notification-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
						<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
					</svg>
					
					<span id="header-notification-badge" class="header-notification-badge">0</span>
				</a>
					
				<?php
				if (\App\Modules\Auth\Services\Auth::isAdmin() || \App\Modules\Auth\Services\Auth::isModerator()):
					$pendingFlagsCount = (new \App\Modules\Flags\Models\Flag())->getPendingCount();
				?>
					<a href="/admin/flags" class="nav-flag">
						🚩 
						<?php if ($pendingFlagsCount > 0): ?>
							<span class="badge">(<?= $pendingFlagsCount ?>)</span>
						<?php endif; ?>
					</a>
				<?php endif; ?>


					<div class="navbar-user-dropdown-container" id="user-dropdown-wrapper">

						<button class="dropdown-trigger-btn" id="user-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
							<div class="dropdown-avatar-badge-wrapper">
								<?php if (!empty($_SESSION['user_avatar'] ?? '')): ?>
									<img src="/uploads/avatars/<?= substr($_SESSION['user_avatar'], 0, 2) ?>/<?= e($_SESSION['user_avatar']) ?>" class="mini-avatar-img" alt="avatar">
								<?php else: ?>
									<span class="mini-avatar-placeholder"><?= e(mb_substr($_SESSION['user_name'], 0, 1)) ?></span>
								<?php endif; ?>

								<?php if ($unreadCount > 0): ?>
									<span class="nav-trigger-alert-dot"></span>
								<?php endif; ?>
							</div>
							<span><?= e($_SESSION['user_name']) ?></span>
							<span class="dropdown-arrow-icon">▼</span>
						</button>

						<div class="navbar-dropdown-menu" id="user-dropdown-menu">
							<a href="<?= route('story.create') ?>" class="dropdown-menu-item">➕ Поделиться</a>

							<a href="<?= route('messages.index') ?>" class="dropdown-menu-item">
								<span>✉️ Сообщения</span>
							</a>

							<a href="<?= route('account.settings') ?>" class="dropdown-menu-item">⚙️ Настройки</a>

							<?php if (\App\Modules\Auth\Services\Auth::isAdmin()): ?>
								<div class="dropdown-divider"></div>
								<a href="/admin" class="dropdown-menu-item dropdown-item-admin">📊 Админка</a>
							<?php endif; ?>

							<?php if (\App\Modules\Auth\Services\Auth::isModerator()): ?>
								<div class="dropdown-divider"></div>
								<a href="/mod/log" class="dropdown-menu-item dropdown-item-mod">📋 Лог модерации</a>
								<a href="/mod/notes" class="dropdown-menu-item dropdown-item-mod">🔒 Заметки</a>
								<a href="/mod/stats" class="dropdown-menu-item dropdown-item-mod">📈 Активность</a>
								<a href="/admin/domains" class="dropdown-menu-item dropdown-item-mod">🌐 Домены</a>
								<a href="<?= route('domains.index') ?>" class="dropdown-menu-item dropdown-item-mod">🚫 Бан</a>
							<?php endif; ?>

							<div class="dropdown-divider"></div>
							<form action="<?= route('auth.logout') ?>" method="POST" style="display:inline;">
								 <?= csrf_field() ?>
								<button type="submit" class="dropdown-menu-item navbar-logout-link">🚪  Выйти</button>
							</form>
														
						</div>

					</div>
				<?php else: ?>
					<a href="<?= route('auth.login') ?>">Войти</a>
					<?php if (config('config.app.invitations_enabled', false, 'bool')): ?>
						<?php if (!\App\Modules\Auth\Services\Auth::check()): ?>
							<a class="nav-link" href="<?= route('home') ?>invite/request">Запросить приглашение</a>
						<?php else: ?>
							<a class="nav-link" href="<?= route('invitations.index') ?>">🎟️ Приглашения</a>
						<?php endif; ?>
					<?php else: ?>
						<?php if (!\App\Modules\Auth\Services\Auth::check()): ?>
							<a class="btn-nav-create" href="<?= route('auth.register') ?>">Регистрация</a>
						<?php endif; ?>
					<?php endif; ?>
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
				<?php if (\App\Modules\Auth\Services\Auth::check()): ?>
				  <a href="<?= route('tags.filters') ?>">Фильтры</a>
				<?php endif; ?>
			</nav>
			<?= \App\Core\Benchmark::renderStats() ?>
		</div>
	</footer>

	<script src="<?= \App\Core\Asset::js() ?>"></script>
</body>

</html>