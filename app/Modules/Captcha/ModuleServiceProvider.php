<?php

declare(strict_types=1);

namespace App\Modules\Captcha;

use App\Core\ModuleServiceProvider as BaseServiceProvider;
use App\Core\Config;
use App\Core\Container;
use App\Core\Request;
use App\Core\Session;
use App\Modules\Captcha\Core\Captcha;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(Container $container): void
    {
        // 1. Регистрируем путь к конфигам
        $config = $container->get(Config::class);
        $config->addModulePath('captcha', __DIR__ . '/Config');

        // 2. Регистрируем Captcha как singleton с DI
        $container->singleton(Captcha::class, function($c) {
            return new Captcha(
                $c->get(Config::class),
                $c->get(Request::class),
                $c->get(Session::class)
            );
        });
        
        // 3. Регистрируем под строковым именем тоже (если нужно)
        $container->singleton('captcha', function($c) {
            return $c->get(Captcha::class);
        });
    }

    public function boot(): void
    {
        // Можно добавить маршруты, если нужны
    }
}