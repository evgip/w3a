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
                (new $errorController())->notFound("Доступ запрещен. Требуются права администратора.");
                exit;
            }
            die("<h1>403 Forbidden</h1>");
        }
    }


}
