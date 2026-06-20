<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller as AppCoreController;
use App\Core\Request as AppCoreRequest;
use App\Core\Auth as AppCoreAuth;
use App\Modules\Tags\Models\Category;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Tags\Services\CategoryService;
use App\Modules\Tags\Services\TagFilterService;

/**
 * Контроллер модуля Tags.
 * Отвечает за отображение категорий тегов и управление фильтрами.
 * Вся бизнес-логика вынесена в сервисы CategoryService и TagFilterService.
 */
class TagsController extends AppCoreController
{
    private ?CategoryService $categoryService = null;
    private ?TagFilterService $filterService = null;

    // =========================================================================
    // ЛЕНИВЫЕ ГЕТТЕРЫ СЕРВИСОВ
    // =========================================================================

    /**
     * Получает экземпляр CategoryService (ленивая инициализация).
     */
    private function getCategoryService(): CategoryService
    {
        if ($this->categoryService === null) {
            $this->categoryService = new CategoryService(
                new Category(),
                new Tag()
            );
        }
        return $this->categoryService;
    }

    /**
     * Получает экземпляр TagFilterService (ленивая инициализация).
     */
    private function getFilterService(): TagFilterService
    {
        if ($this->filterService === null) {
            $this->filterService = new TagFilterService(
                new TagFilter(),
                new Tag()
            );
        }
        return $this->filterService;
    }

    // =========================================================================
    // КАТЕГОРИИ ТЕГОВ
    // =========================================================================

    /**
     * Страница со всеми категориями тегов (GET /categories)
     */
    public function index(): void
    {
        $categories = $this->getCategoryService()->getCategoriesWithTagsCount();
        $tagsByCategory = $this->getCategoryService()->getTagsGroupedByCategory();

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
        $request = new AppCoreRequest();
        $currentPage = max(1, (int)$request->getParams('page', 1));
        $perPage = config_int('constants.pagination.stories_per_page', 15);

        $data = $this->getCategoryService()->getCategoryWithStories($slug, $currentPage, $perPage);

        if (!$data) {
            $this->redirectWithError('/categories', 'Категория не найдена.');
            return;
        }

        $this->render('categories-show', [
            'title' => e($data['category']['name']),
            'category' => $data['category'],
            'stories' => $data['stories'],
            'currentPage' => $data['currentPage'],
            'totalPages' => $data['totalPages'],
            'newCommentsMap' => $data['newCommentsMap'],
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
        $userId = (int)$_SESSION['user_id'];
        $data = $this->getFilterService()->getFiltersData($userId);

        $this->render('filters', [
            'title' => 'Фильтры тегов',
            'filters' => $data['filters'],
            'allTags' => $data['allTags'],
            'request' => new AppCoreRequest()
        ]);
    }

    /**
     * Добавить тег в фильтры (POST /filters/add)
     */
    public function addFilter(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!AppCoreAuth::check()) {
                echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
                exit;
            }

            $request = new AppCoreRequest();

            if (!$request->isCsrfValid()) {
                echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
                exit;
            }

            $tagId = (int)$request->getParams('tag_id', 0);
            $userId = (int)AppCoreAuth::id();

            $result = $this->getFilterService()->addFilter($userId, $tagId);
            echo json_encode($result);

        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
        }

        exit;
    }

    /**
     * Удалить тег из фильтров (POST /filters/remove)
     */
    public function removeFilter(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!AppCoreAuth::check()) {
                error_log('[FILTERS] Auth failed for removeFilter');
                echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
                exit;
            }

            $request = new AppCoreRequest();

            // Проверка CSRF
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            $submittedToken = $request->getParams('csrf_token') ?? '';

            error_log("[FILTERS] CSRF Check -> Session: '{$sessionToken}', Submitted: '{$submittedToken}'");

            if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
                echo json_encode(['success' => false, 'message' => 'Ошибка безопасности (CSRF). Токены не совпадают.']);
                exit;
            }

            $tagId = (int)$request->getParams('tag_id', 0);
            $userId = (int)AppCoreAuth::id();

            $result = $this->getFilterService()->removeFilter($userId, $tagId);
            echo json_encode($result);

        } catch (\Throwable $e) {
            error_log('[FILTERS] Exception in removeFilter: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка сервера: ' . $e->getMessage()
            ], 500);
        }

        exit;
    }
}