<?php

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;

/**
 * Middleware для авторизованных пользователей.
 * Проверяет: авторизация + бан.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private Container $container;

    /**
     * ✅ Конструктор с инъекцией контейнера
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(callable $next): mixed
    {
        // ✅ Получаем Session из контейнера
        $session = $this->container->get(Session::class);

        // 1. Проверяем авторизацию
        if (!Auth::check()) {
            $session->flash('error', 'Необходима авторизация');
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $session->flash('error', 'Необходима авторизация');
            header('Location: /login');
            exit;
        }

        // 2. Проверяем бан
        // ✅ Получаем модель User из контейнера
        $userModel = $this->container->get(User::class);
        
        if ($userModel->isBanned($userId)) {
            $banInfo = $userModel->getBanInfo($userId);
            
            $message = 'Ваш аккаунт заблокирован.';
            if (!empty($banInfo['reason'])) {
                $message .= ' Причина: ' . $banInfo['reason'];
            }
            
            if (class_exists(Audit::class)) {
                Audit::log('security.banned_access', 'Попытка доступа забаненного пользователя', 'security', [
                    'user_id' => $userId,
                    'url' => $_SERVER['REQUEST_URI'] ?? '/',
                ]);
            }
            
            // ✅ Сохраняем flash-сообщение перед уничтожением сессии
            $flash = $_SESSION['flash'] ?? null;
            $session->destroy();
            $session->start();
            
            if ($flash) {
                $_SESSION['flash'] = $flash;
            }
            
            $session->flash('error', $message);
            header('Location: /');
            exit;
        }

        // 3. Всё ок
        return $next();
    }
}