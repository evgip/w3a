<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Events;

use W3a\Core\Events\Event;

/**
 * Событие создания нового пользователя через соцсеть.
 *
 * Генерируется после регистрации пользователя через OAuth-провайдер
 * (Яндекс, VK и др.). Используется слушателями для логирования и аудита.
 */
class SocialUserCreated extends Event
{
    /**
     * @param int    $userId   Идентификатор созданного пользователя
     * @param string $provider Провайдер (yandex, vk, ...)
     */
    public function __construct(
        private int $userId,
        private string $provider
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getName(): string
    {
        return 'auth.social_user_created';
    }

    public function getData(): array
    {
        return [
            'user_id'     => $this->userId,
            'provider'    => $this->provider,
            'description' => sprintf(
                'Новый пользователь (ID: %d) зарегистрирован через %s',
                $this->userId,
                $this->provider
            ),
        ];
    }
}