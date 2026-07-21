<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Audit;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Core\Events\EventDispatcher;
use App\Core\Events\Listeners\AuditListener;

use App\Modules\Users\Events\UserBanned;
use App\Modules\Users\Events\UserUnbanned;

use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Users\Models\RateLimit;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(User::class, function (Container $c) {
            return new User(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        $container->singleton(Notification::class, function (Container $c) {
            return new Notification(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        $container->singleton(RateLimit::class, function (Container $c) {
            return new RateLimit(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // === СЕРВИСЫ ===
        // Строго соответствует вашему реальному коду UserService
        $container->singleton(UserService::class, function (Container $c) {
            return new UserService(
                $c->get(User::class),
                $c->get(Session::class)
            );
        });
        
        $container->singleton(AvatarService::class, function (Container $c) {
            return new AvatarService(
                $c->get(Session::class)
            );
        });

        // === СЛУШАТЕЛИ ===
        $container->singleton(AuditListener::class, function (Container $c) {
            return new AuditListener(
                $c->get(Audit::class)
            );
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = $this->container->get(AuditListener::class);

        // Аудит действий с аккаунтами (бан/разбан), где бы эти события ни генерировались
        $dispatcher->listen(UserBanned::class, [$auditListener, 'handle']);
        $dispatcher->listen(UserUnbanned::class, [$auditListener, 'handle']);
    }
}