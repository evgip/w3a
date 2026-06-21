<?php

declare(strict_types=1);

namespace App\Core\Events;

abstract class Event
{
    abstract public function getName(): string;
    abstract public function getData(): array;

    /**
     * Категория события для фильтрации в журнале.
     * По умолчанию 'general'. Переопределяйте в наследниках.
     */
    public function getCategory(): string
    {
        return 'general';
    }
}