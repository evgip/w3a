<?php

declare(strict_types=1);

namespace App\Modules\Users\Exceptions;

/**
 * Исключение, возникающее при ошибке валидации данных пользователя (email, пароль и т.д.).
 */
class UserValidationException extends \DomainException
{
}