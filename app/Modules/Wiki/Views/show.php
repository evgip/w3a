<article class="wiki-page">
    <!-- Хлебные крошки -->
    <nav class="breadcrumbs">
        <a href="<?= route('home') ?>">Главная</a> →
        <a href="<?= route('tags.index') ?>">Теги</a> →
        <a href="/t/<?= e($tag['tag']) ?>">#<?= e($tag['name']) ?></a> →
        <a href="/t/<?= e($tag['tag']) ?>/wiki">Wiki</a> →
        <span><?= e($page['title']) ?></span>
    </nav>

    <header class="wiki-header">
        <h1><?= e($page['title']) ?></h1>

        <div class="wiki-meta">
            <span class="wiki-meta-item">
                👤 Автор: <a href="/user/<?= e($page['author_name']) ?>"><?= e($page['author_name']) ?></a>
            </span>

            <span class="wiki-meta-item">
                📅 Обновлено: <?= dt($page['updated_at']) ?>
            </span>

            <span class="wiki-meta-item">
                👁️ <?= $page['view_count'] ?> <?= plural($page['view_count'], ['просмотр', 'просмотра', 'просмотров']) ?>
            </span>

            <?php if (!empty($page['is_primary'])): ?>
                <span class="badge">Основная страница</span>
            <?php endif; ?>
        </div>

        <?php if (\App\Modules\Auth\Services\Auth::check()): ?>
            <?php
            $userId = \App\Modules\Auth\Services\Auth::id();

            // Получаем сервис через new, как в других views проекта
            $permissionService = new \App\Modules\Wiki\Services\WikiPermissionService(
                new \App\Modules\Wiki\Models\WikiPermission(),
                new \App\Modules\Tags\Models\Tag()
            );

            $canEdit = $permissionService->canEditPage($page, $userId);
            $canDelete = $permissionService->canDeletePage($page, $userId);
            ?>

            <?php if ($canEdit || $canDelete): ?>
                <div class="wiki-actions">
                    <?php if ($canEdit): ?>
                        <a href="/t/<?= e($tag['tag']) ?>/wiki/<?= $page['id'] ?>/edit" class="btn btn-sm">
                            ✏️ Редактировать
                        </a>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <form action="/t/<?= e($tag['tag']) ?>/wiki/<?= $page['id'] ?>/delete"
                            method="POST"
                            onsubmit="return confirm('Вы уверены, что хотите удалить эту страницу?')"
                            style="display: inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">
                                🗑️ Удалить
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <div class="wiki-content">
        <?= $page['rendered_content'] ?>
    </div>

    <footer class="wiki-footer">
        <div class="wiki-navigation">
            <a href="/t/<?= e($tag['tag']) ?>/wiki" class="btn">
                ← Назад к wiki тега #<?= e($tag['name']) ?>
            </a>
        </div>

        <?php if ($page['created_at'] != $page['updated_at']): ?>
            <p class="wiki-last-edited">
                <small class="form-text text-muted">
                    Создано: <?= dt($page['created_at']) ?> |
                    Последнее изменение: <?= dt($page['updated_at']) ?>
                </small>
            </p>
        <?php endif; ?>
    </footer>
</article>