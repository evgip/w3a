<?php

declare(strict_types=1);

namespace App\Modules\Content;

use App\Core\ModuleServiceProvider as BaseServiceProvider;
use App\Core\Config;
use App\Core\Container;
use App\Modules\Content\Core\Markdown;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(Container $container): void
    {
        // 1. Регистрируем путь к конфигам модуля
        $config = $container->get(Config::class);
        $config->addModulePath('content', __DIR__ . '/Config');

        // 2. Регистрируем Markdown как singleton
        $container->singleton(Markdown::class, function($c) {
            return new Markdown($c->get(Config::class));
        });
		
    }

    public function boot(): void
    {
        // Инициализация модуля
    }
}