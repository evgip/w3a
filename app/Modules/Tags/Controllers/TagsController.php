<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller as AppCoreController;
use App\Modules\Auth\Services\Auth;
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
		$userId = (int)\App\Modules\Auth\Services\Auth::id();
		
		$result = $this->service(TagFilterService::class)->addFilter($userId, $tagId);
		
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
		$userId = (int)\App\Modules\Auth\Services\Auth::id();
		
		$result = $this->service(TagFilterService::class)->removeFilter($userId, $tagId);
		
		if ($result['success']) {
			Session::setFlash('success', $result['message'] ?? 'Фильтр удалён');
		} else {
			Session::setFlash('error', $result['message'] ?? 'Ошибка удаления фильтра');
		}
		
		$this->redirect('/filters');
	}

}