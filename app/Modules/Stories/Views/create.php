<h1>Создание публикации</h1>

<?php if (!empty($error)): ?>
<div role="alert" class="alert is-danger">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form action="/stories/create" method="POST" id="story-form">
    <?= csrf_field() ?>

    <?php partial('Common::_tags', [
        'availableTags' => $availableTags,
        'selectedTagIds' => isset($old['tags']) ? array_map('intval', $old['tags']) : [],
    ]); ?>

    <?php
    partial('Common::_editor', [
        'editor' => [
            'name' => 'description',
            'value' => '', // При создании всегда пусто
            'placeholder' => 'Расскажите подробнее о вашей ссылке или задайте вопрос...',
            'label' => 'Текст обсуждения',
            'hint' => 'Первая строка, оформленная как заголовок (H1 или H2), станет заголовком статьи. Используйте меню блоков (/).',
        ]
    ]);
    ?>

    <div class="form-group">
        <label>
            <input type="checkbox" name="user_is_following" value="1" checked>
            Получать уведомления о новых комментариях к этой истории.
        </label><br>
        <small class="form-text text-muted hint">
            Вы будете получать уведомления о всех новых комментариях в этой истории.
        </small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="comments_disabled" value="1">
            Отключить комментарии к этой истории.
        </label><br>
        <small class="form-text text-muted hint">
            Читатели не смогут оставлять комментарии под этой публикацией.
        </small>
    </div>

    <div class="form-actions v-center">
        <button type="submit" name="action" value="publish">Опубликовать</button>
        <button type="submit" name="action" value="draft" class="btn btn--secondary">Сохранить черновик</button>
        <a href="/">Отмена</a>
    </div>
</form>
