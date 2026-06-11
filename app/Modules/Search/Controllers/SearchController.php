<?php

namespace App\Modules\Search\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Search\Models\SearchResult;

class SearchController extends Controller
{
    /**
     * Handles contextual searching across stories or comments (GET /search?q=...)
     */
    public function index(): void
    {
        $request = new Request();
        
        $query  = trim($request->getParams('q', ''));
        $sortBy = $request->getParams('order', 'relevance'); 
        $what   = $request->getParams('what', 'stories'); // NEW: 'stories' or 'comments'

        $results = [];
        if (strlen($query) >= 3) {
            $searchModel = new SearchResult();
            
            // Dynamically dispatch execution routes based on type selection parameters
            if ($what === 'comments') {
                $results = $searchModel->searchComments($query, $sortBy);
            } else {
                $results = $searchModel->searchStories($query, $sortBy);
            }
        } elseif (!empty($query)) {
            \App\Core\Session::setFlash('error', 'Поисковый запрос должен содержать минимум 3 символа.');
        }

        $this->render('index', [
            'title'   => !empty($query) ? 'Результаты поиска' : 'Поиск по сайту',
            'query'   => $query,
            'sortBy'  => $sortBy,
            'what'    => $what,
            'results' => $results
        ]);
    }
}

