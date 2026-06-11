<?php 
    $request = new \App\Core\Request(); 
    $voteModel = new \App\Modules\Votes\Models\Vote();
    $currentUserId = \App\Core\Auth::check() ? (int)$_SESSION['user_id'] : 0;
    $isAdmin = \App\Core\Auth::isAdmin();
    
    // Вычисляем статус удаления текущей истории
    $isStoryDeleted = !empty($story['deleted_at']);

    // CONFIGURATION VALUE CHECK
    $config = require dirname(__DIR__, 3) . '/Config/config.php';
    $minKarmaForDownvote = (int)($config['app']['min_karma_for_downvote'] ?? 10);

    // Dedicate a boolean permission flag for downvoting rights
    $canUserDownvote = false;

    if ($currentUserId > 0) {
        $userModel = new \App\Modules\Users\Models\User();
        // Calculate total aggregated score footprint once per request
        $viewerKarma = $userModel->getUserKarma($currentUserId);
        $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
    }
?>

<div class="container">
    <!-- КАРТОЧКА ИСТОРИИ (С динамическим классом .story-item-moderated, если пост скрыт) -->
    <article class="story-item story-item-detailed <?= $isStoryDeleted ? 'story-item-moderated' : '' ?>">
        
		<!-- Substitute the story-voting-wrapper section inside app/Modules/Stories/Views/index.php -->
		<div class="story-voting-wrapper">
			<?php if ($currentUserId > 0): ?>
				<?php 
					$userVoteState = $voteModel->getUserVote($currentUserId, 'story', (int)$story['id']);
				?>
				<!-- UPVOTE TRIGGER ACTION -->
				<form action="<?= route('votes.toggle', ['type' => 'story', 'id' => $story['id'], 'direction' => 'up']) ?>" method="POST" class="vote-action-form">
					<?= $request->csrfField() ?>
					<button type="submit" class="btn-vote-arrow <?= $userVoteState === 1 ? 'btn-vote-arrow-active' : '' ?>" title="Интересно">
						▲
					</button>
				</form>

				<div class="story-counter-value"><?= (int)$story['score'] ?></div>

				<!-- DOWNVOTE TRIGGER ACTION -->
				<?php if ($canUserDownvote): ?>
					<form action="<?= route('votes.toggle', ['type' => 'story', 'id' => $story['id'], 'direction' => 'down']) ?>" method="POST" class="vote-action-form">
						<?= $request->csrfField() ?>
						<button type="submit" class="btn-vote-arrow btn-vote-down <?= $userVoteState === -1 ? 'btn-vote-down-active' : '' ?>" title="Не интересно / Спам">
							▼
						</button>
					</form>
				<?php endif; ?>
		
			<?php else: ?>
				<span class="btn-vote-arrow" title="Войдите, чтобы проголосовать">▲</span>
				<div class="story-counter-value"><?= (int)$story['score'] ?></div>
				<span class="btn-vote-arrow btn-vote-down" title="Войдите, чтобы проголосовать">▼</span>
			<?php endif; ?>
		</div>

        <div class="story-content-body">
            <h3 class="story-title-line story-title-large">
                <!-- Если пост скрыт, выводим бадж-метку -->
                <?php if ($isStoryDeleted): ?>
                    <span class="badge-moderated-label">[Удален модератором]</span>
                <?php endif; ?>

                <?php 
                    $isExternal = !empty($story['url']);
                    $targetUrl = $isExternal ? htmlspecialchars($story['url']) : "#";
                    $domain = $isExternal ? '(' . parse_url($story['url'], PHP_URL_HOST) . ')' : '';
                ?>
                <a href="<?= $targetUrl ?>" class="story-title-link" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?= htmlspecialchars($story['title']) ?>
                </a>
                <?php if ($isExternal): ?>
                    <span class="story-domain-tag"><?= htmlspecialchars($domain) ?></span>
                <?php endif; ?>

                <!-- ВЫВОД ТЕГОВ В ПОЛНОЙ НОВОСТИ -->
                <?php if (!empty($story['tags'])): ?>
                    <span class="story-tags-group">
                        <?php foreach ($story['tags'] as $tagName): ?>
                            <a href="<?= route('tags.filter', ['tagname' => $tagName]) ?>" class="tag-badge-link">
                                <?= htmlspecialchars($tagName) ?>
                            </a>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </h3>
            
            <div class="story-metadata-line story-meta-spacing">
                <?php if (!empty($story['author_avatar'])): ?>
                    <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($story['author_avatar']) ?>" class="mini-avatar-img" alt="avatar">
                <?php else: ?>
                    <span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($story['author_name'], 0, 1)) ?></span>
                <?php endif; ?>
                <strong>
                    <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" class="comment-action-link user-profile-link">
                        <?= htmlspecialchars($story['author_name']) ?>
                    </a>
                </strong> 
                | <?= htmlspecialchars(date('d.m.Y H:i', strtotime($story['created_at']))) ?>

                <!-- Редактировать пост может только автор и только если пост активен -->
                <?php if ($currentUserId > 0 && ((int)$story['user_id'] === $currentUserId || $isAdmin) && !$isStoryDeleted): ?>
                    | <a href="<?= route('story.edit', ['id' => $story['id']]) ?>" class="comment-action-link story-metadata-link story-metadata-edit-link">📝 Редактировать пост</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($story['description'])): ?>
                <div class="story-text-container">
					<?= \App\Core\Markdown::parse($story['description']) ?>
                </div>
            <?php endif; ?>

            <!-- ИНТЕРФЕЙС МОДЕРАЦИИ ПОСТА ДЛЯ АДМИНИСТРАТОРА -->
            <?php if ($isAdmin): ?>
                <div class="story-moderation-actions-row">
                    <?php if ($isStoryDeleted): ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="vote-action-form">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="btn-action btn-restore">♻️ Восстановить публикацию в ленту</button>
                        </form>
                    <?php else: ?>
                        <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="vote-action-form js-comment-delete-form">
                            <?= $request->csrfField() ?>
                            <button type="submit" class="btn-action btn-archive">🗑️ Принудительно удалить пост</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
</div>

<!-- БЛОК ОБСУЖДЕНИЯ -->
<section class="comments-section">
    <h4 class="comments-title">💬 Обсуждение (<?= (int)$story['comments_count'] ?>)</h4>

    <?php
    $renderTree = function(int $parentId) use (&$renderTree, $commentsTree, $request, $voteModel, $currentUserId, $isAdmin) {
        if (!isset($commentsTree[$parentId])) {
            return;
        }

        foreach ($commentsTree[$parentId] as $comment) {
            $commentId = (int)$comment['id'];
            $isCommentDeleted = !empty($comment['deleted_at']);
            $isOwner = ((int)$comment['user_id'] === $currentUserId);
            ?>
            <div class="comment-node" id="comment-block-<?= $commentId ?>">
                <div class="comment-wrapper">
                    
				<div class="comment-meta">
					<?php if ($currentUserId > 0 && !$isCommentDeleted): ?>
						<?php 
							$commentVoteState = $voteModel->getUserVote($currentUserId, 'comment', $commentId); 
						?>
						<div class="comment-vote-form-group">
							<!-- COMMENT UPVOTE FORM BUTTON -->
							<form action="<?= route('votes.toggle', ['type' => 'comment', 'id' => $commentId, 'direction' => 'up']) ?>" method="POST" class="vote-action-form admin-action-form">
								<?= $request->csrfField() ?>
								<button type="submit" class="btn-vote-arrow <?= $commentVoteState === 1 ? 'btn-vote-arrow-active' : '' ?>">▲</button>
							</form>
							
							<!-- COMMENT DOWNVOTE FORM BUTTON -->
							<form action="<?= route('votes.toggle', ['type' => 'comment', 'id' => $commentId, 'direction' => 'down']) ?>" method="POST" class="vote-action-form admin-action-form">
								<?= $request->csrfField() ?>
								<button type="submit" class="btn-vote-arrow btn-vote-down <?= $commentVoteState === -1 ? 'btn-vote-down-active' : '' ?>">▼</button>
							</form>
						</div>
					<?php endif; ?>

					<?php if (!empty($comment['author_avatar']) && !$isCommentDeleted): ?>
						<img src="/uploads/avatars/<?= substr($comment['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($comment['author_avatar']) ?>" class="mini-avatar-img" alt="avatar">
					<?php elseif (!$isCommentDeleted): ?>
						<span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($comment['author_name'], 0, 1)) ?></span>
					<?php endif; ?>

					<strong>
						<a href="<?= route('user.profile', ['username' => $comment['author_name']]) ?>" class="comment-action-link user-profile-link">
							<?= htmlspecialchars($comment['author_name']) ?>
						</a>
					</strong> 
					
					<?php if (!$isCommentDeleted): ?>
						<small class="story-counter-value">(<?= (int)$comment['score'] ?>)</small>
					<?php endif; ?>

					• <?= htmlspecialchars(date('d.m.Y H:i', strtotime($comment['created_at']))) ?>
					<!-- rest of comment-meta metrics links stays identical... -->
                        
                        <?php if (!empty($comment['updated_at']) && $comment['updated_at'] !== $comment['created_at'] && !$isCommentDeleted): ?>
                            <span class="comment-meta-edited-flag">(изменен)</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($isCommentDeleted): ?>
                        <div class="comment-deleted-placeholder">
                            [Комментарий удален автором]
                            <?php if ($isOwner || $isAdmin): ?>
                                <form action="/comments/<?= $commentId ?>/restore" method="POST" class="admin-action-form comment-restore-form">
                                    <?= $request->csrfField() ?>
                                    <button type="submit" class="comment-action-delete-trigger btn-restore-link">[Восстановить]</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="comment-text" id="comment-text-content-<?= $commentId ?>">
						    <?= \App\Core\Markdown::parse($comment['comment']) ?>
						</div>
                        
                        <div class="comment-actions-bar">
                            <?php if ($currentUserId > 0): ?>
                                <a href="#reply-to-<?= $commentId ?>" class="comment-reply-link">Ответить</a>
                            <?php endif; ?>

                            <?php if ($isOwner || $isAdmin): ?>
                                <span class="comment-action-divider">|</span>
                                <a class="comment-action-link comment-edit-trigger" data-id="<?= $commentId ?>">Редактировать</a>
                                <span class="comment-action-divider">|</span>
                                
                                <form action="/comments/<?= $commentId ?>/delete" method="POST" class="admin-action-form js-comment-delete-form">
                                    <?= $request->csrfField() ?>
                                    <button type="submit" class="comment-action-delete-trigger">Удалить</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- РЕКУРСИЯ ВЕТКИ ОБСУЖДЕНИЯ -->
                <?php $renderTree($commentId); ?>
            </div>
            <?php
        }
    };

    if (isset($commentsTree) && !empty($commentsTree)) {
        $renderTree(0);
    } else {
        echo '<p class="comment-empty-text">Здесь пока нет комментариев. Будьте первым!</p>';
    }
    ?>
</section>

<!-- ФОРМА НАПИСАНИЯ КОРНЕВОГО КОММЕНТАРИЯ К ИСТОРИИ -->
<div class="comments-section">
    <?php if ($currentUserId > 0): ?>
        <h4 id="comment-form-title" class="comments-area-heading">Оставить комментарий</h4>
        
        <form action="/comments/create" method="POST" id="main-comment-form">
            <?= $request->csrfField() ?>
            
            <input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>">
            <input type="hidden" name="parent_id" id="form-parent-id" value="">

            <div class="form-group-field comment-textarea-wrapper">
                <textarea name="comment_text" id="form-comment-textarea" required placeholder="Напишите, что вы думаете по этому поводу..." class="comment-input-textarea"></textarea>
            </div>

            <div class="comment-form-actions-row">
                <button type="submit" class="btn-submit-comment">
                    💬 Опубликовать комментарий
                </button>
                <button type="button" id="btn-cancel-reply" class="btn-cancel-reply-node" style="display: none;">
                    Отмена
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="guest-notice-box">
            Вы должны <a href="/login">войти в аккаунт</a>, чтобы принимать участие в обсуждениях.
        </div>
    <?php endif; ?>
</div>

