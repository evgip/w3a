<h1><?= e($title) ?></h1>

<hr>

<h2>Общая статистика</h2>

<table class="data zebra">
    <tbody>
        <tr>
            <td>Пользователей</td>
            <td><strong><?= number_format($totalUsers, 0, ',', ' ') ?></strong></td>
        </tr>
        <tr>
            <td>Публикаций</td>
            <td><strong><?= number_format($totalStories, 0, ',', ' ') ?></strong></td>
        </tr>
        <tr>
            <td>Комментариев</td>
            <td><strong><?= number_format($totalComments, 0, ',', ' ') ?></strong></td>
        </tr>
        <tr>
            <td>Голосов</td>
            <td><strong><?= number_format($totalVotes, 0, ',', ' ') ?></strong></td>
        </tr>
    </tbody>
</table>

<hr>

<h2>Графики</h2>

<h3>Новые пользователи</h3>
<div>
    <?= $usersChartSvg ?>
</div>

<h3>Публикации</h3>
<div>
    <?= $storiesChartSvg ?>
</div>

<h3>Комментарии</h3>
<div>
    <?= $commentsChartSvg ?>
</div>
 

