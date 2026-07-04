<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Logger;

/**
 * Конвейер middleware.
 * Последовательно выполняет middleware, передавая управление по цепочке.
 * 
 * ✅ ИЗМЕНЕНО: Убран fallback на new — все middleware должны создаваться через контейнер
 */
class MiddlewarePipeline
{
    /** @var string[] Список классов middleware */
    private array $middlewares = [];

    /** @var Container DI-контейнер для создания middleware */
    private Container $container;

    /**
     * Конструктор pipeline
     * 
     * @param Container $container DI-контейнер для инъекции зависимостей в middleware
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Добавить middleware в конвейер
     */
    public function pipe(string $middlewareClass): self
    {
        if (!class_exists($middlewareClass)) {
            $logger = $this->container->get(Logger::class);
            $logger->warning("Middleware class not found: {$middlewareClass}");
            return $this;
        }
        if (!is_subclass_of($middlewareClass, MiddlewareInterface::class)) {
            $logger = $this->container->get(Logger::class);
            $logger->warning("Class {$middlewareClass} must implement MiddlewareInterface");
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
     * ✅ Все middleware создаются через контейнер с автоматической инъекцией зависимостей
     */
    public function process(callable $destination): mixed
    {
        // Создаём цепочку вызовов справа налево
        $pipeline = $destination;
        
        foreach (array_reverse($this->middlewares) as $middlewareClass) {
            // ✅ Используем make() для автоматической инъекции Container
            $middleware = $this->container->make($middlewareClass);
            
            $next = $pipeline;
            $pipeline = function () use ($middleware, $next) {
                return $middleware->handle($next);
            };
        }
        
        return $pipeline();
    }
}