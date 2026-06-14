<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            иконка Уведомления
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger"><?= $unreadCount ?></span>
            <?php endif; ?>
        </h1>
        
        <?php if (!empty($notifications)): ?>
            <form method="POST" action="/notifications/mark-all-read" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    иконка Отметить все как прочитанные
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info">
            иконка У вас нет уведомлений.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $notif): ?>
                <div class="list-group-item list-group-item-action <?= $notif['is_read'] ? '' : 'list-group-item-light border-start border-primary border-3' ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <div class="d-flex align-items-start">
                            <!-- Аватар пользователя -->
                            <div class="me-3">
                                <?php if (!empty($notif['actor_avatar'])): ?>
                                    <img src="<?= e($notif['actor_avatar']) ?>" 
                                         alt="<?= e($notif['actor_name']) ?>" 
                                         class="rounded-circle" 
                                         width="40" 
                                         height="40">
                                <?php else: ?>
                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <?= strtoupper(substr($notif['actor_name'] ?? '?', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Содержимое уведомления -->
                            <div class="flex-grow-1">
                                <div class="mb-1">
                                    <strong><?= e($notif['actor_name'] ?? 'Пользователь') ?></strong>
                                    <span class="text-muted"><?= e($notif['message']) ?></span>
                                    
                                    <?php if (!$notif['is_read']): ?>
                                        <span class="badge bg-primary ms-2">Новое</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Контекст уведомления -->
                                <?php if ($notif['notifiable_type'] === 'Comment' && !empty($notif['story_title'])): ?>
                                    <div class="small text-muted mb-2">
                                        иконка
                                        В публикации: 
                                        <a href="/story/<?= $notif['story_id'] ?>#comment-<?= $notif['notifiable_id'] ?>">
                                            <?= e($notif['story_title']) ?>
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($notif['comment_text'])): ?>
                                        <div class="card bg-light">
                                            <div class="card-body py-2 px-3">
                                                <p class="mb-0 small text-truncate">
                                                    <?= e(mb_substr($notif['comment_text'], 0, 150)) ?>
                                                    <?= mb_strlen($notif['comment_text']) > 150 ? '...' : '' ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <small class="text-muted">
                                    иконка
                                    <?= date('d.m.Y в H:i', strtotime($notif['created_at'])) ?>
                                </small>
                            </div>
                        </div>

                        <!-- Действия -->
                        <div class="ms-3">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="/notifications/<?= $notif['id'] ?>/read" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Отметить как прочитанное">
                                        иконка
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($notif['notifiable_type'] === 'Comment' && !empty($notif['story_id'])): ?>
                                <a href="/story/<?= $notif['story_id'] ?>#comment-<?= $notif['notifiable_id'] ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Перейти к комментарию">
                                    иконка
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Пагинация -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $currentPage - 1 ?>">← Назад</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $currentPage + 1 ?>">Вперёд →</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>