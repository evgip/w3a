<?php
declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminTagService;
use App\Modules\Admin\Services\AdminCategoryService;
use App\Modules\Admin\Services\AdminAuditService;
use App\Modules\Admin\Services\AdminToolsService;
use App\Modules\Admin\Services\AdminFirewallService;
use App\Modules\Admin\Services\AdminInvitationService;

class AdminController extends Controller
{
    // Ленивые сервисы
    private ?AdminUserService $userService = null;
    private ?AdminTagService $tagService = null;
    private ?AdminCategoryService $categoryService = null;
    private ?AdminAuditService $auditService = null;
    private ?AdminToolsService $toolsService = null;
    private ?AdminFirewallService $firewallService = null;
    private ?AdminInvitationService $invitationService = null;
    

    // =========================================================================
    // ЛЕНИВЫЕ ГЕТТЕРЫ
    // =========================================================================
    
    private function getUserService(): AdminUserService
    {
        return $this->userService ??= new AdminUserService();
    }
    
    private function getTagService(): AdminTagService
    {
        return $this->tagService ??= new AdminTagService();
    }
    
    private function getCategoryService(): AdminCategoryService
    {
        return $this->categoryService ??= new AdminCategoryService();
    }
    
    private function getAuditService(): AdminAuditService
    {
        return $this->auditService ??= new AdminAuditService();
    }
    
    private function getToolsService(): AdminToolsService
    {
        return $this->toolsService ??= new AdminToolsService();
    }
    
    private function getFirewallService(): AdminFirewallService
    {
        return $this->firewallService ??= new AdminFirewallService();
    }
    
    private function getInvitationService(): AdminInvitationService
    {
        return $this->invitationService ??= new AdminInvitationService();
    }
    
    // =========================================================================
    // DASHBOARD
    // =========================================================================
    
    public function index(): void
    {
        $users = $this->getUserService()->getAllUsers();
        
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
		$user = $this->getUserService()->getAllUsers();
		

        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $user
        ]);
    }
    
    public function usersIndex(): void
    {
        $this->render('users_list', [
            'title' => 'Управление пользователями',
            'users' => $this->getUserService()->getAdminUsersList(100),
            'request' => new Request()
        ]);
    }
    
    public function editUser(string $id): void
    {
        $user = $this->getUserService()->findUser((int)$id);
        
        if (!$user) {
            header('Location: /admin/users');
            exit;
        }
        
        $this->render('user_edit_panel', [
            'title' => 'Модерация профиля: ' . e($user['username']),
            'userItem' => $user,
            'request' => new Request()
        ]);
    }
    
    public function updateUser(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $this->getUserService()->updateUserProfile((int)$id, [
            'email' => $request->getParams('email'),
            'role' => $request->getParams('role'),
            'bio' => $request->getParams('bio'),
        ]);
        
        Session::setFlash('success', 'Данные профиля пользователя успешно изменены администратором.');
        header('Location: /admin/users');
        exit;
    }
    
    public function archiveUser(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $success = $this->getUserService()->archiveUser((int)$id, (int)$_SESSION['user_id']);
        
        if ($success) {
            Session::setFlash('success', 'Пользователь успешно отправлен в архив.');
        }
        
        header('Location: /admin/users');
        exit;
    }
    
    public function restoreUser(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $this->getUserService()->restoreUser((int)$id);
        Session::setFlash('success', 'Аккаунт пользователя успешно восстановлен из архива.');
        
        header('Location: /admin/users');
        exit;
    }
    
    public function toggleUserStatus(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $result = $this->getUserService()->toggleUserStatus((int)$id, (int)$_SESSION['user_id']);
        
        if ($result === -2) {
            // Уже установлена ошибка в сервисе
        } elseif ($result === -1) {
            Session::setFlash('error', 'Пользователь не найден.');
        } elseif ($result === 0) {
            Session::setFlash('success', 'Пользователь успешно заблокирован.');
        } else {
            Session::setFlash('success', 'Доступ для пользователя успешно восстановлен.');
        }
        
        header('Location: /admin/users');
        exit;
    }
    
    public function deleteUserAvatar(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $userId = (int)$id;
        
        if ($this->getUserService()->deleteUserAvatar($userId)) {
            Session::setFlash('success', 'Аватар пользователя успешно удален.');
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
            'tags' => $this->getTagService()->getAllTags()
        ]);
    }
    
    public function showTagCreateForm(): void
    {
        $this->render('tag_create', [
            'title' => 'Создание нового тега',
            'request' => new Request()
        ]);
    }
    
    public function createTag(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $result = $this->getTagService()->createTag([
			'name' => $request->getParams('name'),
            'tag' => $request->getParams('tag'),
            'description' => $request->getParams('description'),
            'is_media' => isset($_POST['is_media']) ? 1 : 0,
            'category_id' => $request->getParams('category_id'),
        ]);
        
        if ($result) {
            Session::setFlash('success', "Тег успешно добавлен.");
            header('Location: /admin/tags');
        } else {
            header('Location: /admin/tags/create');
        }
        exit;
    }
    
    public function showTagEditForm(string $id): void
    {
        $tag = $this->getTagService()->getTagById((int)$id);
        
        if (!$tag) {
            header('Location: /admin/tags');
            exit;
        }
        
        $this->render('tag_edit', [
            'title' => 'Редактирование тега #' . e($tag['tag']),
            'tagItem' => $tag,
            'request' => new Request()
        ]);
    }
    
    public function updateTag(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $tagId = (int)$id;
        $success = $this->getTagService()->updateTag($tagId, [
			'name' => $request->getParams('name'),
            'tag' => $request->getParams('tag'),
            'description' => $request->getParams('description'),
            'is_media' => isset($_POST['is_media']) ? 1 : 0,
            'category_id' => $request->getParams('category_id'),
        ]);
        
        if ($success) {
            Session::setFlash('success', "Параметры тега сохранены.");
            header('Location: /admin/tags');
        } else {
            header("Location: /admin/tags/{$tagId}/edit");
        }
        exit;
    }
    
    public function deleteTag(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $tagId = (int)$id;
        $success = $this->getTagService()->softDeleteTag($tagId);
        
        if ($success) {
            Session::setFlash('success', "Тег успешно удален (перемещен в архив).");
        } else {
            Session::setFlash('error', "Не удалось удалить тег.");
        }
        
        header('Location: /admin/tags');
        exit;
    }
    
    public function restoreTag(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $tagId = (int)$id;
        $success = $this->getTagService()->restoreTag($tagId);
        
        if ($success) {
            Session::setFlash('success', "Тег успешно восстановлен.");
        } else {
            Session::setFlash('error', "Не удалось восстановить тег.");
        }
        
        header('Location: /admin/tags');
        exit;
    }
	
    // =========================================================================
    // КАТЕГОРИИ
    // =========================================================================
    
    public function categoriesIndex(): void
    {
        $this->render('categories_list', [
            'title' => 'Управление категориями тегов',
            'categories' => $this->getCategoryService()->getCategoriesList()
        ]);
    }
    
    public function showCategoryCreateForm(): void
    {
        $this->render('category_create', [
            'title' => 'Создание новой категории',
            'request' => new Request()
        ]);
    }
    
    public function createCategory(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $result = $this->getCategoryService()->createCategory([
            'name' => $request->getParams('name'),
            'slug' => $request->getParams('slug'),
            'description' => $request->getParams('description'),
            'sort_order' => $request->getParams('sort_order'),
        ]);
        
        if ($result) {
            Session::setFlash('success', "Категория успешно создана.");
            header('Location: /admin/categories');
        } else {
            header('Location: /admin/categories/create');
        }
        exit;
    }
    
    public function showCategoryEditForm(string $id): void
    {
        $category = $this->getCategoryService()->getCategoryById((int)$id);
        
        if (!$category) {
            Session::setFlash('error', 'Категория не найдена.');
            header('Location: /admin/categories');
            exit;
        }
        
        $this->render('category_edit', [
            'title' => 'Редактирование категории: ' . e($category['name']),
            'categoryItem' => $category,
            'request' => new Request()
        ]);
    }
    
    public function updateCategory(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $categoryId = (int)$id;
        $success = $this->getCategoryService()->updateCategory($categoryId, [
            'name' => $request->getParams('name'),
            'slug' => $request->getParams('slug'),
            'description' => $request->getParams('description'),
            'sort_order' => $request->getParams('sort_order'),
        ]);
        
        if ($success) {
            Session::setFlash('success', "Категория успешно обновлена.");
            header('Location: /admin/categories');
        } else {
            header("Location: /admin/categories/{$categoryId}/edit");
        }
        exit;
    }
    
    public function deleteCategory(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        if ($this->getCategoryService()->deleteCategory((int)$id)) {
            Session::setFlash('success', "Категория успешно удалена.");
        }
        
        header('Location: /admin/categories');
        exit;
    }
    
    // =========================================================================
    // АУДИТ
    // =========================================================================
    
    public function auditLogs(): void
    {
        $request = new Request();
        
        $filterUserId = isset($_GET['filter_user_id']) && $_GET['filter_user_id'] !== '' ? (int)$_GET['filter_user_id'] : null;
        $filterAction = isset($_GET['filter_action']) && $_GET['filter_action'] !== '' ? trim($_GET['filter_action']) : null;
        $searchQuery = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;
        
        $currentPage = max(1, (int)$request->getParams('page', 1));
        $perPage = 25;
        $offset = ($currentPage - 1) * $perPage;
        
        $logs = $this->getAuditService()->getFilteredLogs($perPage, $offset, $filterUserId, $filterAction, $searchQuery);
        $totalLogs = $this->getAuditService()->getFilteredCount($filterUserId, $filterAction, $searchQuery);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));
        
        $this->render('audit_list', [
            'title' => 'Журнал аудита системы',
            'logs' => $logs,
            'uniqueActions' => $this->getAuditService()->getUniqueActions(),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'currentFilters' => [
                'user_id' => $filterUserId,
                'action' => $filterAction,
                'search' => $searchQuery
            ]
        ]);
    }
    
    public function getSecurityAlertsApi(): void
    {
        header('Content-Type: application/json');
        
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
        
        echo json_encode([
            'status' => 'success',
            'alerts' => $this->getAuditService()->getRecentSecurityAlerts(),
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
            'bannedIps' => $this->getFirewallService()->getBannedIps(),
            'request' => new Request()
        ]);
    }
    
    public function banIp(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $ip = trim($request->getParams('ip_address'));
        $reason = trim($request->getParams('reason')) ?: 'Нарушение правил сообщества';
        
        if ($this->getFirewallService()->banIp($ip, $reason)) {
            Session::setFlash('success', "IP-адрес {$ip} успешно внесен в черный список.");
        } else {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                Session::setFlash('error', 'Указан некорректный IP-адрес.');
            } else {
                Session::setFlash('error', 'Этот IP-адрес уже заблокирован.');
            }
        }
        
        header('Location: /admin/firewall');
        exit;
    }
    
    public function unbanIp(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $ip = $this->getFirewallService()->unbanIp((int)$id);
        
        if ($ip) {
            Session::setFlash('success', "IP-адрес {$ip} успешно разблокирован.");
        }
        
        header('Location: /admin/firewall');
        exit;
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
        $request = new Request();
        $request->validateCsrf();
        
        $this->getToolsService()->compileAssets();
        Session::setFlash('success', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearFileLogs(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $count = $this->getToolsService()->clearFileLogs();
        Session::setFlash('success', "Текстовые логи успешно очищены (обнулено файлов: {$count}).");
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearDbAudit(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        if ($this->getAuditService()->clearAuditLogs()) {
            \App\Core\Audit::log('admin.tools_clear_db', 'Администратор выполнил полную очистку (TRUNCATE) таблицы аудита в базе данных');
            Session::setFlash('success', 'Таблица логов аудита в базе данных успешно и полностью очищена.');
        } else {
            Session::setFlash('error', 'Не удалось очистить таблицу в БД.');
        }
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function cacheRoutes(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        global $router;
        $this->getToolsService()->cacheRoutes($router);
        Session::setFlash('success', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearCacheRoutes(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        global $router;
        $this->getToolsService()->clearCacheRoutes($router);
        Session::setFlash('success', 'Кэш маршрутов успешно сброшен.');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function sendTestEmail(): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        $email = $request->getParams('email');
        
        if (!$email) {
            Session::setFlash('error', 'Не удалось определить email администратора.');
            header('Location: /admin/tools');
            exit;
        }
        
        $error = $this->getToolsService()->sendTestEmail($email);
        
        if ($error === null) {
            Session::setFlash('success', 'Тестовое письмо отправлено успешно на ' . e($email));
        } else {
            Session::setFlash('error', $error);
        }
        
        header('Location: /admin/tools');
        exit;
    }
    
    // =========================================================================
    // ПРИГЛАШЕНИЯ
    // =========================================================================
    
    public function invitationsIndex(): void
    {
        $status = $_GET['status'] ?? 'pending';
        
        $this->render('invitations', [
            'title' => 'Запросы приглашений',
            'requests' => $this->getInvitationService()->getRequests($status),
            'currentStatus' => $status
        ], 'Invitations');
    }
    
    public function approveInvitation(int $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        if ($this->getInvitationService()->approveRequest($id)) {
            Session::setFlash('success', 'Запрос одобрен.');
        } else {
            Session::setFlash('error', 'Не удалось одобрить запрос.');
        }
        
        header('Location: /admin/invitations?status=pending');
        exit;
    }
    
    public function rejectInvitation(int $id): void
    {
        $request = new Request();
        $request->validateCsrf();
        
        if ($this->getInvitationService()->rejectRequest($id)) {
            Session::setFlash('success', 'Запрос отклонён.');
        } else {
            Session::setFlash('error', 'Не удалось отклонить запрос.');
        }
        
        header('Location: /admin/invitations?status=pending');
        exit;
    }
}