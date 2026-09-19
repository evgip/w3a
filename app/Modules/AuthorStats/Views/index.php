<?php
/**
 * Статистика автора
 */
?>
<h1>Моя статистика</h1>

    <div class="flex gap my3">
        <div class="summary-container">
            <strong class="score"><?= (int)$totalViews ?></strong>
            <div class="text-muted">Просмотров</div>
        </div>
        <div class="summary-container">
            <strong class="score"><?= (int)$uniqueReaders ?></strong>
            <div class="text-muted">Уникальных читателей</div>
        </div>
        <div class="summary-container">
            <strong class="score"><?= $medianReadTime ?><?php if ($medianReadTime > 60): ?> <span class="text-muted">(<?= round($medianReadTime / 60, 1) ?> мин)</span><?php endif; ?></strong>
            <div class="text-muted">Медиана времени, сек</div>
        </div>
        <div class="summary-container">
            <strong class="score"><?= $avgReadTime ?><?php if ($avgReadTime > 60): ?> <span class="text-muted">(<?= round($avgReadTime / 60, 1) ?> мин)</span><?php endif; ?></strong>
            <div class="text-muted">Среднее время, сек</div>
        </div>
        <div class="summary-container">
            <strong class="score"><?= (int)$totalClaps ?></strong>
            <div class="text-muted">Всего хлопков</div>
        </div>
    </div>

    <?php if ($readersChartSvg): ?>
        <h2 class="section-title mt-4">Динамика читателей</h2>
        <div class="chart-block">
            <?= $readersChartSvg ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($trafficLegend)): ?>
        <h2 class="section-title mt-4">Источники трафика</h2>
        <div class="flex gap">
            <?php foreach ($trafficLegend as $src): ?>
                <div class="summary-container">
                    <strong class="score"><?= (int)$src['count'] ?></strong>
                    <div class="text-muted"><?= e($src['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="hint">
            Источники учитываются для читателей, пришедших после внедрения трекинга переходов.
        </p>
    <?php else: ?>
        <p class="hint mt-4">Источники трафика появятся после того, как читатели начнут переходить на статьи.</p>
    <?php endif; ?>

    <?php if (!empty($stories)): ?>
        <h2 class="section-title mt-4">Статьи</h2>
        <table class="is-striped">
            <thead>
                <tr>
                    <th>Статья</th>
                    <th>Дата</th>
                    <th>Просмотры</th>
                    <th>Время</th>
                    <th>Хлопки</th>
                    <th>Комм.</th>
                    <th>Читатели 7д</th>
                    <th>Тренд</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stories as $story): ?>
                    <?php
                    $active7 = (int)($story['active_7'] ?? 0);
                    $activePrev7 = (int)($story['active_prev7'] ?? 0);
                    $delta = $active7 - $activePrev7;
                    $trendClass = $delta > 0 ? 'trend-up' : ($delta < 0 ? 'trend-down' : '');
                    $trendLabel = $delta > 0 ? '▲ +' . $delta : ($delta < 0 ? '▼ ' . $delta : '—');
                    ?>
                    <tr>
                        <td><a href="<?= route('story.show', ['id' => (int)$story['id']]) ?>"><?= e($story['title'] ?: '(без названия)') ?></a></td>
                        <td class="text-muted"><?= adaptive_time($story['created_at']) ?></td>
                        <td><?= (int)$story['views'] ?></td>
                        <td><?= $story['avg_seconds'] > 0 ? round((float)$story['avg_seconds']) . ' с' : '—' ?></td>
                        <td><?= (int)$story['claps'] ?></td>
                        <td><?= (int)$story['comments_count'] ?></td>
                        <td><?= $active7 ?></td>
                        <td class="<?= $trendClass ?>"><?= $trendLabel ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted mt-4">У вас ещё нет опубликованных статей.</p>
    <?php endif; ?>

    <?php if (!empty($recentReaders)): ?>
        <h2 class="section-title mt-6">Недавние читатели</h2>
        <div class="flex gap">
            <?php foreach ($recentReaders as $reader): ?>
                <a href="<?= route('user.profile', ['username' => $reader['username']]) ?>" class="btn btn-sm">
                    <?= e($reader['username']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>