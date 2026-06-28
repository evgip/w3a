<?php
// $changeLog передается из контроллера
$logs = $changeLog ?? [];
?>

<?php if (!empty($logs)): ?>

<div class="story_content">
<details>
	<summary>
		<b>История изменений</b>
	</summary>
	<div class="suggestions-container">

	   <?php foreach ($logs as $log): ?>
				 
					 
						<strong class="<?= $log['is_community_action'] ? 'comment-link' : '' ?>">
							<?= e($log['actor_name'] ?? 'Сообщество') ?>
						</strong>
						<small class="hint"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></small>
						<div class="story-description">
							<?= e($log['action_text']) ?>
						</div>
			 
			<?php endforeach; ?>

	</div>
</details>
</div>

  
<?php endif; ?>