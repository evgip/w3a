<?php
namespace App\Core\Middleware;

use App\Modules\Auth\Services\Auth;

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