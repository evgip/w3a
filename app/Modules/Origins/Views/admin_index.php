<?php
/**
 * Административная панель управления доменами
 */
?>

<h1>🌐 Управление доменами (Origins)</h1>

<p class="hint">
    Модераторы могут банить целые домены (спам-сайты, фейковые новости).
    Истории с забаненных доменов автоматически отклоняются при публикации.
</p>

<hr>

<!-- ФОРМА БЫСТРОГО БАНА -->
<div class="form-field-group">
    <h3>➕ Заблокировать новый домен</h3>
    <form action="<?= route('admin.domains.ban') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="domain">Домен <span class="form-field-hint-inline">(обязательно)</span></label>
            <input type="text" id="domain" name="domain" required class="form-input-wide"
                   placeholder="например: spam-site.com"
                   pattern="[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]*(\.[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]*)*\.[a-zA-Z]{2,}">
            <div class="hint">Введите домен без протокола (https://) и пути.</div>
        </div>

        <div class="form-field-group">
            <label for="ban_reason">Причина блокировки <span class="form-field-hint-inline">(обязательно)</span></label>
            <input type="text" id="ban_reason" name="ban_reason" required class="form-input-wide"
                   placeholder="например: Массовый спам, фишинг, фейковые новости">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">🔒 Заблокировать домен</button>
        </div>
    </form>
</div>

<hr>

<!-- ТАБЛИЦА ВСЕХ ДОМЕНОВ -->
<div class="form-field-group">
    <h3>📋 Реестр доменов
        <span class="hint">(заблокировано: <strong><?= (int) $totalBanned ?></strong>)</span>
    </h3>
</div>

<?php if (!empty($allDomains)): ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Домен</th>
                <th>Статус</th>
                <th>Причина</th>
                <th>Заблокировал</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allDomains as $domain): ?>
                <tr>
                    <td><?= (int) $domain['id'] ?></td>
                    <td>
                        <code><?= e($domain['domain']) ?></code>
                    </td>
                    <td>
                        <?php if ($domain['status'] === 'banned'): ?>
                            <span style="color: #ac130d; font-weight: bold;">🚫 Забанен</span>
                        <?php else: ?>
                            <span style="color: #2e8b57;">✅ Разрешён</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($domain['ban_reason'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($domain['banned_by_name'])): ?>
                            <?= e($domain['banned_by_name']) ?>
                        <?php else: ?>
                            <em>система</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= e($domain['created_at'] ?? '—') ?></code>
                    </td>
                    <td>
                        <?php if ($domain['status'] === 'banned'): ?>
                            <form action="<?= route('admin.domains.unban', ['id' => $domain['id']]) ?>"
                                  method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="button" style="color: #2e8b57;"
                                        onclick="return confirm('Разблокировать домен «<?= e($domain['domain']) ?>»?');">
                                    🔓 Разбанить
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">Реестр доменов пуст. Ни один домен ещё не был заблокирован.</p>
<?php endif; ?>