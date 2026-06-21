<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие бана пользователя.
 * 
 * Отправляется после успешного бана.
 * Всегда попадает в категорию 'moderation'.
 */
class UserBanned extends Event
{
    /**
     * @param int $userId ID забаненного пользователя
     * @param int $bannedByUserId ID модератора, который забанил
     * @param string $reason Причина бана
     * @param string|null $duration Длительность бана (null = навсегда)
     */
    public function __construct(
        private int $userId,
        private int $bannedByUserId,
        private string $reason,
        private ?string $duration = null
    ) {}

    public function getName(): string
    {
        return 'moderation.user_banned';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        $durationText = $this->duration ?: 'навсегда';
        $reasonText = trim($this->reason) !== '' 
            ? $this->reason 
            : 'Без указания причины';

        $description = sprintf(
            'Модератор (ID: %d) забанил пользователя ID: %d на %s. Причина: %s',
            $this->bannedByUserId,
            $this->userId,
            $durationText,
            $reasonText
        );

        return [
            'user_id' => $this->userId,
            'banned_by' => $this->bannedByUserId,
            'reason' => $this->reason,
            'duration' => $this->duration,
            'description' => $description,
        ];
    }
}