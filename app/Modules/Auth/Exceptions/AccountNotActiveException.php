<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

/**
 * Исключение, возникающее при попытке входа в неактивированный аккаунт.
 */
class AccountNotActiveException extends \DomainException
{
}