<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Search\Models\SearchResult;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;

/**
 * Контроллер поиска.
 * 
 * Обрабатывает контекстный поиск по историям и комментариям.
 */
class SearchController extends Controller
{
    /**
     * Обработка контекстного поиска по историям или комментариям (GET /search?q=...)
     */
    public function index(): void
    {
        $query  = trim($this->request->getParams('q', ''));
        $sortBy = $this->request->getParams('order', 'relevance');
        $what   = $this->request->getParams('what', 'stories');

        $results = [];
        if (strlen($query) >= 3) {
            $searchModel = $this->service(SearchResult::class);

            if ($what === 'comments') {
                $results = $searchModel->searchComments($query, $sortBy);
            } else {
                $results = $searchModel->searchStories($query, $sortBy);
            }
        } elseif (!empty($query)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Поисковый запрос должен содержать минимум 3 символа.');
        }

        $userContext = $this->getUserContext();

        $canUserDownvote = false;
        $currentVotes = [];

        if ($userContext['isLoggedIn']) {
            // Получаем карму пользователя для проверки возможности downvote
            $userModel = $this->container->get(User::class);
            $viewerKarma = $userModel->getUserKarma($userContext['id']);
            $minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');
            $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);

            // Получаем голоса пользователя за истории (если ищем истории)
            if ($what === 'stories' && !empty($results)) {
                $voteModel = $this->container->get(Vote::class);
                $storyIds = array_column($results, 'id');
                $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
            }
        }

        $this->render('index', [
            'title'   => !empty($query) ? 'Результаты поиска' : 'Поиск по сайту',
            'query'   => $query,
            'sortBy'  => $sortBy,
            'what'    => $what,
            'results' => $results,
            'currentUserId' => $userContext['id'],
            'canUserDownvote' => $canUserDownvote,
            'currentVotes' => $currentVotes,
        ]);
    }
}
