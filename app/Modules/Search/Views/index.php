<?php 
    $request = new \App\Core\Request(); 
    $voteModel = new \App\Modules\Votes\Models\Vote();
    $currentUserId = \App\Core\Auth::check() ? (int)$_SESSION['user_id'] : 0;
?>

<div class="container">
    
    <!-- ФОРМА ПОИСКА LOBSTERS STYLE С ТУГЛЕРАМИ КОНТЕНТА -->
    <div class="search-form-card">
        <form action="/search" method="GET">
            <div class="search-input-wrapper">
                <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Введите поисковый запрос..." required autofocus class="search-bar-field">
                <button type="submit" class="btn btn-primary search-btn-submit">🔍 Искать</button>
            </div>

            <div class="search-options-row">
                <span>Искать в:</span>
                <label class="search-radio-group">
                    <input type="radio" name="what" value="stories" <?= $what === 'stories' ? 'checked' : '' ?>>
                    <span>Статьях / Постах</span>
                </label>
                <label class="search-radio-group">
                    <input type="radio" name="what" value="comments" <?= $what === 'comments' ? 'checked' : '' ?>>
                    <span>Комментариях</span>
                </label>

                <span style="margin-left: 20px;">Сортировка:</span>
                <label class="search-radio-group">
                    <input type="radio" name="order" value="relevance" <?= $sortBy === 'relevance' ? 'checked' : '' ?>>
                    <span>Релевантность</span>
                </label>
                <label class="search-radio-group">
                    <input type="radio" name="order" value="date" <?= $sortBy === 'date' ? 'checked' : '' ?>>
                    <span>По дате</span>
                </label>
            </div>
        </form>
    </div>

    <!-- ВЫВОД РЕЗУЛЬТАТОВ НА ОСНОВЕ ФИЛЬТРА ТИПА КОНТЕНТА -->
    <?php if (!empty($query) && strlen($query) >= 3): ?>
        <h4 class="search-results-heading">Найдено совпадений: <?= count($results) ?></h4>
        
        <?php if (!empty($results)): ?>
            
            <?php if ($what === 'stories'): ?>
                <!-- РЕНДЕРИНГ НАЙДЕННЫХ СТАТЕЙ -->
                <div class="stories-feed search-results-feed-box">
                    <?php foreach ($results as $story): ?>
                        <article class="story-item">
                            
                            <div class="story-voting-wrapper">
                                <?php if ($currentUserId > 0): ?>
                                    <?php $userVoteState = $voteModel->getUserVote($currentUserId, 'story', (int)$story['id']); ?>
                                    <form action="<?= route('votes.toggle', ['type' => 'story', 'id' => $story['id'], 'direction' => 'up']) ?>" method="POST" class="vote-action-form">
                                        <?= $request->csrfField() ?>
                                        <button type="submit" class="btn-vote-arrow <?= $userVoteState === 1 ? 'btn-vote-arrow-active' : '' ?>">▲</button>
                                    </form>
                                    <div class="story-counter-value"><?= (int)$story['score'] ?></div>
                                <?php else: ?>
                                    <span class="btn-vote-arrow">▲</span>
                                    <div class="story-counter-value"><?= (int)$story['score'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="story-content-body">
                                <h4 class="story-title-line">
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
                                    размещено 
                                    <?php if (!empty($story['author_avatar'])): ?>
                                        <img src="/uploads/avatars/<?= substr($story['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($story['author_avatar']) ?>" class="mini-avatar-img" alt="avatar">
                                    <?php else: ?>
                                        <span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($story['author_name'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                    автором <strong><a href="<?= route('user.profile', ['username' => $story['author_name']]) ?>" class="comment-action-link user-profile-link"><?= htmlspecialchars($story['author_name']) ?></a></strong>
                                    | <?= htmlspecialchars(date('d.m.Y H:i', strtotime($story['created_at']))) ?>
                                    | <a href="<?= route('story.show', ['id' => $story['id']]) ?>">💬 <?= (int)$story['comments_count'] ?> коммент.</a>
                                    <?php if (isset($story['relevance'])): ?>
                                        | <span class="search-relevance-metric">Релевантность: <?= round($story['relevance'], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- РЕНДЕРИНГ НАЙДЕННЫХ КОММЕНТАРИЕВ -->
                <div class="search-comments-feed-box">
                    <?php foreach ($results as $comment): ?>
                        <div class="search-comment-item-card">
                            <!-- Ссылка на родительский пост, где оставлен комментарий -->
                            <a href="<?= route('story.show', ['id' => $comment['story_id']]) ?>#comment-block-<?= $comment['id'] ?>" class="search-comment-parent-link">
                                📌 В теме: «<?= htmlspecialchars($comment['story_title']) ?>»
                            </a>
                            
                            <!-- Рендеринг текста комментария через наш безопасный Markdown-парсер -->
                            <div class="search-comment-snippet-box markdown-body">
                                <?= \App\Core\Markdown::parse($comment['comment']) ?>
                            </div>

                            <!-- Метаданные автора комментария -->
                            <div class="story-metadata-line search-meta-group-row">
                                <div class="search-meta-group-row" style="gap:4px;">
                                    <?php if (!empty($comment['author_avatar'])): ?>
                                        <img src="/uploads/avatars/<?= substr($comment['author_avatar'], 0, 2) ?>/<?= htmlspecialchars($comment['author_avatar']) ?>" class="mini-avatar-img" alt="avatar">
                                    <?php else: ?>
                                        <span class="mini-avatar-placeholder"><?= htmlspecialchars(mb_substr($comment['author_name'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                    от <strong><a href="<?= route('user.profile', ['username' => $comment['author_name']]) ?>" class="comment-action-link user-profile-link"><?= htmlspecialchars($comment['author_name']) ?></a></strong>
                                </div>
                                <span>• Оценка: (<?= (int)$comment['score'] ?>)</span>
                                <span>• <?= htmlspecialchars(date('d.m.Y H:i', strtotime($comment['created_at']))) ?></span>
                                <?php if (isset($comment['relevance'])): ?>
                                    <span class="search-relevance-metric">Релевантность: <?= round($comment['relevance'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="story-empty-fallback search-empty-fallback-box">
                <h3>Ничего не найдено 🔍</h3>
                <p>Попробуйте изменить формулировку или выбрать другой фильтр поиска.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
