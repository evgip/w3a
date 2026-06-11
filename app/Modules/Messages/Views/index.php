<?php 
    $request = new \App\Core\Request(); 
?>

<div class="container">
    <h3 class="chats-heading-title">✉️ Мои личные сообщения</h3>

    <?php if (!empty($chats)): ?>
        <?php foreach ($chats as $chat): ?>
            <?php 
                $isUnread = ((int)$chat['is_read'] === 0 && !empty($chat['last_message']) && (int)$chat['last_sender_id'] !== (int)$_SESSION['user_id']);
            ?>
            <a href="<?= route('messages.dialog', ['id' => $chat['conversation_id']]) ?>" class="chat-item-row <?= $isUnread ? 'chat-item-row-unread' : '' ?>">
                <div class="chat-avatar-group">
                    <?php if (!empty($chat['participant_avatar'])): ?>
                        <img src="/uploads/avatars/<?= substr($chat['participant_avatar'], 0, 2) ?>/<?= htmlspecialchars($chat['participant_avatar']) ?>" class="profile-avatar-render-img chat-avatar-mini-size" alt="avatar">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder chat-avatar-placeholder-size">
                            <?= htmlspecialchars(mb_substr($chat['participant_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong class="text-dark-title"><?= htmlspecialchars($chat['participant_name']) ?></strong>
                        <div class="chat-meta-body">
                            <?= !empty($chat['last_message']) ? htmlspecialchars($chat['last_message']) : '<span class="chat-meta-body-empty">Нет сообщений</span>' ?>
                        </div>
                    </div>
                </div>
                <div class="chat-timestamp-aside">
                    <?= htmlspecialchars(date('d.m H:i', strtotime($chat['updated_at']))) ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="chat-empty-card-fallback">
            <h3>Ваша папка входящих пуста 💬</h3>
            <p>Перейдите в профиль любого пользователя системы, чтобы начать приватный диалог.</p>
        </div>
    <?php endif; ?>
</div>
