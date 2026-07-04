<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Users\Models\RateLimit;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;

/**
 * Провайдер сервисов модуля Users.
 * 
 * Регистрирует модели и сервисы для работы с профилями пользователей.
 * 
 * ⚠️ Модели EmailActivation и PasswordResetToken перенесены в Auth модуль,
 * т.к. они используются только для аутентификации.
 * 
 * Ответственность модуля:
 * - Публичные профили пользователей (просмотр)
 * - Управление аккаунтом (настройки, смена пароля, аватар)
 * - Уведомления пользователей
 * - Rate limiting (защита от флуда)
 * 
 * НЕ отвечает за:
 * - Вход/регистрацию/выход (это Auth)
 * - Восстановление пароля (это Auth)
 * - Remember me токены (это Auth)
 * - Email активацию (это Auth)
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);

        // === МОДЕЛИ ===
        
        // ✅ Основная модель пользователя (используется везде)
        $container->singleton(User::class, function (Container $c) {
            return new User(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // ✅ Модель уведомлений пользователя
        $container->singleton(Notification::class, function (Container $c) {
            return new Notification(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // ✅ Модель rate limiting (используется в Core\RateLimiter)
        $container->singleton(RateLimit::class, function (Container $c) {
            return new RateLimit(
                $c->get(Database::class),
                $c->get(Logger::class)
            );
        });
        
        // === СЕРВИСЫ ===
        
        // ✅ Сервис для работы с пользователями и профилями
        $container->singleton(UserService::class, function (Container $c) {
            return new UserService(
                $c->get(User::class),
                $c->get(Session::class)
            );
        });
        
        // Сервис для загрузки и обработки аватаров
		$container->singleton(AvatarService::class, function (Container $c) {
			return new AvatarService(
				$c->get(Session::class)
			);
		});
    }

    public function boot(): void
    {
        // Регистрация слушателей событий, если есть
    }
}