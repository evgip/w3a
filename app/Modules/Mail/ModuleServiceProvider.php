<?php

namespace App\Modules\Mail;

use App\Core\ModuleServiceProvider as BaseServiceProvider;
use App\Core\Config;
use App\Core\Container;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрируем путь к конфигам модуля
        Config::addModulePath('mail', __DIR__ . '/Config');
    }
}