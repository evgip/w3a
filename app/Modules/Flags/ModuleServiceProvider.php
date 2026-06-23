<?php

declare(strict_types=1);

namespace App\Modules\Flags;

use App\Core\Container;
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
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Flag::class, fn() => new Flag());
    }
}