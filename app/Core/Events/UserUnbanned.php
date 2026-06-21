<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие разбана пользователя.
 * 
 * Отправляется после успешного разбана.
 * Всегда попадает в категорию 'moderation'.
 */
class UserUnbanned extends Event
{
    /**
     * @param int $userId ID разбаненного пользователя
     * @param int $unbannedByUserId ID модератора, который разбанил
     * @param string $reason Причина разбана
     */
    public function __construct(
        private int $userId,
        private int $unbannedByUserId,
        private string $reason = 'Разбан пользователя'
    ) {}

    public function getName(): string
    {
        return 'moderation.user_unbanned';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        $reasonText = trim($this->reason) !== '' 
            ? $this->reason 
            : 'Без указания причины';

        $description = sprintf(
            'Модератор (ID: %d) разбанил пользователя ID: %d. Причина: %s',
            $this->unbannedByUserId,
            $this->userId,
            $reasonText
        );

        return [
            'user_id' => $this->userId,
            'unbanned_by' => $this->unbannedByUserId,
            'reason' => $this->reason,
            'description' => $description,
        ];
    }
}