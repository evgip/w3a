<?php
// ✅ Все данные приходят из контроллера, не создаём модели здесь
$currentUserId = $currentUserId ?? 0;
$isAdmin = $isAdmin ?? false;
$isModerator = $isModerator ?? false;
$isAuthor = $isAuthor ?? false;
$canUserDownvote = $canUserDownvote ?? false;
$currentStoryVote = $currentStoryVote ?? null;
$currentCommentVotes = $currentCommentVotes ?? [];
$userSuggestionsCount = $userSuggestionsCount ?? 0;

$commentsTree = $commentsTree ?? [];

$isStoryDeleted = !empty($story['deleted_at']);
$hasNewComments = false;
$showMarkReadButton = ($currentUserId > 0 && ($newCount ?? 0) > 0);
?>

<!-- КАРТОЧКА ПУБЛИКАЦИИ -->
<ol class="stories">
    <li class="story <?= $isStoryDeleted ? 'deleted' : '' ?>">

        <!-- Голосование -->
        <?php partial('Votes::_voters', [
            'type' => 'story',
            'id' => (int)$story['id'],
            'score' => (int)$story['score'],
            'currentVoteState' => $currentStoryVote,
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
                        <a href="<?= route('tags.filter', ['tagslug' => e($tagData['slug'])]) ?>" class="tag tag-<?= e($tagData['slug']); ?>"><?= e($tagData['name']) ?></a>
                    <?php endforeach; ?>
                </span>

                <!-- Кнопка "Предложить правку" -->
                <?php
                // ✅ Используем переданные данные вместо Auth::isModerator()
                $canSuggest = $currentUserId > 0 && (
                    !$isAuthor || // Не автор
                    $isModerator || $isAdmin // Или модератор/админ
                );
                ?>

                <?php if ($canSuggest): ?>
                    <?php
                    // ✅ Проверяем лимит предложений (только для не-модераторов)
                    if (!$isModerator && !$isAdmin) {
                        $maxSuggestions = \App\Modules\Suggestions\Services\SuggestionService::MAX_USER_SUGGESTIONS;
                        $canSuggest = $userSuggestionsCount < $maxSuggestions;
                    }
                    ?>

                    <?php if ($canSuggest): ?>
                        <?php partial('Suggestions::suggest_button', ['story' => $story]) ?>
                    <?php else: ?>
                        <p class="hint">Вы уже отправили максимальное количество предложений.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Блок активных предложений (только если есть) -->
                <?php if ($currentUserId > 0 && !empty($activeSuggestions)): ?>
                    <?php partial('Suggestions::active_suggestions', ['activeSuggestions' => $activeSuggestions ?? []]) ?>
                <?php endif; ?>

                <?php if ($currentUserId > 0): ?>

                    <!-- Блок истории изменений (только если есть) -->
                    <?php if (!empty($changeLog)): ?>
                        <?php partial('Suggestions::change_log', ['changeLog' => $changeLog ?? []]) ?>
                    <?php endif; ?>

                    <!-- Модальное окно -->
                    <?php partial('Suggestions::suggest_modal', [
                        'allTags' => $allTags ?? [],
                        'currentTagIds' => $currentTagIds ?? []
                    ]) ?>
                <?php endif; ?>

            <?php endif; ?>


            <!-- Описание -->
            <?php if (!empty($story['description'])): ?>
                <div class="story_content">
                    <?= markdown($story['description']) ?>
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
                <?php if ($currentUserId > 0 && ($isAuthor || $isAdmin) && !$isStoryDeleted): ?>
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
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <h3 id="comments" style="margin: 0;">Комментарии (<?= (int)$story['comments_count'] ?>)</h3>
    <?php if (!empty($commentsTree)): ?>
        <button type="button" id="collapse-all-comments" class="btn btn-sm btn-outline-secondary">
            Свернуть все ветки
        </button>
    <?php endif; ?>
</div>


<?php if (empty($commentsTree)): ?>
    <p class="hint">Здесь пока нет комментариев. Будьте первым!</p>
<?php else: ?>

    <?php
    $renderTree = function (int $parentId) use (&$renderTree, $commentsTree, $currentUserId, $isAdmin, $isModerator, $canUserDownvote, $isAuthor, $currentCommentVotes) {
        if (!isset($commentsTree[$parentId])) {
            return;
        }
    ?>
        <ol class="comments">
            <?php foreach ($commentsTree[$parentId] as $comment): ?>
                <?php
                $commentId = (int)$comment['id'];
                $isCommentDeleted = !empty($comment['deleted_at']);
                // ✅ Получаем голос за комментарий из переданного массива
                $currentCommentVote = $currentCommentVotes[$commentId] ?? null;
                ?>
                
				<?php partial('Comments::_item', [
					'comment' => $comment,
					'currentUserId' => $currentUserId,
					'isAdmin' => $isAdmin,
					'isModerator' => $isModerator,
					'isStoryAuthor' => $isAuthor,
					'canDownvote' => $canUserDownvote,
					'currentVote' => $currentCommentVote,
					'showStoryContext' => false,
					'showCollapseToggle' => true,
					'renderTree' => $renderTree,
					'commentsTree' => $commentsTree,
				]); ?>
			
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