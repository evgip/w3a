<?php

/** @var array $categories */ ?>

<h1><?= e($title ?? 'Управление категориями тегов') ?></h1>

<p class="hint">
    Структурированная система группировки тегов. Каждая категория определяет колонку на странице общего каталога тегов.
</p>

<p>
    <a href="<?= route('admin.categories.create') ?>" class="button">+ Добавить новую категорию</a>
</p>

<?php if (empty($categories)): ?>
    <p class="hint">
        Категории пока не созданы.
        <a href="<?= route('admin.categories.create') ?>">Создать первую категорию</a>
    </p>
<?php else: ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Slug</th>
                <th>Описание</th>
                <th>Порядок</th>
                <th>Тегов</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= (int)($cat['id'] ?? 0) ?></td>
                    <td>
                        <strong><?= e($cat['name'] ?? '') ?></strong>
                    </td>
                    <td>
                        <code><?= e($cat['slug'] ?? '') ?></code>
                    </td>
                    <td>
                        <?= e($cat['description'] ?? '') ?: '<span class="hint">—</span>' ?>
                    </td>
                    <td><?= (int)($cat['sort_order'] ?? 0) ?></td>
                    <td><?= (int)($cat['tags_count'] ?? 0) ?></td>
                    <td>
                        <a href="<?= route('admin.categories.edit', ['id' => $cat['id']]) ?>" class="button">
                            Изменить
                        </a>

                        <?php if ((int)($cat['tags_count'] ?? 0) === 0): ?>
                            <form method="POST"
                                action="<?= route('admin.categories.delete', ['id' => $cat['id']]) ?>"
                                style="display:inline;"
                                class="delete-link"
                                data-confirm="Вы уверены, что хотите удалить категорию «<?= e($cat['name']) ?>»?">
                                <?= csrf_field() ?>
                                <button type="submit" class="button" style="color: #ac130d;">
                                    Удалить
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="hint" title="Нельзя удалить категорию, в которой есть теги. Сначала перенесите их.">
                                🔒
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>