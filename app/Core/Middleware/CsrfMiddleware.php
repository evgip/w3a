<?php
namespace App\Core\Middleware;

use App\Core\Request;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        $request = new Request();
        $request->validateCsrf();  // Используем наш улучшенный validateCsrf()
        
        return $next();
    }
}