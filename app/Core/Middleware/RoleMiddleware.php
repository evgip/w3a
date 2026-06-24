<?php
namespace App\Core\Middleware;

use App\Core\Session;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;

/**
 * Базовый middleware для проверки ролей.
 * Наследуется конкретными middleware (AdminMiddleware, ModeratorMiddleware).
 */
abstract class RoleMiddleware implements MiddlewareInterface
{
    /**
     * Минимальная роль для доступа
     */
    protected string $requiredRole = 'user';

    public function handle(callable $next): mixed
    {
        // 1. Проверяем авторизацию
        if (!Auth::check()) {
            Session::setFlash('error', 'Необходима авторизация');
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            Session::setFlash('error', 'Необходима авторизация');
            header('Location: /login');
            exit;
        }

        // 2. Проверяем бан
        $userModel = new User();
        if ($userModel->isBanned($userId)) {
            $this->handleBannedUser($userId, $userModel);
        }

        // 3. Проверяем роль
        $userRole = $this->getUserRole($userId, $userModel);
        
        if (!$this->hasAccess($userRole)) {
            $this->handleAccessDenied($userId, $userRole);
        }

        // 4. Всё ок — продолжаем
        return $next();
    }


	/**
	 * Получить роль пользователя
	 */
	protected function getUserRole(int $userId, User $userModel): string
	{
		$user = $userModel->find($userId);
		
		if ($user === null) {
			return 'user';
		}
		
		// Поддержка разных форматов (role или is_admin)
		if (isset($user['role'])) {
			return (string)$user['role'];
		}
		
		if (isset($user['is_admin']) && (int)$user['is_admin'] === 1) {
			return 'admin';
		}
		
		return 'user';
	}

    /**
     * Проверка доступа
     */
    protected function hasAccess(string $userRole): bool
    {
        // Иерархия ролей: admin > moderator > user > guest
        $hierarchy = [
            'guest' => 0,
            'user' => 1,
            'moderator' => 2,
            'admin' => 3,
        ];

        $userLevel = $hierarchy[$userRole] ?? 0;
        $requiredLevel = $hierarchy[$this->requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Обработка забаненного пользователя
     */
    protected function handleBannedUser(int $userId, User $userModel): void
    {
        $banInfo = $userModel->getBanInfo($userId);
        
        $message = 'Ваш аккаунт заблокирован.';
        if (!empty($banInfo['reason'])) {
            $message .= ' Причина: ' . $banInfo['reason'];
        }
        
        // Логируем
        if (class_exists(\App\Core\Audit::class)) {
            \App\Core\Audit::log('security.banned_access', 'Попытка доступа забаненного пользователя', [
                'user_id' => $userId,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
            ]);
        }
        
        // Очищаем сессию
        $flash = $_SESSION['flash'] ?? null;
        session_destroy();
        session_start();
        
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
        
        Session::setFlash('error', $message);
        header('Location: /');
        exit;
    }

    /**
     * Обработка отказа в доступе
     */
    protected function handleAccessDenied(int $userId, string $userRole): void
    {
        if (class_exists(\App\Core\Audit::class)) {
            \App\Core\Audit::log('security.access_denied', 'Попытка доступа к защищённому маршруту', 'security',  [
                'user_id' => $userId,
                'user_role' => $userRole,
                'required_role' => $this->requiredRole,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
            ]);
        }
        
        http_response_code(403);
        
        $errorController = new \App\Modules\Errors\Controllers\ErrorsController();
        $errorController->forbidden("У вас недостаточно прав для доступа к этой странице. Требуется роль: {$this->requiredRole}");
        exit;
    }
}