<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Logger;

/**
 * Конвейер middleware.
 * Последовательно выполняет middleware, передавая управление по цепочке.
 */
class MiddlewarePipeline
{
    /** @var string[] Список классов middleware */
    private array $middlewares = [];

    /**
     * Добавить middleware в конвейер
     */
    public function pipe(string $middlewareClass): self
    {
        if (!class_exists($middlewareClass)) {
            Logger::warning("Middleware class not found: {$middlewareClass}");
            return $this;
        }

        if (!is_subclass_of($middlewareClass, MiddlewareInterface::class)) {
            Logger::warning("Class {$middlewareClass} must implement MiddlewareInterface");
            return $this;
        }

        $this->middlewares[] = $middlewareClass;
        return $this;
    }

    /**
     * Добавить несколько middleware
     */
    public function pipeMany(array $middlewareClasses): self
    {
        foreach ($middlewareClasses as $middleware) {
            $this->pipe($middleware);
        }
        return $this;
    }

    /**
     * Запустить конвейер с финальным обработчиком
     */
    public function process(callable $destination): mixed
    {
        // Создаём цепочку вызовов справа налево
        $pipeline = $destination;

        foreach (array_reverse($this->middlewares) as $middlewareClass) {
            $middleware = new $middlewareClass();
            $next = $pipeline;
            $pipeline = function() use ($middleware, $next) {
                return $middleware->handle($next);
            };
        }

        return $pipeline();
    }
}