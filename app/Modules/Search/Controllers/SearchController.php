<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use W3a\Core\Http\Session;
use W3a\Core\Http\ViewResponse;
use App\BaseController;
use App\Modules\Search\Models\SearchResult;
use App\Modules\Votes\Models\Vote;
use App\Modules\Muted\Services\MuteService;

/**
 * Контроллер поиска.
 * 
 * Обрабатывает контекстный поиск по историям и комментариям.
 */
class SearchController extends BaseController
{
    /**
     * Максимальная длина поискового запроса (в символах).
     * Более длинные строки обрезаются, а в форму подставляется усечённое значение.
     */
    private const MAX_QUERY_LENGTH = 100;

    /**
     * Обработка контекстного поиска по историям или комментариям (GET /search?q=...)
     */
    public function index(): ViewResponse
    {
        $query  = trim($this->request->getParams('q', ''));
        $sortBy = $this->request->getParams('order', 'relevance');
        $what   = $this->request->getParams('what', 'stories');

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);
            $session = $this->service(Session::class);
            $session->flash('error', 'Запрос слишком длинный, он обрезан до ' . self::MAX_QUERY_LENGTH . ' символов.');
        }

        $results = [];
        if (strlen($query) >= 3) {
            $searchModel = $this->service(SearchResult::class);

            // Авторы-баны (mute): исключаем их истории и комментарии,
            // как в основной ленте, для консистентности результатов поиска.
            $currentUserId = (int)$this->getUserContext()['id'];
            $mutedUserIds = $this->service(MuteService::class)->getMutedUserIds($currentUserId);

            if ($what === 'comments') {
                $results = $searchModel->searchComments($query, $sortBy, $mutedUserIds);
            } else {
                $results = $searchModel->searchStories($query, $sortBy, $mutedUserIds);
            }
        } elseif (!empty($query)) {
            $session = $this->service(Session::class);
            $session->flash('error', 'Поисковый запрос должен содержать минимум 3 символа.');
        }

        $userContext = $this->getUserContext();

        $userClaps = [];
        if ($userContext['isLoggedIn'] && $what === 'stories' && !empty($results)) {
            $voteModel = $this->service(Vote::class);
            $storyIds = array_column($results, 'id');
            $userClaps = $voteModel->getUserClapsForStories($userContext['id'], $storyIds);
        }

        return $this->render('index', [
            'title'   => !empty($query) ? 'Результаты поиска' : 'Поиск по сайту',
            'query'   => $query,
            'sortBy'  => $sortBy,
            'what'    => $what,
            'results' => $results,
            'currentUserId' => $userContext['id'],
            'userClaps' => $userClaps,
        ]);
    }
}
