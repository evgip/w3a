<?php
namespace App\Core\Middleware;

use App\Core\Auth;

class GuestMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        if (Auth::check()) {
            header('Location: /');
            exit;
        }
        
        return $next();
    }
}