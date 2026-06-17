<?php 
/** 
 * @var array $tags 
 */ 
?>

<h1><?= e($title ?? 'Управление тегами') ?></h1>

<p class="hint">
    Теги используются для классификации историй. Каждый тег должен быть привязан к определенной категории.
</p>

<p>
    <a href="<?= route('admin.tags.create') ?>" class="button">+ Добавить новый тег</a>
</p>

<?php if (empty($tags)): ?>
    <p class="hint">
        Теги пока не созданы. 
        <a href="<?= route('admin.tags.create') ?>">Создать первый тег</a>
    </p>
<?php else: ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Тег</th>
                <th>Описание</th>
                <th>Категория</th>
                <th>Медиа</th>
                <th>Историй</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><?= (int)($tag['id'] ?? 0) ?></td>
                    <td>
                        <strong><code><?= e($tag['tag'] ?? '') ?></code></strong>
                    </td>
                    <td>
                        <?= e($tag['description'] ?? '') ?: '<span class="hint">—</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($tag['category_name'])): ?>
                            <?= e($tag['category_name']) ?>
                        <?php else: ?>
                            <span class="hint">Не указана</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= (int)($tag['is_media'] ?? 0) ? 'Да' : 'Нет' ?>
                    </td>
                    <td><?= (int)($tag['stories_count'] ?? 0) ?></td>
                    <td>
                        <a href="<?= route('admin.tags.edit', ['id' => $tag['id']]) ?>" class="button">
                            Изменить
                        </a>
                        
                        <form method="POST" 
                              action="<?= route('admin.tags.delete', ['id' => $tag['id']]) ?>" 
                              style="display:inline;"
                              onsubmit="return confirm('Вы уверены, что хотите удалить тег «<?= e($tag['tag']) ?>»? Это действие также удалит связи с историями.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="button" style="color: #ac130d;">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>