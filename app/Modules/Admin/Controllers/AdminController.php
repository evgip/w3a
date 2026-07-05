<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Logger;
use App\Modules\Auth\Services\Auth;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminTagService;
use App\Modules\Admin\Services\AdminCategoryService;
use App\Modules\Admin\Services\AdminAuditService;
use App\Modules\Admin\Services\AdminToolsService;
use App\Modules\Admin\Services\AdminFirewallService;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Wiki\Models\WikiPage;

class AdminController extends Controller
{
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * ✅ Хелпер: получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * ✅ Хелпер: получить Audit из контейнера
     */
    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

    /**
     * ✅ Хелпер: получить Logger из контейнера
     */
    private function logger(): Logger
    {
        return $this->container->get(Logger::class);
    }

    /**
     * ✅ Хелпер: получить WikiPage из контейнера
     */
    private function wikiPage(): WikiPage
    {
        return $this->container->get(WikiPage::class);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(): void
    {
        $users = $this->service(AdminUserService::class)->getAllUsers();

        $this->render('dashboard', [
            'title' => 'Панель управления',
            'totalUsers' => count($users),
            'totalAdmins' => count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'))
        ]);
    }

    // =========================================================================
    // ПОЛЬЗОВАТЕЛИ
    // =========================================================================

    public function users(): void
    {
        $user = $this->service(AdminUserService::class)->getAllUsers();

        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $user
        ]);
    }

    public function usersIndex(): void
    {
        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $this->service(AdminUserService::class)->getAdminUsersList(100),
            'request' => $this->request
        ]);
    }

    public function editUser(string $id): void
    {
        $user = $this->service(AdminUserService::class)->findUser((int)$id);

        if (!$user) {
            $this->redirectBack('/admin/users');
        }

        $this->render('user_edit_panel', [
            'title' => 'Модерация профиля: ' . e($user['username']),
            'userItem' => $user,
            'request' => $this->request
        ]);
    }

    public function updateUser(string $id): void
    {
        $this->service(AdminUserService::class)->updateUserProfile((int)$id, [
            'email' => $this->request->getParams('email'),
            'role' => $this->request->getParams('role'),
            'bio' => $this->request->getParams('bio'),
        ]);

        // ✅ Используем хелпер
        $this->session()->flash('success', 'Данные профиля пользователя успешно изменены администратором.');
        $this->redirectBack('/admin/users');
    }

    public function archiveUser(string $id): void
    {
        $userId = Auth::id();
        $success = $this->service(AdminUserService::class)->archiveUser((int)$id, $userId);

        if ($success) {
            $this->session()->flash('success', 'Пользователь успешно отправлен в архив.');
        }

        $this->redirectBack('/admin/users');
    }

    public function restoreUser(string $id): void
    {
        $this->service(AdminUserService::class)->restoreUser((int)$id);
        $this->session()->flash('success', 'Аккаунт пользователя успешно восстановлен из архива.');

        $this->redirectBack('/admin/users');
    }

    public function toggleUserStatus(string $id): void
    {
        $userId = Auth::id();
        $result = $this->service(AdminUserService::class)->toggleUserStatus((int)$id, $userId);

        if ($result === -2) {
            // Уже установлена ошибка в сервисе
        } elseif ($result === -1) {
            $this->session()->flash('error', 'Пользователь не найден.');
        } elseif ($result === 0) {
            $this->session()->flash('success', 'Пользователь успешно заблокирован.');
        } else {
            $this->session()->flash('success', 'Доступ для пользователя успешно восстановлен.');
        }

        $this->redirectBack('/admin/users');
    }

    public function deleteUserAvatar(string $id): void
    {
        $userId = (int)$id;

        if ($this->service(AdminUserService::class)->deleteUserAvatar($userId)) {
            $this->session()->flash('success', 'Аватар пользователя успешно удален.');
        }

        header("Location: /admin/users/{$userId}/edit");
        exit;
    }

    // =========================================================================
    // ТЕГИ
    // =========================================================================

    public function tagsIndex(): void
    {
        $this->render('tags_list', [
            'title' => 'Управление тегами',
            'tags' => $this->service(AdminTagService::class)->getAllTags()
        ]);
    }

    public function showTagCreateForm(): void
    {
        $this->render('tag_create', [
            'title' => 'Создание нового тега',
            'request' => $this->request
        ]);
    }

    public function createTag(): void
    {
        $result = $this->service(AdminTagService::class)->createTag([
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'is_media' => $this->request->post('is_media') !== null ? 1 : 0,
            'category_id' => $this->request->getParams('category_id'),
        ]);

        if ($result) {
            $this->session()->flash('success', "Тег успешно добавлен.");
            $this->redirectBack('/admin/tags');
        }

        $this->redirectBack('/admin/tags/create');
    }

    public function showTagEditForm(string $id): void
    {
        $tag = $this->service(AdminTagService::class)->getTagById((int)$id);

        if (!$tag) {
            $this->redirectBack('/admin/tags');
        }

        $this->render('tag_edit', [
            'title' => 'Редактирование тега #' . e($tag['slug']),
            'tagItem' => $tag,
            'request' => $this->request
        ]);
    }

    public function updateTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->updateTag($tagId, [
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'is_media' => $this->request->post('is_media') !== null ? 1 : 0,
            'category_id' => $this->request->getParams('category_id'),
            'hotness_mod' => $this->request->getParams('hotness_mod'),
        ]);

        if ($success) {
            $this->session()->flash('success', "Параметры тега сохранены.");
            $this->redirectBack('/admin/tags');
        }

        $this->redirectBack('/admin/tags/' . $tagId . '/edit');
    }

    public function deleteTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->softDeleteTag($tagId);

        if ($success) {
            $this->session()->flash('success', "Тег успешно удален (перемещен в архив).");
        } else {
            $this->session()->flash('error', "Не удалось удалить тег.");
        }

        $this->redirectBack('/admin/tags');
    }

    public function restoreTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->service(AdminTagService::class)->restoreTag($tagId);

        if ($success) {
            $this->session()->flash('success', "Тег успешно восстановлен.");
        } else {
            $this->session()->flash('error', "Не удалось восстановить тег.");
        }

        $this->redirectBack('/admin/tags');
    }

    // =========================================================================
    // КАТЕГОРИИ
    // =========================================================================

    public function categoriesIndex(): void
    {
        $this->render('categories_list', [
            'title' => 'Управление категориями тегов',
            'categories' => $this->service(AdminCategoryService::class)->getCategoriesList()
        ]);
    }

    public function showCategoryCreateForm(): void
    {
        $this->render('category_create', [
            'title' => 'Создание новой категории',
            'request' => $this->request
        ]);
    }

    public function createCategory(): void
    {
        $result = $this->service(AdminCategoryService::class)->createCategory([
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'sort_order' => $this->request->getParams('sort_order'),
        ]);

        if ($result) {
            $this->session()->flash('success', "Категория успешно создана.");
            $this->redirectBack('/admin/categories');
        }

        $this->redirectBack('/admin/categories/create');
    }

    public function showCategoryEditForm(string $id): void
    {
        $category = $this->service(AdminCategoryService::class)->getCategoryById((int)$id);

        if (!$category) {
            $this->session()->flash('error', 'Категория не найдена.');
            $this->redirectBack('/admin/categories');
        }

        $this->render('category_edit', [
            'title' => 'Редактирование категории: ' . e($category['name']),
            'categoryItem' => $category,
            'request' => $this->request
        ]);
    }

    public function updateCategory(string $id): void
    {
        $categoryId = (int)$id;
        $success = $this->service(AdminCategoryService::class)->updateCategory($categoryId, [
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'sort_order' => $this->request->getParams('sort_order'),
        ]);

        if ($success) {
            $this->session()->flash('success', "Категория успешно обновлена.");
            $this->redirectBack('/admin/categories');
        } else {
            header("Location: /admin/categories/{$categoryId}/edit");
        }
        exit;
    }

    public function deleteCategory(string $id): void
    {
        if ($this->service(AdminCategoryService::class)->deleteCategory((int)$id)) {
            $this->session()->flash('success', "Категория успешно удалена.");
        }

        $this->redirectBack('/admin/categories');
    }

    // =========================================================================
    // WIKI СТРАНИЦЫ
    // =========================================================================

    /**
     * Список всех wiki страниц
     */
    public function wikiIndex(): void
    {
        // ✅ Получаем WikiPage из контейнера через хелпер
        $wikiPage = $this->wikiPage();
        
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $pages = $wikiPage->getAllPagesWithTags($perPage, $offset);
        $totalPages = $wikiPage->getTotalPagesCount();
        $deletedPages = $wikiPage->getDeletedPagesCount();
        
        $this->render('wiki_list', [
            'title' => 'Управление Wiki страницами',
            'pages' => $pages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'deletedPages' => $deletedPages,
            'totalPagesCount' => ceil($totalPages / $perPage),
        ]);
    }

    /**
     * Мягкое удаление wiki страницы
     */
    public function deleteWikiPage(string $id): void
    {
        // ✅ Получаем WikiPage из контейнера
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);
        
        if (!$page) {
            $this->session()->flash('error', 'Wiki страница не найдена');
            $this->redirectBack('/admin/wiki');
        }
        
        if ($wikiPage->softDelete((int)$id)) {
            // ✅ Получаем Audit из контейнера
            $this->audit()->log('admin.wiki.deleted', 'Wiki страница удалена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => Auth::id(),
            ]);
            
            $this->session()->flash('success', "Wiki страница «{$page['title']}» удалена");
        } else {
            $this->session()->flash('error', 'Ошибка при удалении wiki страницы');
        }
        
        $this->redirectBack('/admin/wiki');
    }

    /**
     * Восстановить wiki страницу
     */
    public function restoreWikiPage(string $id): void
    {
        // ✅ Получаем WikiPage из контейнера
        $wikiPage = $this->wikiPage();
        $page = $wikiPage->findWithDeleted((int)$id);
        
        if (!$page) {
            $this->session()->flash('error', 'Wiki страница не найдена');
            $this->redirectBack('/admin/wiki');
        }
        
        if ($wikiPage->restore((int)$id)) {
            // ✅ Получаем Audit из контейнера
            $this->audit()->log('admin.wiki.restored', 'Wiki страница восстановлена администратором', 'wiki', [
                'page_id' => (int)$id,
                'title' => $page['title'],
                'admin_id' => Auth::id(),
            ]);
            
            $this->session()->flash('success', "Wiki страница «{$page['title']}» восстановлена");
        } else {
            $this->session()->flash('error', 'Ошибка при восстановлении wiki страницы');
        }
        
        $this->redirectBack('/admin/wiki');
    }

    // =========================================================================
    // АУДИТ
    // =========================================================================

    public function auditLogs(): void
    {
        $filterUserIdRaw = $this->request->query('filter_user_id');
        $filterUserId = ($filterUserIdRaw !== null && $filterUserIdRaw !== '')
            ? (int)$filterUserIdRaw
            : null;

        $filterActionRaw = $this->request->query('filter_action');
        $filterAction = ($filterActionRaw !== null && $filterActionRaw !== '')
            ? trim($filterActionRaw)
            : null;

        $filterCategoryRaw = $this->request->query('category');
        $filterCategory = ($filterCategoryRaw !== null && $filterCategoryRaw !== '')
            ? trim($filterCategoryRaw)
            : null;

        $searchQueryRaw = $this->request->query('search');
        $searchQuery = ($searchQueryRaw !== null && $searchQueryRaw !== '')
            ? trim($searchQueryRaw)
            : null;

        $currentPage = max(1, (int)$this->request->query('page', 1));
        $perPage = 25;
        $offset = ($currentPage - 1) * $perPage;

        $auditService = $this->service(AdminAuditService::class);
        
        $logs = $auditService->getFilteredLogs(
            $perPage,
            $offset,
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );
        
        $totalLogs = $auditService->getFilteredCount(
            $filterUserId,
            $filterAction,
            $searchQuery,
            $filterCategory
        );
        
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));

        $this->render('audit_list', [
            'title' => 'Журнал аудита системы',
            'logs' => $logs,
            'uniqueActions' => $auditService->getUniqueActions(),
            'uniqueCategories' => $auditService->getUniqueCategories(),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'currentFilters' => [
                'user_id' => $filterUserId,
                'action' => $filterAction,
                'search' => $searchQuery,
                'category' => $filterCategory
            ],
            'categoryLabels' => [
                'general' => 'Обычные',
                'moderation' => 'Модерация',
                'admin' => 'Администрирование',
                'security' => 'Безопасность',
                'system' => 'Системные',
            ]
        ]);
    }

    public function getSecurityAlertsApi(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'status' => 'success',
            'alerts' => $this->service(AdminAuditService::class)->getRecentSecurityAlerts(),
            'timestamp' => time()
        ]);
        exit;
    }

    // =========================================================================
    // FIREWALL
    // =========================================================================

    public function firewallIndex(): void
    {
        $this->render('firewall', [
            'title' => 'Сетевой экран (Firewall)',
            'bannedIps' => $this->service(AdminFirewallService::class)->getBannedIps(),
            'request' => $this->request
        ]);
    }

    public function banIp(): void
    {
        $ip = trim($this->request->getParams('ip_address'));
        $reason = trim($this->request->getParams('reason')) ?: 'Нарушение правил сообщества';

        if ($this->service(AdminFirewallService::class)->banIp($ip, $reason)) {
            $this->session()->flash('success', "IP-адрес {$ip} успешно внесен в черный список.");
        } else {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->session()->flash('error', 'Указан некорректный IP-адрес.');
            } else {
                $this->session()->flash('error', 'Этот IP-адрес уже заблокирован.');
            }
        }

        $this->redirectBack('/admin/firewall');
    }

    public function unbanIp(string $id): void
    {
        $ip = $this->service(AdminFirewallService::class)->unbanIp((int)$id);

        if ($ip) {
            $this->session()->flash('success', "IP-адрес {$ip} успешно разблокирован.");
        }

        $this->redirectBack('/admin/firewall');
    }

    // =========================================================================
    // ИНСТРУМЕНТЫ
    // =========================================================================

    public function tools(): void
    {
        $this->render('tools', [
            'title' => 'Инструменты разработчика фреймворка'
        ]);
    }

    public function compileAssets(): void
    {
        $this->service(AdminToolsService::class)->compileAssets();
        $this->session()->flash('success', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!');

        $this->redirectBack('/admin/tools');
    }

    public function clearFileLogs(): void
    {
        $count = $this->service(AdminToolsService::class)->clearFileLogs();
        $this->session()->flash('success', "Текстовые логи успешно очищены (обнулено файлов: {$count}).");

        $this->redirectBack('/admin/tools');
    }

    public function clearDbAudit(): void
    {
        if ($this->service(AdminAuditService::class)->clearAuditLogs()) {
            // ✅ Получаем Audit из контейнера
            $this->audit()->log('admin.tools_clear_db', 'Администратор выполнил полную очистку (TRUNCATE) таблицы аудита в базе данных', 'admin');
            $this->session()->flash('success', 'Таблица логов аудита в базе данных успешно и полностью очищена.');
        } else {
            $this->session()->flash('error', 'Не удалось очистить таблицу в БД.');
        }

        $this->redirectBack('/admin/tools');
    }

    public function cacheRoutes(): void
    {
        global $router;
        $this->service(AdminToolsService::class)->cacheRoutes($router);
        $this->session()->flash('success', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.');

        $this->redirectBack('/admin/tools');
    }

    public function clearCacheRoutes(): void
    {
        global $router;
        $this->service(AdminToolsService::class)->clearCacheRoutes($router);
        $this->session()->flash('success', 'Кэш маршрутов успешно сброшен.');

        $this->redirectBack('/admin/tools');
    }

    public function sendTestEmail(): void
    {
        $email = $this->request->getParams('email');

        if (!$email) {
            $this->session()->flash('error', 'Не удалось определить email администратора.');
            $this->redirectBack('/admin/tools');
        }

        $error = $this->service(AdminToolsService::class)->sendTestEmail($email);

        if ($error === null) {
            $this->session()->flash('success', 'Тестовое письмо отправлено успешно на ' . e($email));
        } else {
            $this->session()->flash('error', $error);
        }

        $this->redirectBack('/admin/tools');
    }

    /**
     * Пересчитать confidence_score для комментариев (AJAX)
     */
    public function recalculateConfidenceScore(): void
    {
        try {
            // Очищаем буфер вывода (на случай если что-то уже выведено)
            if (ob_get_level()) {
                ob_clean();
            }

            $offset = (int)$this->request->getParams('offset', 0);
            $batchSize = 1000; // пересчет за раз (один проход)

            $result = $this->service(AdminToolsService::class)->recalculateConfidenceScoreBatch($offset, $batchSize);

            $this->json([
                'success' => true,
                'processed' => $result['processed'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'nextOffset' => $result['nextOffset'],
            ]);
        } catch (\Exception $e) {
            // ✅ Используем хелпер для логирования
            $this->logger()->error('Recalculate confidence score error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->json([
                'success' => false,
                'error' => 'Ошибка сервера: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // ПРИГЛАШЕНИЯ
    // =========================================================================

    public function invitationsIndex(): void
    {
        $status = $this->request->query('status', 'pending');

        $this->render('invitations', [
            'title' => 'Запросы приглашений',
            'requests' => $this->service(AdminInvitationService::class)->getRequests($status),
            'currentStatus' => $status
        ], 'Invitations');
    }

    public function approveInvitation(int $id): void
    {
        if ($this->service(AdminInvitationService::class)->approveRequest($id)) {
            $this->session()->flash('success', 'Запрос одобрен.');
        } else {
            $this->session()->flash('error', 'Не удалось одобрить запрос.');
        }

        $this->redirectBack('/admin/invitations?status=pending');
    }

    public function rejectInvitation(int $id): void
    {
        if ($this->service(AdminInvitationService::class)->rejectRequest($id)) {
            $this->session()->flash('success', 'Запрос отклонён.');
        } else {
            $this->session()->flash('error', 'Не удалось отклонить запрос.');
        }

        $this->redirectBack('/admin/invitations?status=pending');
    }
}