<?php

declare(strict_types=1);

namespace App\Modules\Stats;

use App\Core\Container;
use App\Modules\Stats\Models\Stats;

/**
 * Провайдер сервисов модуля Stats.
 * 
 * Регистрирует модель для работы со статистикой.
 * Сервисов в модуле нет — только модель.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Stats::class, fn() => new Stats());
    }
}