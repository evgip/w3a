<h1>Комментарии пользователя <?= e($profileUser['username']) ?></h1>

<?php if (empty($comments)): ?>
    <p class="hint">У пользователя пока нет комментариев.</p>
<?php else: ?>
    <ol class="comments comments-flat">
        <?php foreach ($comments as $comment): ?>
            <?php partial('Comments::_item', [
                'comment' => $comment,
                'currentUserId' => $currentUserId,
                'isAdmin' => $isAdmin,
                'isModerator' => $isModerator,
                'isStoryAuthor' => false,
                'canDownvote' => false,
                'currentVote' => null,
                'showStoryContext' => true,
                'showCollapseToggle' => false,
            ]); ?>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>