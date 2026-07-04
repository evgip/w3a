<?php

declare(strict_types=1);

namespace App\Modules\Suggestions;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\IpResolver;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Core\Events\EventDispatcher;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Models\ContentLog;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\Comment as StoryComment;
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Services\TagValidator;
use App\Modules\Users\Models\User;
use App\Modules\Moderations\Models\Moderation;

/**
 * Провайдер сервисов модуля Suggestions.
 * 
 * Регистрирует модели и сервисы для работы с предложениями изменений контента.
 * 
 * Cross-module зависимости:
 * - Story (из Stories) — получается из контейнера
 * - Comment (из Stories) — получается из контейнера
 * - Tag (из Tags) — получается из контейнера
 * - User (из Users) — получается из контейнера
 * - Moderation (из Moderations) — получается из контейнера
 * - TagValidator (из Tags) — получается из контейнера
 * - StoryValidator (из Stories) — получается из контейнера
 * - EventDispatcher (из Core\Events) — получается из контейнера
 * - Logger (из Core) — получается из контейнера
 * - IpResolver (из Core) — получается из контейнера
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ЭТОГО МОДУЛЯ ===
        // ✅ Передаём Database и Logger в конструкторы моделей
        $container->singleton(Suggestion::class, function (Container $c) {
            return new Suggestion(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(ContentLog::class, function (Container $c) {
            return new ContentLog(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        // ✅ Передаём ВСЕ необходимые зависимости в SuggestionService
        $container->singleton(SuggestionService::class, function (Container $c) {
            return new SuggestionService(
                $c->get(Suggestion::class),
                $c->get(ContentLog::class),
                $c->get(Story::class),
                $c->get(StoryComment::class),
                $c->get(Tag::class),
                $c->get(User::class),
                $c->get(Moderation::class),
                $c->get(EventDispatcher::class),
                $c->get(TagValidator::class),
                $c->get(StoryValidator::class),
                $c->get(Logger::class),
                $c->get(IpResolver::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}
