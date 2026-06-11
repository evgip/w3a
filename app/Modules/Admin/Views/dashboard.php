<div class="card-grid">
    <div class="card">
        <h4>Всего аккаунтов</h4>
        <div class="value"><?= (int)$totalUsers ?></div>
    </div>
    <div class="card">
        <h4>Администраторы</h4>
        <div class="value" style="color: #e74c3c;"><?= (int)$totalAdmins ?></div>
    </div>
    <div class="card">
        <h4>Обычные пользователи</h4>
        <div class="value" style="color: #3498db;"><?= (int)($totalUsers - $totalAdmins) ?></div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h3>Добро пожаловать в панель мониторинга!</h3>
    <p>Здесь вы можете отслеживать метрики активности пользователей, управлять системными конфигурациями и просматривать логи генерации страниц.</p>
</div>
