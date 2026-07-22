<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

/**
 * Исключение, возникающее, когда запрашиваемый пользователь не найден.
 */
class UserNotFoundException extends \DomainException
{
}