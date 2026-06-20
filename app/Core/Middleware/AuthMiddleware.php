<?php
namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Session;
use App\Modules\Users\Models\User;

/**
 * Middleware для авторизованных пользователей.
 * Проверяет: авторизация + бан.
 */
class AuthMiddleware implements MiddlewareInterface
{
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
            $banInfo = $userModel->getBanInfo($userId);
            
            $message = 'Ваш аккаунт заблокирован.';
            if (!empty($banInfo['reason'])) {
                $message .= ' Причина: ' . $banInfo['reason'];
            }
            
            if (class_exists(\App\Core\Audit::class)) {
                \App\Core\Audit::log('security.banned_access', 'Попытка доступа забаненного пользователя', [
                    'user_id' => $userId,
                    'url' => $_SERVER['REQUEST_URI'] ?? '/',
                ]);
            }
            
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

        // 3. Всё ок
        return $next();
    }
}