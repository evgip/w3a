<?php

declare(strict_types=1);

namespace App\Modules\Mail;

use App\Core\Container;
use App\Core\Logger;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Mail\Core\Mailer;

/**
 * Провайдер сервисов модуля Mail.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Mailer: получает Logger через контейнер
        $container->singleton(Mailer::class, function (Container $c) {
            return new Mailer(
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}