<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Tags\Models\Tag;
use App\Modules\Auth\Services\Auth;

/**
 * Контроллер для работы с wiki страницами.
 * 
 * Бизнес-логика вынесена в Service-классы:
 * - WikiService: создание, обновление, удаление wiki страниц
 * - WikiPermissionService: управление правами доступа
 */
class WikiController extends Controller
{
    // =========================================================================
    // СПИСОК WIKI СТРАНИЦ ТЕГА
    // =========================================================================

    /**
     * Отображение списка wiki страниц для тега.
     *
     * @param string $tag Slug тега
     */
    public function index(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $wikiService = $this->service(WikiService::class);
        $pages = $wikiService->getPagesForTag($tagData['id']);
        $primaryPage = $wikiService->getPrimaryPageForTag($tagData['id']);

		// Определяем, может ли пользователь видеть удалённые страницы
		$canSeeDeleted = \App\Modules\Auth\Services\Auth::check() 
			&& (\App\Modules\Auth\Services\Auth::isAdmin() || \App\Modules\Auth\Services\Auth::isModerator());
		
		// Фильтруем удалённые страницы для обычных пользователей
		if (!$canSeeDeleted) {
			$pages = array_filter($pages, fn($p) => empty($p['deleted_at']));
			if (!empty($primaryPage) && !empty($primaryPage['deleted_at'])) {
				$primaryPage = null;
			}
		}

        $title = 'Wiki: ' . e($tagData['name']);

        $this->render('index', [
            'title' => $title,
            'tag' => $tagData,
            'pages' => $pages,
            'primaryPage' => $primaryPage,
			'canSeeDeleted' => $canSeeDeleted,
			'request' => $this->request,
 		    'breadcrumbs' => $this->renderBreadcrumbs([
                ['url' => '/', 'label' => 'Главная'],
                ['url' => "/t/{$tag}", 'label' => "#{$tagData['name']}", 'active_pattern' => "/t/{$tag}"],
                ['url' => "/t/{$tag}/wiki", 'label' => 'Wiki'],
            ])
        ]);
    }

    // =========================================================================
    // ПРОСМОТР WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Просмотр одной wiki страницы.
     *
     * @param string $tag Slug тега
     * @param string $slug Slug wiki страницы
     */
    public function show(string $tag, string $slug): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $wikiService = $this->service(WikiService::class);
        $page = $wikiService->getPageBySlug($slug, $tagData['id']);

        if (!$page) {
            $this->show404('Wiki страница не найдена');
        }

        $title = $page['title'] . ' — Wiki';

        $this->render('show', [
            'title' => $title,
            'tag' => $tagData,
            'page' => $page,
			'request' => $this->request,
			'breadcrumbs' => $this->renderBreadcrumbs([
                ['url' => '/', 'label' => 'Главная'],
                ['url' => "/t/{$tag}", 'label' => "#{$tagData['name']}", 'active_pattern' => "/t/{$tag}"],
                ['url' => "/t/{$tag}/wiki", 'label' => 'Wiki', 'active_pattern' => "/t/{$tag}/wiki"],
                ['url' => "#", 'label' => $page['title']],
            ])
        ]);
    }

    // =========================================================================
    // СОЗДАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Форма создания новой wiki страницы для тега.
     *
     * @param string $tag Slug тега
     */
    public function showCreateForm(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canCreateWikiForTag($tagData['id'], $userId)) {
            Session::setFlash('error', 'У вас нет прав создавать wiki для этого тега');
            $this->redirectBack('/t/' . $tag . '/wiki');
        }

        $this->render('create', [
            'title' => 'Создать wiki страницу для тега ' . e($tagData['name']),
            'tag' => $tagData,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка создания новой wiki страницы.
     */
    public function create(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canCreateWikiForTag($tagData['id'], $userId)) {
            Session::setFlash('error', 'У вас нет прав создавать wiki для этого тега');
            $this->redirectBack('/t/' . $tag . '/wiki');
        }

        $data = [
            'tag_id' => $tagData['id'],
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        $wikiService = $this->service(WikiService::class);
        $pageId = $wikiService->createPage($data, $userId);

        if ($pageId > 0) {
            Session::setFlash('success', 'Wiki страница успешно создана!');
            $page = $wikiService->getById($pageId);
            $this->redirect('/t/' . $tag . '/wiki/' . $page['slug']);
        }

		// Проверка уникальности slug в пределах тега
		$wikiPage = new WikiPage();
		$slug = trim($this->request->post('slug', ''));
		if ($wikiPage->slugExists($slug, (int)$tagData['id'])) {
			Session::setFlash('error', 'Страница с таким URL уже существует в этом теге');
			$this->redirect("/t/{$tag}/wiki/create");
		}


        // Сохраняем старые данные для повторного отображения формы
       //  Session::setFlash('old_input', $data);
        $this->redirectBack('/t/' . $tag . '/wiki/create');
    }

    /**
     * Форма редактирования wiki страницы.
     */
    public function showEditForm(string $tag, string $id): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $wikiService = $this->service(WikiService::class);
        $page = $wikiService->getById((int)$id);

        if (!$page || $page['tag_id'] != $tagData['id']) {
            $this->show404('Wiki страница не найдена');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canEditPage($page, $userId)) {
            Session::setFlash('error', 'У вас нет прав редактировать эту страницу');
            $this->redirectBack('/t/' . $tag . '/wiki/' . $page['slug']);
        }

        // Получаем старые данные из сессии или из страницы
        $old = Session::getFlash('old_input') ?? [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'content' => $page['content'],
            'is_primary' => $page['is_primary']
        ];

        $this->render('edit', [
            'title' => 'Редактировать: ' . e($page['title']),
            'tag' => $tagData,
            'page' => $page,
            'old' => $old,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления wiki страницы.
     */
    public function update(string $tag, string $id): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $pageId = (int)$id;
        $wikiService = $this->service(WikiService::class);
        $page = $wikiService->getById($pageId);

        if (!$page || $page['tag_id'] != $tagData['id']) {
            $this->show404('Wiki страница не найдена');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canEditPage($page, $userId)) {
            Session::setFlash('error', 'У вас нет прав редактировать эту страницу');
            $this->redirectBack('/t/' . $tag . '/wiki/' . $page['slug']);
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'edit_summary' => $this->request->getParams('edit_summary', ''),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];


	    $slug = trim($this->request->post('slug', ''));
		
		// Проверка уникальности slug (исключая текущую страницу)
		$wikiPage = new WikiPage();
		if ($wikiPage->slugExists($slug, (int)$tagData['id'], $id)) {
			Session::setFlash('error', 'Страница с таким URL уже существует в этом теге');
			$this->redirect("/t/{$tag}/wiki/{$id}/edit");
		}


        if ($wikiService->updatePage($pageId, $data, $userId)) {
            Session::setFlash('success', 'Wiki страница успешно обновлена!');
            $page = $wikiService->getById($pageId);
            $this->redirect('/t/' . $tag . '/wiki/' . $page['slug']);
        }

        // Сохраняем старые данные для повторного отображения формы
        Session::setFlash('old_input', $data);
        $this->redirectBack('/t/' . $tag . '/wiki/' . $id . '/edit');
    }


    // =========================================================================
    // УДАЛЕНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Удаление wiki страницы.
     *
     * @param string $tag Slug тега
     * @param string $id ID wiki страницы
     */
    public function delete(string $tag, string $id): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $pageId = (int)$id;
        $wikiService = $this->service(WikiService::class);
        $page = $wikiService->getById($pageId);

        if (!$page || $page['tag_id'] != $tagData['id']) {
            $this->show404('Wiki страница не найдена');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canDeletePage($page, $userId)) {
            Session::setFlash('error', 'У вас нет прав удалять эту страницу');
            $this->redirectBack('/t/' . $tag . '/wiki');
        }

        if ($wikiService->deletePage($pageId, $userId)) {
            Session::setFlash('success', 'Wiki страница удалена!');
        }

        $this->redirectBack('/t/' . $tag . '/wiki');
    }

	/**
	 * Восстановить удалённую wiki страницу
	 */
	public function restore(string $tag, int $id): void
	{
		$userId = \App\Modules\Auth\Services\Auth::id();
		
		if ($userId <= 0) {
			Session::setFlash('error', 'Необходима авторизация');
			$this->redirect('/login');
		}
		
		// Проверка прав (только админы и модераторы)
		if (!\App\Modules\Auth\Services\Auth::isAdmin() && !\App\Modules\Auth\Services\Auth::isModerator()) {
			Session::setFlash('error', 'Недостаточно прав для восстановления');
			$this->redirect("/t/{$tag}/wiki");
		}
		
		$wikiService = $this->service(WikiService::class);
		
		try {
			$success = $wikiService->restorePage($id, $userId);
			
			if ($success) {
				Session::setFlash('success', 'Wiki страница успешно восстановлена');
			} else {
				Session::setFlash('error', 'Не удалось восстановить страницу');
			}
			
		} catch (\Throwable $e) {
			error_log("[WIKI] Error in restore controller: " . $e->getMessage());
			Session::setFlash('error', 'Произошла ошибка при восстановлении страницы');
		}
		
		$this->redirect("/t/{$tag}/wiki");
	}

    // =========================================================================
    // ПОИСК ПО WIKИ
    // =========================================================================

    /**
     * Поиск по wiki страницам тега.
     *
     * @param string $tag Slug тега
     */
    public function search(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $query = trim($this->request->getParams('q', ''));

        if (empty($query)) {
            $this->redirect('/t/' . $tag . '/wiki');
        }

        $wikiService = $this->service(WikiService::class);
        $results = $wikiService->searchInTag($tagData['id'], $query);

        $this->render('search', [
            'title' => 'Поиск в wiki: ' . e($query),
            'tag' => $tagData,
            'query' => $query,
            'results' => $results
        ]);
    }

    // =========================================================================
    // УПРАВЛЕНИЕ ПРАВАМИ
    // =========================================================================

    /**
     * Страница управления правами на wiki тега.
     *
     * @param string $tag Slug тега
     */
    public function permissions(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            Session::setFlash('error', 'Только автор тега может управлять правами');
            $this->redirectBack('/t/' . $tag . '/wiki');
        }

        $permissionService = $this->service(WikiPermissionService::class);
        $editors = $permissionService->getTagEditors($tagData['id']);

        $this->render('permissions', [
            'title' => 'Управление правами wiki: ' . e($tagData['name']),
            'tag' => $tagData,
            'editors' => $editors
        ]);
    }

    /**
     * Выдача прав пользователю.
     *
     * @param string $tag Slug тега
     */
    public function grantPermission(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            Session::setFlash('error', 'Только автор тега может давать права');
            $this->redirectBack('/t/' . $tag . '/wiki/permissions');
        }

        $targetUsername = trim($this->request->getParams('username', ''));
        $canEdit = is_numeric($this->request->getParams('can_edit'));
        $canDelete = is_numeric($this->request->getParams('can_delete'));

        if (empty($targetUsername)) {
            Session::setFlash('error', 'Укажите имя пользователя');
            $this->redirectBack('/t/' . $tag . '/wiki/permissions');
        }

        $permissionService = $this->service(WikiPermissionService::class);

        if ($permissionService->grantPermission(
            $tagData['id'],
            $targetUsername,
            $userId,
            $canEdit,
            $canDelete
        )) {
            Session::setFlash('success', 'Права успешно выданы пользователю ' . e($targetUsername));
        }

        $this->redirectBack('/t/' . $tag . '/wiki/permissions');
    }

    /**
     * Отзыв прав пользователя.
     *
     * @param string $tag Slug тега
     */
    public function revokePermission(string $tag): void
    {
        $tagModel = new Tag();
        $tagData = $tagModel->getBySlug($tag);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            Session::setFlash('error', 'Только автор тега может отзывать права');
            $this->redirectBack('/t/' . $tag . '/wiki/permissions');
        }

        $targetUserId = (int)$this->request->getParams('user_id', 0);

        if (!$targetUserId) {
            Session::setFlash('error', 'Не указан пользователь');
            $this->redirectBack('/t/' . $tag . '/wiki/permissions');
        }

        $permissionService = $this->service(WikiPermissionService::class);

        if ($permissionService->revokePermission($tagData['id'], $targetUserId, $userId)) {
            Session::setFlash('success', 'Права успешно отозваны');
        }

        $this->redirectBack('/t/' . $tag . '/wiki/permissions');
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Обработка 404 ошибки через модуль Errors
     */
    private function show404(string $message = "Страница не найдена"): void
    {
        $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        if (class_exists($errorController)) {
            (new $errorController())->notFound($message);
            exit;
        }
        http_response_code(404);
        die("404 Not Found");
    }
}
