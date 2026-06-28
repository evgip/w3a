<?php

declare(strict_types=1);

namespace App\Modules\Stories;

use App\Core\Container;
use App\Core\Events\EventDispatcher;
use App\Core\Events\StoryCreated;
use App\Core\Events\StoryDeleted;
use App\Core\Events\StoryRestore;
use App\Core\Events\CommentCreated;
use App\Core\Events\CommentUpdated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Core\Events\Listeners\AuditListener;
use App\Core\Events\Listeners\UpdateStoryCommentsCountListener;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\Comment;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\CommentService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Origins\Models\Domain;
use App\Modules\Notifications\Services\NotificationService;

class ModuleServiceProvider extends \App\Core\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Story::class, fn() => new Story());
        $container->singleton(Comment::class, fn() => new Comment());
        $container->singleton(ReadRibbon::class, fn() => new ReadRibbon());

        // === СЕРВИСЫ ===
        $container->singleton(StoryService::class, function (Container $c) {
            return new StoryService(
                $c->get(Story::class),
                $c->get(Domain::class),
                $c->get(EventDispatcher::class)
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
                $c->get(NotificationService::class),
                $c->get(EventDispatcher::class)
            );
        });

        $container->singleton(ReadRibbonService::class, function (Container $c) {
            return new ReadRibbonService(
                $c->get(ReadRibbon::class)
            );
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);

        $auditListener = new AuditListener();
        $commentsCountListener = new UpdateStoryCommentsCountListener();

        // Аудит событий историй
        $dispatcher->listen(StoryCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryRestore::class, [$auditListener, 'handle']);

        // Аудит событий комментариев
        $dispatcher->listen(CommentCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentUpdated::class, [$auditListener, 'handle']);   // ← ДОБАВИТЬ!
        $dispatcher->listen(CommentDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentRestored::class, [$auditListener, 'handle']);

        // Обновление счётчика комментариев
        $dispatcher->listen(CommentCreated::class, [$commentsCountListener, 'handleCreated']);
        $dispatcher->listen(CommentDeleted::class, [$commentsCountListener, 'handleDeleted']);
        $dispatcher->listen(CommentRestored::class, [$commentsCountListener, 'handleRestored']);
    }
}
