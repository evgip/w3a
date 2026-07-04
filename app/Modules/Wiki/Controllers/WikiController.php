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
     */
    public function index(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $wikiService = $this->service(WikiService::class);
        $pages = $wikiService->getPagesForTag($tagData['id']);
        $primaryPage = $wikiService->getPrimaryPageForTag($tagData['id']);

        // Определяем, может ли пользователь видеть удалённые страницы
        $canSeeDeleted = Auth::check() 
            && (Auth::isAdmin() || Auth::isModerator());
        
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
                ['url' => "/t/{$tagslug}", 'label' => "#{$tagData['name']}", 'active_pattern' => "/t/{$tagslug}"],
                ['url' => "/t/{$tagslug}/wiki", 'label' => 'Wiki'],
            ])
        ]);
    }

    // =========================================================================
    // ПРОСМОТР WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Просмотр одной wiki страницы.
     */
    public function show(string $tagslug, string $slug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

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
                ['url' => "/t/{$tagslug}", 'label' => "#{$tagData['name']}", 'active_pattern' => "/t/{$tagslug}"],
                ['url' => "/t/{$tagslug}/wiki", 'label' => 'Wiki', 'active_pattern' => "/t/{$tagslug}/wiki"],
                ['url' => "#", 'label' => $page['title']],
            ])
        ]);
    }

    // =========================================================================
    // СОЗДАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Форма создания новой wiki страницы для тега.
     */
    public function showCreateForm(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canCreateWikiForTag($tagData['id'], $userId)) {
            // ✅ Используем Session через контейнер
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав создавать wiki для этого тега');
            $this->redirectBack('/t/' . $tagslug . '/wiki');
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
    public function create(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();
        $permissionService = $this->service(WikiPermissionService::class);

        if (!$permissionService->canCreateWikiForTag($tagData['id'], $userId)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав создавать wiki для этого тега');
            $this->redirectBack('/t/' . $tagslug . '/wiki');
        }

        // ✅ Получаем WikiPage из контейнера для проверки уникальности slug
        $wikiPage = $this->container->get(WikiPage::class);
        $slug = trim($this->request->post('slug', ''));
        if ($wikiPage->slugExists($slug, (int)$tagData['id'])) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Страница с таким URL уже существует в этом теге');
            $this->redirect("/t/{$tagslug}/wiki/create");
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
            $session = $this->container->get(Session::class);
            $session->flash('success', 'Wiki страница успешно создана!');
            $page = $wikiService->getById($pageId);
            $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/create');
    }

    /**
     * Форма редактирования wiki страницы.
     */
    public function showEditForm(string $tagslug, string $id): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

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
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав редактировать эту страницу');
            $this->redirectBack('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        // Получаем старые данные из сессии или из страницы
        $session = $this->container->get(Session::class);
        $old = $session->getFlash('old_input') ?? [
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
    public function update(string $tagslug, string $id): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

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
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав редактировать эту страницу');
            $this->redirectBack('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'edit_summary' => $this->request->getParams('edit_summary', ''),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        // ✅ Получаем WikiPage из контейнера для проверки уникальности slug
        $wikiPage = $this->container->get(WikiPage::class);
        if ($wikiPage->slugExists($data['slug'], (int)$tagData['id'], $pageId)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Страница с таким URL уже существует в этом теге');
            $this->redirect("/t/{$tagslug}/wiki/{$pageId}/edit");
        }

        if ($wikiService->updatePage($pageId, $data, $userId)) {
            $session = $this->container->get(Session::class);
            $session->flash('success', 'Wiki страница успешно обновлена!');
            $page = $wikiService->getById($pageId);
            $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
        }

        // Сохраняем старые данные для повторного отображения формы
        $session = $this->container->get(Session::class);
        $session->set('flash.old_input', $data);
        $this->redirectBack('/t/' . $tagslug . '/wiki/' . $id . '/edit');
    }

    // =========================================================================
    // УДАЛЕНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Удаление wiki страницы.
     */
    public function delete(string $tagslug, string $id): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

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
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав удалять эту страницу');
            $this->redirectBack('/t/' . $tagslug . '/wiki');
        }

        if ($wikiService->deletePage($pageId, $userId)) {
            $session = $this->container->get(Session::class);
            $session->flash('success', 'Wiki страница удалена!');
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki');
    }

    /**
     * Восстановить удалённую wiki страницу
     */
    public function restore(string $tagslug, int $id): void
    {
        $userId = Auth::id();
        
        if ($userId <= 0) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Необходима авторизация');
            $this->redirect('/login');
        }
        
        // Проверка прав (только админы и модераторы)
        if (!Auth::isAdmin() && !Auth::isModerator()) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Недостаточно прав для восстановления');
            $this->redirect("/t/{$tagslug}/wiki");
        }
        
        $wikiService = $this->service(WikiService::class);
        
        try {
            $success = $wikiService->restorePage($id, $userId);
            
            $session = $this->container->get(Session::class);
            if ($success) {
                $session->flash('success', 'Wiki страница успешно восстановлена');
            } else {
                $session->flash('error', 'Не удалось восстановить страницу');
            }
            
        } catch (\Throwable $e) {
            if ($this->logger ?? null) {
                $this->logger->error("[WIKI] Error in restore controller: " . $e->getMessage());
            }
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Произошла ошибка при восстановлении страницы');
        }
        
        $this->redirect("/t/{$tagslug}/wiki");
    }

    // =========================================================================
    // ПОИСК ПО WIKИ
    // =========================================================================

    /**
     * Поиск по wiki страницам тега.
     */
    public function search(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $query = trim($this->request->getParams('q', ''));

        if (empty($query)) {
            $this->redirect('/t/' . $tagslug . '/wiki');
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
     */
    public function permissions(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Только автор тега может управлять правами');
            $this->redirectBack('/t/' . $tagslug . '/wiki');
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
     */
    public function grantPermission(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Только автор тега может давать права');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
        }

        $targetUsername = trim($this->request->getParams('username', ''));
        $canEdit = is_numeric($this->request->getParams('can_edit'));
        $canDelete = is_numeric($this->request->getParams('can_delete'));

        if (empty($targetUsername)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Укажите имя пользователя');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
        }

        $permissionService = $this->service(WikiPermissionService::class);
        $session = $this->container->get(Session::class);

        if ($permissionService->grantPermission(
            $tagData['id'],
            $targetUsername,
            $userId,
            $canEdit,
            $canDelete
        )) {
            $session->flash('success', 'Права успешно выданы пользователю ' . e($targetUsername));
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
    }

    /**
     * Отзыв прав пользователя.
     */
    public function revokePermission(string $tagslug): void
    {
        // ✅ Получаем Tag из контейнера
        $tagModel = $this->container->get(Tag::class);
        $tagData = $tagModel->getBySlug($tagslug);

        if (!$tagData) {
            $this->show404('Тег не найден');
        }

        $userId = Auth::id();

        if ($tagData['user_id'] != $userId && !Auth::isAdmin()) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Только автор тега может отзывать права');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
        }

        $targetUserId = (int)$this->request->getParams('user_id', 0);

        if (!$targetUserId) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Не указан пользователь');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
        }

        $permissionService = $this->service(WikiPermissionService::class);
        $session = $this->container->get(Session::class);

        if ($permissionService->revokePermission($tagData['id'], $targetUserId, $userId)) {
            $session->flash('success', 'Права успешно отозваны');
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Обработка 404 ошибки через модуль Errors
     * 
     * ✅ ИЗМЕНЕНО: Используем контейнер для создания контроллера ошибок
     */
    private function show404(string $message = "Страница не найдена"): void
    {
        $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        if (class_exists($errorController)) {
            // ✅ Создаём через контейнер с инъекцией зависимостей
            $controller = $this->container->make($errorController);
            $controller->notFound($message);
            exit;
        }
        http_response_code(404);
        die("404 Not Found");
    }
}