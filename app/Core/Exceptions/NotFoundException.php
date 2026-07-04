<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * HTTP 404 Not Found
 */
class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Ресурс не найден', ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}