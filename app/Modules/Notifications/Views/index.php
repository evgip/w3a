<?php
/** @var array $notifications */
/** @var string $currentType */
/** @var array $counts */
/** @var int $totalUnread */
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Уведомления</h2>
        <?php if ($totalUnread > 0): ?>
            <button class="btn btn-sm btn-outline-primary" id="mark-all-read-btn">
                Отметить все как прочитанные
            </button>
        <?php endif; ?>
    </div>

    <!-- Панель фильтров -->
    <ul class="nav nav-pills mb-4" id="notification-filters">
        <li class="nav-item">
            <a class="nav-link <?= $currentType === 'all' ? 'active' : '' ?>" 
               href="/notifications?type=all">
                Все 
                <?php if ($totalUnread > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $totalUnread ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentType === 'reply' ? 'active' : '' ?>" 
               href="/notifications?type=reply">
                💬 Ответы
                <?php if ($counts['reply'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $counts['reply'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentType === 'mention' ? 'active' : '' ?>" 
               href="/notifications?type=mention">
                @ Упоминания
                <?php if ($counts['mention'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $counts['mention'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentType === 'message' ? 'active' : '' ?>" 
               href="/notifications?type=message">
                ✉️ Сообщения
                <?php if ($counts['message'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $counts['message'] ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <!-- Список уведомлений -->
    <div class="list-group">
        <?php if (empty($notifications)): ?>
            <div class="alert alert-info text-center">
                У вас нет уведомлений <?= $currentType !== 'all' ? 'этого типа' : '' ?>.
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <?php 
                // Определяем иконку и текст в зависимости от типа
                $icon = '🔔';
                $actionText = '';
                $link = '/notifications';
                
                if ($notif['type'] === 'reply') {
                    $icon = '💬';
                    $actionText = 'ответил на ваш комментарий';
                    $link = '/comments/' . $notif['source_id'];
                } elseif ($notif['type'] === 'mention') {
                    $icon = '@';
                    $actionText = 'упомянул вас в комментарии';
                    $link = '/comments/' . $notif['source_id'];
                } elseif ($notif['type'] === 'message') {
                    $icon = '✉️';
                    $actionText = 'отправил вам сообщение';
                    // Предполагаем, что в source_id хранится ID сообщения или беседы
                    // Адаптируйте под вашу структуру (например, conversation_id)
                    $link = '/messages/chat/' . ($notif['conversation_id'] ?? $notif['source_id']);
                }
                ?>
                
                <a href="<?= $link ?>" 
                   class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= $notif['is_read'] ? '' : 'fw-bold bg-light' ?>"
                   data-notification-id="<?= $notif['id'] ?>"
                   onclick="markAsRead(<?= $notif['id'] ?>)">
                    
                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-secondary text-white" 
                         style="width: 40px; height: 40px; flex-shrink: 0;">
                        <?= $icon ?>
                    </div>
                    
                    <div class="d-flex gap-2 w-100 justify-content-between">
                        <div>
                            <h6 class="mb-0">
                                <?= htmlspecialchars($notif['sender_name'] ?? 'Пользователь') ?>
                            </h6>
                            <p class="mb-0 opacity-75 small"><?= $actionText ?></p>
                            <?php if (!empty($notif['content_text'])): ?>
                                <p class="mb-0 small text-muted fst-italic">
                                    "<?= htmlspecialchars(mb_substr($notif['content_text'], 0, 100)) ?>..."
                                </p>
                            <?php endif; ?>
                        </div>
                        <small class="opacity-50 text-nowrap">
                            <?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?>
                        </small>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

