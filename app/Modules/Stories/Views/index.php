<?php 
    $request = new \App\Core\Request(); 
    $voteModel = new \App\Modules\Votes\Models\Vote();
    $currentUserId = \App\Core\Auth::check() ? (int)$_SESSION['user_id'] : 0;
    $isAdmin = \App\Core\Auth::isAdmin();

    // NEW LOBSTERS CONFIGURATION VALUE CHECK
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

 
    <?php if (!empty($stories)): ?>
        <?php foreach ($stories as $story): ?>
            <?php 
                $isStoryDeleted = !empty($story['deleted_at']); 
            ?>
            <!-- Append light red indicator border styles automatically if row is moderated -->
            <article class="story-item <?= $isStoryDeleted ? 'story-item-moderated' : '' ?>">
                

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
                    <h4 class="story-title-line">
                        <?php if ($isStoryDeleted): ?>
                            <span class="badge-moderated-label">[Удален админом]</span>
                        <?php endif; ?>

                        <?php 
                            $targetUrl = !empty($story['url']) ? htmlspecialchars($story['url']) : route('story.show', ['id' => $story['id']]);
                            $isExternal = !empty($story['url']);
                            $domain = $isExternal ? '(' . parse_url($story['url'], PHP_URL_HOST) . ')' : '';
                        ?>
                        <a href="<?= $targetUrl ?>" class="story-title-link" <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= htmlspecialchars($story['title']) ?>
                        </a>
                        <?php if ($isExternal): ?>
                            <span class="story-domain-tag"><?= htmlspecialchars($domain) ?></span>
                        <?php endif; ?>

                        <?php if (!empty($story['tags'])): ?>
                            <span class="story-tags-group">
                                <?php foreach ($story['tags'] as $tagName): ?>
                                    <a href="<?= route('tags.filter', ['tagname' => $tagName]) ?>" class="tag-badge-link">
                                        <?= htmlspecialchars($tagName) ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </h4>

                    <div class="story-metadata-line">
                        				   <?php if (!empty($story['author_avatar'])): ?>
						<img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($story['author_avatar']) ?>" class="mini-avatar-img" alt="avatar">
					<?php else: ?>
						<span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($story['author_name'], 0, 1)) ?></span>
					<?php endif; ?>
					
                        <strong>
                            <a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" class="user-profile-link">
                                <?= htmlspecialchars($story['author_name']) ?>
                            </a>
                        </strong> 
                        | <?= htmlspecialchars(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
                        | <a href="<?= route('story.show', ['id' => $story['id']]) ?>">💬 <?= (int)$story['comments_count'] ?> комментариев</a>
                    </div>

                    <!-- ADMINISTRATIVE MODERATION TRIGGER ACTION BAR CONTROLS -->
                    <?php if ($isAdmin): ?>
                        <div class="story-moderation-actions-row">
                            <?php if ($isStoryDeleted): ?>
                                <form action="/admin/stories/<?= (int)$story['id'] ?>/restore" method="POST" class="vote-action-form">
                                    <?= $request->csrfField() ?>
                                    <button type="submit" class="btn-action btn-restore" style="font-size: 11px; padding: 4px 10px;">♻️ Восстановить публикацию</button>
                                </form>
                            <?php else: ?>
                                <form action="/admin/stories/<?= (int)$story['id'] ?>/delete" method="POST" class="vote-action-form js-comment-delete-form">
                                    <?= $request->csrfField() ?>
                                    <button type="submit" class="btn-action btn-archive" style="font-size: 11px; padding: 4px 10px; background: #e11d48;">🗑️ Удалить с сайта</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="story-empty-fallback">
            <h3>Лента историй пока пуста 🚀</h3>
        </div>
    <?php endif; ?>
 

<?php if (isset($totalPages) && $totalPages > 1): ?>
    <nav class="pagination-container">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="/?page=<?= $i ?>" class="pagination-item <?= $i === $currentPage ? 'pagination-item-active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

