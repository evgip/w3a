<?php

declare(strict_types=1);

namespace App\Modules\Admin\Exceptions;

/**
 * Исключение, возникающее при ошибках управления пользователями (например, попытка забанить себя).
 */
class AdminUserException extends \DomainException
{
}