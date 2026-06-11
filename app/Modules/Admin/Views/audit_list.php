<h3>🔒 Журнал аудита действий пользователей</h3>
<p class="audit-description">
    Интерактивный просмотр логов безопасности системы. Данные фильтруются динамически через SQL-запросы к таблице <code>audit_logs</code>.
</p>

<!-- ФИЛЬТРЫ И ПОИСК -->
<div class="card audit-filter-card">
    <form action="/admin/audit" method="GET" class="audit-filter-form">
        
        <div class="filter-group">
            <label class="filter-label">ID Пользователя:</label>
            <input type="number" name="filter_user_id" 
                   value="<?= htmlspecialchars((string)($currentFilters['user_id'] ?? '')) ?>" 
                   placeholder="Например: 1" 
                   class="filter-input input-w-120">
        </div>

        <div class="filter-group">
            <label class="filter-label">Тип события (Action):</label>
            <select name="filter_action" class="filter-select select-min-w-200">
                <option value="">-- Все события --</option>
                <?php foreach ($uniqueActions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($currentFilters['action'] === $act) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group flex-fill-200">
            <label class="filter-label">Поиск по тексту:</label>
            <input type="text" name="search" 
                   value="<?= htmlspecialchars($currentFilters['search'] ?? '') ?>" 
                   placeholder="Имя, описание или контекст..." 
                   class="filter-input">
        </div>

        <div class="filter-actions-group">
            <button type="submit" class="btn-audit-submit">
                🔍 Применить
            </button>
            <?php if (!empty($currentFilters['user_id']) || !empty($currentFilters['action']) || !empty($currentFilters['search'])): ?>
                <a href="/admin/audit" class="btn-audit-reset">
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
            <th class="th-w-140">Время (БД)</th>
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
                    <td><small class="audit-timestamp"><?= htmlspecialchars($log['created_at']) ?></small></td>
                    <td>
                        <strong><?= htmlspecialchars($log['username'] ?? 'Guest') ?></strong> 
                        <?php if (!empty($log['user_id'])): ?>
                            <small class="audit-user-id">(ID: <?= (int)$log['user_id'] ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= ($log['role'] === 'admin') ? 'admin' : 'user' ?>">
                            <?= htmlspecialchars($log['role']) ?>
                        </span>
                    </td>
                    <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
                    <td>
                        <span class="audit-action-badge">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($log['description']) ?></div>
                        <!-- Если у лога есть полезный контекст, выведем его мелким шрифтом -->
                        <?php if (!empty($log['payload'])): ?>
                            <div class="audit-payload-box">
                                <?= htmlspecialchars(json_encode($log['payload'], JSON_UNESCAPED_UNICODE)) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="table-empty-message">
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