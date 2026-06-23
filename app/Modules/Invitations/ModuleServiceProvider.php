<?php

declare(strict_types=1);

namespace App\Modules\Invitations;

use App\Core\Container;
use App\Modules\Invitations\Models\Invitation;
use App\Modules\Invitations\Models\InvitationRequest;

/**
 * Провайдер сервисов модуля Invitations.
 * 
 * Регистрирует модели для работы с приглашениями.
 * Сервисов в модуле нет — только модели.
 * 
 * Cross-module зависимости:
 * - User (из Users) — уже зарегистрирован в Users\ModuleServiceProvider
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Invitation::class, fn() => new Invitation());
        $container->singleton(InvitationRequest::class, fn() => new InvitationRequest());
    }
}