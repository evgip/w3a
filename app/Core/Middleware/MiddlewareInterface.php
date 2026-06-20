<?php
namespace App\Core\Middleware;

interface MiddlewareInterface
{
    public function handle(callable $next): mixed;
}