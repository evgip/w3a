<?php

namespace App\Core;

class Auth
{
	
	private static int $sessionTimeout = 3600; // 1 час неактивностит)
	private static bool $isLoopProtect = false; // Flag to prevent infinite recursive loops

    /**
     * Initializes a secure session environment safely
     */
   public static function initSession(): void
    {
        // Защита от рекурсии
        if (self::$isLoopProtect) {
            return;
        }
        self::$isLoopProtect = true;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Проверяем таймаут сессии
        $currentTime = time();
        if (isset($_SESSION['last_activity_time']) && 
            ($currentTime - $_SESSION['last_activity_time']) > self::$sessionTimeout) {
            
            // Сессия истекла - пробуем восстановить через remember token
            $authService = new \App\Modules\Auth\Services\AuthService();
            if ($authService->attemptRememberLogin()) {
                self::$isLoopProtect = false;
                return; // Сессия восстановлена
            }
            
            // Не удалось восстановить - очищаем сессию
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            self::$isLoopProtect = false;
            
            // Редирект на логин только если это не AJAX запрос
            if (!self::isAjaxRequest()) {
                header('Location: /login?expired=1');
                exit;
            }
            return;
        }

        // Если сессии нет, но есть remember cookie - пробуем восстановить
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
            $authService = new \App\Modules\Auth\Services\AuthService();
            $authService->attemptRememberLogin();
        }

        // Обновляем время последней активности
        $_SESSION['last_activity_time'] = $currentTime;
        self::$isLoopProtect = false;
    }

    /**
     * Проверка AJAX запроса
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Аутентификация пользователя
     */
    public static function attempt(string $email, string $password): bool
    {
        self::initSession();
        
        $userModelClass = "App\\Modules\\Users\\Models\\User";
        if (!class_exists($userModelClass)) {
            return false;
        }

        $userModel = new $userModelClass();
        $user = $userModel->findBy('email', $email);

        if ($user && password_verify($password, $user['password'])) {
			
			// ✅ ПРОВЕРКА БАНА ПРИ ВХОДЕ
			if ((int)($user['is_banned'] ?? 0) === 1) {
				$banMessage = 'Ваш аккаунт заблокирован.';
				if (!empty($user['ban_reason'])) {
					$banMessage .= ' Причина: ' . $user['ban_reason'];
				}
				$banMessage .= ' Обратитесь к администрации.';
				
				Session::setFlash('error', $banMessage);
				
				Audit::log('auth.login_blocked', 
					"Попытка входа забаненного пользователя: {$user['name']} (ID: {$user['id']})");
				
				return false;
			}
					
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity_time'] = time();

            // ЖУРНАЛ АУДИТА: Успешный вход
            Audit::log('auth.login_success', "Пользователь успешно вошел в систему", ['email' => $email]);

            return true;
        }

        // ЖУРНАЛ АУДИТА: Неверный пароль или email (сигнал о возможном подборе)
        Audit::log('auth.login_failed', "Неудачная попытка входа в систему", ['attempted_email' => $email]);

        return false;
    }

    public static function logout(): void
    {
        self::initSession();
        
        if (isset($_SESSION['user_id'])) {
            // ЖУРНАЛ АУДИТА: Фиксируем осознанный выход пользователя
            Audit::log('auth.logout', "Пользователь вышел из системы");
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
	
    public static function check(): bool
    {
        self::initSession();
        return isset($_SESSION['user_id']);
    }

	/**
	 * Получить ID текущего авторизованного пользователя
	 * 
	 * @return int|null ID пользователя или null, если не авторизован
	 */
	public static function id(): ?int
	{
		self::initSession();
		return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
	}

	/**
	 * Получить имя текущего авторизованного пользователя
	 * 
	 * @return string|null Имя пользователя или null, если не авторизован
	 */
	public static function name(): ?string
	{
		self::initSession();
		return $_SESSION['user_name'] ?? null;
	}

	/**
	 * Получить роль текущего авторизованного пользователя
	 * 
	 * @return string|null Роль пользователя или null, если не авторизован
	 */
	public static function role(): ?string
	{
		self::initSession();
		return $_SESSION['user_role'] ?? null;
	}

	/**
	 * Проверка, забанен ли текущий пользователь.
	 * Использует кэш в сессии для оптимизации.
	 * 
	 * @return bool true если пользователь забанен
	 */
	public static function isBanned(): bool
	{
		if (!isset($_SESSION['user_id'])) {
			return false;
		}
		
		$userId = (int)$_SESSION['user_id'];
		$now = time();
		
		// Кэш в сессии: проверяем раз в 60 секунд
		$lastCheck = $_SESSION['ban_check_time'] ?? 0;
		
		if ($now - $lastCheck > 60) {
			$userModel = new \App\Modules\Users\Models\User();
			$isBanned = $userModel->isBanned($userId);
			
			$_SESSION['is_banned'] = $isBanned ? 1 : 0;
			$_SESSION['ban_check_time'] = $now;
		}
		
		return (bool)($_SESSION['is_banned'] ?? false);
	}

    public static function isAdmin(): bool
    {
        self::initSession();
        return self::check() && $_SESSION['user_role'] === 'admin';
    }


	// ============================================
	// ФАЙЛ: app/Core/Auth.php (добавить методы)
	// ============================================

	/**
	 * Проверка: текущий пользователь — модератор (или админ)
	 */
	public static function isModerator(): bool
	{
		self::initSession();
		if (!self::check()) {
			return false;
		}
		return in_array($_SESSION['user_role'], ['moderator', 'admin'], true);
	}

	/**
	 * Проверка: текущий пользователь — член команды модерации (staff)
	 * Админ тоже считается staff
	 */
	public static function isStaff(): bool
	{
		return self::isModerator();
	}
}
