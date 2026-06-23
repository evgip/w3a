<?php

namespace App\Core;

use RuntimeException;

class Container
{
    /** @var array<string, callable> Фабрики для создания сервисов */
    private array $bindings = [];

    /** @var array<string, mixed> Уже созданные экземпляры (singleton) */
    private array $instances = [];

    /**
     * Регистрация singleton-сервиса.
     * Фабрика вызывается один раз, результат кешируется.
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Получение экземпляра сервиса.
     */
    public function get(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException("Binding not found: {$abstract}");
        }

        if (!isset($this->instances[$abstract])) {
            $this->instances[$abstract] = ($this->bindings[$abstract])($this);
        }

        return $this->instances[$abstract];
    }

    /**
     * Проверка, зарегистрирован ли сервис.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}