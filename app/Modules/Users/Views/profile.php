<?php
$currentUserId = \App\Modules\Auth\Services\Auth::check() ? (int)$_SESSION['user_id'] : 0;
$isOwnProfile = ($currentUserId === (int)$profileUser['id']);
?>

<h1>Профиль пользователя</h1>

<hr>

<!-- ШАПКА ПРОФИЛЯ -->
<div class="profile-header">
    
    <!-- Аватар -->
    <?php if (!empty($profileUser['avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($profileUser['avatar'], 0, 2) ?>/<?= e($profileUser['avatar']) ?>" 
             class="profile-avatar-large" 
             alt="<?= e(mb_substr($profileUser['username'], 0, 1)) ?>">
    <?php else: ?>
        <div class="profile-avatar-placeholder-large">
            <?= e(mb_substr($profileUser['username'], 0, 1)) ?>
        </div>
    <?php endif; ?>

    <!-- Информация -->
    <div class="profile-info">
        <h2 class="profile-username">
            # <?= e($profileUser['username']) ?>
        </h2>
        
        <span class="profile-status">Активный пользователь</span>

        <?php if (!$isOwnProfile && $currentUserId > 0): ?>
            <div class="profile-message-btn">
                <form action="<?= route('messages.start', ['userId' => $profileUser['id']]) ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit">✉️ Написать сообщение</button>
                </form>
            </div>
        <?php endif; ?>
		
		<?php if (\App\Modules\Auth\Services\Auth::isModerator() && $profileUser['id'] !== (int)$_SESSION['user_id']): ?>
			<div class="mod-actions">
				<h3>Действия модератора</h3>
				
				<a href="/mod/notes?user_id=<?= $profileUser['id'] ?>" class="btn btn-sm">
					📝 Добавить заметку
				</a>
				
				<?php if (empty($profileUser['is_banned'])): ?>
					<!-- Форма бана -->
					<form method="POST" action="<?= route('mod.ban', ['id' => $profileUser['id']]) ?>" style="display: inline;">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="ban">
						<button type="submit" class="btn btn-sm btn-danger" 
								data-confirm="Забанить пользователя <?= e($profileUser['username']) ?>?">
							🚫 Забанить
						</button>
					</form>
				<?php else: ?>
					<!-- Форма разбана -->
					<form method="POST" action="<?= route('mod.ban', ['id' => $profileUser['id']]) ?>" style="display: inline;">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="unban">
						<button type="submit" class="btn btn-sm btn-success"
								data-confirm="Разбанить пользователя <?= e($profileUser['username']) ?>?">
							✅ Разбанить
						</button>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>
     </div>

</div>

<!-- БИОГРАФИЯ -->
<?php if (!empty($profileUser['bio'])): ?>
    <div class="profile-bio">
        <?= nl2br(e($profileUser['bio'])) ?>
    </div>
<?php endif; ?>

<!-- ДЕТАЛИ ПРОФИЛЯ -->
<table class="profile-details">
    <tbody>
        <tr>
            <td>Аккаунт создан:</td>
            <td>
                <?= e(date('d.m.Y', strtotime($profileUser['created_at']))) ?>
                <span class="profile-id-subtext">(ID: <?= (int)$profileUser['id'] ?>)</span>
            </td>
        </tr>

        <tr>
            <td>Репутация (Карма):</td>
            <td>
                <?php
                $karmaClass = 'profile-karma-neutral';
                if ($userKarma > 0) $karmaClass = 'profile-karma-positive';
                if ($userKarma < 0) $karmaClass = 'profile-karma-negative';
                ?>
                <span class="<?= $karmaClass ?>">
                    <?= $userKarma > 0 ? '+' : '' ?><?= (int)$userKarma ?> баллов
                </span>
            </td>
        </tr>

        <tr>
            <td>Роль на сайте:</td>
            <td>
                <strong><?= e($profileUser['role']) ?></strong>
            </td>
        </tr>

        <tr>
            <td>Размещено историй:</td>
            <td>
                <a href="/?author=<?= urlencode($profileUser['username']) ?>">
                    <?= (int)$storiesCount ?> публикаций
                </a>
            </td>
        </tr>

        <tr>
            <td>Оставлено ответов:</td>
            <td>
                <?= (int)$commentsCount ?> комментариев
            </td>
        </tr>

    </tbody>
</table>