<?php

namespace App\Core;

use RuntimeException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

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
     * Регистрация transient-сервиса.
     * Фабрика вызывается каждый раз при запросе.
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        // Помечаем как transient (не singleton)
        $this->bindings[$abstract . ':transient'] = true;
    }

    /**
     * Получение экземпляра сервиса.
     */
    public function get(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            // Если binding не найден, пробуем создать через make()
            return $this->make($abstract);
        }

        // Если это transient-сервис — создаём каждый раз
        if (isset($this->bindings[$abstract . ':transient'])) {
            return ($this->bindings[$abstract])($this);
        }

        // Singleton — кешируем
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

    /**
     * Создание экземпляра класса с автоматической инъекцией зависимостей.
     * Использует рефлексию для анализа конструктора.
     *
     * @param string $abstract Полное имя класса
     * @param array $parameters Дополнительные параметры для конструктора
     * @return object Созданный экземпляр
     * @throws RuntimeException Если не удалось создать экземпляр
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Если есть binding — используем его
        if (isset($this->bindings[$abstract])) {
            return $this->get($abstract);
        }

        // Проверяем существование класса
        if (!class_exists($abstract)) {
            throw new RuntimeException("Class not found: {$abstract}");
        }

        $reflection = new ReflectionClass($abstract);

        // Если класс абстрактный или интерфейс — не можем создать
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$abstract} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // Если конструктора нет — просто создаём экземпляр
        if ($constructor === null) {
            return new $abstract();
        }

        // Разрешаем параметры конструктора
        $resolvedParameters = $this->resolveParameters($constructor->getParameters(), $parameters);

        return $reflection->newInstanceArgs($resolvedParameters);
    }

    /**
     * Разрешение параметров конструктора через рефлексию.
     *
     * @param ReflectionParameter[] $parameters Параметры конструктора
     * @param array $overrides Дополнительные параметры
     * @return array Разрешённые значения параметров
     */
    private function resolveParameters(array $parameters, array $overrides = []): array
    {
        $resolved = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Если параметр передан явно — используем его
            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];
                continue;
            }

            $type = $parameter->getType();

            // Если тип указан и это класс — пытаемся разрешить из контейнера
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                // Пытаемся получить из контейнера
                if ($this->has($className)) {
                    $resolved[] = $this->get($className);
                } else {
                    // Пытаемся создать через make() (рекурсивно)
                    try {
                        $resolved[] = $this->make($className);
                    } catch (RuntimeException $e) {
                        // Если не удалось и есть дефолтное значение — используем его
                        if ($parameter->isDefaultValueAvailable()) {
                            $resolved[] = $parameter->getDefaultValue();
                        } elseif ($parameter->allowsNull()) {
                            $resolved[] = null;
                        } else {
                            throw new RuntimeException(
                                "Cannot resolve parameter \${$name} of type {$className}: " . $e->getMessage()
                            );
                        }
                    }
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Есть дефолтное значение — используем его
                $resolved[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                // Параметр nullable — передаём null
                $resolved[] = null;
            } else {
                throw new RuntimeException(
                    "Cannot resolve parameter \${$name}: no type hint, no default value"
                );
            }
        }

        return $resolved;
    }

    /**
     * Установка экземпляра напрямую (для тестирования или ручного создания).
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
        // Также регистрируем как binding, чтобы get() работал
        if (!isset($this->bindings[$abstract])) {
            $this->bindings[$abstract] = fn() => $instance;
        }
    }
}