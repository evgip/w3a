<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Базовый класс для HTTP исключений
 */
class HttpException extends \RuntimeException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(
        int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}