<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Исключение для ошибок CSRF-валидации
 */
class CsrfException extends HttpException
{
    protected string $message = 'CSRF token validation failed';
    protected int $statusCode = 419;
    
    /**
     * Конструктор
     * 
     * @param string $message Сообщение для пользователя
     * @param array $context Дополнительный контекст для логирования
     */
    public function __construct(
        string $message = 'Срок действия формы истёк. Пожалуйста, обновите страницу и попробуйте снова.',
        array $context = []
    ) {
        parent::__construct($message, 419, null, $context);
    }
}