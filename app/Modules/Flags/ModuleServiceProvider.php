<?php

declare(strict_types=1);

namespace App\Modules\Flags;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Flags\Models\Flag;

/**
 * Провайдер сервисов модуля Flags.
 * 
 * Регистрирует модель Flag.
 * Сервисов в модуле нет — только модель.
 * 
 * Cross-module зависимости:
 * - Comment (из Stories) — уже зарегистрирован в Stories\ModuleServiceProvider
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Передаём Database и Logger в конструктор модели
        $container->singleton(Flag::class, function (Container $c) {
            return new Flag(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}