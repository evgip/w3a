<?php
// app/Modules/Comments/Views/index.php

$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$isModerator = $isModerator ?? false;
$canDownvote = $canDownvote ?? false;
$currentCommentVotes = $currentCommentVotes ?? [];
$lastReadAt = $lastReadAt ?? null;
$comments = $comments ?? [];
?>

<div class="container">
    <h1>Последние комментарии</h1>
    
    <?php if (empty($comments)): ?>
        <p class="hint">Пока нет комментариев.</p>
    <?php else: ?>
        <ol class="comments comments-flat">
            <?php 
            $dividerShown = false;
            foreach ($comments as $comment): 
                $isNew = $lastReadAt && strtotime($comment['created_at']) > strtotime($lastReadAt);
                $commentId = (int)$comment['id'];
                $currentVote = $currentCommentVotes[$commentId] ?? null;
            ?>
                
                <?php if ($isNew && !$dividerShown): ?>
                    <li class="new-comments-divider">
                        <hr>
                        <span>↑ Новые комментарии ↓</span>
                        <hr>
                    </li>
                    <?php $dividerShown = true; ?>
                <?php endif; ?>
                
                <?php partial('Comments::_item', [
                    'comment' => $comment,
                    'currentUserId' => $currentUserId,
                    'isAdmin' => $isAdmin,
                    'isModerator' => $isModerator,
                    'isStoryAuthor' => false,
                    'canDownvote' => $canDownvote,
                    'currentVote' => $currentVote,
                    'showStoryContext' => true,
                    'showCollapseToggle' => false,
                    'isNew' => $isNew,
                ]); ?>
                
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<style>
/* Плоская лента комментариев (без отступов) */
.comments-flat {
    list-style: none;
    padding-left: 0;
}

.comments-flat .comment {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
}

/* Ссылка на историю в контексте */
.story-context {
    color: #666;
    font-size: 0.9em;
    font-style: italic;
}

/* Разделитель новых комментариев */
.new-comments-divider {
    list-style: none;
    text-align: center;
    margin: 20px 0;
    position: relative;
}

.new-comments-divider hr {
    border: 0;
    border-top: 2px solid #007bff;
    margin: 0;
}

.new-comments-divider span {
    background: white;
    padding: 0 15px;
    color: #007bff;
    font-weight: bold;
    position: relative;
    top: -10px;
}

/* Подсветка новых комментариев */
.comment.is-new {
    border-left: 3px solid #007bff;
    background-color: #f8f9fa;
}
</style>