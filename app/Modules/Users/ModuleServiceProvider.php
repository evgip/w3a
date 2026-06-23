<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Container;
use App\Modules\Users\Models\User;
use App\Modules\Users\Models\RememberToken;
use App\Modules\Users\Models\Notification;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AuthService;
use App\Modules\Users\Services\AvatarService;

/**
 * Провайдер сервисов модуля Users.
 * 
 * Регистрирует модели и сервисы для работы с пользователями,
 * аутентификацией, аватарами и настройками уведомлений.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(User::class, fn() => new User());
        $container->singleton(RememberToken::class, fn() => new RememberToken());
        $container->singleton(Notification::class, fn() => new Notification());
        
        // === СЕРВИСЫ ===
        
        $container->singleton(UserService::class, function (Container $c) {
            return new UserService($c->get(User::class));
        });
        
		$container->singleton(AuthService::class, function (Container $c) {
			return new AuthService(
				$c->get(User::class),
				$c->get(RememberToken::class)
			);
		});
        
        $container->singleton(AvatarService::class, fn() => new AvatarService());
    }
}