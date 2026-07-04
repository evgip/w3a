<?php

declare(strict_types=1);

namespace App\Modules\Stories;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Audit;
use App\Core\IpResolver;
use App\Core\Validator;
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
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Tags\Services\TagValidator;
use App\Modules\Origins\Models\Domain;
use App\Modules\Notifications\Services\NotificationService;

class ModuleServiceProvider extends \App\Core\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Story::class, function(Container $c) {
            return new Story(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(Comment::class, function(Container $c) {
            return new Comment(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(ReadRibbon::class, function(Container $c) {
            return new ReadRibbon(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(Domain::class, function(Container $c) {
            return new Domain(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
		$container->singleton(StoryService::class, function (Container $c) {
			return new StoryService(
				$c->get(Story::class),
				$c->get(Domain::class),
				$c->get(StoryValidator::class),
				$c->get(Session::class),
				$c->get(Audit::class),
				$c->get(Validator::class),
				$c->get(EventDispatcher::class)
			);
		});

		$container->singleton(StoryValidator::class, function (Container $c) {
			return new StoryValidator(
				$c->get(TagValidator::class),
				$c->get(Domain::class)
			);
		});

        // ✅ ИЗМЕНЕНО: Передаём Container в StoryFilterService
        $container->singleton(StoryFilterService::class, function (Container $c) {
            return new StoryFilterService(
                $c->get(Story::class),
                $c->get(Domain::class),
                $c  // ← Передаём контейнер
            );
        });

		$container->singleton(CommentService::class, function (Container $c) {
			return new CommentService(
				$c->get(Comment::class),              // 1. Comment
				$c->get(Session::class),              // 2. Session
				$c->get(Validator::class),            // 3. Validator
				$c->get(NotificationService::class),  // 4. NotificationService
				$c->get(EventDispatcher::class)       // 5. EventDispatcher
			);
		});

        $container->singleton(ReadRibbonService::class, function (Container $c) {
            return new ReadRibbonService(
                $c->get(ReadRibbon::class),
				$c->get(Session::class) 
            );
        });

        // === СЛУШАТЕЛИ СОБЫТИЙ ===
        $container->singleton(AuditListener::class, function(Container $c) {
            return new AuditListener(
                $c->get(Audit::class)
            );
        });

        $container->singleton(UpdateStoryCommentsCountListener::class, function(Container $c) {
            return new UpdateStoryCommentsCountListener(
                $c->get(Story::class)
            );
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);

        $auditListener = $this->container->get(AuditListener::class);
        $commentsCountListener = $this->container->get(UpdateStoryCommentsCountListener::class);

        // Аудит событий историй
        $dispatcher->listen(StoryCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryRestore::class, [$auditListener, 'handle']);

        // Аудит событий комментариев
        $dispatcher->listen(CommentCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentUpdated::class, [$auditListener, 'handle']); 
        $dispatcher->listen(CommentDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentRestored::class, [$auditListener, 'handle']);

        // Обновление счётчика комментариев
        $dispatcher->listen(CommentCreated::class, [$commentsCountListener, 'handleCreated']);
        $dispatcher->listen(CommentDeleted::class, [$commentsCountListener, 'handleDeleted']);
        $dispatcher->listen(CommentRestored::class, [$commentsCountListener, 'handleRestored']);
    }
}