<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Исключение для редиректов
 * Используется для прерывания потока выполнения при редиректе
 */
class RedirectException extends \RuntimeException
{
    protected string $url;
    protected int $statusCode;

    public function __construct(string $url, int $statusCode = 302, ?\Throwable $previous = null)
    {
        $this->url = $url;
        $this->statusCode = $statusCode;
        
        parent::__construct("Redirect to: {$url}", $statusCode, $previous);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}