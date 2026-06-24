<?php

namespace App\Modules\Content;

use App\Core\ModuleServiceProvider as BaseServiceProvider;
use App\Core\Config;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register($container): void
    {
        // Регистрируем путь к конфигам модуля
        Config::addModulePath('content', __DIR__ . '/Config');
    }
}