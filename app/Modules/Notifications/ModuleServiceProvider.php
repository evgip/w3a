<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Container;
use App\Core\Logger;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Провайдер сервисов модуля Notifications.
 * 
 * ✅ ИЗМЕНЕНО: Не дублирует регистрацию моделей из других модулей.
 * Модели Notification, User, Comment уже зарегистрированы в своих модулях.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === СЕРВИСЫ ===
        // ✅ Все зависимости уже зарегистрированы в других модулях:
        // - Notification → Users/ModuleServiceProvider
        // - Comment → Stories/ModuleServiceProvider
        // - User → Users/ModuleServiceProvider
        $container->singleton(NotificationService::class, function (Container $c) {
            return new NotificationService(
                $c->get(\App\Modules\Notifications\Models\Notification::class),
                $c->get(Comment::class),
                $c->get(User::class),
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}
