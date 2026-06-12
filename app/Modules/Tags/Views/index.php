<h3 class="tags-page-title">🏷️ Каталог тегов сообщества</h3>
    <p class="tags-page-subtitle">Вы можете кликнуть по любому тегу, чтобы отфильтровать ленту обсуждений по интересующей вас теме.</p>

    <?php if (!empty($tags)): ?>
        <?php 
            // Инициализируем переменную для отслеживания смены категории в цикле
            $currentCategory = ''; 
            $isFirst = true;
        ?>
        <?php foreach ($tags as $tagItem): ?>
            <?php if ($tagItem['category'] !== $currentCategory): ?>
                <!-- Если категория изменилась, закрываем предыдущую сетку grid (кроме самого первого раза) -->
                <?php if (!$isFirst): ?>
                    </div></div> <!-- Закрываем .tags-catalog-grid и .tag-category-section -->
                <?php endif; ?>

                <?php 
                    $currentCategory = $tagItem['category']; 
                    $isFirst = false;
                ?>
                
                <!-- Открываем новый блок категории Lobsters -->
                <div class="tag-category-section">
                    <h4 class="tag-category-title"># <?= htmlspecialchars($currentCategory) ?></h4>
                    <div class="tags-catalog-grid">
            <?php endif; ?>

            <!-- Карточка самого тега -->
            <div class="tag-catalog-card">
                <h4>
                    <a href="<?= route('tags.filter', ['tagname' => $tagItem['tag']]) ?>" class="tag-badge-link tag-badge-large">
                        <?= htmlspecialchars($tagItem['tag']) ?>
                    </a>
                </h4>
                <div class="tag-catalog-desc">
                    <?= htmlspecialchars($tagItem['description'] ?? 'Описание темы пока отсутствует.') ?>
                </div>
            </div>

        <?php endforeach; ?>
        
        </div></div> <!-- Закрываем последние открытые теги grid и section -->
        
    <?php else: ?>
        <p class="comment-empty-text">Список тегов пуст.</p>
    <?php endif; ?>
