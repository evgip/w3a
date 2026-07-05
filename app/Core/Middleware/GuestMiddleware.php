<?php

namespace App\Core\Middleware;

use App\Modules\Auth\Services\Auth;
use App\Core\Exceptions\RedirectException; 

class GuestMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        // Если пользователь уже авторизован, прерываем выполнение через исключение
        if (Auth::check()) {
            throw new RedirectException('/');
        }
        
        // Иначе продолжаем выполнение цепочки middleware
        return $next();
    }
}