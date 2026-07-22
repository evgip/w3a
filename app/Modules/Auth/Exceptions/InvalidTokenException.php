<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

/**
 * Исключение, возникающее при использовании недействительного или просроченного токена.
 */
class InvalidTokenException extends \DomainException
{
}