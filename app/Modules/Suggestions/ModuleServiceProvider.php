<?php

declare(strict_types=1);

namespace App\Modules\Suggestions;

use App\Core\Container;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Models\ContentLog;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Models\Tag;
use App\Core\Events\EventDispatcher;

/**
 * Провайдер сервисов модуля Suggestions.
 * 
 * Регистрирует модели и сервисы для работы с предложениями изменений контента.
 * 
 * Cross-module зависимости:
 * - Story (из Stories) — создается напрямую, т.к. модели не требуют DI
 * - Tag (из Tags) — создается напрямую, т.к. модели не требуют DI
 * - EventDispatcher (из Core\Events) — должен быть зарегистрирован в ядре
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ЭТОГО МОДУЛЯ ===
        $container->singleton(Suggestion::class, fn() => new Suggestion());
        $container->singleton(ContentLog::class, fn() => new ContentLog());

        // === СЕРВИСЫ ===
        $container->singleton(SuggestionService::class, function (Container $c) {
            // Модели из других модулей создаем напрямую через new,
            // т.к. они не имеют зависимостей и не всегда зарегистрированы в контейнере.
            // EventDispatcher берем из контейнера - он должен быть зарегистрирован в ядре.
            return new SuggestionService(
                new Suggestion(),
                new ContentLog(),
                new Story(),
                new Tag(),
                $c->get(EventDispatcher::class)
            );
        });
    }
}
