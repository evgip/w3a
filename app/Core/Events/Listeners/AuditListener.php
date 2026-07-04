<?php

declare(strict_types=1);

namespace App\Core\Events\Listeners;

use App\Core\Events\Event;
use App\Core\Audit;

/**
 * Слушатель событий для аудита.
 * Записывает все важные события в журнал аудита.
 */
class AuditListener
{
    /**
     * @var Audit Сервис аудита
     */
    private Audit $audit;

    /**
     * Конструктор с инъекцией зависимостей.
     *
     * @param Audit $audit Сервис аудита
     */
    public function __construct(Audit $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Обработчик события для аудита.
     *
     * @param Event $event Событие для логирования
     *
     * @return void
     */
    public function handle(Event $event): void
    {
        $data = $event->getData();
        
        $description = $data['description'] 
            ?? json_encode($data, JSON_UNESCAPED_UNICODE);
        
        $payload = $data;
        unset($payload['description']);
        
        $this->audit->log(
            $event->getName(),
            $description,
            $event->getCategory(),
            $payload
        );
    }
}