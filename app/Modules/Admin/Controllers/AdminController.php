<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Modules\Users\Models\User;
use App\Modules\Tags\Models\Category;
use App\Core\Request as AppCoreRequest;
use App\Core\Session as AppCoreSession;
use App\Core\Audit as AppCoreAudit; 

class AdminController extends Controller
{
    public function __construct()
    {
        // STRICT PROTECTION: If someone is not an admin, freeze immediately with a 403 error page.
        Auth::middlewareAdmin();
    }

    /**
     * Admin Dashboard landing page (GET /admin)
     */
    public function index(): void
    {
        $userModel = new User();
        $allUsers = $userModel->all();

        // Calculate simple analytical summary metrics
        $totalUsersCount = count($allUsers);
        $adminCount = count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'admin'));

        $this->render('dashboard', [
            'title' => 'Панель управления',
            'totalUsers' => $totalUsersCount,
            'totalAdmins' => $adminCount
        ]);
    }

    /**
     * Admin Registered Users List (GET /admin/users)
     */
	public function users(): void
	{
		$userModel = new User();
		
		// Включаем архивные записи в выборку с помощью созданного метода
		$allUsers = $userModel->withTrashed()->all();

		$this->render('users_list', [
			'title' => 'Управление пользователями',
			'users' => $allUsers
		]);
	}
	
  /**
     * Outputs recent high-severity security breaches as clean JSON packets (GET /api/admin/security-alerts)
     */
    public function getSecurityAlertsApi(): void
    {
        header('Content-Type: application/json');

        // Security reinforcement check: Halt immediately if the active session lacks admin credentials
        if (!\App\Core\Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }

        $auditModel = new \App\Modules\Admin\Models\Audit();
        $alerts = $auditModel->getRecentSecurityAlerts();

        echo json_encode([
            'status'  => 'success',
            'alerts'  => $alerts,
            'timestamp' => time()
        ]);
        exit;
    }
	
   /**
     * Журнал аудита системы с поддержкой поиска, фильтрации и постраничной навигации
     */
    public function auditLogs(): void
    {
        \App\Core\Auth::middlewareAdmin();
        $request = new \App\Core\Request();
        
        // 1. Сохраняем и очищаем ваши параметры фильтрации и поиска
        $filterUserId = isset($_GET['filter_user_id']) && $_GET['filter_user_id'] !== '' ? (int)$_GET['filter_user_id'] : null;
        $filterAction = isset($_GET['filter_action']) && $_GET['filter_action'] !== '' ? trim($_GET['filter_action']) : null;
        $searchQuery  = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;

        // 2. Инициализируем параметры пагинации
        $currentPage = (int)$request->getParams('page', 1);
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $perPage = 25; // Выводим по 25 логов на экран вместо жесткого лимита 100
        $offset = ($currentPage - 1) * $perPage;

        // 3. Вызываем модель
        $auditLogModel = new \App\Modules\Admin\Models\AuditLog();
        
        // Получаем отфильтрованные логи для текущей страницы
        $logs = $auditLogModel->getFilteredLogs($perPage, $offset, $filterUserId, $filterAction, $searchQuery);
        
        // Получаем точный список уникальных экшенов для формы фильтрации
        $uniqueActions = $auditLogModel->getUniqueActions();

        // Расчет общего количества страниц с учетом примененных фильтров
        $totalLogs = $auditLogModel->getFilteredCount($filterUserId, $filterAction, $searchQuery);
        $totalPages = (int)ceil($totalLogs / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        // 4. Безопасное декодирование JSON-контекста (переименовано под вашу структуру payload)
        foreach ($logs as &$log) {
            // Проверяем, какое поле используется в вашей таблице: context или payload
            $payloadField = $log['payload'] ?? $log['context'] ?? '';
            $log['decoded_payload'] = $payloadField ? json_decode($payloadField, true) : [];
        }

        // 5. Рендерим ваш шаблон audit_list, передавая все параметры назад
        $this->render('audit_list', [
            'title'         => 'Журнал аудита системы',
            'logs'          => $logs,
            'uniqueActions' => $uniqueActions,
            'currentPage'   => $currentPage,
            'totalPages'    => $totalPages,
            'currentFilters' => [
                'user_id' => $filterUserId,
                'action'  => $filterAction,
                'search'  => $searchQuery
            ]
        ]);
    }
	
	
    /**
     * Archive a user account via soft delete (POST /admin/users/{id}/archive)
     */
    public function archiveUser(string $id): void
    {
        $request = new Request();
        $request->validateCsrf(); // Critical anti-CSRF check

        // Prevent an administrator from accidentally soft-deleting their own active session account
        if ((int)$id === (int)($_SESSION['user_id'] ?? 0)) {
            Session::setFlash('error', 'Вы не можете отправить в архив собственный аккаунт!');
            header('Location: /admin/users');
            exit;
        }

        $userModel = new User();
        $userModel->delete((int)$id); // Core soft delete executor mechanism

        Session::setFlash('success', 'Пользователь успешно отправлен в архив.');
        header('Location: /admin/users');
        exit;
    }

    /**
     * Restore a soft-deleted user account (POST /admin/users/{id}/restore)
     */
    public function restoreUser(string $id): void
    {
        $request = new Request();
        $request->validateCsrf(); // Critical anti-CSRF check

        $userModel = new User();
        $userModel->restore((int)$id); // Core restoration execution loop

        Session::setFlash('success', 'Аккаунт пользователя успешно восстановлен из архива.');
        header('Location: /admin/users');
        exit;
    }
	
    /**
     * Страница инструментов разработчика (GET /admin/tools)
     */
    public function tools(): void
    {
        $this->render('tools', [
            'title' => 'Инструменты разработчика фреймворка'
        ]);
    }

    /**
     * Принудительная сборка ассетов силами PHP (POST /admin/tools/compile-assets)
     */
    public function compileAssets(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf(); // Защита от межсайтовой подделки запросов

        // Вызываем метод ручной пересборки ядра
        \App\Core\Asset::forceRebuild();

        // Записываем событие в аудит безопасности
        \App\Core\Audit::log('admin.assets_compile', 'Администратор запустил ручную сборку CSS ассетов через панель инструментов');

        \App\Core\Session::setFlash('success', 'Все CSS файлы модулей успешно найдены, объединены и сжаты силами PHP!');
        header('Location: /admin/tools');
        exit;
    }

  /**
     * Очистка текстовых файлов логов на диске (POST /admin/tools/clear-file-logs)
     */
    public function clearFileLogs(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf(); // Защита от CSRF атак
 
        $logDir = dirname(__DIR__, 4) . '/storage/logs/';
        $files = ['app.log', 'audit.log'];
        $clearedCount = 0;

        foreach ($files as $file) {
            $filePath = $logDir . $file;
            if (file_exists($filePath)) {
                // Вместо удаления файла просто обнуляем его содержимое, чтобы не ломать права доступа
                file_put_contents($filePath, '');
                $clearedCount++;
            }
        }

        // Фиксируем действие в свежеочищенном аудите
        \App\Core\Audit::log('admin.tools_clear_files', 'Администратор очистил текстовые файлы системных логов на диске');

        \App\Core\Session::setFlash('success', "Текстовые логи успешно очищены (обнулено файлов: {$clearedCount}).");
        header('Location: /admin/tools');
        exit;
    }

    /**
     * Полная очистка таблицы логов аудита в базе данных (POST /admin/tools/clear-db-audit)
     */
    public function clearDbAudit(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        try {
            $db = \App\Core\Database::getConnection();
            
            // Быстрая и полная очистка таблицы сo сбросом автоинкремента
            $db->exec("TRUNCATE TABLE `audit_logs`");

            // Так как таблица полностью пуста, записываем первое новое событие безопасности
            \App\Core\Audit::log('admin.tools_clear_db', 'Администратор выполнил полную очистку (TRUNCATE) таблицы аудита в базе данных');

            \App\Core\Session::setFlash('success', 'Таблица логов аудита в базе данных успешно и полностью очищена.');
        } catch (\Exception $e) {
            \App\Core\Session::setFlash('error', 'Не удалось очистить таблицу в БД: ' . $e->getMessage());
        }

        header('Location: /admin/tools');
        exit;
    }

    /**
     * Display all community tags inside the Admin space (GET /admin/tags)
     */
    public function tagsIndex(): void
    {
        $tagModel = new \App\Modules\Tags\Models\Tag();
        $allTags = $tagModel->getAllTags();

        $this->render('tags_list', [
            'title' => 'Управление тегами',
            'tags' => $allTags
        ]);
    }

    /**
     * Display the new tag form (GET /admin/tags/create)
     */
    public function showTagCreateForm(): void
    {
        $this->render('tag_create', [
            'title' => 'Создание нового тега',
            'request' => new \App\Core\Request()
        ]);
    }

    /**
     * Process new tag insertions (POST /admin/tags/create)
     */
    public function createTag(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $tagName = strtolower(trim($request->getParams('tag')));
        $description = trim($request->getParams('description'));
        $isMedia = isset($_POST['is_media']) ? 1 : 0;

        // Perform fast validation constraints parsing
        $validator = new \App\Core\Validator();
        $isValid = $validator->validate(['tag' => $tagName], ['tag' => 'required|min:2']);

        if (!$isValid) {
            \App\Core\Session::setFlash('error', 'Имя тега должно содержать не менее 2 символов.');
            header('Location: /admin/tags/create');
            exit;
        }

        $tagModel = new \App\Modules\Tags\Models\Tag();
        if ($tagModel->exists($tagName)) {
            \App\Core\Session::setFlash('error', "Тег '{$tagName}' уже присутствует в базе данных.");
            header('Location: /admin/tags/create');
            exit;
        }

		$categoryId = (int)$request->getParams('category_id');
		
		// Валидация категории
		$categoryModel = new Category();
		if (!$categoryModel->find($categoryId)) {
			AppCoreSession::setFlash('error', 'Выбранная категория не существует.');
			header('Location: /admin/tags/create'); // или edit
			exit;
		}

        // Persist safely using base model class mapping definitions
        $tagModel->create([
            'tag' => $tagName,
            'description' => $description,
            'is_media' => $isMedia,
			'category_id' => $categoryId,
        ]);

        \App\Core\Audit::log('admin.tag_created', "Администратор создал новый тег #{$tagName}");
        \App\Core\Session::setFlash('success', "Тег #{$tagName} успешно добавлен.");
        header('Location: /admin/tags');
        exit;
    }

    /**
     * Display the tag modification panel (GET /admin/tags/{id}/edit)
     */
    public function showTagEditForm(string $id): void
    {
        $tagId = (int)$id;
        $tagModel = new \App\Modules\Tags\Models\Tag();
        $tag = $tagModel->getById($tagId);

        if (!$tag) {
            header('Location: /admin/tags');
            exit;
        }

        $this->render('tag_edit', [
            'title' => 'Редактирование тега #' . e($tag['tag']),
            'tagItem' => $tag,
            'request' => new \App\Core\Request()
        ]);
    }

    /**
     * Process tag structural updates (POST /admin/tags/{id}/edit)
     */
    public function updateTag(string $id): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $tagId = (int)$id;
        $tagModel = new \App\Modules\Tags\Models\Tag();
        $tag = $tagModel->getById($tagId);

        if (!$tag) { header('Location: /admin/tags'); exit; }

        $tagName = strtolower(trim($request->getParams('tag')));
        $description = trim($request->getParams('description'));
        $isMedia = isset($_POST['is_media']) ? 1 : 0;

        if (strlen($tagName) < 2) {
            \App\Core\Session::setFlash('error', 'Имя тега должно содержать не менее 2 символов.');
            header("Location: /admin/tags/{$tagId}/edit");
            exit;
        }

        if ($tagModel->exists($tagName, $tagId)) {
            \App\Core\Session::setFlash('error', "Имя тега '{$tagName}' занято другим элементом.");
            header("Location: /admin/tags/{$tagId}/edit");
            exit;
        }

		$categoryId = (int)$request->getParams('category_id');
		
		// Валидация категории
		$categoryModel = new Category();
		if (!$categoryModel->getById($categoryId)) {
			AppCoreSession::setFlash('error', 'Выбранная категория не существует.');
			header('Location: /admin/tags/create'); // или edit
			exit;
		}
		

        $tagModel->update($tagId, [
            'tag' => $tagName,
            'description' => $description,
            'is_media' => $isMedia,
			'category_id' => $categoryId,
        ]);

        \App\Core\Audit::log('admin.tag_updated', "Администратор изменил параметры тега #{$tagName}");
        \App\Core\Session::setFlash('success', "Параметры тега #{$tagName} сохранены.");
        header('Location: /admin/tags');
        exit;
    }

    /**
     * Render the admin profile edit form layout (GET /admin/users/{id}/edit)
     */
    public function editUser(string $id): void
    {
        $userId = (int)$id;
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->find($userId);

        if (!$user) { header('Location: /admin/users'); exit; }

        $this->render('user_edit_panel', [
            'title' => 'Модерация профиля: ' . e($user['username']),
            'userItem' => $user,
            'request' => new \App\Core\Request()
        ]);
    }

    /**
     * Process administrative modifications overrides (POST /admin/users/{id}/edit)
     */
    public function updateUser(string $id): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $userId = (int)$id;
        $userModel = new \App\Modules\Users\Models\User();

        $userModel->update($userId, [
            'email' => trim($request->getParams('email')),
            'role'  => trim($request->getParams('role')),
            'bio'   => trim($request->getParams('bio'))
        ]);

        \App\Core\Session::setFlash('success', 'Данные профиля пользователя успешно изменены администратором.');
        header('Location: /admin/users');
        exit;
    }

    /**
     * Completely remove a user avatar image off the storage system partition (POST /admin/users/{id}/avatar/delete)
     */
	public function deleteUserAvatar(string $id): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $userId = (int)$id;
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->find($userId);

        if ($user && !empty($user['avatar'])) {
            $subFolder = substr($user['avatar'], 0, 2);
            $baseUploadDir = dirname(__DIR__, 3) . '/public/uploads/avatars';
            $oldFolderDir = $baseUploadDir . '/' . $subFolder;
            $avatarPath = $oldFolderDir . '/' . $user['avatar'];
            
            // 1. Удаляем физический файл
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }

            // 2. Проверяем и удаляем опустевшую подпапку шардирования
            if (is_dir($oldFolderDir)) {
                $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
                if (empty($remainingFiles)) {
                    rmdir($oldFolderDir);
                }
            }

            $userModel->update($userId, ['avatar' => null]);
            
            \App\Core\Audit::log('admin.avatar_deleted', "Администратор принудительно удалил аватар пользователя ID: {$userId}");
            \App\Core\Session::setFlash('success', 'Аватар пользователя успешно удален, пустые директории очищены.');
			
			// Inside deleteUserAvatar() right after processing unlinks:
			(new \App\Modules\Users\Models\Notification())->create([
				'user_id' => $userId,
				'type' => 'danger',
				'message' => 'Ваш профильный аватар был принудительно удален администратором из-за нарушения правил сообщества.'
			]);
						
        }

        header("Location: /admin/users/{$userId}/edit");
        exit;
    }

  /**
     * Скомпилировать и сохранить кэш маршрутов (POST /admin/tools/cache-routes)
     */
    public function cacheRoutes(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        global $router; // Обращаемся к запущенному в index.php объекту маршрутизатора
        $router->compileCache();

        \App\Core\Audit::log('admin.cache_routes_compiled', 'Администратор скомпилировал кэш маршрутов фреймворка');
        \App\Core\Session::setFlash('success', 'Маршруты всех модулей успешно оптимизированы и сохранены в кэш-файл.');
        
        header('Location: /admin/tools');
        exit;
    }

    /**
     * Полное удаление кэш-файла маршрутов (POST /admin/tools/clear-cache-routes)
     */
    public function clearCacheRoutes(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        global $router;
        $router->clearCache();

        \App\Core\Audit::log('admin.cache_routes_cleared', 'Администратор полностью удалил кэш маршрутов');
        \App\Core\Session::setFlash('success', 'Кэш маршрутов успешно сброшен. Система вернулась к динамическому чтению файлов.');
        
        header('Location: /admin/tools');
        exit;
    }
	
	
    public function firewallIndex(): void
    {
        $db = \App\Core\Database::getConnection();
        $stmt = $db->query("SELECT * FROM `banned_ips` ORDER BY id DESC");
        $bannedList = $stmt->fetchAll();

        $this->render('firewall', [
            'title' => 'Сетевой экран (Firewall)',
            'bannedIps' => $bannedList,
            'request' => new \App\Core\Request()
        ]);
    }

    public function banIp(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $ip = trim($request->getParams('ip_address'));
        $reason = trim($request->getParams('reason')) ?: 'Нарушение правил сообщества';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            \App\Core\Session::setFlash('error', 'Указан некорректный IP-адрес.');
            header('Location: /admin/firewall');
            exit;
        }

        $db = \App\Core\Database::getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO `banned_ips` (`ip_address`, `reason`) VALUES (:ip, :reason)");
            $stmt->execute(['ip' => $ip, 'reason' => $reason]);

            \App\Core\Audit::log('admin.ip_banned', "Администратор заблокировал IP: {$ip}", ['reason' => $reason]);
            \App\Core\Session::setFlash('success', "IP-адрес {$ip} успешно внесен в черный список.");
        } catch (\Exception $e) {
            \App\Core\Session::setFlash('error', 'Этот IP-адрес уже заблокирован.');
        }

        header('Location: /admin/firewall');
        exit;
    }

    public function unbanIp(string $id): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $db = \App\Core\Database::getConnection();
        
        $stmt = $db->prepare("SELECT `ip_address` FROM `banned_ips` WHERE `id` = :id");
        $stmt->execute(['id' => (int)$id]);
        $ip = $stmt->fetchColumn();

        if ($ip) {
            $stmt = $db->prepare("DELETE FROM `banned_ips` WHERE `id` = :id");
            $stmt->execute(['id' => (int)$id]);

            \App\Core\Audit::log('admin.ip_unbanned', "Администратор разблокировал IP: {$ip}");
            \App\Core\Session::setFlash('success', "IP-адрес {$ip} успешно разблокирован.");
        }

        header('Location: /admin/firewall');
        exit;
    }

    /**
     * Отображение реестра пользователей в админке (GET /admin/users)
     */
    public function usersIndex(): void
    {
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

        // ПОДДКЛЮЧАЕМ ИЗОЛИРОВАННУЮ АДМИНИСТРАТИВНУЮ МОДЕЛЬ ИЗ ТЕКУЩЕГО МОДУЛЯ
        $adminUserModel = new \App\Modules\Admin\Models\AdminUser();
        $usersList = $adminUserModel->getAdminUsersList(100);

        $this->render('users_list', [
            'title'   => 'Управление пользователями',
            'users'   => $usersList,
            'request' => new \App\Core\Request()
        ]);
    }

    /**
     * Переключение статуса активации аккаунта (POST /admin/users/{id}/toggle-status)
     */
    public function toggleUserStatus(string $id): void
    {
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

        $request = new \App\Core\Request();
        $request->validateCsrf();

        $targetUid = (int)$id;
        $currentAdminId = (int)$_SESSION['user_id'];

        if ($targetUid === $currentAdminId) {
            \App\Core\Session::setFlash('error', 'Вы не можете заблокировать собственный административный аккаунт.');
            header('Location: /admin/users');
            exit;
        }

        // ИСПОЛЬЗУЕМ АДМИНИСТРАТИВНУЮ МОДЕЛЬ
        $adminUserModel = new \App\Modules\Admin\Models\AdminUser();
        $newStatus = $adminUserModel->toggleActivationStatus($targetUid);

        if ($newStatus === -1) {
            \App\Core\Session::setFlash('error', 'Пользователь не найден.');
            header('Location: /admin/users');
            exit;
        }

        $user = $adminUserModel->find($targetUid);
        $notifModel = new \App\Modules\Users\Models\Notification();
        
        if ($newStatus === 0) {
            $notifModel->create([
                'user_id' => $targetUid,
                'type'    => 'danger',
                'message' => 'Ваша учетная запись была временно деактивирована администратором из-за нарушения правил сообщества.'
            ]);
            
            \App\Core\Audit::log('admin.user_suspended', "Администратор принудительно ЗАБЛОКИРОВАЛ аккаунт: {$user['username']} (ID: {$targetUid})");
            \App\Core\Session::setFlash('success', "Пользователь {$user['username']} успешно заблокирован.");
        } else {
            $notifModel->create([
                'user_id' => $targetUid,
                'type'    => 'success',
                'message' => 'Приветствуем снова! Доступ к вашей учетной записи полностью восстановлен администратором.'
            ]);
            
            \App\Core\Audit::log('admin.user_unsuspended', "Администратор СНЯЛ блокировку с аккаунта: {$user['username']} (ID: {$targetUid})");
            \App\Core\Session::setFlash('success', "Доступ для пользователя {$user['username']} успешно восстановлен.");
        }

        header('Location: /admin/users');
        exit;
    }

	/**
	 * Отправка тестового письма администратору (POST /admin/tools/send-test-email)
	 */
	public function sendTestEmail(): void
	{
		$request = new \App\Core\Request();
		$request->validateCsrf();

		$adminEmail = $request->getParams('email');

		if (!$adminEmail) {
			\App\Core\Session::setFlash('error', 'Не удалось определить email администратора.');
			header('Location: /admin/tools');
			exit;
		}

		$subject = 'Тестовое письмо — проверка настроек почты';
		$message = 'Это тестовое письмо для проверки работоспособности настроек почты в системе.';

		try {

            // Dispatch via PHPMailer engine
            $success = \App\Core\Mailer::send($adminEmail, $subject, $message);
            // ------------------------------------------------------------------

			if ($success) {
				\App\Core\Session::setFlash('success', 'Тестовое письмо отправлено успешно на ' . e($adminEmail));
				\App\Core\Audit::log('admin.test_email_sent', "Администратор отправил тестовое письмо на {$adminEmail}");
			} else {
				\App\Core\Session::setFlash('error', 'Не удалось отправить тестовое письмо. Проверьте настройки PHP mail() или SMTP.');
			}
		} catch (\Exception $e) {
			\App\Core\Session::setFlash('error', 'Ошибка при отправке письма: ' . $e->getMessage());
		}

		header('Location: /admin/tools');
		exit;
	}
	
	// ==================== INVITATION REQUESTS MANAGEMENT ====================

	/**
	 * Список запросов на приглашение (GET /admin/invitations)
	 */
	public function invitationsIndex(): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }
		
		$status = $_GET['status'] ?? 'pending';
		$allowedStatuses = ['pending', 'approved', 'rejected'];
		
		if (!in_array($status, $allowedStatuses)) {
			$status = 'pending';
		}
		
		$requestModel = new \App\Modules\Invitations\Models\InvitationRequest();
		$requests = $requestModel->getAllRequests($status);
		
		$this->render('invitations', [
			'title' => 'Запросы приглашений',
			'requests' => $requests,
			'currentStatus' => $status
		], 'Invitations');
	}

	/**
	 * Одобрить запрос на приглашение (POST /admin/invitations/{id}/approve)
	 */
	public function approveInvitation(int $id): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }
		
		$request = new \App\Core\Request();
		$request->validateCsrf();
		
		$requestModel = new \App\Modules\Invitations\Models\InvitationRequest();
		
		if ($requestModel->approveRequest($id)) {
			\App\Core\Session::setFlash('success', 'Запрос одобрен.');
		} else {
			\App\Core\Session::setFlash('error', 'Не удалось одобрить запрос.');
		}
		
		header('Location: /admin/invitations?status=pending');
		exit;
	}

	/**
	 * Отклонить запрос на приглашение (POST /admin/invitations/{id}/reject)
	 */
	public function rejectInvitation(int $id): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }
		
		$request = new \App\Core\Request();
		$request->validateCsrf();
		
		$requestModel = new \App\Modules\Invitations\Models\InvitationRequest();
		
		if ($requestModel->rejectRequest($id)) {
			\App\Core\Session::setFlash('success', 'Запрос отклонён.');
		} else {
			\App\Core\Session::setFlash('error', 'Не удалось отклонить запрос.');
		}
		
		header('Location: /admin/invitations?status=pending');
		exit;
	}
	
	// ==================== CATEGORIES MANAGEMENT ====================

	/**
	 * Список категорий (GET /admin/categories)
	 */
	public function categoriesIndex(): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$categoryModel = new Category();
		$categories = $categoryModel->getAdminCategoriesList();

		$this->render('categories_list', [
			'title' => 'Управление категориями тегов',
			'categories' => $categories,
		]);
	}

	/**
	 * Форма создания категории (GET /admin/categories/create)
	 */
	public function showCategoryCreateForm(): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$this->render('category_create', [
			'title' => 'Создание новой категории',
			'request' => new \App\Core\Request(),
		]);
	}

	/**
	 * Обработка создания категории (POST /admin/categories/create)
	 */
	public function createCategory(): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$request = new \App\Core\Request();
		$request->validateCsrf();

		$name = trim($request->getParams('name'));
		$slug = strtolower(trim($request->getParams('slug')));
		$description = trim($request->getParams('description'));
		$sortOrder = (int)$request->getParams('sort_order', 0);

		// Валидация
		if (strlen($name) < 2) {
			AppCoreSession::setFlash('error', 'Название категории должно содержать не менее 2 символов.');
			header('Location: /admin/categories/create');
			exit;
		}

		if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
			AppCoreSession::setFlash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
			header('Location: /admin/categories/create');
			exit;
		}

		$categoryModel = new Category();

		if ($categoryModel->slugExists($slug)) {
			AppCoreSession::setFlash('error', "Категория с slug '{$slug}' уже существует.");
			header('Location: /admin/categories/create');
			exit;
		}

		$categoryId = $categoryModel->createCategory([
			'name' => $name,
			'slug' => $slug,
			'description' => $description,
			'sort_order' => $sortOrder,
		]);

		AppCoreAudit::log('admin.category_created', "Администратор создал категорию '{$name}' (slug: {$slug})");
		AppCoreSession::setFlash('success', "Категория '{$name}' успешно создана.");
		header('Location: /admin/categories');
		exit;
	}

	/**
	 * Форма редактирования категории (GET /admin/categories/{id}/edit)
	 */
	public function showCategoryEditForm(string $id): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$categoryId = (int)$id;
		$categoryModel = new Category();
		$category = $categoryModel->getById($categoryId);

		if (!$category) {
			AppCoreSession::setFlash('error', 'Категория не найдена.');
			header('Location: /admin/categories');
			exit;
		}

		$this->render('category_edit', [
			'title' => 'Редактирование категории: ' . e($category['name']),
			'categoryItem' => $category,
			'request' => new \App\Core\Request(),
		]);
	}

	/**
	 * Обработка обновления категории (POST /admin/categories/{id}/edit)
	 */
	public function updateCategory(string $id): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$request = new \App\Core\Request();
		$request->validateCsrf();

		$categoryId = (int)$id;
		$categoryModel = new Category();
		$category = $categoryModel->getById($categoryId);

		if (!$category) {
			AppCoreSession::setFlash('error', 'Категория не найдена.');
			header('Location: /admin/categories');
			exit;
		}

		$name = trim($request->getParams('name'));
		$slug = strtolower(trim($request->getParams('slug')));
		$description = trim($request->getParams('description'));
		$sortOrder = (int)$request->getParams('sort_order', 0);

		// Валидация
		if (strlen($name) < 2) {
			AppCoreSession::setFlash('error', 'Название категории должно содержать не менее 2 символов.');
			header("Location: /admin/categories/{$categoryId}/edit");
			exit;
		}

		if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
			AppCoreSession::setFlash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
			header("Location: /admin/categories/{$categoryId}/edit");
			exit;
		}

		if ($categoryModel->slugExists($slug, $categoryId)) {
			AppCoreSession::setFlash('error', "Slug '{$slug}' уже используется другой категорией.");
			header("Location: /admin/categories/{$categoryId}/edit");
			exit;
		}

		$categoryModel->updateCategory($categoryId, [
			'name' => $name,
			'slug' => $slug,
			'description' => $description,
			'sort_order' => $sortOrder,
		]);

		AppCoreAudit::log('admin.category_updated', "Администратор обновил категорию '{$name}' (ID: {$categoryId})");
		AppCoreSession::setFlash('success', "Категория '{$name}' успешно обновлена.");
		header('Location: /admin/categories');
		exit;
	}

	/**
	 * Удаление категории (POST /admin/categories/{id}/delete)
	 */
	public function deleteCategory(string $id): void
	{
        if (!\App\Core\Auth::isAdmin()) { 
            header('Location: /'); 
            exit; 
        }

		$request = new \App\Core\Request();
		$request->validateCsrf();

		$categoryId = (int)$id;
		$categoryModel = new Category();
		$category = $categoryModel->getById($categoryId);

		if (!$category) {
			AppCoreSession::setFlash('error', 'Категория не найдена.');
			header('Location: /admin/categories');
			exit;
		}

		// Проверяем наличие тегов
		if ($categoryModel->hasTags($categoryId)) {
			AppCoreSession::setFlash('error', 'Нельзя удалить категорию, содержащую теги. Сначала перенесите теги в другую категорию.');
			header('Location: /admin/categories');
			exit;
		}

		if ($categoryModel->deleteCategory($categoryId)) {
			AppCoreAudit::log('admin.category_deleted', "Администратор удалил категорию '{$category['name']}' (ID: {$categoryId})");
			AppCoreSession::setFlash('success', "Категория '{$category['name']}' успешно удалена.");
		} else {
			AppCoreSession::setFlash('error', 'Не удалось удалить категорию.');
		}

		header('Location: /admin/categories');
		exit;
	}
}
