<div class="admin-header-row">
    <h3>👥 Управление пользователями системы</h3>
</div>

<p class="admin-subtitle-desc">Полный перечень зарегистрированных учетных записей платформы. Изменение ролей, блокировка и модерирование профилей.</p>

<table>
    <thead>
        <tr>
            <th class="w-60">ID</th>
            <th>Имя пользователя</th>
            <th>Email адрес</th>
            <th>Статус / Роль</th>
            <th>Дата создания</th>
            <th class="text-right w-180">Действия</th>
			<th class="text-right w-180">История</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <?php 
                    $isArchived = !empty($user['deleted_at']); 
                    $isSelf = ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0));
                ?>
                <tr class="<?= $isArchived ? 'tr-archived' : '' ?>">
                    <td><?= (int)$user['id'] ?></td>
                    <td>
                        <strong><?= e($user['username'] ?? 'Unknown') ?></strong>
                        <?php if ($isSelf): ?>
                            <small class="self-account-badge">(Вы)</small>
                        <?php endif; ?>
                        <?php if ($isArchived): ?>
                            <span class="archive-date-meta">[В АРХИВЕ]</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($user['email'] ?? '') ?></td>
                    <td>
                        <?php if ($isArchived): ?>
                            <span class="badge">Архивирован</span>
                        <?php elseif (($user['role'] ?? 'user') === 'admin'): ?>
                            <span class="badge badge-admin">admin</span>
							
						<?php elseif (($user['role'] ?? 'user') === 'moderator'): ?>
						    <span class="badge badge-moderator">moderator</span>
                        <?php else: ?>
                            <span class="badge badge-user">user</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= e($user['created_at'] ?? '') ?>
                        </small>
                        <?php if ($isArchived): ?>
                            <br><small class="archive-date-meta">Архив: <?= e(date('d.m.Y H:i', strtotime($user['deleted_at']))) ?></small>
                        <?php endif; ?>
                    </td>
					
					
    <td class="text-right">
                        <form action="<?= route('admin.users.toggle_status', ['id' => $user['id']]) ?>" method="POST">
                            <?= csrf_field() ?>
                            
                            <?php if ((int)$user['is_active'] === 1): ?>
                                <button type="submit" class="btn-status-toggle-ban">
                                    🛑 Заблокировать
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn-status-toggle-activate">
                                    🔓 Разблокировать
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
					
					
                    <td class="text-right">
                        <?php if ($isSelf): ?>
                            <small class="text-muted">Нет действий</small>
                        <?php else: ?>
                            <div class="admin-form-actions-row">
                                <a href="<?= route('admin.users.edit', ['id' => $user['id']]) ?>" class="btn-action-inline btn-moderation-blue">
                                    🔧 Модерировать
                                </a>
                                <?php if ($isArchived): ?>
                                    <form action="/admin/users/<?= (int)$user['id'] ?>/restore" method="POST" class="admin-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-action btn-restore">♻️ Восстановить</button>
                                    </form>
                                <?php else: ?>
                                    <form action="/admin/users/<?= (int)$user['id'] ?>/archive" method="POST" class="admin-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-action btn-archive">📦 В архив</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center text-muted">Пользователи не найдены.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
