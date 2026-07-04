<?php /** @var array $invitations */ ?>
<?php /** @var int $activeCount */ ?>
<?php /** @var int $maxInvitations */ ?>
<?php /** @var bool $hasEnoughKarma */ ?>
<?php /** @var int $minKarma */ ?>

<div class="container mt-4">
    <h1>🎟️ Управление приглашениями</h1>

    <?= render_flashes() ?>

    <?php if (!$hasEnoughKarma): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Недостаточно кармы!</strong><br>
            Для создания приглашений необходимо минимум <strong><?= $minKarma ?></strong> кармы.
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Создать новое приглашение</h5>
        </div>
        <div class="card-body">
            <p>
                Активных приглашений: <strong><?= $activeCount ?></strong> из
                <strong><?= $maxInvitations ?></strong>
                (<?= plural($activeCount, ['осталось', 'осталось', 'осталось']) ?>
                 <?= $maxInvitations - $activeCount ?>)
            </p>

            <?php if ($hasEnoughKarma && $activeCount < $maxInvitations): ?>
                <form method="POST" action="<?= route('invitations.create') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email получателя (опционально)</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Оставьте пустым, чтобы получить ссылку для себя">
                        <small class="text-muted">Если укажете email, приглашение будет отправлено автоматически</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Создать приглашение</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    Вы достигли лимита активных приглашений или недостаточно кармы.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Ваши приглашения</h5>
        </div>
        <div class="card-body">
            <?php if (empty($invitations)): ?>
                <p class="text-muted mb-0">У вас пока нет приглашений.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Код</th>
                                <th>Email</th>
                                <th>Статус</th>
                                <th>Использовал</th>
                                <th>Создано</th>
                                <th>Истекает</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitations as $inv): ?>
                                <?php
                                    $statusClass = match($inv['status']) {
                                        'pending' => 'bg-warning text-dark',
                                        'accepted' => 'bg-success',
                                        'expired' => 'bg-secondary',
                                        'revoked' => 'bg-danger',
                                        default => 'bg-light text-dark'
                                    };
                                    $statusText = match($inv['status']) {
                                        'pending' => 'Ожидает',
                                        'accepted' => 'Принято',
                                        'expired' => 'Истекло',
                                        'revoked' => 'Отозвано',
                                        default => $inv['status']
                                    };
                                    $inviteUrl = route('home') . 'register/invite/' . $inv['code'];
                                ?>
                                <tr>
                                    <td>
                                        <code class="user-select-all" style="font-size: 0.85em;"><?= e($inv['code']) ?></code>
                                    </td>
                                    <td>
                                        <?= !empty($inv['invitee_email']) ? e($inv['invitee_email']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($inv['invitee_username'])): ?>
                                            <a href="/u/<?= e($inv['invitee_username']) ?>">@<?= e($inv['invitee_username']) ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= dt($inv['created_at']) ?></td>
                                    <td><?= dt($inv['expires_at']) ?></td>
                                    <td>
                                        <?php if ($inv['status'] === 'pending' && strtotime($inv['expires_at']) > time()): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-copy"
                                                        data-url="<?= e($inviteUrl) ?>" title="Скопировать ссылку">
                                                    📋
                                                </button>
                                                <form method="POST" action="<?= route('invitations.revoke', ['id' => $inv['id']]) ?>"
                                                      style="display: inline;" 
													  class="restore-link" 
													  data-confirm="Отозвать это приглашение?">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-danger" title="Отозвать">
                                                        ✖
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                const originalText = this.innerHTML;
                this.innerHTML = '✅';
                setTimeout(() => { this.innerHTML = originalText; }, 1500);
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Ссылка скопирована: ' + url);
            });
        });
    });
});
</script>