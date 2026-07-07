<?php
// app/Modules/Saved/ModuleServiceProvider.php

declare(strict_types=1);

namespace App\Modules\Saved;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Modules\Saved\Models\SavedStory;
use App\Modules\Saved\Services\SavedService;

class ModuleServiceProvider extends \App\Core\ModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        $container->singleton(SavedStory::class, function(Container $c) {
            return new SavedStory(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(SavedService::class, function(Container $c) {
            return new SavedService(
                $c->get(SavedStory::class),
                $c->get(Session::class)
            );
        });
    }
}