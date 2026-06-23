<?php

declare(strict_types=1);

namespace App\Modules\Tags;

use App\Core\Container;
use App\Modules\Tags\Models\Category;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Tags\Services\CategoryService;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Stories\Models\ReadRibbon;

/**
 * Провайдер сервисов модуля Tags.
 * 
 * Регистрирует модели и сервисы для работы с тегами и категориями.
 * 
 * Cross-module зависимости:
 * - ReadRibbon (из Stories) — уже зарегистрирован в Stories\ModuleServiceProvider
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(Category::class, fn() => new Category());
        $container->singleton(Tag::class, fn() => new Tag());
        $container->singleton(TagFilter::class, fn() => new TagFilter());
        
        // === СЕРВИСЫ ===
        
        $container->singleton(CategoryService::class, function (Container $c) {
            return new CategoryService(
                $c->get(Category::class),
                $c->get(Tag::class),
                $c->get(TagFilter::class),
                $c->get(ReadRibbon::class)
            );
        });
        
        $container->singleton(TagFilterService::class, function (Container $c) {
            return new TagFilterService(
                $c->get(TagFilter::class),
                $c->get(Tag::class)
            );
        });
    }
}