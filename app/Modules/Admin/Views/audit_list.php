<?php

/** 
 * @var array $logs 
 * @var array $uniqueActions 
 * @var array $currentFilters 
 * @var int $currentPage 
 * @var int $totalPages 
 */
?>

<h1>🔒 Журнал аудита действий пользователей</h1>

<p class="hint">
    Интерактивный просмотр логов безопасности системы. Данные фильтруются динамически через SQL-запросы к таблице <code>audit_logs</code>.
</p>

<!-- Форма фильтров -->
<form method="get" action="/admin/audit" class="audit-filters">
    <!-- Фильтр по пользователю -->
    <label>
        User ID:
        <input type="number" name="filter_user_id"
            value="<?= e((string)($currentFilters['user_id'] ?? '')) ?>"
            placeholder="Все">
    </label>

    <!-- Фильтр по действию -->
    <label>
        Действие:
        <select name="filter_action">
            <option value="">Все действия</option>
            <?php foreach ($uniqueActions as $action): ?>
                <option value="<?= e($action) ?>"
                    <?= ($currentFilters['action'] ?? '') === $action ? 'selected' : '' ?>>
                    <?= e($action) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- ✅ НОВЫЙ ФИЛЬТР: Категория -->
    <label>
        Категория:
        <select name="category">
            <option value="">Все категории</option>
            <?php foreach ($categoryLabels as $value => $label): ?>
                <option value="<?= e($value) ?>"
                    <?= ($currentFilters['category'] ?? '') === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- Поиск -->
    <label>
        Поиск:
        <input type="text" name="search"
            value="<?= e($currentFilters['search'] ?? '') ?>"
            placeholder="Поиск...">
    </label>

    <button type="submit">Применить</button>

    <?php if (!empty(array_filter($currentFilters))): ?>
        <a href="/admin/audit" class="btn-reset">Сбросить</a>
    <?php endif; ?>
</form>

<hr>

<!-- ТАБЛИЦА ЛОГОВ -->
<?php if (!empty($logs)): ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Пользователь</th>
                <th>Роль</th>
                <th>IP</th>
                <th>Действие</th>
                <th>Описание</th>
                <th>Категория</th> <!-- ✅ НОВАЯ КОЛОНКА -->
                <th>Данные</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="9" style="text-align: center;">Нет записей</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                        <td>
                            <?php if ($log['user_id']): ?>
                                <a href="/admin/users/<?= $log['user_id'] ?>/edit">
                                    <?= e($log['username']) ?>
                                </a>
                            <?php else: ?>
                                <?= e($log['username']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status role-<?= e($log['role']) ?>">
                                <?= e($log['role']) ?>
                            </span>
                        </td>
                        <td><code><?= e($log['ip_address']) ?></code></td>
                        <td><code><?= e($log['action']) ?></code></td>
                        <td><?= e($log['description']) ?></td>
                        <td>
                            <span class="category-badge category-<?= e($log['category']) ?>">
                                <?= e($categoryLabels[$log['category']] ?? $log['category']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['decoded_payload'])): ?>
                                <details>
                                    <summary>Показать</summary>
                                    <pre><?= e(json_encode($log['decoded_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                </details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">
        Записи, соответствующие заданным критериям фильтрации, не найдены.
    </p>
<?php endif; ?>

<!-- Блок пагинации -->
<?php if (isset($totalPages) && $totalPages > 1): ?>
    <hr>
    <div class="form-actions">
        <?= pagination(
            $currentPage,
            $totalPages,
            array_filter([
                'filter_user_id' => $currentFilters['user_id'] ?? null,
                'filter_action'  => $currentFilters['action'] ?? null,
                'search'         => $currentFilters['search'] ?? null
            ]),
            2, // диапазон страниц
            route('admin.audit') // базовый URL для ссылок
        ) ?>
        
        <!-- Если вам всё ещё нужен текстовый hint, его можно оставить здесь -->
        <span class="hint" style="margin-left: 15px; color: #666;">
            Страница <?= $currentPage ?> из <?= $totalPages ?>
        </span>
    </div>
<?php endif; ?>