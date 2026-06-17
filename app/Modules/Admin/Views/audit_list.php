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

<hr>

<!-- ФИЛЬТРЫ И ПОИСК -->
<form action="<?= route('admin.audit') ?>" method="GET">
    <div class="form-field-group">
        <label for="filter_user_id">ID Пользователя:</label>
        <input type="number" id="filter_user_id" name="filter_user_id" class="form-input-wide" style="max-width: 200px;"
               value="<?= e((string)($currentFilters['user_id'] ?? '')) ?>" 
               placeholder="Например: 1">
    </div>

    <div class="form-field-group">
        <label for="filter_action">Тип события (Action):</label>
        <select id="filter_action" name="filter_action" class="form-input-wide">
            <option value="">-- Все события --</option>
            <?php foreach ($uniqueActions as $act): ?>
                <option value="<?= e($act) ?>" <?= (($currentFilters['action'] ?? '') === $act) ? 'selected' : '' ?>>
                    <?= e($act) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-field-group">
        <label for="search">Поиск по тексту:</label>
        <input type="text" id="search" name="search" class="form-input-wide"
               value="<?= e($currentFilters['search'] ?? '') ?>" 
               placeholder="Имя, описание или контекст...">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">🔍 Применить фильтры</button>
        
        <?php if (!empty($currentFilters['user_id']) || !empty($currentFilters['action']) || !empty($currentFilters['search'])): ?>
            <a href="<?= route('admin.audit') ?>" class="button">❌ Сбросить фильтры</a>
        <?php endif; ?>
    </div>
</form>

<hr>

<!-- ТАБЛИЦА ЛОГОВ -->
<?php if (!empty($logs)): ?>
    <table class="data">
        <thead>
            <tr>
                <th>Время (БД)</th>
                <th>Пользователь</th>
                <th>Роль</th>
                <th>IP адрес</th>
                <th>Событие</th>
                <th>Описание</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <code><?= e($log['created_at'] ?? '—') ?></code>
                    </td>
                    <td>
                        <strong><?= e($log['username'] ?? 'Guest') ?></strong>
                        <?php if (!empty($log['user_id'])): ?>
                            <br><span class="hint">(ID: <?= (int)$log['user_id'] ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($log['role'] ?? '') === 'admin'): ?>
                            <strong style="color: #ac130d;">admin</strong>
                        <?php else: ?>
                            <?= e($log['role'] ?? 'user') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= e($log['ip_address'] ?? '—') ?></code>
                    </td>
                    <td>
                        <strong><?= e($log['action'] ?? 'Неизвестно') ?></strong>
                    </td>
                    <td>
                        <div><?= e($log['description'] ?? '—') ?></div>
                        <?php if (!empty($log['payload'])): ?>
                            <div class="hint" style="margin-top: 0.5rem; font-size: 0.8em;">
                                <code><?= e(json_encode($log['payload'], JSON_UNESCAPED_UNICODE)) ?></code>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
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
        <?php 
        // Собираем массив текущих фильтров для ссылок пагинации
        $urlParams = array_filter([
            'filter_user_id' => $currentFilters['user_id'] ?? '',
            'filter_action'  => $currentFilters['action'] ?? '',
            'search'         => $currentFilters['search'] ?? ''
        ]);
        ?>

        <?php if ($currentPage > 1): ?>
            <?php $urlParams['page'] = $currentPage - 1; ?>
            <a href="<?= route('admin.audit') ?>?<?= http_build_query($urlParams) ?>" class="button">« Назад</a>
        <?php endif; ?>

        <span class="hint">
            Страница <?= $currentPage ?> из <?= $totalPages ?>
        </span>

        <?php if ($currentPage < $totalPages): ?>
            <?php $urlParams['page'] = $currentPage + 1; ?>
            <a href="<?= route('admin.audit') ?>?<?= http_build_query($urlParams) ?>" class="button">Вперёд »</a>
        <?php endif; ?>
    </div>
<?php endif; ?>