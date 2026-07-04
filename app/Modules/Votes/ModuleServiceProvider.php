<?php

declare(strict_types=1);

namespace App\Modules\Votes;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Votes\Models\Vote;
use App\Modules\Votes\Services\VoteService;
use App\Modules\Users\Models\User;
use App\Modules\Stories\Models\Comment;

/**
 * Провайдер сервисов модуля Votes.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(Vote::class, function (Container $c) {
            return new Vote(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        $container->singleton(VoteService::class, function (Container $c) {
            return new VoteService(
                $c->get(Vote::class),
                $c->get(User::class),
                $c->get(Comment::class),
                $c->get(Logger::class),
                $c->get(Database::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}