<?php
/**
 * Публичная страница списка забаненных доменов
 * Доступна всем пользователям
 */
?>

<h1>🚫 Заблокированные домены</h1>

<p class="hint">
    Список доменов, заблокированных модераторами за распространение спама, фейковых новостей
    или иное нарушение правил сообщества. Публикации с этих доменов автоматически отклоняются.
</p>

<hr>

<?php if (!empty($bannedDomains)): ?>
    <p class="hint">Всего заблокировано: <strong><?= (int) $totalBanned ?></strong> домен(ов).</p>

    <table class="data">
        <thead>
            <tr>
                <th>Домен</th>
                <th>Причина блокировки</th>
                <th>Дата блокировки</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bannedDomains as $banned): ?>
                <tr>
                    <td>
                        <code><?= e($banned['domain']) ?></code>
                    </td>
                    <td><?= e($banned['ban_reason'] ?? '—') ?></td>
                    <td>
                        <code><?= e($banned['created_at'] ?? '—') ?></code>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">
        ✅ Список заблокированных доменов пуст. Все домены разрешены.
    </p>
<?php endif; ?>