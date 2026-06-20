<?php

namespace App\Core;

class Auth
{
	
	    private static int $sessionTimeout = 900; // Время бездействия в секундах (900 секунд = 15 минут)
		private static bool $isLoopProtect = false; // Flag to prevent infinite recursive loops

    /**
     * Безопасная инициализация сессии с защитой кук
     */

    /**
     * Initializes a secure session environment safely
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            session_start();
        }

        // PROTECTION LAYER: Stop if we are already analyzing a timeout check
        if (self::$isLoopProtect) {
            return;
        }

        // Process session timeout checks safely only for authenticated users
        if (isset($_SESSION['user_id'])) {
            self::$isLoopProtect = true; // Raise lock flag
            
            $currentTime = time();
            $lastActivity = $_SESSION['last_activity_time'] ?? $currentTime;

			// ✅ ПРОВЕРКА БАНА: разлогин забаненных пользователей
			if (self::isBanned()) {
				$userName = $_SESSION['user_name'] ?? 'ID:' . $_SESSION['user_id'];
				
				// Логируем
				Audit::log('auth.banned_logout', 
					"Забаненный пользователь разлогинен: {$userName} (ID: {$_SESSION['user_id']})");
				
				// Очищаем сессию
				$_SESSION = [];
				if (ini_get("session.use_cookies")) {
					$params = session_get_cookie_params();
					setcookie(session_name(), '', time() - 42000,
						$params["path"], $params["domain"],
						$params["secure"], $params["httponly"]
					);
				}
				session_destroy();
				
				self::$isLoopProtect = false;
				
				// Редирект с сообщением
				Session::setFlash('error', 'Ваш аккаунт заблокирован. Обратитесь к администрации.');
				header('Location: /login?banned=1');
				exit;
			}

            if (($currentTime - $lastActivity) > self::$sessionTimeout) {
                // Clear session tracking variables silently without invoking cascading method chains
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                
                self::$isLoopProtect = false; // Reset lock flag
                header('Location: /login?expired=1');
                exit;
            }

            $_SESSION['last_activity_time'] = $currentTime;
            self::$isLoopProtect = false; // Reset lock flag
        }
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

 

    public static function middlewareAdmin(): void
    {
        if (!self::isAdmin()) {
            http_response_code(403);
   
            // ЖУРНАЛ АУДИТА: Попытка несанкционированного доступа к админке
            Audit::log('security.unauthorized_access', "Блокировка попытки входа в панель администратора", [
                'requested_url' => $_SERVER['REQUEST_URI'] ?? '/admin'
            ]);
   
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->forbidden("Доступ запрещен. Требуются права администратора.");
                exit;
            }
            die("<h1>403 Forbidden</h1>");
        }
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

	/**
	 * Middleware: доступ только для модераторов и админов
	 */
	public static function middlewareModerator(): void
	{
		if (!self::isModerator()) {
			http_response_code(403);
			Audit::log('security.unauthorized_access', "Блокировка попытки входа в панель модерации", [
				'requested_url' => $_SERVER['REQUEST_URI'] ?? '/mod',
				'user_id' => $_SESSION['user_id'] ?? null,
			]);
			$errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
			if (class_exists($errorController)) {
				(new $errorController())->forbidden("Доступ запрещен. Требуются права модератора.");
				exit;
			}
			die("<h1>403 Forbidden</h1>");
		}
	}
}
