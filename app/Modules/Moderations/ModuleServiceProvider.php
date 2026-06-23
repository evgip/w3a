<?php

declare(strict_types=1);

namespace App\Modules\Moderations;

use App\Core\Container;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModActivity;

/**
 * Провайдер сервисов модуля Moderations.
 * 
 * Регистрирует модели для работы с модерацией.
 * Сервисов в модуле нет — только модели.
 * 
 * Cross-module зависимости (уже зарегистрированы в других модулях):
 * - AuditLog (из Admin) — уже зарегистрирован в Admin\ModuleServiceProvider
 * - User (из Users) — уже зарегистрирован в Users\ModuleServiceProvider
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(ModNote::class, fn() => new ModNote());
        $container->singleton(Moderation::class, fn() => new Moderation());
        $container->singleton(ModActivity::class, fn() => new ModActivity());
    }
}