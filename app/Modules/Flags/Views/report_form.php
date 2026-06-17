<?php
/**
 * Форма подачи жалобы на контент
 */
?>

<div class="container">
    <h1>🚩 Пожаловаться на <?= $type === 'story' ? 'новость' : 'комментарий' ?></h1>

    <p class="hint">
        Модераторы рассмотрят вашу жалобу. Если вы считаете, что контент нарушает правила —
        заполните форму ниже. Злоупотребление жалобами может привести к ограничению аккаунта.
    </p>

    <form action="<?= route('flags.submit') ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="flaggable_type" value="<?= e($type) ?>">
        <input type="hidden" name="flaggable_id" value="<?= (int) $targetId ?>">

        <div class="form-field-group">
            <label for="reason">Причина жалобы <span class="form-field-hint-inline">(обязательно)</span></label>
            <select name="reason" id="reason" required>
                <option value="">— Выберите причину —</option>
                <?php foreach ($reasons as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field-group">
            <label for="comment">Пояснение <span class="form-field-hint-inline">(необязательно)</span></label>
            <textarea name="comment" id="comment" rows="4" maxlength="500"
                      placeholder="Опишите подробнее, в чём заключается нарушение..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button">🚩 Отправить жалобу</button>
            <a href="javascript:history.back()" class="button">Отмена</a>
        </div>
    </form>
</div>