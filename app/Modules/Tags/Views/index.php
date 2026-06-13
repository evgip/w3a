<h1><?= e($title) ?></h1>

<?php if (empty($tags)): ?>
    <p class="hint">Теги ещё не созданы.</p>
<?php else: ?>

    <?php 
    // Группируем теги по категориям
    $groupedTags = [];
    foreach ($tags as $tag) {
        $category = $tag['category'] ?? 'Другое';
        if (!isset($groupedTags[$category])) {
            $groupedTags[$category] = [];
        }
        $groupedTags[$category][] = $tag;
    }
    ?>

    <?php foreach ($groupedTags as $category => $categoryTags): ?>
        
        <h2><?= e($category) ?></h2>
        
        <ul>
            <?php foreach ($categoryTags as $tag): ?>
                <li>
                    <a href="<?= route('tags.filter', ['tagname' => $tag['tag']]) ?>" class="tag">
                        <?= e($tag['tag']) ?>
                    </a>
                    <?php if (!empty($tag['description'])): ?>
                        — <?= e($tag['description']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        
    <?php endforeach; ?>

<?php endif; ?>