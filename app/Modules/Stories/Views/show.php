<?php

$user_id = $_SESSION['user_id'] ?? false;

$voteModel = new \App\Modules\Votes\Models\Vote();
$currentUserId = \App\Core\Auth::check() ? (int)$user_id : 0;

$commentsTree = $commentsTree ?? [];

$minKarmaForDownvote = config_int('config.app.min_karma_for_downvote', 10);

$canUserDownvote = false;
if ($currentUserId > 0) {
    $userModel = new \App\Modules\Users\Models\User();
    $viewerKarma = $userModel->getUserKarma($currentUserId);
    $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
}

$isAuthor = (int)$story['user_id'] === (int)$user_id;
$isStoryDeleted = !empty($story['deleted_at']);
$isAdmin = \App\Core\Auth::isAdmin();
$isModerator =  \App\Core\Auth::isModerator();

$hasNewComments = false;

$showMarkReadButton = (\App\Core\Auth::check() && ($newCount ?? 0) > 0);
?>

<!-- КАРТОЧКА ПУБЛИКАЦИИ -->
<ol class="stories">
    <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

        <!-- Голосование -->
        <?php partial('Votes::_voters', [
            'type' => 'story',
            'id' => (int)$story['id'],
            'score' => (int)$story['score'],
            'currentVoteState' => $voteModel->getUserVote($currentUserId, 'story', (int)$story['id']),
            'canDownvote' => $canUserDownvote,
            'isLoggedIn' => $currentUserId > 0,
			'contentOwnerId' => $isAuthor
        ]); ?>

        <!-- Контент публикации -->
        <div class="story_liner">

            <!-- Заголовок и ссылка -->
            <div class="link">
                <?php if ($isStoryDeleted): ?>
                    <em>[Удалена модератором]</em>
                <?php endif; ?>

                <?php
                $isExternal = !empty($story['url']);
                $targetUrl = $isExternal ? e($story['url']) : route('story.show', ['id' => $story['id']]);
                ?>

                <a href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?= e($story['title']) ?>
                </a>

                <?php if ($isExternal): ?>
                    <?php
                    $domainHost = !empty($story['url']) ? parse_url($story['url'], PHP_URL_HOST) : null;
                    if ($domainHost):
                    ?>
                        <a href="<?= route('domain.show', ['domain' => $domainHost]) ?>" class="domain">
                            <?= e($domainHost) ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($story['tags'])): ?>  
							<span class="tags">
								<?php foreach ($story['tags_with_names'] as $tagData): ?>  
									<a href="<?= route('tags.filter', ['tagname' => e($tagData['tag'])]) ?>" class="tag"><?= e($tagData['name']) ?></a>
								<?php endforeach; ?>
							</span> 
						<?php endif; ?>

            <!-- Описание -->
            <?php if (!empty($story['description'])): ?>
                <div class="story_content">
                    <?= \App\Core\Markdown::parse($story['description']) ?>
                </div>
            <?php endif; ?>

            <!-- Метаданные (byline) -->
            <div class="byline">
                <?php if (!empty($story['author_avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= e($story['author_avatar']) ?>" class="avatar" alt="">
                <?php endif; ?>

                <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" <?= (int)$story['user_id'] === $currentUserId ? 'class="user_is_author"' : '' ?>>
                    <?= e($story['author_name']) ?>
                </a>

                <span class="divider">|</span>
                <span title="<?= e(date('d.m.Y H:i:s', strtotime($story['created_at']))) ?>">
                    <?= e(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
                </span>

                <span class="divider">|</span>
                <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments">
                    <?php if ((int)$story['comments_count'] === 0): ?>
                        обсудить
                    <?php else: ?>
                        <?= (int)$story['comments_count'] ?> <?= plural((int)$story['comments_count'], ['комментарий', 'комментария', 'комментариев']) ?>
                    <?php endif; ?>
                </a>

                <!-- Редактирование -->
                <?php if ($currentUserId > 0 && ((int)$story['user_id'] === $currentUserId || $isAdmin) && !$isStoryDeleted): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('story.edit', ['id' => $story['id']]) ?>">edit</a>
                <?php endif; ?>

                <?php if ($currentUserId > 0): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('flags.report', ['type' => 'story', 'id' => (int)$story['id']]) ?>"
                        class="flag-link"
                        title="Пожаловаться на контент"
                        data-confirm="Вы уверены, что хотите подать жалобу?">
                        🚩
                    </a>
                <?php endif; ?>

                <!-- Админские действия -->
                <?php if ($isAdmin): ?>
                    <span class="divider">|</span>
                    <?php if ($isStoryDeleted): ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link">восстановить</button>
                        </form>
                    <?php else: ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-link" style="color: var(--color-fg-negative);">удалить</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($isAuthor): ?> <br><br>
                    <form method="POST" action="/story/<?= $story['id'] ?>/follow" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm <?= $story['user_is_following'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <?= $story['user_is_following'] ? '🔔 Вы подписаны' : '🔕 Подписаться на ответы' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($showMarkReadButton): ?> <br><br>
                    <form action="/story/<?= (int)$story['id'] ?>/mark-read" method="POST" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Сбросить счётчик новых комментариев">
                            ✓ Отметить прочитанным
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </li>
</ol>

<!-- ФОРМА КОРНЕВОГО КОММЕНТАРИЯ -->
<div class="comment_form_container" id="comment-form-container">
    <?php if ($currentUserId > 0 && !$isStoryDeleted): ?>
        <h3>Оставить комментарий</h3>
        <form action="/comments/create" method="POST" id="main-comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>">
            <input type="hidden" name="parent_id" id="form-parent-id" value="">

            <textarea name="comment_text" id="form-comment-textarea" required
                placeholder="Ваш комментарий... (поддерживается Markdown)"></textarea>

            <button type="submit">Опубликовать комментарий</button>
            <button type="button" id="btn-cancel-reply" style="display: none;">Отмена</button>
        </form>
    <?php else: ?>
        <p class="hint">
            Вы должны <a href="/login">войти в аккаунт</a>, чтобы принимать участие в обсуждениях.
        </p>
    <?php endif; ?>
</div>

<hr>

<!-- КОММЕНТАРИИ -->
<h3 id="comments">Комментарии (<?= (int)$story['comments_count'] ?>)</h3>

<?php if (empty($commentsTree)): ?>
    <p class="hint">Здесь пока нет комментариев. Будьте первым!</p>
<?php else: ?>

    <?php
    $renderTree = function (int $parentId) use (&$renderTree, $commentsTree,  $voteModel, $currentUserId, $isAdmin, $isModerator, $canUserDownvote, $isAuthor) {
        if (!isset($commentsTree[$parentId])) {
            return;
        }
    ?>
        <ol class="comments">
            <?php foreach ($commentsTree[$parentId] as $comment): ?>
                <?php
                $commentId = (int)$comment['id'];
                $isCommentDeleted = !empty($comment['deleted_at']);
                ?>
                <li class="comment <?= $isCommentDeleted ? 'deleted' : '' ?>" id="comment-block-<?= $commentId ?>">

                    <!-- Голосование -->
                    <?php if (!$isCommentDeleted): ?>
                        <div class="comment_votes">
                            <?php partial('Votes::_voters', [
                                'type' => 'comment',
                                'id' => $commentId,
                                'score' => (int)$comment['score'],
                                'currentVoteState' => $voteModel->getUserVote($currentUserId, 'comment', $commentId),
                                'canDownvote' => $canUserDownvote,
                                'isLoggedIn' => true,
								'contentOwnerId' => $isAuthor,
                            ]); ?>
                        </div>

                    <?php elseif (!$isCommentDeleted): ?>
                        <div class="comment_votes">
                            <span class="score"><?= (int)$comment['score'] ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Обёртка для метаданных, текста и действий -->
                    <div class="comment_body">

                        <!-- Метаданные комментария -->
                        <?php partial('Users::_comment_meta', [
                            'comment' => $comment,
                            'currentUserId' => $currentUserId,
                            'isAdmin' => $isAdmin,
                        ]); ?>

                        <!-- Тело комментария -->
                        <?php if (!$isCommentDeleted): ?>
                            <div class="comment_text" id="comment-text-content-<?= $commentId ?>"
                                data-raw="<?= e($comment['comment'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= \App\Core\Markdown::parse($comment['comment']) ?>
                            </div>

                            <!-- Действия -->
                            <div class="comment_actions">
                                <?php if ($currentUserId > 0): ?>
                                    <a href="#reply-to-<?= $commentId ?>" class="comment-reply-link" data-id="<?= $commentId ?>">Ответить</a>
                                <?php endif; ?>

                                <?php if ((int)$comment['user_id'] === $currentUserId || $isAdmin || $isModerator): ?>
                                    <span class="divider">|</span>
                                    <a class="comment-edit-trigger" data-id="<?= $commentId ?>">Редактировать</a>
                                    <span class="divider">|</span>
                                    <form action="/comments/<?= $commentId ?>/delete" method="POST" class="inline-form js-confirm-delete" data-confirm-message="Удалить комментарий?">
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

                    <!-- Рекурсия ветки -->
                    <?php $renderTree($commentId); ?>

                </li>
            <?php endforeach; ?>
        </ol>
    <?php
    };

    if (!empty($commentsTree)) {
        $renderTree(0);
    } else {
        echo '<p class="hint">Здесь пока нет комментариев. Будьте первым!</p>';
    }
    ?>

<?php endif; ?>