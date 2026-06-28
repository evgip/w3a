<div id="suggest-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Предложить изменения</h3>

        <div class="flash-notice">
            <strong>Внимание:</strong> Ваши изменения будут предложены сообществу.
            Для применения необходимо, чтобы <?= \App\Modules\Suggestions\Services\SuggestionService::QUORUM_SIZE ?>
            пользователей предложили абсолютно одинаковые изменения.
        </div>

        <form id="suggest-form">
            <input type="hidden" name="target_type" id="suggest-target-type">
            <input type="hidden" name="target_id" id="suggest-target-id">
            <?= csrf_field() ?>

            <div class="form-field-group">
                <label for="suggest-title"><strong>Заголовок</strong></label>
                <input type="text"
                    id="suggest-title"
                    name="title"
                    class="form-input-wide"
                    placeholder="Оставьте пустым, если не меняете">
            </div>

            <div class="form-field-group" id="suggest-tags-group">
                <label><strong>Теги</strong></label>
                <p class="hint">Выберите один или несколько тегов, соответствующих теме публикации:</p>

                <?php foreach ($allTags as $tagItem): ?>
                    <?php
                    $isBound = in_array((int)$tagItem['id'], $currentTagIds ?? []);
                    ?>
                    <div class="tag">
                        <input type="checkbox"
                            name="tags[]"
                            value="<?= (int)$tagItem['id'] ?>"
                            <?= $isBound ? 'checked' : '' ?>>
                        <span><?= e($tagItem['name']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-field-group" id="suggest-text-group" style="display:none;">
                <label for="suggest-text"><strong>Текст</strong></label>
                <textarea id="suggest-text" name="text" rows="5"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit">Отправить предложение</button>
                <button type="button" class="close-modal">Отмена</button>
            </div>
        </form>
    </div>
</div>