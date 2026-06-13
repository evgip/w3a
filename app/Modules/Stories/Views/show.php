<?php
$request = new \App\Core\Request();
$voteModel = new \App\Modules\Votes\Models\Vote();
$currentUserId = \App\Core\Auth::check() ? (int)$_SESSION['user_id'] : 0;

$commentsTree = $commentsTree ?? [];
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
                $targetUrl = $isExternal ? htmlspecialchars($story['url']) : route('story.show', ['id' => $story['id']]);
                ?>

                <a href="<?= $targetUrl ?>" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?= htmlspecialchars($story['title']) ?>
                </a>

                <?php if ($isExternal): ?>
                    <?php 
					$domainHost = !empty($story['url']) ? parse_url($story['url'], PHP_URL_HOST) : null;
					if ($domainHost): 
					?>
						<a href="<?= route('domains.show', ['domain' => $domainHost]) ?>" class="domain">
							<?= htmlspecialchars($domainHost) ?>
						</a>
					<?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Теги -->
            <?php if (!empty($story['tags'])): ?>
                <span class="tags">
                    <?php foreach ($story['tags'] as $tagName): ?>
                        <a href="<?= route('tags.filter', ['tagname' => $tagName]) ?>" class="tag"><?= htmlspecialchars($tagName) ?></a>
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
                    <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($story['author_avatar']) ?>" class="avatar" alt="">
                <?php endif; ?>

                <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" <?= (int)$story['user_id'] === $currentUserId ? 'class="user_is_author"' : '' ?>>
                    <?= htmlspecialchars($story['user_name']) ?>
               </a>

                <span class="divider">|</span>
                <span title="<?= htmlspecialchars(date('d.m.Y H:i:s', strtotime($story['created_at']))) ?>">
                    <?= htmlspecialchars(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
                </span>

                <span class="divider">|</span>
                <a href="<?= route('story.show', ['id' => $story['id']]) ?>#comments">
                    <?php if ((int)$story['comments_count'] === 0): ?>
                        обсудить
                    <?php else: ?>
                        <?= (int)$story['comments_count'] ?> <?= declension((int)$story['comments_count'], ['комментарий', 'комментария', 'комментариев']) ?>
                    <?php endif; ?>
                </a>

                <!-- Редактирование -->
                <?php if ($currentUserId > 0 && ((int)$story['user_id'] === $currentUserId || $isAdmin) && !$isStoryDeleted): ?>
                    <span class="divider">|</span>
                    <a href="<?= route('story.edit', ['id' => $story['id']]) ?>">edit</a>
                <?php endif; ?>

                <!-- Админские действия -->
                <?php if ($isAdmin): ?>
                    <span class="divider">|</span>
                    <?php if ($isStoryDeleted): ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="inline-form">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="btn-link">восстановить</button>
                        </form>
                    <?php else: ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="inline-form">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="btn-link" style="color: var(--color-fg-negative);">удалить</button>
                        </form>
                    <?php endif; ?>
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
            <?= $request->csrfField() ?>
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
    $renderTree = function(int $parentId) use (&$renderTree, $commentsTree, $request, $voteModel, $currentUserId, $isAdmin, $canUserDownvote) {
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
                    <?php if (!$isCommentDeleted && $currentUserId > 0): ?>
                        <div class="comment_votes">
                            <?php partial('Votes::_voters', [
                                'type' => 'comment',
                                'id' => $commentId,
                                'score' => (int)$comment['score'],
                                'currentVoteState' => $voteModel->getUserVote($currentUserId, 'comment', $commentId),
                                'canDownvote' => $canUserDownvote,
                                'isLoggedIn' => true,
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
                                 data-raw="<?= htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= \App\Core\Markdown::parse($comment['comment']) ?>
                            </div>

                            <!-- Действия -->
                            <div class="comment_actions">
                                <?php if ($currentUserId > 0): ?>
                                    <a href="#reply-to-<?= $commentId ?>" class="comment-reply-link" data-id="<?= $commentId ?>">Ответить</a>
                                <?php endif; ?>

                                <?php if (((int)$comment['user_id'] === $currentUserId) || $isAdmin): ?>
                                    <span class="divider">|</span>
                                    <a class="comment-edit-trigger" data-id="<?= $commentId ?>">Редактировать</a>
                                    <span class="divider">|</span>
                                    <form action="/comments/<?= $commentId ?>/delete" method="POST" class="inline-form js-confirm-delete" data-confirm-message="Удалить комментарий?">
										<?= $request->csrfField() ?>
										<button type="submit">Удалить</button>
									</form>
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