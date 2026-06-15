<h1>📋 Лог модерации</h1>

<?php if (empty($items)): ?>
    <p class="text-muted">Записей пока нет.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Модератор</th>
                <th>Действие</th>
                <th>Объект</th>
                <th>Причина</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['created_at']) ?></td>
                <td><?= e($item['moderator_name'] ?? '—') ?></td>
                <td><code><?= e($item['action']) ?></code></td>
                <td><?= e($item['target_type']) ?> #<?= (int)$item['target_id'] ?></td>
                <td><?= safeLink($item['reason']) ?? '—'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="/mod/log?page=<?= $i ?>" 
               class="<?= $i === $current_page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>