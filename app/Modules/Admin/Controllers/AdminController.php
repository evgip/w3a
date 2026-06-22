<?php
declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Auth;
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
            'request' => $this->request
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
            'request' => $this->request
        ]);
    }
    
    public function updateUser(string $id): void
    {
        $this->getUserService()->updateUserProfile((int)$id, [
            'email' => $this->request->getParams('email'),
            'role' => $this->request->getParams('role'),
            'bio' => $this->request->getParams('bio'),
        ]);
        
        Session::setFlash('success', 'Данные профиля пользователя успешно изменены администратором.');
        header('Location: /admin/users');
        exit;
    }
    
    public function archiveUser(string $id): void
    {
        $success = $this->getUserService()->archiveUser((int)$id, (int)$_SESSION['user_id']);
        
        if ($success) {
            Session::setFlash('success', 'Пользователь успешно отправлен в архив.');
        }
        
        header('Location: /admin/users');
        exit;
    }
    
    public function restoreUser(string $id): void
    {
        $this->getUserService()->restoreUser((int)$id);
        Session::setFlash('success', 'Аккаунт пользователя успешно восстановлен из архива.');
        
        header('Location: /admin/users');
        exit;
    }
    
    public function toggleUserStatus(string $id): void
    {
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
            'request' => $this->request
        ]);
    }
    
    public function createTag(): void
    {
        $result = $this->getTagService()->createTag([
			'name' => $this->request->getParams('name'),
            'tag' => $this->request->getParams('tag'),
            'description' => $this->request->getParams('description'),
            'is_media' => isset($_POST['is_media']) ? 1 : 0,
            'category_id' => $this->request->getParams('category_id'),
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
            'request' => $this->request
        ]);
    }
    
    public function updateTag(string $id): void
    {
        $tagId = (int)$id;
        $success = $this->getTagService()->updateTag($tagId, [
			'name' => $this->request->getParams('name'),
            'tag' => $this->request->getParams('tag'),
            'description' => $this->request->getParams('description'),
            'is_media' => isset($_POST['is_media']) ? 1 : 0,
            'category_id' => $this->request->getParams('category_id'),
			'hotness_mod' => $this->request->getParams('hotness_mod'),
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
            'request' => $this->request
        ]);
    }
    
    public function createCategory(): void
    {
        $result = $this->getCategoryService()->createCategory([
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'sort_order' => $this->request->getParams('sort_order'),
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
            'request' => $this->request
        ]);
    }
    
    public function updateCategory(string $id): void
    {
        $categoryId = (int)$id;
        $success = $this->getCategoryService()->updateCategory($categoryId, [
            'name' => $this->request->getParams('name'),
            'slug' => $this->request->getParams('slug'),
            'description' => $this->request->getParams('description'),
            'sort_order' => $this->request->getParams('sort_order'),
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
		// ✅ Используем query() для GET-параметров с защитой от null
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
		
		$logs = $this->getAuditService()->getFilteredLogs(
			$perPage, $offset, $filterUserId, $filterAction, $searchQuery, $filterCategory
		);
		$totalLogs = $this->getAuditService()->getFilteredCount(
			$filterUserId, $filterAction, $searchQuery, $filterCategory
		);
		$totalPages = max(1, (int)ceil($totalLogs / $perPage));
		
		$this->render('audit_list', [
			'title' => 'Журнал аудита системы',
			'logs' => $logs,
			'uniqueActions' => $this->getAuditService()->getUniqueActions(),
			'uniqueCategories' => $this->getAuditService()->getUniqueCategories(),
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
            'request' => $this->request
        ]);
    }
    
    public function banIp(): void
    {
        $ip = trim($this->request->getParams('ip_address'));
        $reason = trim($this->request->getParams('reason')) ?: 'Нарушение правил сообщества';
        
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
        $this->getToolsService()->compileAssets();
        Session::setFlash('success', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearFileLogs(): void
    {
        $count = $this->getToolsService()->clearFileLogs();
        Session::setFlash('success', "Текстовые логи успешно очищены (обнулено файлов: {$count}).");
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearDbAudit(): void
    {
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
        global $router;
        $this->getToolsService()->cacheRoutes($router);
        Session::setFlash('success', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function clearCacheRoutes(): void
    {
        global $router;
        $this->getToolsService()->clearCacheRoutes($router);
        Session::setFlash('success', 'Кэш маршрутов успешно сброшен.');
        
        header('Location: /admin/tools');
        exit;
    }
    
    public function sendTestEmail(): void
    {
        $email = $this->request->getParams('email');
        
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
			
			$result = $this->getToolsService()->recalculateConfidenceScoreBatch($offset, $batchSize);
			
			$this->json([
				'success' => true,
				'processed' => $result['processed'],
				'total' => $result['total'],
				'hasMore' => $result['hasMore'],
				'nextOffset' => $result['nextOffset'],
			]);
			
		} catch (\Exception $e) {
			// Логируем ошибку
			\App\Core\Logger::error('Recalculate confidence score error', [
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
        $status = $_GET['status'] ?? 'pending';
        
        $this->render('invitations', [
            'title' => 'Запросы приглашений',
            'requests' => $this->getInvitationService()->getRequests($status),
            'currentStatus' => $status
        ], 'Invitations');
    }
    
    public function approveInvitation(int $id): void
    {
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
        if ($this->getInvitationService()->rejectRequest($id)) {
            Session::setFlash('success', 'Запрос отклонён.');
        } else {
            Session::setFlash('error', 'Не удалось отклонить запрос.');
        }
        
        header('Location: /admin/invitations?status=pending');
        exit;
    }
}