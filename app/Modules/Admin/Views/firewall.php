<div class="admin-header-row">
    <h3>🧱 Управление сетевым экраном (Блокировка IP)</h3>
</div>

<!-- BLOCK IP FORM COMPONENT -->
<div class="admin-edit-panel-card firewall-form-card">
    <h4>➕ Внести новый IP-адрес в черный список</h4>
    
    <form action="<?= route('admin.firewall.ban') ?>" method="POST" class="firewall-inline-form">
        <?= $request->csrfField() ?>
        
        <div class="admin-form-group firewall-form-ip-group">
            <label>IP-адрес (IPv4 / IPv6):</label>
            <input type="text" name="ip_address" required placeholder="например: 127.0.0.1" class="firewall-form-input">
        </div>

        <div class="admin-form-group firewall-form-reason-group">
            <label>Официальная причина блокировки:</label>
            <input type="text" name="reason" required placeholder="например: Попытка подбора паролей (брутфорс), DDoS" class="firewall-form-input">
        </div>

        <button type="submit" class="btn btn-primary btn-firewall-ban">
            🔒 Заблокировать IP
        </button>
    </form>
</div>

<!-- BLACKLIST DATA SHEET MATRIX -->
<p class="admin-subtitle-desc">Текущие активные блокировки сетевого экрана. Запросы с этих IP сбрасываются до инициализации модулей.</p>

<table>
    <thead>
        <tr>
            <th class="w-60">ID</th>
            <th>IP-адрес</th>
            <th>Причина ограничения доступа</th>
            <th>Дата блокировки</th>
            <th class="text-right w-180">Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($bannedIps)): ?>
            <?php foreach ($bannedIps as $ban): ?>
                <tr>
                    <td><?= (int)$ban['id'] ?></td>
                    <td>
                        <code class="security-alert-toast-ip firewall-ip-badge">
                            <?= e($ban['ip_address']) ?>
                        </code>
                    </td>
                    <td><?= e($ban['reason']) ?></td>
                    <td><small class="text-muted"><?= e($ban['created_at']) ?></small></td>
                    <td class="text-right">
                        <form action="<?= route('admin.firewall.unban', ['id' => $ban['id']]) ?>" method="POST" class="firewall-unban-form">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="btn-action btn-restore btn-firewall-unban">
                                🔓 Разблокировать
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center text-muted firewall-empty-td">Черный список пуст. Сетевой экран не зафиксировал угроз.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

