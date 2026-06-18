<?php 
/** 
 * @var array $users 
 */ 
?>

<h1><?= e($title ?? 'Управление пользователями') ?></h1>

<p class="hint">
    Список всех зарегистрированных пользователей системы. Здесь вы можете изменять их роли или удалять учетные записи.
</p>

<?php if (empty($users)): ?>
    <p class="hint">
        Пользователи пока не найдены.
    </p>
<?php else: ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Статус</th>
                <th>Регистрация</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int)($user['id'] ?? 0) ?></td>
                    <td>
                        <strong><code><?= e($user['username'] ?? '') ?></code></strong>
                    </td>
                    <td>
                        <?= e($user['email'] ?? '') ?>
                    </td>
                    <td>
                        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                            <strong>Администратор</strong>
                        <?php else: ?>
                            Пользователь
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // Предполагаем, что 1 = активен, 0 = заблокирован. Адаптируйте под вашу БД.
                        $status = (int)($user['status'] ?? 1);
                        if ($status === 0): ?>
                            <span style="color: #ac130d; font-weight: bold;">Заблокирован</span>
                        <?php else: ?>
                            Активен
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($user['created_at'] ?? '—') ?>
                    </td>
                    <td>
                        <a href="<?= route('admin.users.edit', ['id' => $user['id']]) ?>" class="button">
                            Изменить
                        </a>
                        
                        <?php if ((int)($user['id'] ?? 0) !== 1): // Защита от удаления супер-админа (ID=1) ?>
                            <form method="POST" 
                                  action="<?= route('admin.users.delete', ['id' => $user['id']]) ?>" 
                                  style="display:inline;"
								  class="delete-link"
								  data-confirm="Вы уверены, что хотите удалить пользователя «<?= e($user['username']) ?>»? Это действие необратимо.">
                                <?= csrf_field() ?>
                                <button type="submit" class="button" style="color: #ac130d;">
                                    Удалить
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="hint" title="Главного администратора нельзя удалить">
                                🔒
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>