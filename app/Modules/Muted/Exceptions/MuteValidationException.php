<?php

declare(strict_types=1);

namespace App\Modules\Muted\Exceptions;

/**
 * Исключение, возникающее при нарушении правил игнорирования пользователей
 * (например, попытка игнорировать самого себя).
 */
class MuteValidationException extends \DomainException
{
}