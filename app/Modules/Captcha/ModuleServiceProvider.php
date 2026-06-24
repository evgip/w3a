<?php

namespace App\Modules\Captcha;

use App\Core\ModuleServiceProvider as BaseServiceProvider;
use App\Core\Config;
use App\Core\Container;

class ModuleServiceProvider extends BaseServiceProvider
{
    /**
     * Зарегистрировать сервисы модуля
     */
    public function register(Container $container): void
    {
        // 1. Регистрируем путь к конфигам модуля
        Config::addModulePath('captcha', __DIR__ . '/Config');

        // 2. Регистрируем Captcha как singleton
        $container->singleton('captcha', function() {
            return new \App\Modules\Captcha\Core\Captcha();
        });

        // 3. Регистрируем класс для type-hinting
        $container->singleton(\App\Modules\Captcha\Core\Captcha::class, function() {
            return new \App\Modules\Captcha\Core\Captcha();
        });
    }

    /**
     * Загрузить маршруты модуля (если есть)
     */
    public function boot(): void
    {
        // Можно добавить маршруты, если нужны
        // Router::group(['prefix' => '/captcha'], function() {
        //     Router::get('/generate', 'CaptchaController@generate');
        // });
    }
}