<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;

class TagsController extends Controller
{
    /**
     * Renders the Lobsters-style Master Tags Matrix Catalog Index (GET /tags)
     */
    public function index(): void
    {
        $categoryModel = new Category();
        $categories = $categoryModel->getAllWithTagsCount();

        // Получаем теги для каждой категории
        $tagModel = new Tag();
        $allTags = $tagModel->getAllTags();

        // Группируем теги по category_id
        $tagsByCategory = [];
        foreach ($allTags as $tag) {
            $catId = $tag['category_id'] ?? 0;
            if (!isset($tagsByCategory[$catId])) {
                $tagsByCategory[$catId] = [];
            }
            $tagsByCategory[$catId][] = $tag;
        }

        $this->render('categories/index', [
            'title' => 'Категории тегов',
            'categories' => $categories,
            'tagsByCategory' => $tagsByCategory,
        ]);
    }
	
	/**
	 * Страница историй, которые прикреплены к тегам конкретной категории
	 */
	public function categoriesShow(string $slug): void
	{
		$request = new \App\Core\Request();
		$currentPage = (int)$request->getParams('page', 1);
		if ($currentPage < 1) $currentPage = 1;

		$perPage = config_int('constants.pagination.stories_per_page', 15);
		$offset = ($currentPage - 1) * $perPage;

		// Получаем фильтры пользователя
		$excludeTagIds = [];
		if (\App\Core\Auth::check()) {
			$filterModel = new \App\Modules\Tags\Models\TagFilter();
			$excludeTagIds = $filterModel->getFilteredTagIds(\App\Core\Auth::id());
		}

		$categoryModel = new Category();
		
		// Получаем общее количество историй для пагинации
		$totalStories = $categoryModel->getStoriesCountBySlug($slug, $excludeTagIds);
		$totalPages = (int)ceil($totalStories / $perPage);

		// Получаем категорию с историями
		$category = $categoryModel->getStoriesBySlug($slug, $perPage, $offset, $excludeTagIds);

		if (!$category) {
			$this->redirectWithError('/categories', 'Категория не найдена.');
			return;
		}

		// Подсчёт новых комментариев для каждой истории
		$newCommentsMap = [];
		if (\App\Core\Auth::check() && !empty($category['stories'])) {
			$storyIds = array_column($category['stories'], 'id');
			$readRibbon = new \App\Modules\Stories\Models\ReadRibbon();
			$newCommentsMap = $readRibbon->getNewCommentsCounts(
				(int)$_SESSION['user_id'],
				array_map('intval', $storyIds)
			);
		}

		$this->render('categories-show', [
			'title' => e($category['name']),
			'category' => $category,
			'stories' => $category['stories'],
			'currentPage' => $currentPage,
			'totalPages' => $totalPages,
			'newCommentsMap' => $newCommentsMap,
		]);
	}
		
	/**
	 * Страница управления фильтрами тегов (GET /filters)
	 */
	public function filters(): void
	{
		$this->requireAuth();

		$userId = (int)$_SESSION['user_id'];
		$filterModel = new \App\Modules\Tags\Models\TagFilter();
		$tagModel = new \App\Modules\Tags\Models\Tag();
		
		$filters = $filterModel->getUserFilters($userId);
		$allTags = $tagModel->getAllTags();
		
		$this->render('filters', [
			'title' => 'Фильтры тегов',
			'filters' => $filters,
			'allTags' => $allTags,
			'request' => new \App\Core\Request()
		]);
	}

	/**
	 * Добавить тег в фильтры (POST /filters/add)
	 */
	public function addFilter(): void
	{
		// УБРАЛИ die() — теперь возвращаем настоящий JSON
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if (!\App\Core\Auth::check()) {
				echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
				exit;
			}
			
			$request = new \App\Core\Request();
			
			if (!$request->isCsrfValid()) {
				echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
				exit;
			}
			
			$tagId = (int)$request->getParams('tag_id', 0);
			$userId = (int)\App\Core\Auth::id();
			
			if (!$tagId || !$userId) {
				echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
				exit;
			}
			
			$filterModel = new \App\Modules\Tags\Models\TagFilter();
			$result = $filterModel->addFilter($userId, $tagId);
			
			if ($result) {
				echo json_encode(['success' => true, 'message' => 'Тег добавлен в фильтры']);
			} else {
				echo json_encode(['success' => false, 'message' => 'Тег уже в фильтрах']);
			}
			
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
		// 1. Форсируем JSON
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			// 2. Проверка авторизации
			if (!\App\Core\Auth::check()) {
				error_log('[FILTERS] Auth failed for removeFilter');
				echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
				exit;
			}
			
			$request = new \App\Core\Request();
			
			// 3. ПРЯМАЯ проверка CSRF (НЕ используйте $request->validateCsrf() здесь!)
			$sessionToken = $_SESSION['csrf_token'] ?? '';
			$submittedToken = $request->getParams('csrf_token') ?? '';
			
			error_log("[FILTERS] CSRF Check -> Session: '{$sessionToken}', Submitted: '{$submittedToken}'");
			
			if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
				echo json_encode(['success' => false, 'message' => 'Ошибка безопасности (CSRF). Токены не совпадают.']);
				exit;
			}
			
			$tagId = (int)$request->getParams('tag_id', 0);
			$userId = (int)\App\Core\Auth::id();
			
			if (!$tagId || !$userId) {
				echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
				exit;
			}
			
			// 4. Вызов модели
			$modelClass = '\\App\\Modules\\Tags\\Models\\TagFilter';
			if (!class_exists($modelClass)) {
				throw new \Exception('Класс TagFilter не найден');
			}
			
			$filterModel = new $modelClass();
			$filterModel->removeFilter($userId, $tagId);
			
			echo json_encode(['success' => true, 'message' => 'Тег удалён из фильтров']);
			
		} catch (\Throwable $e) {
			error_log('[FILTERS] Exception in removeFilter: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
			echo json_encode([
				'success' => false, 
				'message' => 'Ошибка сервера: ' . $e->getMessage()
			], 500);
		}
		
		// 5. Жёсткое завершение
		exit;
	}
	
}


