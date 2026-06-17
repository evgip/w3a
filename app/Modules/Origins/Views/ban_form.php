<?php
/**
 * Страница формы бана домена (если нужна отдельная страница)
 */
?>

<h1>🔒 Заблокировать домен</h1>

<div class="form-field-group">
    <form action="<?= route('admin.domains.ban') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="domain">Домен <span class="form-field-hint-inline">(обязательно)</span></label>
            <input type="text" id="domain" name="domain" required class="form-input-wide"
                   placeholder="например: spam-site.com">
            <div class="hint">Укажите домен без протокола (https://) и пути.</div>
        </div>

        <div class="form-field-group">
            <label for="ban_reason">Причина блокировки</label>
            <input type="text" id="ban_reason" name="ban_reason" class="form-input-wide"
                   placeholder="Спам, фишинг, фейковые новости...">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">🔒 Заблокировать</button>
            <a href="/admin/domains" class="button">Отмена</a>
        </div>
    </form>
</div>