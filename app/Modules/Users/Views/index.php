<!-- Обычный перевод -->
<h1><?= __('users.title') ?></h1>

<!-- Перевод с динамической переменной -->
<p><?= __('users.welcome', ['name' => 'Администратор']) ?></p>

<!-- Общий перевод из ядра -->
<button><?= __('buttons.submit') ?></button>

<br><br>

<h1><?= htmlspecialchars($title ?? 'Список пользователей') ?></h1>
<ul>
    <?php if (!empty($users)): ?>
        <?php foreach ($users as $user): ?>
            <li>
                <a href="/user/<?= urlencode($story['author_name']) ?>" class="comment-action-link user-profile-link">
                    <!-- We check 'name', fallback to 'username', fallback to 'email', or show 'Unknown' -->
                    <?= htmlspecialchars($user['name'] ?? $user['username'] ?? $user['email'] ?? 'Пользователь без имени') ?>
                </a>
                <!-- Optional: display their role next to them -->
                <small style="color: gray;">(<?= htmlspecialchars($user['role'] ?? 'user') ?>)</small>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li>Пользователи не найдены.</li>
    <?php endif; ?>
</ul>