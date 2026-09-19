<?php

declare(strict_types=1);

namespace App\Modules\AuthorStats\Controllers;

use App\BaseController;
use W3a\Core\Http\ViewResponse;
use W3a\Core\View\SvgChart;
use App\Modules\AuthorStats\Models\AuthorStatsModel;
use App\Modules\Common\Support\Layout;

class AuthorStatsController extends BaseController
{
    public function index(): ViewResponse
    {
        $userContext = $this->getUserContext();
        $userId = $userContext['id'];

        if ($userId <= 0) {
            return $this->redirect('/login');
        }

        Layout::set(Layout::MEDIUM);

        $model = $this->service(AuthorStatsModel::class);

        $totalViews = $model->getTotalViews($userId);
        $uniqueReaders = $model->getUniqueReaders($userId);
        $avgReadTime = $model->getAvgReadTime($userId);
        $medianReadTime = $model->getMedianReadTime($userId);
        $totalClaps = $model->getTotalClapsReceived($userId);
        $stories = $model->getStoriesStats($userId);

        // Графики
        $chart = new SvgChart(600, 200, 40, '#0077cc', 'rgba(0, 119, 204, 0.1)');
        $readersChartSvg = $chart->lineChart($model->getReadersByDay($userId, 30), 'Новые читатели (30 дней)');

        // Источники трафика
        $trafficSources = $model->getTrafficSources($userId);
        $trafficSourceNames = $model->getTrafficSourceNames();
        $trafficSourceMap = [];
        foreach ($trafficSources as $src) {
            $trafficSourceMap[$src['label']] = (int)$src['value'];
        }

        // Тренд по статьям: активные читатели за 7 и предыдущие 7 дней
        $storiesWithTrend = [];
        foreach ($stories as $story) {
            $story['active_7'] = $model->getStoryActiveReaders((int)$story['id'], 7);
            $story['active_prev7'] = $model->getStoryActiveReaders((int)$story['id'], 14) - $story['active_7'];
            $storiesWithTrend[] = $story;
        }

        // Ближайший заполненный интервал для графика источников
        $trafficLegend = [];
        foreach ($trafficSourceNames as $key => $name) {
            if (isset($trafficSourceMap[$key]) && $trafficSourceMap[$key] > 0) {
                $trafficLegend[] = ['key' => $key, 'name' => $name, 'count' => $trafficSourceMap[$key]];
            }
        }
        arsort($trafficSourceMap);

        return $this->render('index', [
            'title'           => 'Моя статистика',
            'totalViews'      => $totalViews,
            'uniqueReaders'   => $uniqueReaders,
            'avgReadTime'     => round($avgReadTime),
            'medianReadTime'  => round($medianReadTime),
            'totalClaps'      => $totalClaps,
            'stories'         => $storiesWithTrend,
            'recentReaders'   => $model->getRecentReaders($userId),
            'readersChartSvg' => $readersChartSvg,
            'trafficSourceMap' => $trafficSourceMap,
            'trafficLegend'   => $trafficLegend,
        ]);
    }
}
