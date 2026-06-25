<?php

declare(strict_types=1);

namespace App\Modules\Moderations;

use App\Core\Container;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Core\Events\EventDispatcher;
use App\Core\Events\UserBanned;
use App\Core\Events\UserUnbanned;
use App\Core\Events\ModNoteAdded;
use App\Core\Events\Listeners\AuditListener;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Users\Models\User;

/**
 * Провайдер модуля Moderations.
 *
 * Регистрирует:
 *  - Модели модуля (ModNote, Moderation, ModActivity)
 *  - Сервис ModerationService с EventDispatcher
 *  - Слушателей событий модерации (UserBanned, UserUnbanned, ModNoteAdded)
 *
 * Cross-module зависимости:
 *  - User (из модуля Users) — уже зарегистрирован
 *  - AuditLog (из модуля Admin) — используется через контроллер
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ МОДУЛЯ ===
        $container->singleton(ModNote::class, fn() => new ModNote());
        $container->singleton(Moderation::class, fn() => new Moderation());
        $container->singleton(ModActivity::class, fn() => new ModActivity());

        // === СЕРВИС ===
        $container->singleton(ModerationService::class, function (Container $c) {
            return new ModerationService(
                $c->get(Moderation::class),
                $c->get(ModNote::class),
                $c->get(User::class),
                $c->get(EventDispatcher::class)
            );
        });
    }

    /**
     * Регистрация слушателей событий модуля.
     * Все события модерации логируются через AuditListener.
     */
    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = new AuditListener();

        // Аудит событий модерации
        $dispatcher->listen(UserBanned::class, [$auditListener, 'handle']);
        $dispatcher->listen(UserUnbanned::class, [$auditListener, 'handle']);
        $dispatcher->listen(ModNoteAdded::class, [$auditListener, 'handle']);
    }
}