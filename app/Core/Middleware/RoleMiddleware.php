<?php

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;
use App\Core\Exceptions\RedirectException;
use App\Core\Exceptions\AccessDeniedException;

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
    
    /**
     * ✅ DI-контейнер для получения зависимостей
     */
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
            throw new RedirectException('/login');
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $session->flash('error', 'Необходима авторизация');
            throw new RedirectException('/login');
        }

        // 2. Проверяем бан
        $userModel = $this->container->get(User::class);
        
        if ($userModel->isBanned($userId)) {
            $this->handleBannedUser($userId, $userModel, $session);
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
    protected function handleBannedUser(int $userId, User $userModel, Session $session): void
    {
        $banInfo = $userModel->getBanInfo($userId);
        
        $message = 'Ваш аккаунт заблокирован.';
        if (!empty($banInfo['reason'])) {
            $message .= ' Причина: ' . $banInfo['reason'];
        }
        
        // ✅ Логируем через внедрённый Audit
        $audit = $this->container->get(Audit::class);
        $audit->log('security.banned_access', 'Попытка доступа забаненного пользователя', 'security', [
            'user_id' => $userId,
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
        ]);
        
        // ✅ Очищаем сессию через Session
        $flash = $_SESSION['flash'] ?? null;
        $session->destroy();
        session_start();
        
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
        
        $session->flash('error', $message);
        
        // ✅ Выбрасываем исключение вместо header + exit
        throw new RedirectException('/');
    }

    /**
     * Обработка отказа в доступе
     */
    protected function handleAccessDenied(int $userId, string $userRole): void
    {
        // ✅ Логируем через внедрённый Audit
        $audit = $this->container->get(Audit::class);
        $audit->log('security.access_denied', 'Попытка доступа к защищённому маршруту', 'security', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'required_role' => $this->requiredRole,
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
        ]);
        
        // ✅ Выбрасываем исключение вместо прямого рендеринга и exit
        throw new AccessDeniedException(
            "У вас недостаточно прав для доступа к этой странице. Требуется роль: {$this->requiredRole}"
        );
    }
}