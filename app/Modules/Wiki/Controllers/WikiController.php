<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ForbiddenException;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Tags\Models\Tag;
use App\Modules\Auth\Services\Auth;

class WikiController extends Controller
{
    // =========================================================================
    // СПИСОК WIKI СТРАНИЦ ТЕГА
    // =========================================================================

    public function index(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);

        $wikiService = $this->wikiService();
        $pages = $wikiService->getPagesForTag($tagData['id']);
        $primaryPage = $wikiService->getPrimaryPageForTag($tagData['id']);

        $canSeeDeleted = Auth::check() && (Auth::isAdmin() || Auth::isModerator());
        
        if (!$canSeeDeleted) {
            $pages = array_filter($pages, fn($p) => empty($p['deleted_at']));
            if (!empty($primaryPage) && !empty($primaryPage['deleted_at'])) {
                $primaryPage = null;
            }
        }

        $this->render('index', [
            'title' => 'Wiki: ' . e($tagData['name']),
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

    public function show(string $tagslug, string $slug): void
    {
        $tagData = $this->getTagOr404($tagslug);

        $page = $this->wikiService()->getPageBySlug($slug, $tagData['id']);
        
        if (!$page) {
            throw new NotFoundException('Wiki страница не найдена');
        }

        $this->render('show', [
            'title' => $page['title'] . ' — Wiki',
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

    public function showCreateForm(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkCreatePermission($tagData);

        $this->render('create', [
            'title' => 'Создать wiki страницу для тега ' . e($tagData['name']),
            'tag' => $tagData,
            'request' => $this->request
        ]);
    }

    public function create(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkCreatePermission($tagData);

        $slug = trim($this->request->post('slug', ''));
        if ($this->container->get(WikiPage::class)->slugExists($slug, (int)$tagData['id'])) {
            $this->session()->flash('error', 'Страница с таким URL уже существует в этом теге');
            $this->redirect("/t/{$tagslug}/wiki/create");
            return;
        }

        $data = [
            'tag_id' => $tagData['id'],
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        $pageId = $this->wikiService()->createPage($data, Auth::id());

        if ($pageId > 0) {
            $this->session()->flash('success', 'Wiki страница успешно создана!');
            $page = $this->wikiService()->getById($pageId);
            $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
            return;
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/create');
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    public function showEditForm(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        $this->checkEditPermission($page);

        $old = $this->session()->getFlash('old_input') ?? [
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

    public function update(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $pageId = (int)$id;
        $page = $this->getPageOr404($pageId, $tagData['id']);
        $this->checkEditPermission($page);

        $data = [
            'title' => $this->request->getParams('title'),
            'slug' => $this->request->getParams('slug'),
            'content' => $this->request->getParams('content'),
            'edit_summary' => $this->request->getParams('edit_summary', ''),
            'is_primary' => is_numeric($this->request->getParams('is_primary')) ? 1 : 0,
            'status' => $this->request->getParams('status', 'published')
        ];

        if ($this->container->get(WikiPage::class)->slugExists($data['slug'], (int)$tagData['id'], $pageId)) {
            $this->session()->flash('error', 'Страница с таким URL уже существует в этом теге');
            $this->redirect("/t/{$tagslug}/wiki/{$pageId}/edit");
            return;
        }

        if ($this->wikiService()->updatePage($pageId, $data, Auth::id())) {
            $this->session()->flash('success', 'Wiki страница успешно обновлена!');
            $page = $this->wikiService()->getById($pageId);
            $this->redirect('/t/' . $tagslug . '/wiki/' . $page['slug']);
            return;
        }

        $this->session()->set('flash.old_input', $data);
        $this->redirectBack('/t/' . $tagslug . '/wiki/' . $id . '/edit');
    }

    // =========================================================================
    // УДАЛЕНИЕ WIKI СТРАНИЦЫ
    // =========================================================================

    public function delete(string $tagslug, string $id): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $page = $this->getPageOr404((int)$id, $tagData['id']);
        $this->checkDeletePermission($page);

        if ($this->wikiService()->deletePage((int)$id, Auth::id())) {
            $this->session()->flash('success', 'Wiki страница удалена!');
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki');
    }

    public function restore(string $tagslug, int $id): void
    {
        if (!Auth::check()) {
            $this->session()->flash('error', 'Необходима авторизация');
            $this->redirect('/login');
            return;
        }
        
        if (!Auth::isAdmin() && !Auth::isModerator()) {
            $this->session()->flash('error', 'Недостаточно прав для восстановления');
            $this->redirect("/t/{$tagslug}/wiki");
            return;
        }
        
        try {
            $success = $this->wikiService()->restorePage($id, Auth::id());
            
            if ($success) {
                $this->session()->flash('success', 'Wiki страница успешно восстановлена');
            } else {
                $this->session()->flash('error', 'Не удалось восстановить страницу');
            }
            
        } catch (\Throwable $e) {

            $this->container->get(\App\Core\Logger::class)->error(
                "[WIKI] Error in restore controller: " . $e->getMessage()
            );
            $this->session()->flash('error', 'Произошла ошибка при восстановлении страницы');
        }
        
        $this->redirect("/t/{$tagslug}/wiki");
    }

    // =========================================================================
    // ПОИСК ПО WIKИ
    // =========================================================================

    public function search(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $query = trim($this->request->getParams('q', ''));

        if (empty($query)) {
            $this->redirect('/t/' . $tagslug . '/wiki');
            return;
        }

        $results = $this->wikiService()->searchInTag($tagData['id'], $query);

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

    public function permissions(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может управлять правами');

        $editors = $this->permissionService()->getTagEditors($tagData['id']);

        $this->render('permissions', [
            'title' => 'Управление правами wiki: ' . e($tagData['name']),
            'tag' => $tagData,
            'editors' => $editors
        ]);
    }

    public function grantPermission(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может давать права');

        $targetUsername = trim($this->request->getParams('username', ''));
        $canEdit = is_numeric($this->request->getParams('can_edit'));
        $canDelete = is_numeric($this->request->getParams('can_delete'));

        if (empty($targetUsername)) {
            $this->session()->flash('error', 'Укажите имя пользователя');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
            return;
        }

        if ($this->permissionService()->grantPermission(
            $tagData['id'],
            $targetUsername,
            Auth::id(),
            $canEdit,
            $canDelete
        )) {
            $this->session()->flash('success', 'Права успешно выданы пользователю ' . e($targetUsername));
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
    }

    public function revokePermission(string $tagslug): void
    {
        $tagData = $this->getTagOr404($tagslug);
        $this->checkTagOwnerOrAdmin($tagData, 'Только автор тега может отзывать права');

        $targetUserId = (int)$this->request->getParams('user_id', 0);

        if (!$targetUserId) {
            $this->session()->flash('error', 'Не указан пользователь');
            $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
            return;
        }

        if ($this->permissionService()->revokePermission($tagData['id'], $targetUserId, Auth::id())) {
            $this->session()->flash('success', 'Права успешно отозваны');
        }

        $this->redirectBack('/t/' . $tagslug . '/wiki/permissions');
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * ✅ НОВОЕ: Получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * ✅ НОВОЕ: Получить WikiService из контейнера
     */
    private function wikiService(): WikiService
    {
        return $this->service(WikiService::class);
    }

    /**
     * ✅ НОВОЕ: Получить WikiPermissionService из контейнера
     */
    private function permissionService(): WikiPermissionService
    {
        return $this->service(WikiPermissionService::class);
    }

    /**
     * ✅ НОВОЕ: Получить тег или выбросить 404
     */
    private function getTagOr404(string $tagslug): array
    {
        $tagData = $this->container->get(Tag::class)->getBySlug($tagslug);
        
        if (!$tagData) {
            throw new NotFoundException('Тег не найден');
        }
        
        return $tagData;
    }

    /**
     * ✅ НОВОЕ: Получить wiki страницу или выбросить 404
     */
    private function getPageOr404(int $pageId, int $tagId): array
    {
        $page = $this->wikiService()->getById($pageId);
        
        if (!$page || $page['tag_id'] != $tagId) {
            throw new NotFoundException('Wiki страница не найдена');
        }
        
        return $page;
    }

    /**
     * ✅ НОВОЕ: Проверить права на создание wiki
     */
    private function checkCreatePermission(array $tagData): void
    {
        if (!$this->permissionService()->canCreateWikiForTag($tagData['id'], Auth::id())) {
            $this->session()->flash('error', 'У вас нет прав создавать wiki для этого тега');
            $this->redirectBack('/t/' . $tagData['slug'] . '/wiki');
            exit; // Временный exit до полного рефакторинга
        }
    }

    /**
     * ✅ НОВОЕ: Проверить права на редактирование wiki
     */
    private function checkEditPermission(array $page): void
    {
        if (!$this->permissionService()->canEditPage($page, Auth::id())) {
            $this->session()->flash('error', 'У вас нет прав редактировать эту страницу');
            $this->redirectBack('/t/' . $page['tag_slug'] . '/wiki/' . $page['slug']);
            exit; // Временный exit до полного рефакторинга
        }
    }

    /**
     * ✅ НОВОЕ: Проверить права на удаление wiki
     */
    private function checkDeletePermission(array $page): void
    {
        if (!$this->permissionService()->canDeletePage($page, Auth::id())) {
            $this->session()->flash('error', 'У вас нет прав удалять эту страницу');
            $this->redirectBack('/t/' . $page['tag_slug'] . '/wiki');
            exit; // Временный exit до полного рефакторинга
        }
    }

    /**
     * ✅ НОВОЕ: Проверить что пользователь владелец тега или админ
     */
    private function checkTagOwnerOrAdmin(array $tagData, string $errorMessage): void
    {
        if ($tagData['user_id'] != Auth::id() && !Auth::isAdmin()) {
            $this->session()->flash('error', $errorMessage);
            $this->redirectBack('/t/' . $tagData['slug'] . '/wiki');
            exit; // Временный exit до полного рефакторинга
        }
    }
}