<?php 
    $request = new \App\Core\Request(); 
?>


    
    <!-- Dialogue Header Bar Container -->
    <div class="chat-history-header">
        <a href="<?= route('messages.index') ?>" class="tag-badge-link chat-btn-back">← К списку</a>
        <?php if (!empty($recipient['avatar'])): ?>
            <img src="/uploads/avatars/<?= substr($recipient['avatar'], 0, 2) ?>/<?= htmlspecialchars($recipient['avatar']) ?>" class="profile-avatar-render-img chat-avatar-dialog-size" alt="avatar">
        <?php else: ?>
            <div class="profile-avatar-placeholder chat-avatar-dialog-placeholder">
                <?= htmlspecialchars(mb_substr($recipient['name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <strong class="chat-title-text">Чат с пользователем <?= htmlspecialchars($recipient['name']) ?></strong>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="chat-history-pagination-nav">
            <?php if ($currentPage < $totalPages): ?>
                <!-- Clicking here increases page parameter index, pulling older message blocks -->
                <a href="?chat_page=<?= $currentPage + 1 ?>" class="btn-load-older-chats">
                    ☝️ Загрузить более ранние сообщения
                </a>
            <?php else: ?>
                <span class="chat-pagination-status-lbl">🗄️ Вы пролистали до самого начала переписки</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>


    <!-- Messages Scroller Stream Container -->
    <div class="chat-messages-stream">
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <?php 
                    $isOutgoing = ((int)$msg['sender_id'] === (int)$_SESSION['user_id']);
                ?>
                <div class="chat-bubble <?= $isOutgoing ? 'chat-bubble-outgoing' : 'chat-bubble-incoming' ?>" title="<?= htmlspecialchars(date('d.m Y H:i', strtotime($msg['created_at']))) ?>">
                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="chat-empty-history-text">История сообщений пуста. Напишите что-нибудь первое!</p>
        <?php endif; ?>
    </div>

    <!-- Submission Input Controls Form Bar Footer -->
    <div class="chat-footer-form-bar">
        <form action="<?= route('messages.send.submit') ?>" method="POST" class="chat-input-row">
            <?= $request->csrfField() ?>
            <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
            
            <input type="text" name="message_text" required autocomplete="off" placeholder="Введите ваше сообщение..." class="form-input-text chat-input-field-modifier">
            <button type="submit" class="btn btn-primary btn-chat-send">
                Отправить 🚀
            </button>
        </form>
    </div>

