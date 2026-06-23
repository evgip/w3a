<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Core\Container;
use App\Modules\Search\Models\SearchResult;

/**
 * Провайдер сервисов модуля Search.
 * 
 * Регистрирует модель для работы с поиском.
 * Сервисов в модуле нет — только модель.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(SearchResult::class, fn() => new SearchResult());
    }
}