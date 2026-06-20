<?php
namespace App\Core\Middleware;

/**
 * Middleware для администраторов.
 * Админ имеет доступ ко ВСЕМУ (включая moderator и auth маршруты).
 */
class AdminMiddleware extends RoleMiddleware
{
    protected string $requiredRole = 'admin';
}