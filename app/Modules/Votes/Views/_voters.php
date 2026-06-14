<?php
/**
 * Компонент голосования
 * 
 * Ожидаемые переменные:
 * @var string $type           - тип ('story' или 'comment')
 * @var int    $id             - ID сущности
 * @var int    $score          - текущий счёт
 * @var int    $currentVoteState - голос текущего пользователя (-1, 0, 1)
 * @var bool   $canDownvote    - можно ли голосовать против
 * @var bool   $isLoggedIn     - авторизован ли пользователь
 */

$request = new \App\Core\Request();
?>
<div class="voters">
    <?php if ($isLoggedIn): ?>
        
        <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'up']) ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="upvoter <?= $currentVoteState === 1 ? 'upvoted' : '' ?>" title="Интересно">▲</button>
        </form>
        
        <div class="score"><?= $score ?></div>

        <?php if ($canDownvote): ?>
            <form action="<?= route('votes.toggle', ['type' => $type, 'id' => $id, 'direction' => 'down']) ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="upvoter <?= $currentVoteState === -1 ? 'upvoted' : '' ?>" title="Не интересно">▼</button>
            </form>
        <?php endif; ?>
        
    <?php else: ?>
        <span class="upvoter">▲</span>
        <div class="score"><?= $score ?></div>
        <span class="upvoter">▼</span>
    <?php endif; ?>
</div>