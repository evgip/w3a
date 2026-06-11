<h3>🔒 Журнал аудита действий пользователей</h3>
<p style="color: #7f8c8d; margin-bottom: 20px;">
    Интерактивный просмотр логов безопасности системы. Данные фильтруются динамически через SQL-запросы к таблице <code>audit_logs</code>.
</p>

<!-- ФИЛЬТРЫ И ПОИСК -->
<div class="card" style="margin-bottom: 25px; padding: 20px; background: #fff; border-radius: 8px;">
    <form action="/admin/audit" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
        
        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 12px; font-weight: bold; color: #7f8c8d;">ID Пользователя:</label>
            <input type="number" name="filter_user_id" 
                   value="<?= htmlspecialchars((string)($currentFilters['user_id'] ?? '')) ?>" 
                   placeholder="Например: 1" 
                   style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 120px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 12px; font-weight: bold; color: #7f8c8d;">Тип события (Action):</label>
            <select name="filter_action" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;">
                <option value="">-- Все события --</option>
                <?php foreach ($uniqueActions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($currentFilters['action'] === $act) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 200px;">
            <label style="font-size: 12px; font-weight: bold; color: #7f8c8d;">Поиск по тексту:</label>
            <input type="text" name="search" 
                   value="<?= htmlspecialchars($currentFilters['search'] ?? '') ?>" 
                   placeholder="Имя, описание или контекст..." 
                   style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" style="padding: 9px 15px; background: #34495e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                🔍 Применить
            </button>
            <?php if (!empty($currentFilters['user_id']) || !empty($currentFilters['action']) || !empty($currentFilters['search'])): ?>
                <a href="/admin/audit" style="padding: 9px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; display: inline-block;">
                    ❌ Сбросить
                </a>
            <?php endif; ?>
        </div>

    </form>
</div>

<!-- ТАБЛИЦА ЛОГОВ -->
<table>
    <thead>
        <tr>
            <th style="width: 140px;">Время (БД)</th>
            <th>Пользователь</th>
            <th>Роль</th>
            <th>IP адрес</th>
            <th>Событие</th>
            <th>Описание</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><small style="color: #95a5a6;"><?= htmlspecialchars($log['created_at']) ?></small></td>
                    <td>
                        <strong><?= htmlspecialchars($log['username'] ?? 'Guest') ?></strong> 
                        <?php if (!empty($log['user_id'])): ?>
                            <small style="color: gray;">(ID: <?= (int)$log['user_id'] ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= ($log['role'] === 'admin') ? 'admin' : 'user' ?>">
                            <?= htmlspecialchars($log['role']) ?>
                        </span>
                    </td>
                    <td><code><?= htmlspecialchars($log['ip']) ?></code></td>
                    <td>
                        <span style="font-family: monospace; color: #2980b9; font-weight: bold;">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($log['description']) ?></div>
                        <!-- Если у лога есть полезный контекст, выведем его мелким шрифтом -->
                        <?php if (!empty($log['payload'])): ?>
                            <div style="margin-top: 5px; font-size: 11px; background: #f8f9fa; padding: 5px; border-left: 2px solid #ccc; font-family: monospace; color: #555;">
                                <?= htmlspecialchars(json_encode($log['payload'], JSON_UNESCAPED_UNICODE)) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #95a5a6; padding: 30px;">
                    Записи, соответствующие заданным критериям фильтрации, не найдены.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Блок пагинации в самом низу вашего файла audit_list.php -->
<?php if (isset($totalPages) && $totalPages > 1): ?>
    <nav class="pagination-container">
        <?php 
            // Собираем массив текущих фильтров, чтобы подставить их в ссылки страниц
            $urlParams = array_filter([
                'filter_user_id' => $currentFilters['user_id'] ?? '',
                'filter_action'  => $currentFilters['action'] ?? '',
                'search'         => $currentFilters['search'] ?? ''
            ]);
        ?>

        <!-- Кнопка Назад -->
        <?php if ($currentPage > 1): ?>
            <?php $urlParams['page'] = $currentPage - 1; ?>
            <a href="/admin/audit?<?= http_build_query($urlParams) ?>" class="pagination-item">« Назад</a>
        <?php endif; ?>

        <!-- Номера страниц -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $urlParams['page'] = $i; ?>
            <a href="/admin/audit?<?= http_build_query($urlParams) ?>" class="pagination-item <?= $i === $currentPage ? 'pagination-item-active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <!-- Кнопка Вперед -->
        <?php if ($currentPage < $totalPages): ?>
            <?php $urlParams['page'] = $currentPage + 1; ?>
            <a href="/admin/audit?<?= http_build_query($urlParams) ?>" class="pagination-item">Вперед »</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>