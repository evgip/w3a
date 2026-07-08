<?php

declare(strict_types=1);

namespace App\Modules\Wiki;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Audit;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Core\Events\EventDispatcher;
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
 * 
 * ✅ ИЗМЕНЕНО: WikiPermissionService получает все зависимости через контейнер.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(WikiPage::class, function (Container $c) {
            return new WikiPage(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(WikiRevision::class, function (Container $c) {
            return new WikiRevision(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(WikiPermission::class, function (Container $c) {
            return new WikiPermission(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        $container->singleton(WikiPermissionService::class, function (Container $c) {
            return new WikiPermissionService(
                $c->get(WikiPermission::class),
                $c->get(Tag::class),
                $c->get(User::class),
                $c->get(Session::class),
                $c->get(Audit::class)
            );
        });

		$container->singleton(WikiService::class, function (Container $c) {
			return new WikiService(
				$c->get(WikiPage::class),
				$c->get(WikiRevision::class),
				$c->get(Session::class),
				$c->get(Audit::class),
				$c->get(EventDispatcher::class)
			);
		});
		
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}