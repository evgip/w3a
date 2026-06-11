<div class="admin-header-panel">
    <h3>🏷️ Управление тегами платформы</h3>
    <a href="<?= route('admin.tags.create') ?>" class="btn-action btn-restore btn-tag-add">
        ➕ Добавить новый тег
    </a>
</div>

<p class="admin-description">Общий список тем сообщества. Изменение тегов здесь мгновенно отражается во всех связанных историях и лентах.</p>

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
                        <span class="tag-badge-link tag-badge-custom">
                            <?= htmlspecialchars($tagItem['tag']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($tagItem['description'] ?? 'Описание отсутствует.') ?></td>
                    <td>
                        <?php if ((int)$tagItem['is_media'] === 1): ?>
                            <span class="badge badge-media">Медиа (Video/PDF)</span>
                        <?php else: ?>
                            <span class="badge badge-standard">Стандартная тема</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <a href="<?= route('admin.tags.edit', ['id' => $tagItem['id']]) ?>" class="btn-action btn-restore btn-tag-edit">
                            📝 Изменить
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="table-empty-message">Теги не настроены в системе.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>