<?php
namespace App\Core\Middleware;

/**
 * Middleware для модераторов.
 * Модератор имеет доступ к moderator и auth маршрутам, но не к admin.
 */
class ModeratorMiddleware extends RoleMiddleware
{
    protected string $requiredRole = 'moderator';
}