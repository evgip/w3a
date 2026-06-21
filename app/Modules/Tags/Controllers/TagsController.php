<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller as AppCoreController;
use App\Core\Auth;
use App\Core\Session;
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
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
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
            'request' => $this->request
        ]);
    }

	/**
	 * Добавить тег в фильтры (POST /filters/add)
	 */
	public function addFilter(): void
	{

		$tagId = (int)$this->request->post('tag_id', 0);
		$userId = (int)\App\Core\Auth::id();
		
		$result = $this->getFilterService()->addFilter($userId, $tagId);
		
		if ($result['success']) {
			Session::setFlash('success', $result['message'] ?? 'Фильтр добавлен');
		} else {
			Session::setFlash('error', $result['message'] ?? 'Ошибка добавления фильтра');
		}
		
		$this->redirect('/filters');
	}

	/**
	 * Удалить тег из фильтров (POST /filters/remove)
	 */
	public function removeFilter(): void
	{
		$tagId = (int)$this->request->post('tag_id', 0);
		$userId = (int)\App\Core\Auth::id();
		
		$result = $this->getFilterService()->removeFilter($userId, $tagId);
		
		if ($result['success']) {
			Session::setFlash('success', $result['message'] ?? 'Фильтр удалён');
		} else {
			Session::setFlash('error', $result['message'] ?? 'Ошибка удаления фильтра');
		}
		
		$this->redirect('/filters');
	}

}