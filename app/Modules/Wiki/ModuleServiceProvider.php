<?php

declare(strict_types=1);

namespace App\Modules\Wiki;

use App\Core\Container;
use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Wiki\Models\WikiRevision;
use App\Modules\Wiki\Models\WikiPermission;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Services\WikiPermissionService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;

/**
 * Провайдер сервисов модуля Wiki.
 *
 * Регистрирует модели и сервисы для работы с wiki страницами.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        $container->singleton(WikiPage::class, fn() => new WikiPage());
        $container->singleton(WikiRevision::class, fn() => new WikiRevision());
        $container->singleton(WikiPermission::class, fn() => new WikiPermission());

        // === СЕРВИСЫ ===
        $container->singleton(WikiPermissionService::class, function (Container $c) {
            return new WikiPermissionService(
                $c->get(WikiPermission::class),
                $c->get(Tag::class),
                $c->get(User::class)  // ← ДОБАВЛЕНО
            );
        });

        $container->singleton(WikiService::class, function (Container $c) {
            return new WikiService(
                $c->get(WikiPage::class),
                $c->get(WikiRevision::class),
                null // EventDispatcher (опционально)
            );
        });
    }
}
