<?php

declare(strict_types=1);

namespace App\Modules\Stories;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Audit;

use App\Core\Validator;
use App\Core\Events\EventDispatcher;

use App\Modules\Stories\Events\StoryCreated;
use App\Modules\Stories\Events\StoryDeleted;
use App\Modules\Stories\Events\StoryRestored;


use App\Modules\Comments\Events\CommentCreated;
use App\Modules\Comments\Events\CommentDeleted;
use App\Modules\Comments\Events\CommentRestored;

use App\Core\Events\Listeners\AuditListener;
use App\Modules\Stories\Listeners\UpdateStoryCommentsCountListener;

use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Stories\Services\UrlFetcherService; 
use App\Modules\Stories\Services\RankingService; 
use App\Modules\Tags\Services\TagValidator;
use App\Modules\Origins\Models\Domain;

use App\Modules\Muted\Services\MuteService;

class ModuleServiceProvider extends \App\Core\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(RankingService::class, function(Container $c) {
            return new RankingService();
        });

        // === МОДЕЛИ ===
        $container->singleton(Story::class, function(Container $c) {
            return new Story(
                $c->get(Database::class),
                $c->get(Logger::class),
                $c->get(RankingService::class)
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
        $container->singleton(StoryValidator::class, function (Container $c) {
            return new StoryValidator(
                $c->get(TagValidator::class),
                $c->get(Domain::class)
            );
        });

        $container->singleton(StoryService::class, function (Container $c) {
            return new StoryService(
                $c->get(Story::class),
                $c->get(Domain::class),
                $c->get(StoryValidator::class),
                $c->get(Validator::class),
                $c->get(Audit::class),
                $c->get(EventDispatcher::class),
                $c->get(\App\Core\Security\UserContext::class)
            );
        });

        $container->singleton(ReadRibbonService::class, function (Container $c) {
            return new ReadRibbonService(
                $c->get(ReadRibbon::class),
                $c->get(\App\Core\Security\UserContext::class)
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
        
        $container->singleton(UrlFetcherService::class, function(Container $c) {
            return new UrlFetcherService();
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);
        $auditListener = $this->container->get(AuditListener::class);
        $commentsCountListener = $this->container->get(UpdateStoryCommentsCountListener::class);

        // 1. Аудит событий историй (Core функционал)
        $dispatcher->listen(StoryCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryDeleted::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryRestored::class, [$auditListener, 'handle']);

        // 2. Обновление счетчика комментариев при действиях с комментариями
        // (Слушаем события модуля Comments, но обрабатываем их внутри модуля Stories)
        $dispatcher->listen(CommentCreated::class, [$commentsCountListener, 'handleCreated']);
        $dispatcher->listen(CommentDeleted::class, [$commentsCountListener, 'handleDeleted']);
        $dispatcher->listen(CommentRestored::class, [$commentsCountListener, 'handleRestored']);
    }
}