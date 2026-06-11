<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3 style="margin: 0;">🏷️ Управление тегами платформы</h3>
    <a href="<?= route('admin.tags.create') ?>" class="btn-action btn-restore" style="text-decoration: none; padding: 10px 15px;">
        ➕ Добавить новый тег
    </a>
</div>

<p style="color: #7f8c8d; margin-bottom: 20px;">Общий список тем сообщества. Изменение тегов здесь мгновенно отражается во всех связанных историях и лентах.</p>

<table>
    <thead>
        <tr>
            <th class="w-60">ID</th>
            <th>Слуг (Тег)</th>
            <th>Описание назначения</th>
            <th>Тип контента</th>
            <th class="text-right w-180">Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($tags)): ?>
            <?php foreach ($tags as $tagItem): ?>
                <tr>
                    <td><?= (int)$tagItem['id'] ?></td>
                    <td>
                        <span class="tag-badge-link" style="font-size: 13px; font-weight: bold; pointer-events: none;">
                            <?= htmlspecialchars($tagItem['tag']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($tagItem['description'] ?? 'Описание отсутствует.') ?></td>
                    <td>
                        <?php if ((int)$tagItem['is_media'] === 1): ?>
                            <span class="badge" style="background: #9b59b6; color: white;">Медиа (Video/PDF)</span>
                        <?php else: ?>
                            <span class="badge" style="background: #95a5a6; color: white;">Стандартная тема</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <a href="<?= route('admin.tags.edit', ['id' => $tagItem['id']]) ?>" class="btn-action btn-restore" style="text-decoration: none; font-size: 11px; padding: 5px 10px; background: #34495e;">
                            📝 Изменить
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center; color: #95a5a6; padding: 30px;">Теги не настроены в системе.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
