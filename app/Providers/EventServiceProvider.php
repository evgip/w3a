<?php

namespace App\Providers;

use App\Core\Events\EventDispatcher;
use App\Core\Events\StoryCreated;
use App\Core\Events\StoryDeleted;
use App\Core\Events\StoryRestore;
use App\Core\Events\CommentCreated;
use App\Core\Events\CommentUpdated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Core\Events\UserBanned;
use App\Core\Events\FlagResolved;
use App\Core\Events\Listeners\AuditListener;
use App\Core\Events\Listeners\UpdateStoryCommentsCountListener;

class EventServiceProvider
{
    /**
     * Зарегистрировать все слушатели событий.
     */
    public static function register(EventDispatcher $dispatcher): void
    {
        $auditListener = new AuditListener();
		$commentsCountListener = new UpdateStoryCommentsCountListener();

        // Регистрируем слушателя для всех событий, которые нужно логировать
        $dispatcher->listen(StoryCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(StoryDeleted::class, [$auditListener, 'handle']);
		$dispatcher->listen(StoryRestore::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentCreated::class, [$auditListener, 'handle']);
        $dispatcher->listen(CommentUpdated::class, [$auditListener, 'handle']);
		$dispatcher->listen(CommentDeleted::class, [$auditListener, 'handle']);
		$dispatcher->listen(CommentRestored::class, [$auditListener, 'handle']);
        $dispatcher->listen(UserBanned::class, [$auditListener, 'handle']);
        $dispatcher->listen(FlagResolved::class, [$auditListener, 'handle']);

        // =====================================================================
        // АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ СЧЁТЧИКА КОММЕНТАРИЕВ
        // =====================================================================
        $dispatcher->listen(CommentCreated::class, [$commentsCountListener, 'handleCreated']);
        $dispatcher->listen(CommentDeleted::class, [$commentsCountListener, 'handleDeleted']);
        $dispatcher->listen(CommentRestored::class, [$commentsCountListener, 'handleRestored']);

        // В будущем можно добавить другие слушатели:
        // $dispatcher->listen(UserRegistered::class, [new WelcomeEmailListener(), 'handle']);
        // $dispatcher->listen(StoryCreated::class, [new NotifyFollowersListener(), 'handle']);
    }
}