<?php
/**
 * Страница «Подписки» — менеджер подписок (Medium-стиль).
 *
 * @var string $activeTab          feed | authors | topics
 * @var int    $currentUserId
 * @var bool   $isAdmin
 *
 * @var array  $stories            Только для feed
 * @var int    $currentPage
 * @var int    $totalPages
 * @var array  $newCommentsMap
 * @var string $sort
 * @var bool   $isEmptyState
 *
 * @var array  $followedAuthors    Только для authors
 * @var int    $followedUserCount
 *
 * @var array  $followedTopics     Только для topics
 * @var int    $followedTopicCount
 */

$baseUrl = '/subscribed';
$tabs = [
    'feed'    => 'Лента',
    'authors' => 'Авторы',
    'topics'  => 'Темы',
];
?>

<h1 class="page-title">Подписки</h1>

<nav class="nav br-none" aria-label="Подписки">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= $baseUrl ?>?tab=<?= $key ?>"
           class="<?= $activeTab === $key ? 'is-active' : '' ?>">
            <?= $label ?>
            <?php if ($key === 'authors' && ($followedUserCount ?? 0) > 0): ?>
                <span class="count-badge"><?= (int)$followedUserCount ?></span>
            <?php endif; ?>
            <?php if ($key === 'topics' && ($followedTopicCount ?? 0) > 0): ?>
                <span class="count-badge"><?= (int)$followedTopicCount ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($activeTab === 'feed'): ?>

    <?php if (!empty($isEmptyState)): ?>
        <div class="empty-state empty-state--subscribed">
            <h2>📭 У вас пока нет подписок</h2>
            <p class="hint">Здесь будут появляться новые истории от авторов и по темам, на которые вы подпишетесь.</p>
            <div class="empty-state__actions">
                <a href="/tags" class="btn btn-pill btn-outline">🏷️ Посмотреть теги</a>
                <a href="/" class="btn btn-pill btn-primary">🏠 На главную</a>
            </div>
        </div>
    <?php elseif (!empty($stories)): ?>
        <div class="subscribed-feed__sort">
            <?php foreach (['new' => 'Новые', 'hot' => 'Горячее', 'top' => 'Лучшие'] as $key => $label): ?>
                <a href="<?= $baseUrl ?>?tab=feed&amp;sort=<?= $key ?>" class="sort-link <?= $sort === $key ? 'is-active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <ol class="stories">
            <?php foreach ($stories as $story): ?>
                <?php partial('Stories::_story_row', [
                    'story' => $story,
                    'currentUserId' => $currentUserId,
                    'isAdmin' => $isAdmin,
                    'currentVotes' => [],
                    'newCommentsMap' => $newCommentsMap,
                    'hideAuthor' => false,
                ]); ?>
            <?php endforeach; ?>
        </ol>

        <?php if (($totalPages ?? 1) > 1): ?>
            <?= pagination($currentPage, $totalPages) ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><p>Пока нет новых историй от ваших подписок.</p></div>
    <?php endif; ?>

<?php elseif ($activeTab === 'authors'): ?>

    <div class="follow-manager">
        <?php if (empty($followedAuthors)): ?>
            <div class="empty-state">
                <h2>Вы пока ни на кого не подписаны</h2>
                <p class="hint">Здесь появятся авторы, за которыми вы следите. Начните с кого-нибудь на главной.</p>
                <div class="empty-state__actions">
                    <a href="/" class="btn btn-pill btn-primary">🏠 На главную</a>
                </div>
            </div>
        <?php else: ?>
            <ul class="follow-manager__list">
                <?php foreach ($followedAuthors as $author): ?>
                    <li class="follow-manager__item">
                        <a href="<?= route('user.profile', ['username' => $author['username']]) ?>" class="follow-manager__identity">
                            <?php if (!empty($author['avatar'])): ?>
                                <img class="avatar avatar--md" src="/uploads/avatars/<?= substr($author['avatar'], 0, 2) ?>/<?= e($author['avatar']) ?>" alt="">
                            <?php else: ?>
                                <span class="avatar avatar--md avatar--placeholder"><?= e(mb_substr($author['username'], 0, 1)) ?></span>
                            <?php endif; ?>
                            <span class="follow-manager__name"><?= e($author['username']) ?></span>
                        </a>
                        <form action="/subscribe/user/<?= (int)$author['id'] ?>" method="POST" class="follow-manager__form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-pill btn-outline-secondary">Отписаться</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

<?php elseif ($activeTab === 'topics'): ?>

    <div class="follow-manager">
        <?php if (empty($followedTopics)): ?>
            <div class="empty-state">
                <h2>Вы пока не подписаны на темы</h2>
                <p class="hint">Здесь появятся теги, за которыми вы следите.</p>
                <div class="empty-state__actions">
                    <a href="/tags" class="btn btn-pill btn-primary">🏷️ К тегам</a>
                </div>
            </div>
        <?php else: ?>
            <ul class="follow-manager__list">
                <?php foreach ($followedTopics as $topic): ?>
                    <li class="follow-manager__item">
                        <a href="<?= route('tags.filter', ['tagslug' => $topic['slug']]) ?>" class="follow-manager__identity">
                            <span class="follow-manager__avatar follow-manager__avatar--tag">🏷️</span>
                            <span class="follow-manager__name"><?= e($topic['name']) ?></span>
                        </a>
                        <form action="/subscribe/tag/<?= (int)$topic['id'] ?>" method="POST" class="follow-manager__form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-pill btn-outline-secondary">Отписаться</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

<?php endif; ?>