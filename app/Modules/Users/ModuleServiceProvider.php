<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Container;
use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;

/**
 * Провайдер сервисов модуля Users.
 * 
 * Регистрирует модели и сервисы для работы с профилями пользователей,
 * аватарами и настройками уведомлений.
 * 
 * ⚠️ Логика аутентификации (вход, регистрация, восстановление пароля)
 * перенесена в App\Modules\Auth\ModuleServiceProvider.
 * 
 * Ответственность модуля:
 * - Публичные профили пользователей (просмотр)
 * - Управление аккаунтом (настройки, смена пароля, аватар)
 * - Уведомления пользователей
 * 
 * НЕ отвечает за:
 * - Вход/регистрацию/выход (это Auth)
 * - Восстановление пароля (это Auth)
 * - Remember me токены (это Auth)
 */
class ModuleServiceProvider
{
    /**
     * Регистрация сервисов и моделей модуля в контейнере.
     * 
     * Все сервисы регистрируются как singleton — создаются один раз
     * и переиспользуются в течение всего запроса.
     * 
     * @param Container $container Контейнер зависимостей
     */
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===
        
        // Основная модель пользователя (используется везде)
        $container->singleton(User::class, fn() => new User());
        
        // Модель уведомлений пользователя
        $container->singleton(Notification::class, fn() => new Notification());
        
        // === СЕРВИСЫ ===
        
        // Сервис для работы с пользователями и профилями
        // Зависит от модели User
        $container->singleton(UserService::class, function (Container $c) {
            return new UserService($c->get(User::class));
        });
        
        // Сервис для загрузки и обработки аватаров
        // Не имеет зависимостей от других сервисов
        $container->singleton(AvatarService::class, fn() => new AvatarService());
    }
}