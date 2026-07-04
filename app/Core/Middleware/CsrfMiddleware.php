<?php

namespace App\Core\Middleware;

use App\Core\Container;
use App\Core\Request;

/**
 * Middleware для проверки CSRF-токена.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private Container $container;

    /**
     * ✅ Конструктор с инъекцией контейнера
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(callable $next): mixed
    {
        $request = $this->container->get(Request::class);
        $request->validateCsrf();
        
        return $next();
    }
}