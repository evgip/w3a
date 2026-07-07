<?php

namespace App\Modules\Stats\Controllers;

use App\Core\Controller;
use App\Modules\Stats\Models\Stats;

class StatsController extends Controller
{
    public function index(): void
    {
        $stats =  $this->service(Stats::class);

        $this->render('index', [
            'title'             => 'Статистика',
            'totalUsers'        => $stats->getTotalUsers(),
            'totalStories'      => $stats->getTotalStories(),
            'totalComments'     => $stats->getTotalComments(),
            'totalVotes'        => $stats->getTotalVotes(),
            'usersChartSvg'     => $stats->getUsersChartSvg(12),
            'storiesChartSvg'   => $stats->getStoriesChartSvg(12),
            'commentsChartSvg'  => $stats->getCommentsChartSvg(12),
        ]);
    }
}
