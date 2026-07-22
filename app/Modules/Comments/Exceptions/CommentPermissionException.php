<?php

declare(strict_types=1);

namespace App\Modules\Comments\Exceptions;

/**
 * Исключение для ошибок прав доступа при работе с комментариями.
 * Бросается сервисом, когда пользователь пытается изменить или удалить чужой комментарий без прав модератора.
 * Контроллер должен ловить это исключение и показывать flash-сообщение об отказе в доступе.
 */
class CommentPermissionException extends \DomainException
{
}