<?php
/**
 * @var string $title
 * @var array  $filters
 * @var array  $allTags
 */
?>
<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h1>Фильтры тегов</h1>
    <p class="hint" style="color: #666; margin-bottom: 20px;">
        Истории с отфильтрованными тегами не будут отображаться в вашей ленте.
    </p>

    <h2>Активные фильтры</h2>
    <?php if (empty($filters)): ?>
        <div class="empty-state" style="padding: 20px; background: #f9f9f9; border-radius: 4px; text-align: center;">
            <p>У вас пока нет активных фильтров.</p>
        </div>
    <?php else: ?>
        <div class="filters-list" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px;">
            <?php foreach ($filters as $filter): ?>
                <div class="filter-item" 
                     data-tag-id="<?= e($filter['tag_id']) ?>" 
                     style="display: flex; align-items: center; gap: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    
                    <span class="tag-badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                        #<?= e($filter['tag']) ?>
                    </span>
                    
                    <?php if (!empty($filter['description'])): ?>
                        <span class="tag-description" style="color: #666; font-size: 0.9em;">
                            <?= e($filter['description']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <button type="button" 
                            class="btn-remove-filter" 
                            style="margin-left: auto; padding: 6px 12px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 4px; cursor: pointer;">
                        Удалить
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Добавить фильтр</h2>
    <div class="form-group" style="display: flex; gap: 10px; align-items: flex-start;">
        <select id="tag-select" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <option value="">Выберите тег для скрытия...</option>
            <?php foreach ($allTags as $tag): ?>
                <?php 
                // Исключаем теги, которые уже в фильтрах
                $isFiltered = false;
                foreach ($filters as $f) {
                    if ($f['tag_id'] == $tag['id']) {
                        $isFiltered = true;
                        break;
                    }
                }
                if (!$isFiltered): 
                ?>
                    <option value="<?= e($tag['id']) ?>">
                        #<?= e($tag['tag']) ?> (<?= (int)($tag['stories_count'] ?? 0) ?>)
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        
        <button type="button" 
                id="btn-add-filter" 
                style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            Добавить
        </button>
    </div>
</div>