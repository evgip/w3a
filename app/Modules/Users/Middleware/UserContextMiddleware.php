<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use App\Core\Container;
use App\Core\Session;
use App\Core\Security\UserContext;
use App\Modules\Users\Models\User;

/**
 * Middleware модуля Users.
 * Отвечает исключительно за создание и регистрацию UserContext для текущего запроса.
 * Core об этом ничего не знает.
 */
class UserContextMiddleware
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(callable $next): mixed
    {
        $session = $this->container->get(Session::class);
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($userId > 0) {
            $userModel = $this->container->get(User::class);
            $user = $userModel->find($userId);

            // ⚠️ Выберите вариант, который подходит под вашу БД:
            
            // ВАРИАНТ А: Если есть поле `role`
            $role = $user['role'] ?? 'user';
            $isAdmin = ($role === 'admin');
            $isModerator = ($role === 'moderator' || $role === 'admin');

            /*
            // ВАРИАНТ Б: Если есть поля `is_admin` и `is_moderator`
            $isAdmin = (bool)($user['is_admin'] ?? false);
            $isModerator = (bool)($user['is_moderator'] ?? false) || $isAdmin;
            */

            // Создаем объект контекста
            $currentUserContext = new UserContext(
                id: $userId,
                isAdmin: $isAdmin,
                isModerator: $isModerator
            );

            // Регистрируем его в контейнере
            $this->container->instance(UserContext::class, $currentUserContext);
        } else {
            // Если пользователь не авторизован, создаем "гостевой" контекст
            $guestContext = new UserContext(
                id: 0,
                isAdmin: false,
                isModerator: false
            );
            $this->container->instance(UserContext::class, $guestContext);
        }

        return $next();
    }
}