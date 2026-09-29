<?php
/**
 * Страница «Мои истории» — табы по статусам (Medium-стиль).
 *
 * @var string $activeTab   published | drafts | scheduled
 * @var array  $counts      Счётчики для каждой вкладки
 * @var array  $stories     Статьи текущей вкладки
 * @var int    $currentPage
 * @var int    $totalPages
 * @var int    $currentUserId
 * @var bool   $isAdmin
 */

$baseUrl = '/me/stories';

$tabs = [
    'published' => 'Опубликовано',
    'drafts'    => 'Черновики',
    'scheduled' => 'Запланировано',
];
?>

<h1>Мои истории</h1>

<p>
    <a href="/stories/create" class="btn btn--primary">+ Начать писать</a>
</p>

<nav class="nav br-none" aria-label="Мои истории">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= $baseUrl ?>?tab=<?= $key ?>"
           class="<?= $activeTab === $key ? 'is-active' : '' ?>">
            <?= $label ?>
            <?php if (($counts[$key] ?? 0) > 0): ?>
                <span class="count-badge"><?= (int)$counts[$key] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (empty($stories)): ?>
    <p class="hint">
        <?php if ($activeTab === 'drafts'): ?>
            У вас пока нет черновиков. Начните писать — и сохраняйте незаконченные статьи здесь.
        <?php elseif ($activeTab === 'scheduled'): ?>
            Нет запланированных публикаций.
        <?php else: ?>
            У вас пока нет опубликованных историй.
        <?php endif; ?>
    </p>
<?php else: ?>
    <ol class="stories">
        <?php foreach ($stories as $story): ?>
            <?php partial('Stories::_story_row', [
                'story'         => $story,
                'currentUserId' => $currentUserId,
                'isAdmin'       => $isAdmin,
                'currentVotes'  => [],
                'newCommentsMap'=> [],
                'isMyStories'   => true,
            ]); ?>
        <?php endforeach; ?>
    </ol>

    <?php if ($totalPages > 1): ?>
        <?= pagination($currentPage, $totalPages) ?>
    <?php endif; ?>
<?php endif; ?>