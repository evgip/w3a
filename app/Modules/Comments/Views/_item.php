<?php
/**
 * Универсальный partial для отображения одного комментария
 * 
 * @var array $comment           - данные комментария
 * @var int   $currentUserId     - ID текущего пользователя
 * @var bool  $isAdmin           - является ли текущий пользователь админом
 * @var bool  $isModerator       - является ли текущий пользователь модератором
 * @var bool  $isStoryAuthor     - является ли текущий пользователь автором истории
 * @var bool  $canDownvote       - можно ли голосовать против
 * @var mixed $currentVote       - текущий голос пользователя за этот комментарий
 * @var bool  $showStoryContext  - показывать ли ссылку на историю (для глобальной ленты)
 * @var bool  $showCollapseToggle - показывать ли кнопку сворачивания
 * @var bool  $isNew             - является ли комментарий новым
 */
$commentId = (int)$comment['id'];
$isDeleted = !empty($comment['deleted_at']);
$isOwner = ((int)$comment['user_id'] === $currentUserId);

// Опциональные параметры с дефолтами
$showStoryContext = $showStoryContext ?? false;
$showCollapseToggle = $showCollapseToggle ?? true;

// Логика подсветки: комментарий новый, если его ID больше отметки прочтения
$isNew = $lastReadCommentId > 0 && $commentId > $lastReadCommentId;

?>

<li class="comment comment-thread <?= $isDeleted ? 'deleted' : '' ?> <?= $isNew ? 'is-new' : '' ?>" 
    data-comment-id="<?= $commentId ?>" 
    id="comment-block-<?= $commentId ?>">

    <!-- Голосование -->
    <?php if (!$isDeleted): ?>
        <div class="comment_votes">
            <?php partial('Votes::_voters', [
                'type' => 'comment',
                'id' => $commentId,
                'score' => (int)$comment['score'],
                'currentVoteState' => $currentVote ?? null,
                'canDownvote' => $canDownvote ?? false,
                'isLoggedIn' => $currentUserId > 0,
                'contentOwnerId' => $isStoryAuthor ?? false,
            ]); ?>
        </div>
    <?php else: ?>
        <div class="comment_votes">
            <span class="score"><?= (int)$comment['score'] ?></span>
        </div>
    <?php endif; ?>

    <!-- Обёртка для метаданных, текста и действий -->
    <div class="comment_body">

        <!-- Метаданные комментария -->
        <div class="comment-header">
            <?php if ($showCollapseToggle): ?>
                <span class="collapse-toggle" title="Свернуть ветку">[–]</span>
            <?php endif; ?>
            
            <?php partial('Comments::_comment_meta', [
                'comment' => $comment,
                'currentUserId' => $currentUserId,
                'isAdmin' => $isAdmin,
                'isModerator' => $isModerator ?? false,
            ]); ?>
            
            <?php if ($showStoryContext && !empty($comment['story_title'])): ?>
				<div class="comment_meta">
					<span class="divider">|</span>
					<a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#comment-block-<?= $commentId ?>" 
					   class="story-context">
						на: <?= e($comment['story_title']) ?>
					</a>
				</div>
            <?php endif; ?>
        </div>

        <!-- Тело комментария -->
        <?php if (!$isDeleted): ?>
            <div class="comment_text" id="comment-text-content-<?= $commentId ?>"
                 data-raw="<?= e($comment['comment'], ENT_QUOTES, 'UTF-8') ?>">
                <?= markdown_comment($comment['comment']) ?>
            </div>

            <!-- Действия -->
            <div class="comment_actions">
				<?php if ($currentUserId > 0): ?>
					<?php if ($showStoryContext): ?>
						<!-- В плоской ленте: ведём на страницу истории -->
						<a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#reply-to-<?= $commentId ?>" 
						   class="comment-reply-link" 
						   data-id="<?= $commentId ?>">Ответить</a>
					<?php else: ?>
						<!-- В истории: простой якорь -->
						<a href="#reply-to-<?= $commentId ?>" 
						   class="comment-reply-link" 
						   data-id="<?= $commentId ?>">Ответить</a>
					<?php endif; ?>
				<?php endif; ?>


 

                <?php if ($isOwner || $isAdmin || ($isModerator ?? false)): ?>
                    <span class="divider">|</span>
                    <a class="comment-edit-trigger" data-id="<?= $commentId ?>">Редактировать</a>
                    <span class="divider">|</span>
                    <form action="/comments/<?= $commentId ?>/delete" method="POST" 
                          class="inline-form js-confirm-delete" 
                          data-confirm-message="Удалить комментарий?">
                        <?= csrf_field() ?>
                        <button type="submit">Удалить</button>
                    </form>
                <?php endif; ?>

                <?php if ($currentUserId > 0): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('flags.report', ['type' => 'comment', 'id' => $commentId]) ?>"
                       class="flag-link"
                       title="Пожаловаться на контент"
                       data-confirm="Вы уверены, что хотите подать жалобу?">
                        🚩
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Рекурсия ветки (только для show.php) -->
    <?php if (!empty($renderTree) && !empty($commentsTree)): ?>
        <?php $renderTree($commentId); ?>
    <?php endif; ?>

</li>