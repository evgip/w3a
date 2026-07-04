<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Исключение для JSON ответов (AJAX)
 */
class JsonResponseException extends \RuntimeException
{
    protected array $data;
    protected int $statusCode;

    public function __construct(array $data, int $statusCode = 200, ?\Throwable $previous = null)
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        
        parent::__construct(json_encode($data), $statusCode, $previous);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}