<?php

namespace App\Core;

/**
 * Провайдер сервисов ядра.
 * Регистрирует базовые объекты, которые уже созданы в точке входа.
 */
class ModuleServiceProvider
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function register(Container $container): void
    {
        // Request уже создан в index.php — кладём его в контейнер
        $container->singleton(Request::class, fn() => $this->request);

    }
}