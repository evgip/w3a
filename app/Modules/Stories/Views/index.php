<?php
$request = new \App\Core\Request();
$voteModel = new \App\Modules\Votes\Models\Vote();
$currentUserId = \App\Core\Auth::check() ? (int)$_SESSION['user_id'] : 0;
$isAdmin = \App\Core\Auth::isAdmin();

$minKarmaForDownvote = config_int('config.app.min_karma_for_downvote', 10);

$canUserDownvote = false;
if ($currentUserId > 0) {
    $userModel = new \App\Modules\Users\Models\User();
    $viewerKarma = $userModel->getUserKarma($currentUserId);
    $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
}
?>

<?php if (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php $isStoryDeleted = !empty($story['deleted_at']); ?>

            <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

                <!-- Голосование (1 строка вместо 20) -->
                <?php partial('Votes::_voters', [
                    'type' => 'story',
                    'id' => (int)$story['id'],
                    'score' => (int)$story['score'],
                    'currentVoteState' => $voteModel->getUserVote($currentUserId, 'story', (int)$story['id']),
                    'canDownvote' => $canUserDownvote,
                    'isLoggedIn' => $currentUserId > 0,
                ]); ?>

                <div class="story_liner">
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

                    <?php if (!empty($story['tags'])): ?>
                        <span class="tags">
                            <?php foreach ($story['tags'] as $tagName): ?>
                                <a href="<?= route('tags.filter', ['tagname' => $tagName]) ?>" class="tag"><?= htmlspecialchars($tagName) ?></a>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($story['description'])): ?>
                        <div class="story_content">
                            <?= \App\Core\Markdown::parse($story['description']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Метаданные (1 строка вместо 30) -->
                    <?php partial('Users::_story_meta', [
                        'story' => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin,
                    ]); ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="page_link_buttons">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1 ?>">← назад</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $currentPage): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?>">вперёд →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <p class="hint">Лента историй пока пуста.</p>
<?php endif; ?>