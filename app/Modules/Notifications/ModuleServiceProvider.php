<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Container;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Провайдер сервисов модуля Notifications.
 * 
 * Регистрирует NotificationService и его зависимости.
 * 
 * Cross-module зависимости:
 * - Comment (из Stories) — уже зарегистрирован в Stories\ModuleServiceProvider
 * - User (из Users) — TODO: перенести в Users\ModuleServiceProvider когда он появится
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(Notification::class, fn() => new Notification());
        
        // Cross-module: User из модуля Users
        // TODO: перенести в Users\ModuleServiceProvider когда он появится
        $container->singleton(User::class, fn() => new User());
        
        // === СЕРВИСЫ ===
        
        $container->singleton(NotificationService::class, function (Container $c) {
            return new NotificationService(
                $c->get(Notification::class),
                $c->get(Comment::class),
                $c->get(User::class)
            );
        });
    }
}