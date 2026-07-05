<?php

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;
use App\Core\Exceptions\RedirectException;

/**
 * Middleware для проверки бана пользователя.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости внедряются через DI-контейнер.
 * ✅ Использует RedirectException для управления потоком вместо exit.
 */
class BanCheckMiddleware implements MiddlewareInterface
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
        // Проверяем только авторизованных пользователей
        if (!Auth::check()) {
            return $next();
        }
        
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return $next();
        }
        
        // ✅ Получаем User из контейнера
        $userModel = $this->container->get(User::class);
        
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
            
            // ✅ Логируем через внедрённый Audit
            $audit = $this->container->get(Audit::class);
            $audit->log('security.banned_access', 'Попытка доступа забаненного пользователя', 'security', [
                'user_id' => $userId,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
            
            // ✅ Очищаем сессию через Session
            $session = $this->container->get(Session::class);
            $flash = $_SESSION['flash'] ?? null;
            $session->destroy();
            session_start();
            
            if ($flash) {
                $_SESSION['flash'] = $flash;
            }
            
            $session->flash('error', $message);
            
            // ✅ Выбрасываем исключение
            throw new RedirectException('/');
        }
        
        return $next();
    }
}