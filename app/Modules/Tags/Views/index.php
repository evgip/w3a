<h1><?= e($title) ?></h1>
<?php if (empty($categories)): ?>
    <p class="hint">Категории ещё не созданы.</p>
<?php else: ?>

    <div class="categories-grid">
        <?php foreach ($categories as $category): ?>
            <section class="category-card">
                <header class="category-header">
                    <h2>
                        <a href="<?= route('categories.show', ['slug' => $category['slug']]) ?>">
                            <?= e($category['name']) ?>
                        </a>
						<sup><?= (int)$category['tags_count'] ?></sup>
                    </h2>
                </header>

                <?php if (!empty($category['description'])): ?>
                    <p class="hint"><?= e($category['description']) ?></p>
                <?php endif; ?>

                <?php
                $catTags = $tagsByCategory[$category['id']] ?? [];
                ?>

                <?php if (!empty($catTags)): ?>
                    <ul class="tag-list">
                        <?php foreach ($catTags as $tag): ?>
                            <li>
                                <a href="<?= route('tags.filter', ['tagname' => $tag['tag']]) ?>" class="tag tag-<?= e($tag['tag']); ?>" id="<?= e($tag['tag']); ?>">
                                    <?= e($tag['name']) ?>
                                </a>
                                <?php if (!empty($tag['description'])): ?>
                                    <span class="tag-desc">— <?= e($tag['description']) ?></span>
                                <?php endif; ?>
                                <?php if (($tag['stories_count'] ?? 0) > 0): ?>
                                    <span class="tag-count">(<?= (int)$tag['stories_count'] ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="hint">В этой категории пока нет тегов.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

<?php endif; ?>