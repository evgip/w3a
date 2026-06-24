<?php

namespace App\Core;

/**
 * Базовый провайдер сервисов для модулей.
 * Все модули должны наследоваться от этого класса.
 */
class ModuleServiceProvider
{
    protected ?Request $request = null;

    /**
     * 🔑 Request теперь ОПЦИОНАЛЬНЫЙ!
     * 
     * - Ядро передаёт Request
     * - Модули могут не передавать ничего
     * - Модули могут переопределить конструктор, если нужен Request
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Зарегистрировать сервисы модуля в контейнере
     */
    public function register(Container $container): void
    {
        // По умолчанию ничего не делаем.
        // Если это провайдер ядра — зарегистрируем Request.
        if ($this->request !== null) {
            $container->singleton(Request::class, fn() => $this->request);
        }
    }

    /**
     * Загрузить сервисы (вызывается после регистрации)
     */
    public function boot(): void
    {
        // По умолчанию ничего не делаем
    }
}