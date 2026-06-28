<?php
// $changeLog передается из контроллера
$logs = $changeLog ?? [];
?>

<?php if (!empty($logs)): ?>
    <div class="change-log-container">
        <h4>История изменений</h4>
        
        <?php foreach ($logs as $log): ?>
            <div class="story-row">
                <div class="story-details">
                    <strong class="<?= $log['is_community_action'] ? 'comment-link' : '' ?>">
                        <?= e($log['actor_name'] ?? 'Сообщество') ?>
                    </strong>
                    <small class="hint"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></small>
                    <div class="story-description">
                        <?= e($log['action_text']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>