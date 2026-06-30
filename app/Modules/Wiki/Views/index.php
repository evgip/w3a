<div class="wiki-index">
    <!-- Хлебные крошки -->
    <nav class="breadcrumbs">
        <a href="/">Главная</a> →
        <a href="/t/<?= e($tag['tag']) ?>">#<?= e($tag['name']) ?></a> →
        <span>Wiki</span>
    </nav>

    <header class="wiki-header">
        <h1>📚 Wiki: #<?= e($tag['name']) ?></h1>

        <?php if ($tag['description']): ?>
            <p class="tag-description"><?= e($tag['description']) ?></p>
        <?php endif; ?>
    </header>

    <div class="wiki-actions">
        <a href="<?= route('wiki.tag.create', ['tag' => $tag['tag']]) ?>" class="btn btn-primary">
            ➕ Создать страницу
        </a>

        <form action="<?= route('wiki.tag.search', ['tag' => $tag['tag']]) ?>" method="GET" class="wiki-search">
            <input type="text" name="q" placeholder="Поиск по wiki..." required>
            <button type="submit" class="btn">🔍 Найти</button>
        </form>
    </div>

    <!-- Основная страница -->
    <?php if ($primaryPage): ?>
        <section class="primary-wiki">
            <h2>📖 Основная документация</h2>
            <article class="wiki-item primary">
                <h3>
                    <a href="<?= route('wiki.tag.show', ['tag' => $tag['tag'], 'slug' => $primaryPage['slug']]) ?>">
                        <?= e($primaryPage['title']) ?>
                    </a>
                </h3>

                <div class="wiki-meta">
                    <span class="author">👤 <?= e($primaryPage['author_name']) ?></span>
                    <span class="date">📅 <?= dt($primaryPage['updated_at']) ?></span>
                    <span class="views">👁️ <?= $primaryPage['view_count'] ?></span>
                </div>

                <div class="wiki-excerpt">
                    <?= truncateDescription($primaryPage['rendered_content'], 300) ?>
                </div>

                <a href="<?= route('wiki.tag.show', ['tag' => $tag['tag'], 'slug' => $primaryPage['slug']]) ?>" class="read-more">
                    Читать полностью →
                </a>
            </article>
        </section>
    <?php endif; ?>

    <!-- Остальные страницы -->
    <?php
    $otherPages = array_filter($pages, fn($p) => $p['id'] != ($primaryPage['id'] ?? 0));
    ?>

    <?php if (!empty($otherPages)): ?>
        <section class="wiki-list">
            <h2>📄 Все страницы</h2>

            <?php foreach ($otherPages as $page): ?>
                <article class="wiki-item">
                    <h3>
                        <a href="<?= route('wiki.tag.show', ['tag' => $tag['tag'], 'slug' => $page['slug']]) ?>">
                            <?= e($page['title']) ?>
                        </a>
                    </h3>

                    <div class="wiki-meta">
                        <span class="author">👤 <?= e($page['author_name']) ?></span>
                        <span class="date">📅 <?= dt($page['updated_at']) ?></span>
                        <span class="views">👁️ <?= $page['view_count'] ?></span>
                    </div>

                    <div class="wiki-excerpt">
                        <?= truncateDescription($page['rendered_content'], 200) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (empty($pages)): ?>
        <div class="empty-state">
            <p>Для этого тега еще нет wiki страниц.</p>
            <p><a href="<?= route('wiki.tag.create', ['tag' => $tag['tag']]) ?>">Создайте первую страницу!</a></p>
        </div>
    <?php endif; ?>
</div>