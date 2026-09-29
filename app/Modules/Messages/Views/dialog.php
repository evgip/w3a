<!-- Шапка диалога -->
<div class="dialog-header">
    <a href="<?= route('messages.index') ?>" class="dialog-back-link">← К списку</a>
    
    <?php if (!empty($recipient['avatar'])): ?>
        <img src="/uploads/avatars/<?= substr($recipient['avatar'], 0, 2) ?>/<?= e($recipient['avatar']) ?>" 
             class="avatar avatar--md" alt="avatar">
    <?php else: ?>
        <span class="avatar avatar--md avatar--placeholder">
            <?= e(mb_substr($recipient['username'], 0, 1)) ?>
        </span>
    <?php endif; ?>
    
    <span class="dialog-title">
        Чат с пользователем <?= e($recipient['username']) ?>
    </span>
</div>

<!-- Пагинация (загрузка старых сообщений) -->
<?php if ($totalPages > 1): ?>
    <div class="dialog-pagination">
        <?php if ($currentPage < $totalPages): ?>
            <a href="?chat_page=<?= $currentPage + 1 ?>" class="dialog-load-older">
                Загрузить более ранние сообщения
            </a>
        <?php else: ?>
            <span class="dialog-pagination-status">Вы пролистали до самого начала переписки</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Сообщения -->
<div class="dialog-messages">
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <?php $isOutgoing = ((int)$msg['sender_id'] === \W3a\Core\Auth\Auth::id()); ?>
            
            <div class="dialog-message <?= $isOutgoing ? 'outgoing' : 'incoming' ?>" 
                 title="<?= e(date('d.m.Y H:i', strtotime($msg['created_at']))) ?>">
                <div class="dialog-message-text">
                    <?= nl2br(e($msg['message'])) ?>
                </div>
                <?php if ($isOutgoing): ?>
                    <details class="dialog-edit">
                        <summary class="dialog-edit-toggle">Изменить</summary>
                        <form action="<?= route('messages.edit.submit') ?>" method="POST" class="dialog-edit-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                            <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
                            <input type="hidden" name="chat_page" value="<?= (int)$currentPage ?>">
                            <label class="dialog-edit-label" for="edit-message-<?= (int)$msg['id'] ?>">Текст сообщения</label>
                            <textarea id="edit-message-<?= (int)$msg['id'] ?>" name="message_text" required class="dialog-edit-input"><?= e($msg['message']) ?></textarea>
                            <div class="dialog-edit-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                                <a href="?chat_page=<?= (int)$currentPage ?>" class="btn btn-secondary btn-sm">Отмена</a>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
                <div class="dialog-message-time">
                    <?= e(date('d.m H:i', strtotime($msg['created_at']))) ?>
                </div>
            </div>
            
        <?php endforeach; ?>
    <?php else: ?>
        <p class="dialog-empty">История сообщений пуста. Напишите что-нибудь первое!</p>
    <?php endif; ?>
</div>

<!-- Форма отправки сообщения -->
<div class="dialog-form">
    <form action="<?= route('messages.send.submit') ?>" method="POST" class="dialog-form-row">
        <?= csrf_field() ?>
        <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
        
        <input type="text" name="message_text" required autocomplete="off" 
               placeholder="Введите ваше сообщение..." class="dialog-form-input">
        
        <button type="submit" class="dialog-form-button">Отправить</button>
    </form>
</div>