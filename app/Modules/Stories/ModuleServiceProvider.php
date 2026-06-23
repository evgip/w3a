<?php

declare(strict_types=1);

namespace App\Modules\Stories;

use App\Core\Container;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\Comment;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\CommentService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Origins\Models\Domain; 
use App\Modules\Notifications\Services\NotificationService;

/**
 * Провайдер сервисов модуля Stories.
 * 
 * Регистрирует все зависимости, необходимые для работы контроллеров модуля.
 * В будущем, когда модули Origins и Notifications будут иметь свои провайдеры,
 * зависимости Domain и NotificationService будут перенесены туда.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(Story::class, fn() => new Story());
        $container->singleton(Comment::class, fn() => new Comment());
        $container->singleton(ReadRibbon::class, fn() => new ReadRibbon());
        
        
        // === СЕРВИСЫ ===
        
        $container->singleton(StoryService::class, function (Container $c) {
            return new StoryService(
                $c->get(Story::class),
                $c->get(Domain::class)
            );
        });
        
        $container->singleton(StoryFilterService::class, function (Container $c) {
            return new StoryFilterService(
                $c->get(Story::class),
                $c->get(Domain::class)
            );
        });
        
        $container->singleton(CommentService::class, function (Container $c) {
            return new CommentService(
                $c->get(Comment::class),
                $c->get(NotificationService::class)
            );
        });
        
        $container->singleton(ReadRibbonService::class, function (Container $c) {
            return new ReadRibbonService(
                $c->get(ReadRibbon::class)
            );
        });
        
    }
}