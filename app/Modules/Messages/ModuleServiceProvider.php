<?php

declare(strict_types=1);

namespace App\Modules\Messages;

use App\Core\Container;
use App\Modules\Messages\Models\Conversation;
use App\Modules\Messages\Models\Message;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;
use App\Modules\Users\Models\User;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Провайдер сервисов модуля Messages.
 * 
 * Регистрирует модели и сервисы для работы с личными сообщениями.
 * 
 * Cross-module зависимости:
 * - User (из Users) — уже зарегистрирован в Users\ModuleServiceProvider
 * - NotificationService (из Notifications) — уже зарегистрирован в Notifications\ModuleServiceProvider
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(Conversation::class, fn() => new Conversation());
        $container->singleton(Message::class, fn() => new Message());
        
        // === СЕРВИСЫ ===
        
        $container->singleton(ConversationService::class, function (Container $c) {
            return new ConversationService(
                $c->get(Conversation::class),
                $c->get(User::class)
            );
        });
        
        $container->singleton(MessageService::class, function (Container $c) {
            return new MessageService(
                $c->get(Message::class),
                $c->get(Conversation::class),
                $c->get(NotificationService::class)
            );
        });
    }
}