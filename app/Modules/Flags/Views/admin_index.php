<?php
/**
 * Панель модератора: список жалоб
 */
?>

<h1>🚩 Жалобы пользователей</h1>

<p class="hint">
    Всего активных жалоб: <strong><?= (int) $pendingCount ?></strong>.
    Порог авто-скрытия: <strong><?= (int) $hideThreshold ?></strong> флагов.
</p>

<hr>

<?php if (!empty($pendingFlags)): ?>
    <h2>⏳ Ожидают рассмотрения (<?= count($pendingFlags) ?>)</h2>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Тип</th>
                <th>Цель</th>
                <th>Причина</th>
                <th>Автор жалобы</th>
                <th>Пояснение</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingFlags as $f): ?>
                <tr>
                    <td><?= (int) $f['id'] ?></td>
                    <td>
                        <?= $f['flaggable_type'] === 'story' ? '📰 Новость' : '💬 Комментарий' ?>
                    </td>
                    <td>
                        <?php if ($f['flaggable_type'] === 'story'): ?>
                            <a href="/stories/<?= (int) $f['flaggable_id'] ?>" target="_blank">
                                #<?= (int) $f['flaggable_id'] ?>
                            </a>
                        <?php else: ?>
                            <a href="/stories#comment-<?= (int) $f['flaggable_id'] ?>" target="_blank">
                                #<?= (int) $f['flaggable_id'] ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($reasons[$f['reason']] ?? $f['reason']) ?></strong></td>
                    <td>
                        <a href="/user/<?= e($f['reporter_name'] ?? '') ?>"><?= e($f['reporter_name'] ?? '—') ?></a>
                    </td>
                    <td><?= e($f['comment'] ?? '—') ?></td>
                    <td><code><?= e($f['created_at']) ?></code></td>
                    <td>
                        <form action="<?= route('admin.flags.resolve', ['id' => $f['id']]) ?>"
                              method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="hide">
                            <button type="submit" class="button delete-link"
							        data-confirm="Подтвердить жалобу и скрыть контент?">
                                ✓ Подтвердить
                            </button>
                        </form>
                        <form action="<?= route('admin.flags.resolve', ['id' => $f['id']]) ?>"
                              method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="dismiss">
                            <button type="submit" class="button delete-link" data-confirm="Отклонить жалобу?">
                                ✗ Отклонить
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">✅ Нет жалоб, ожидающих рассмотрения.</p>
<?php endif; ?>

<hr>

<h2>📜 Последние жалобы (включая обработанные)</h2>
<?php if (!empty($recentFlags)): ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Тип</th>
                <th>Цель</th>
                <th>Причина</th>
                <th>Автор</th>
                <th>Статус</th>
                <th>Модератор</th>
                <th>Дата</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentFlags as $f): ?>
                <tr>
                    <td><?= (int) $f['id'] ?></td>
                    <td><?= $f['flaggable_type'] === 'story' ? '📰' : '💬' ?></td>
                    <td>#<?= (int) $f['flaggable_id'] ?></td>
                    <td><?= e($reasons[$f['reason']] ?? $f['reason']) ?></td>
                    <td><?= e($f['reporter_name'] ?? '—') ?></td>
                    <td>
                        <?php
                        $statusMap = [
                            'pending'   => '⏳ Ожидает',
                            'resolved'  => '✓ Подтверждена',
                            'dismissed' => '✗ Отклонена',
                        ];
                        ?>
                        <?= $statusMap[$f['status']] ?? '?' ?>
                    </td>
                    <td><?= e($f['resolver_name'] ?? '—') ?></td>
                    <td><code><?= e($f['created_at']) ?></code></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">Жалоб пока не было.</p>
<?php endif; ?>