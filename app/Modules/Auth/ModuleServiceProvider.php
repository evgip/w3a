<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Audit;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Modules\Auth\Models\PasswordResetToken;
use App\Modules\Users\Models\User;
use App\Modules\Mail\Core\Mailer; 

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        $container->singleton(RememberToken::class, function (Container $c) {
            return new RememberToken(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(EmailActivation::class, function (Container $c) {
            return new EmailActivation(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        $container->singleton(PasswordResetToken::class, function (Container $c) {
            return new PasswordResetToken(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });

        // === СЕРВИСЫ ===
        $container->singleton(AuthService::class, function (Container $c) {
            return new AuthService(
                $c->get(User::class),
                $c->get(RememberToken::class),
                $c->get(EmailActivation::class),
                $c->get(Database::class),
                $c->get(Logger::class),
                $c->get(Session::class),
                $c->get(Audit::class),
                $c->get(Mailer::class) 
            );
        });

        $container->singleton(PasswordResetService::class, function (Container $c) {
            return new PasswordResetService(
                $c->get(User::class),
                $c->get(PasswordResetToken::class),
                $c->get(Mailer::class)
            );
        });
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}