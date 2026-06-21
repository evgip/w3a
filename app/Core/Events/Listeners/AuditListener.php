<?php

declare(strict_types=1);

namespace App\Core\Events\Listeners;

use App\Core\Events\Event;
use App\Core\Audit;

class AuditListener
{
    public function handle(Event $event): void
    {
        $data = $event->getData();
        
        $description = $data['description'] 
            ?? json_encode($data, JSON_UNESCAPED_UNICODE);
        
        $payload = $data;
        unset($payload['description']);
        
        Audit::log(
            $event->getName(),
            $description,
            $event->getCategory(),
            $payload
        );
    }
}