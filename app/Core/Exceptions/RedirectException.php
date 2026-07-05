<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Исключение для редиректов.
 * Используется для прерывания потока выполнения (Control Flow) при необходимости редиректа.
 */
class RedirectException extends \RuntimeException
{
    public function __construct(
        public readonly string $url,
        public readonly int $statusCode = 302,
        ?\Throwable $previous = null
    ) {
        parent::__construct("Redirect to: {$url}", $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
}