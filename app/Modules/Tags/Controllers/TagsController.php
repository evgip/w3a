<?php

declare(strict_types=1);

namespace App\Modules\Tags\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Auth\Services\Auth;
use App\Modules\Tags\Services\CategoryService;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;

/**
 * Контроллер модуля Tags.
 * Отвечает за отображение категорий тегов и управление фильтрами.
 * Вся бизнес-логика вынесена в сервисы CategoryService и TagFilterService.
 */
class TagsController extends Controller
{
    // =========================================================================
    // КАТЕГОРИИ ТЕГОВ
    // =========================================================================

    /**
     * Страница со всеми категориями тегов (GET /categories)
     */
    public function index(): void
    {
        $categories = $this->service(CategoryService::class)->getCategoriesWithTagsCount();
        $tagsByCategory = $this->service(CategoryService::class)->getTagsGroupedByCategory();

        $this->render('index', [
            'title' => 'Категории тегов',
            'categories' => $categories,
            'tagsByCategory' => $tagsByCategory,
        ]);
    }

    /**
     * Страница историй, которые прикреплены к тегам конкретной категории
     * (GET /categories/{slug})
     */
    public function categoriesShow(string $slug): void
    {
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');

        $data = $this->service(CategoryService::class)->getCategoryWithStories($slug, $currentPage, $perPage);

        if (!$data) {
            $this->redirectWithMessage('/categories', 'Категория не найдена.', 'error');
            return;
        }

        $currentUserId = Auth::check() ? Auth::id() : 0;
        $isAdmin = Auth::isAdmin();
        $canUserDownvote = false;
        $currentVotes = [];
        
        if ($currentUserId > 0) {
            // Получаем модель User из контейнера
            $userModel = $this->container->get(User::class);
            $viewerKarma = $userModel->getUserKarma($currentUserId);
            $minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');
            $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
            
            $storyIds = array_column($data['stories'], 'id');
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($currentUserId, $storyIds);
        }

        $this->render('categories-show', [
            'title' => e($data['category']['name']),
            'category' => $data['category'],
            'stories' => $data['stories'],
            'currentPage' => $data['currentPage'],
            'totalPages' => $data['totalPages'],
            'newCommentsMap' => $data['newCommentsMap'],
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
            'canUserDownvote' => $canUserDownvote,
            'currentVotes' => $currentVotes,
        ]);
    }

    // =========================================================================
    // ФИЛЬТРЫ ТЕГОВ
    // =========================================================================

    /**
     * Страница управления фильтрами тегов (GET /filters)
     */
    public function filters(): void
    {
        $userId = Auth::id();
        $data = $this->service(TagFilterService::class)->getFiltersData($userId);

        $this->render('filters', [
            'title' => 'Фильтры тегов',
            'filters' => $data['filters'],
            'allTags' => $data['allTags'],
            'request' => $this->request
        ]);
    }

    /**
     * Добавить тег в фильтры (POST /filters/add)
     */
    public function addFilter(): void
    {
        $tagId = (int)$this->request->post('tag_id', 0);
        $userId = (int)Auth::id();
        
        $result = $this->service(TagFilterService::class)->addFilter($userId, $tagId);
        
        $session = $this->container->get(Session::class);
        if ($result['success']) {
            $session->flash('success', $result['message'] ?? 'Фильтр добавлен');
        } else {
            $session->flash('error', $result['message'] ?? 'Ошибка добавления фильтра');
        }
        
        $this->redirect('/filters');
    }

    /**
     * Удалить тег из фильтров (POST /filters/remove)
     */
    public function removeFilter(): void
    {
        $tagId = (int)$this->request->post('tag_id', 0);
        $userId = (int)Auth::id();
        
        $result = $this->service(TagFilterService::class)->removeFilter($userId, $tagId);
        
        $session = $this->container->get(Session::class);
        if ($result['success']) {
            $session->flash('success', $result['message'] ?? 'Фильтр удалён');
        } else {
            $session->flash('error', $result['message'] ?? 'Ошибка удаления фильтра');
        }
        
        $this->redirect('/filters');
    }
}