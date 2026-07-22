<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

/**
 * Исключение, возникающее при неверном email или пароле.
 */
class InvalidCredentialsException extends \DomainException
{
}