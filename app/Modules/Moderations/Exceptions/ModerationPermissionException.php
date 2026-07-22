<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Exceptions;

/**
 * Исключение, возникающее при попытке выполнить модераторское действие без необходимых прав.
 */
class ModerationPermissionException extends \DomainException
{
}