<?php
/**
 * @var string $title
 * @var array  $filters
 * @var array  $allTags
 */
?>
<div>
    <h1>Фильтры тегов</h1>
    <p class="hint">
        Истории с отфильтрованными тегами не будут отображаться в вашей ленте.
    </p>

    <h2>Активные фильтры</h2>
    <?php if (empty($filters)): ?>
        <div class="alert alert-notice empty-state">
            <p>У вас пока нет активных фильтров.</p>
        </div>
    <?php else: ?>
        <div class="filters-list">
            <?php foreach ($filters as $filter): ?>
                <div class="filter-item"
                     data-tag-id="<?= e($filter['tag_id']) ?>">
                    
                    <span class="tag tag-filter">
                        #<?= e($filter['tag']) ?>
                    </span>
                    
                    <?php if (!empty($filter['description'])): ?>
                        <span class="tag-description hint">
                            — <?= e($filter['description']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <a class="btn-remove-filter delete">
                         удалить
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Добавить фильтр</h2>
    <div class="form-field-group form-group">
        <select id="tag-select">
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
                class="btn-add-filter">
            Добавить
        </button>
    </div>
</div>