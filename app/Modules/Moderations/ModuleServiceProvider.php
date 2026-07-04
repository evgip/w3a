<?php

declare(strict_types=1);

namespace App\Modules\Moderations;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Events\EventDispatcher;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Users\Models\User;

/**
 * Провайдер сервисов модуля Moderations.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(ModActivity::class, function (Container $c) {
            return new ModActivity(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(Moderation::class, function (Container $c) {
            return new Moderation(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(ModNote::class, function (Container $c) {
            return new ModNote(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        // ✅ ModerationService: 5 зависимостей
        $container->singleton(ModerationService::class, function (Container $c) {
            return new ModerationService(
                $c->get(Moderation::class),
                $c->get(ModNote::class),
                $c->get(User::class),
                $c->get(Session::class),
                $c->get(EventDispatcher::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}