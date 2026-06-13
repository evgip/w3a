<?php
/**
 * Компонент метаданных (byline)
 * 
 * @var array $story         - данные публикации
 * @var int   $currentUserId - ID текущего пользователя
 */
?>
<div class="byline">
    <?php include __DIR__ . '/_avatar.php'; ?>
    
    <?php
    // Переопределяем переменные для _avatar.php
    $filename = $story['author_avatar'] ?? null;
    $name = $story['author_name'] ?? null;
    $sizeClass = '';
    ?>
    
    <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>"
       <?= (int)$story['user_id'] === $currentUserId ? 'class="user_is_author"' : '' ?>>
        <?= e($story['author_name']) ?>
    </a>
    
    <span class="divider">|</span>
    <span title="<?= e(date('d.m.Y H:i:s', strtotime($story['created_at']))) ?>">
        <?= e(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
    </span>
</div>