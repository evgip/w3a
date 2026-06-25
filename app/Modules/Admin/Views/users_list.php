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
                        if ($user['is_active']): ?>
                            Активен
                        <?php else: ?>
                            <span style="color: #ac130d; font-weight: bold;">Не активирован</span>
                        <?php endif; ?>

                        <?php if (($user['role'] ?? 'user') != 'admin'): ?>
                            <form method="POST"
                                action="/admin/users/<?= $user['id'] ?>/toggle-status"
                                style="display: inline;">
                                <?= csrf_field() ?>

                                <?php if ($user['is_active']): ?>
                                    <!-- Пользователь активен → кнопка "Деактивировать" -->
                                    <button type="submit"
                                        class="btn btn-sm btn-warning"
                                        data-confirm="Деактивировать пользователя «<?= e($user['username'] ?? $user['name']) ?>»? Он не сможет войти в систему.">
                                        ⏸️ Деактивировать
                                    </button>
                                <?php else: ?>
                                    <!-- Пользователь неактивен → кнопка "Активировать" -->
                                    <button type="submit"
                                        class="btn btn-sm btn-success"
                                        data-confirm="Активировать пользователя «<?= e($user['username'] ?? $user['name']) ?>»?">
                                        ✅ Активировать
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($user['created_at'] ?? '—') ?>
                    </td>
                    <td>
                        <a href="<?= route('admin.users.edit', ['id' => $user['id']]) ?>" class="button">
                            Изменить
                        </a>




                        <?php if ((int)($user['id'] ?? 0) !== 1): // Защита от удаления супер-админа (ID=1) 
                        ?>
                            <?php if (empty($user['is_banned'])): ?>
                                <!-- Форма бана -->
                                <form method="POST" action="<?= route('mod.ban', ['id' => $user['id']]) ?>" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="ban">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                        data-confirm="Забанить пользователя <?= e($user['username']) ?>?">
                                        🚫 Забанить
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- Форма разбана -->
                                <form method="POST" action="<?= route('mod.ban', ['id' => $user['id']]) ?>" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="unban">
                                    <button type="submit" class="btn btn-sm btn-success"
                                        data-confirm="Разбанить пользователя <?= e($user['username']) ?>?">
                                        ✅ Разбанить
                                    </button>
                                </form>
                            <?php endif; ?>
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