<?php
$mutedUsers = $mutedUsers ?? [];
?>

<div class="container">
    <h1>🔇 Замьюченные пользователи</h1>
    
    <p class="hint">
        Вы не будете видеть истории и комментарии замьюченных пользователей в своей ленте. 
        Они также не будут отправлять вам уведомления.
    </p>
    
    <?php if (empty($mutedUsers)): ?>
        <p class="hint">Вы никого не замьютили.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Замьючен</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mutedUsers as $user): ?>
                    <tr>
                        <td>
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="/uploads/avatars/<?= substr($user['avatar'], 0, 2) ?>/<?= e($user['avatar']) ?>" 
                                     class="avatar" alt="">
                            <?php endif; ?>
                            <a href="/user/<?= e($user['username']) ?>">
                                <?= e($user['username']) ?>
                            </a>
                        </td>
                        <td>
                            <?= e(date('d.m.Y', strtotime($user['muted_at']))) ?>
                        </td>
                        <td>
                            <form action="/mute/toggle/<?= (int)$user['id'] ?>" method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-link delete">
                                    🔊 Размьютить
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>