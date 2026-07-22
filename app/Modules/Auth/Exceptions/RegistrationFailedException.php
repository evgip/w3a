<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

/**
 * Исключение, возникающее при ошибке создания пользователя в базе данных.
 */
class RegistrationFailedException extends \DomainException
{
}