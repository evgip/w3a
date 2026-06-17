<h1>🧱 Управление сетевым экраном (Блокировка IP)</h1>

<p class="hint">
    Система фильтрации нежелательных IP-адресов. Запросы с заблокированных адресов отклоняются до инициализации модулей приложения.
</p>

<hr>

<!-- ФОРМА ДОБАВЛЕНИЯ IP В ЧЕРНЫЙ СПИСОК -->
<div class="form-field-group">
    <h3>➕ Внести новый IP-адрес в черный список</h3>
    
    <form action="<?= route('admin.firewall.ban') ?>" method="POST">
        <?= csrf_field() ?>
        
        <div class="form-field-group">
            <label for="ip_address">IP-адрес (IPv4 / IPv6) <span class="form-field-hint-inline">(обязательно)</span></label>
            <input type="text" id="ip_address" name="ip_address" required class="form-input-wide"
                   placeholder="например: 192.168.1.100 или 2001:db8::1">
            <div class="hint">Введите полный IP-адрес, который нужно заблокировать.</div>
        </div>

        <div class="form-field-group">
            <label for="reason">Официальная причина блокировки <span class="form-field-hint-inline">(обязательно)</span></label>
            <input type="text" id="reason" name="reason" required class="form-input-wide"
                   placeholder="например: Попытка подбора паролей (брутфорс), DDoS-атака">
            <div class="hint">Краткое описание причины для журнала аудита.</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">🔒 Заблокировать IP-адрес</button>
        </div>
    </form>
</div>

<hr>

<!-- ТАБЛИЦА ЗАБЛОКИРОВАННЫХ IP -->
<div class="form-field-group">
    <h3>📋 Текущие активные блокировки</h3>
    <p class="hint">
        Список всех IP-адресов, находящихся в черном списке сетевого экрана.
    </p>
</div>

<?php if (!empty($bannedIps)): ?>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>IP-адрес</th>
                <th>Причина ограничения доступа</th>
                <th>Дата блокировки</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bannedIps as $ban): ?>
                <tr>
                    <td><?= (int)($ban['id'] ?? 0) ?></td>
                    <td>
                        <code><?= e($ban['ip_address'] ?? '—') ?></code>
                    </td>
                    <td><?= e($ban['reason'] ?? '—') ?></td>
                    <td>
                        <code><?= e($ban['created_at'] ?? '—') ?></code>
                    </td>
                    <td>
                        <form action="<?= route('admin.firewall.unban', ['id' => $ban['id']]) ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="button" style="color: #ac130d;" 
                                    onclick="return confirm('Вы уверены, что хотите разблокировать IP-адрес «<?= e($ban['ip_address']) ?>»?');">
                                🔓 Разблокировать
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="hint">
        Черный список пуст. Сетевой экран не зафиксировал угроз.
    </p>
<?php endif; ?>