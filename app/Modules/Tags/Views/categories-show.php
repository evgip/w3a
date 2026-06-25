<?php
/**
 * Страница историй с тегами из конкретной категории
 * 
 * @var array $category       Данные категории (id, name, slug, description, stories)
 * @var array $stories        Список историй
 * @var int $currentPage      Текущая страница пагинации
 * @var int $totalPages       Общее количество страниц
 * @var array $newCommentsMap Карта новых комментариев для каждой истории
 */

$request = new \App\Core\Request();
$voteModel = new \App\Modules\Votes\Models\Vote();
$currentUserId = \App\Modules\Auth\Services\Auth::check() ? \App\Modules\Auth\Services\Auth::id() : 0;
$isAdmin = \App\Modules\Auth\Services\Auth::isAdmin();

$minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');

$canUserDownvote = false;
if ($currentUserId > 0) {
    $userModel = new \App\Modules\Users\Models\User();
    $viewerKarma = $userModel->getUserKarma($currentUserId);
    $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
}

// Базовый URL для пагинации (сохраняем slug категории)
$paginationBaseUrl = route('categories.show', ['slug' => $category['slug']]);
?>

<h1><?= e($title) ?></h1>

<p class="hint">
	Истории, помеченные тегами в категориях информатики:  <b><?= e($title) ?></b>

	<?php if (!empty($category['description'])): ?>
		<br><?= e($category['description']) ?>
	<?php endif; ?>
</p>

<?php if (!empty($stories)): ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php 
                $isStoryDeleted = !empty($story['deleted_at']); 
                
                $fullHtml = markdown_comment($story['description'] ?? '');
                $needsTruncation = needsTruncation($fullHtml, 300);
                
                $newCount = $newCommentsMap[$story['id']] ?? 0;
            ?>

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

                <div class="story_liner">
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
                        
						<?php if (!empty($story['tags'])): ?>  
							<span class="tags">
								<?php foreach ($story['tags_with_names'] as $tagData): ?>  
									<a href="<?= route('tags.filter', ['tagname' => e($tagData['tag'])]) ?>" class="tag"><?= e($tagData['name']) ?></a>
								<?php endforeach; ?>
							</span> 
						<?php endif; ?>
                    </div>

                    <div class="story_content">
                        <?php if ($needsTruncation): ?>
                            <details>
                                <summary>
                                    <?= truncateDescription($fullHtml, 300) ?>
                                </summary>
                                <div class="full-content">
                                    <?= $fullHtml ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <?= $fullHtml ?>
                        <?php endif; ?>
                    </div>

                    <!-- Метаданные -->
                    <?php partial('Users::_story_meta', [
                        'story' => $story,
                        'currentUserId' => $currentUserId,
                        'isAdmin' => $isAdmin,
                        'newCount' => $newCount,
                    ]); ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="page_link_buttons">
            <?php if ($currentPage > 1): ?>
                <a href="<?= e($paginationBaseUrl) ?>?page=<?= $currentPage - 1 ?>">← назад</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $currentPage): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e($paginationBaseUrl) ?>?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= e($paginationBaseUrl) ?>?page=<?= $currentPage + 1 ?>">вперёд →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <p class="hint">В этой категории пока нет публикаций.</p>
<?php endif; ?>