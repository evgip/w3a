<?php
namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Session;
use App\Modules\Users\Models\User;

class BanCheckMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        // Проверяем только авторизованных пользователей
        if (!Auth::check()) {
            return $next();
        }
        
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return $next();
        }
        
        $userModel = new User();
        
        if ($userModel->isBanned($userId)) {
            // Получаем информацию о бане
            $banInfo = $userModel->getBanInfo($userId);
            
            // Формируем сообщение
            $message = 'Ваш аккаунт заблокирован.';
            if (!empty($banInfo['reason'])) {
                $message .= ' Причина: ' . $banInfo['reason'];
            }
            if (!empty($banInfo['expires_at'])) {
                $message .= ' Срок до: ' . date('d.m.Y H:i', strtotime($banInfo['expires_at']));
            }
            
            // Логируем попытку доступа
            if (class_exists(\App\Core\Audit::class)) {
                \App\Core\Audit::log('security.banned_access', 'Попытка доступа забаненного пользователя', [
                    'user_id' => $userId,
                    'url' => $_SERVER['REQUEST_URI'] ?? '/',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ]);
            }
            
            // Очищаем сессию (кроме flash-сообщения)
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
        
        return $next();
    }
}