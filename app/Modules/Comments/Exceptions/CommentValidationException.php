<?php

declare(strict_types=1);

namespace App\Modules\Comments\Exceptions;

/**
 * Исключение для ошибок валидации комментариев.
 * Бросается сервисом, когда данные не соответствуют правилам (например, слишком короткий текст).
 * Контроллер должен ловить это исключение и показывать flash-сообщение пользователю.
 */
class CommentValidationException extends \DomainException
{
}