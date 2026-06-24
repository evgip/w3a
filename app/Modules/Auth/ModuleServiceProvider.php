<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Container;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Modules\Users\Models\User;

class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        $container->singleton(RememberToken::class, fn() => new RememberToken());
        $container->singleton(EmailActivation::class, fn() => new EmailActivation());

        // === СЕРВИСЫ ===
        
        $container->singleton(AuthService::class, function (Container $c) {
            return new AuthService(
                $c->get(User::class),
                $c->get(RememberToken::class),
                $c->get(EmailActivation::class)
            );
        });

        $container->singleton(PasswordResetService::class, function (Container $c) {
            return new PasswordResetService($c->get(User::class));
        });
    }
}