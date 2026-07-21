<?php

declare(strict_types=1);

namespace App\Modules\Moderations;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Events\EventDispatcher;
use App\Core\Events\Listeners\AuditListener;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;

// ✅ Импорты событий, которые генерирует этот модуль
use App\Modules\Moderations\Events\ModNoteAdded;

use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Users\Models\User;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(ModActivity::class, function (Container $c) {
            return new ModActivity($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(Moderation::class, function (Container $c) {
            return new Moderation($c->get(Database::class), $c->get(Logger::class));
        });

        $container->singleton(ModNote::class, function (Container $c) {
            return new ModNote($c->get(Database::class), $c->get(Logger::class));
        });

        // === СЕРВИСЫ ===
        $container->singleton(ModerationService::class, function (Container $c) {
            return new ModerationService(
                $c->get(Moderation::class),
                $c->get(ModNote::class),
                $c->get(User::class),
                $c->get(Session::class),
                $c->get(EventDispatcher::class)
            );
        });

        // === СЛУШАТЕЛИ ===
        $container->singleton(AuditListener::class, function (Container $c) {
            return new AuditListener($c->get(Audit::class));
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = $this->container->get(AuditListener::class);

        // Аудит добавления модераторских заметок
        $dispatcher->listen(ModNoteAdded::class, [$auditListener, 'handle']);
        
        // Примечание: события UserBanned и UserUnbanned здесь НЕ регистрируются, 
        // потому что их слушатели (AuditListener) уже зарегистрированы в модуле Users, 
        // а Moderations только их генерирует (dispatch). Это корректное разделение ответственности.
    }
}