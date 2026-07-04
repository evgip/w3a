<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Search\Models\SearchResult;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;

class SearchController extends Controller
{
    /**
     * Handles contextual searching across stories or comments (GET /search?q=...)
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

        // ✅ Получаем данные для голосования
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $canUserDownvote = false;
        $currentVotes = [];
        
        if ($currentUserId > 0) {
            // Получаем модель User из контейнера
            $userModel = $this->container->get(User::class);
            $viewerKarma = $userModel->getUserKarma($currentUserId);
            $minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');
            $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
            
            // Получаем голоса для всех результатов (если это истории)
            if ($what === 'stories' && !empty($results)) {
                $voteModel = $this->container->get(Vote::class);
                foreach ($results as $story) {
                    $currentVotes[$story['id']] = $voteModel->getUserVote($currentUserId, 'story', (int)$story['id']);
                }
            }
        }

        $this->render('index', [
            'title'   => !empty($query) ? 'Результаты поиска' : 'Поиск по сайту',
            'query'   => $query,
            'sortBy'  => $sortBy,
            'what'    => $what,
            'results' => $results,
            // ✅ Передаём данные для голосования в шаблон
            'currentUserId' => $currentUserId,
            'canUserDownvote' => $canUserDownvote,
            'currentVotes' => $currentVotes,
        ]);
    }
}
