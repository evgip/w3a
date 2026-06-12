<div class="profile-container">

    <div class="profile-header-wrapper">

        <?php if (!empty($profileUser['avatar'])): ?>
            <img src="/uploads/avatars/<?= substr($profileUser['avatar'], 0, 2) ?>/<?= htmlspecialchars($profileUser['avatar']) ?>" class="profile-avatar-render-img" alt="<?= htmlspecialchars(mb_substr($profileUser['name'], 0, 1)) ?>">
        <?php else: ?>
            <div class="profile-avatar-placeholder">
                <?= htmlspecialchars(mb_substr($profileUser['name'], 0, 1)) ?>
            </div>
        <?php endif; ?>

        <div>
            <h2 class="profile-title-username"># <?= htmlspecialchars($profileUser['name']) ?></h2>
            <span class="profile-status-tag">Активный пользователь</span>

            <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== (int)$profileUser['id']): ?>
                <form action="<?= route('messages.start', ['userId' => $profileUser['id']]) ?>" method="POST" class="profile-chat-form-inline">
                    <?= (new \App\Core\Request())->csrfField() ?>
                    <button type="submit" class="tag-badge-link btn-profile-chat-start">
                        ✉️ Написать сообщение
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($profileUser['bio'])): ?>
        <div class="profile-bio-quote-box">
            <?= nl2br(htmlspecialchars($profileUser['bio'])) ?>
        </div>
    <?php endif; ?>

    <table class="profile-details-table">
        <tbody>
            <tr>
                <td class="profile-label-cell">Аккаунт создан:</td>
                <td class="profile-value-cell">
                    <?= htmlspecialchars(date('d.m.Y', strtotime($profileUser['created_at']))) ?>
                    <small class="profile-id-subtext">(ID: <?= (int)$profileUser['id'] ?>)</small>
                </td>
            </tr>
            <tr>
                <td class="profile-label-cell">Репутация (Карма):</td>
                <td class="profile-value-cell">
                    <?php
                    // Deduce visual color markers based on data weights
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
                <td class="profile-label-cell">Роль на сайте:</td>
                <td class="profile-value-cell">
                    <strong class="user-role-highlight"><?= htmlspecialchars($profileUser['role']) ?></strong>
                </td>
            </tr>
            <tr>
                <td class="profile-label-cell">Размещено историй:</td>
                <td class="profile-value-cell">
                    <a href="/?author=<?= urlencode($profileUser['name']) ?>">
                        <?= (int)$storiesCount ?> публикаций
                    </a>
                </td>
            </tr>
            <tr>
                <td class="profile-label-cell">Оставлено ответов:</td>
                <td class="profile-value-cell">
                    <?= (int)$commentsCount ?> комментариев
                </td>
            </tr>
            <tr>
                <td class="profile-label-cell">Контактный Email:</td>
                <td class="profile-value-cell">
                    <code><?= htmlspecialchars($profileUser['email']) ?></code>
                </td>
            </tr>
        </tbody>
    </table>

</div>