<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Exceptions;

/**
 * Исключение, возникающее при попытке выполнить действие без необходимых прав доступа.
 */
class WikiPermissionException extends \DomainException
{
}