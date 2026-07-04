<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Search\Models\SearchResult;

/**
 * Провайдер сервисов модуля Search.
 * 
 * Регистрирует модель для работы с поиском.
 * Сервисов в модуле нет — только модель.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // ✅ Передаём Database и Logger в конструктор модели
        $container->singleton(SearchResult::class, function (Container $c) {
            return new SearchResult(
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