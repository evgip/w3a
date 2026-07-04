<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * HTTP 400 Bad Request
 */
class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Некорректный запрос', ?\Throwable $previous = null)
    {
        parent::__construct(400, $message, $previous);
    }
}