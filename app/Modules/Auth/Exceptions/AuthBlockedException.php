<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

/**
 * Исключение, возникающее при превышении лимита попыток входа (брутфорс-защита).
 */
class AuthBlockedException extends \DomainException
{
}